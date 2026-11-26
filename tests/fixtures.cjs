const assert = require('node:assert/strict');
const { chromium } = require('playwright');

const base = new URL(process.env.RJHUB_TEST_URL || 'http://127.0.0.1:8081');
const launchOptions = process.env.RJHUB_CHROMIUM ? { executablePath: process.env.RJHUB_CHROMIUM } : {};

async function pageOne(page, path, type, tagFilter) {
    const questions = new URLSearchParams({ 'rj-search': 'rjhub-qa-fixture', 'rj-category': 'rjhub-qa-keep' });
    if (tagFilter) questions.set('rj-tag', 'rjhub-qa-keep');
    await page.goto(new URL(path + '?' + questions, base).href);
    const links = page.locator('.rjh-results h2 a');
    assert.equal(await links.count(), 12, 'Expected twelve matching items');
    const firstPageTitles = await links.allTextContents();
    const expectedFirstTitles = Array.from({ length: 12 }, (_, index) => 'rjhub-qa-fixture ' + type + ' ' + (13 - index));
    assert.deepEqual(firstPageTitles, expectedFirstTitles, 'Search or taxonomy filter returned the wrong first page');

    const next = page.locator('main a[href*="rj-page=2"]').first();
    await next.click();
    const second = new URL(page.url());
    for (const [key, value] of questions.entries()) assert.equal(second.searchParams.get(key), value);
    await links.first().waitFor({ state: 'visible' });
    assert.equal(await links.count(), 1);
    const last = await links.innerText();
    assert.equal(last, 'rjhub-qa-fixture ' + type + ' 1', 'Wrong or repeated item on page two');
    await links.click();
    await page.getByRole('heading', { name: last, exact: true }).waitFor({ state: 'visible' });
    if (type === 'Blog') {
        await page.locator('main').getByText('Synthetic QA Author', { exact: true }).waitFor({ state: 'visible' });
        assert.match(await page.locator('main time').getAttribute('datetime'), /^2024-01-01/);
    }
    console.log('PASS ' + type + ' search, category, pagination and detail links');
}

async function mapChecks(page) {
    await page.goto(new URL('/maps/?rj-map-search=rjhub-qa-fixture', base).href);
    const list = page.locator('.rjh-map-list');
    const marker = page.locator('.rjh-map-marker');
    assert.equal(await marker.count(), 1);
    assert.match(await list.innerText(), /Verified map record/);
    assert.doesNotMatch(await list.innerText(), /Unverified map record|Incomplete map record/);
    const context = page.locator(await marker.getAttribute('href'));
    await marker.focus();
    await page.keyboard.press('Enter');
    assert.equal(await context.evaluate((node) => node === document.activeElement), true);
    assert.equal(await context.getByRole('link', { name: /source/i }).getAttribute('href'), 'https://example.org/');
    console.log('PASS only verified complete map data renders, keyboard marker opens its sourced context');
}

async function main() {
    assert.ok(['127.0.0.1', 'localhost', '[::1]'].includes(base.hostname) && base.port === '8081', 'Run against the disposable QA stack.');
    const browser = await chromium.launch(launchOptions);
    try {
        const page = await browser.newPage();
        page.setDefaultTimeout(8000);
        await pageOne(page, '/blog/', 'Blog', true);
        await pageOne(page, '/resources/', 'Resource', false);
        await mapChecks(page);
        const noScript = await browser.newPage({ javaScriptEnabled: false });
        await noScript.goto(new URL('/maps/', base).href);
        const input = noScript.locator('input[name="rj-map-search"]');
        await input.fill('Synthetic location');
        await Promise.all([noScript.waitForURL((url) => url.searchParams.has('rj-map-search')), input.press('Enter')]);
        await noScript.locator('.rjh-map-list').getByText(/Verified map record/).waitFor({ state: 'visible' });
        console.log('PASS location field search and context list work with JavaScript disabled');
    } finally {
        await browser.close();
    }
}

main().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
