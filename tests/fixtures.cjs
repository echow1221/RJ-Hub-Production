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
    await page.goto(new URL('/rjhub-qa-map/?rj-map-search=rjhub-qa-fixture', base).href);
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

async function routeChecks(page) {
    const approved = await page.request.get(new URL('/qa-disposable-legacy-document/', base).href, { maxRedirects: 0 });
    assert.equal(approved.status(), 301);
    assert.equal(approved.headers()['x-redirect-by'], 'RJ Hub source route');
    const target = new URL(approved.headers().location, base);
    assert.equal(target.origin, base.origin);
    assert.equal(target.pathname, '/resource/approved-source-route-target/');
    await page.goto(target.href);
    await page.getByRole('heading', { name: 'Approved source route target' }).waitFor({ state: 'visible' });

    const withheld = await page.request.get(new URL('/qa-pending-legacy-review/', base).href, { maxRedirects: 0 });
    assert.equal(withheld.status(), 404);
    const liveRoute = await page.request.get(new URL('/resources/', base).href, { maxRedirects: 0 });
    assert.equal(liveRoute.status(), 200);
    console.log('PASS confirmed source path redirects to public content, draft and existing paths are preserved');
}

async function homeFeedChecks(page) {
    await page.goto(base.href);
    const main = page.locator('main');
    await main.getByRole('heading', { name: 'Research and projects', exact: true }).waitFor({ state: 'visible' });
    await main.getByRole('heading', { name: 'Blog and news', exact: true }).waitFor({ state: 'visible' });
    await main.getByRole('link', { name: 'Published research fixture', exact: true }).waitFor({ state: 'visible' });
    assert.equal(await main.getByText(/Draft research fixture|Protected research fixture/i).count(), 0);
    assert.equal(await main.locator('a[href$="/projects/"], a[href$="/blog/"]').count(), 0);
    console.log('PASS homepage editorial feeds show published material and omit draft, protected and withheld archive links');
}

async function main() {
    assert.ok(['127.0.0.1', 'localhost', '[::1]'].includes(base.hostname) && base.port === '8081', 'Run against the disposable QA stack.');
    const browser = await chromium.launch(launchOptions);
    try {
        const page = await browser.newPage();
        page.setDefaultTimeout(8000);
        await pageOne(page, '/rjhub-qa-journal/', 'Blog', true);
        await pageOne(page, '/resources/', 'Resource', false);
        await mapChecks(page);
        await routeChecks(page);
        await homeFeedChecks(page);
        const noScript = await browser.newPage({ javaScriptEnabled: false });
        await noScript.goto(new URL('/rjhub-qa-map/', base).href);
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
