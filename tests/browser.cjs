/* Run against a freshly seeded local site. See tests/README.md. */
const assert = require('node:assert/strict');
const fs = require('node:fs/promises');
const path = require('node:path');
const { chromium } = require('playwright');

const base = new URL(process.env.RJHUB_TEST_URL || 'http://127.0.0.1:8080');
const output = process.env.RJHUB_TEST_OUTPUT || '/tmp/rjhub-browser-qa';
const axePath = process.env.RJHUB_AXE_PATH || require.resolve('axe-core/axe.min.js');
const launchOptions = process.env.RJHUB_CHROMIUM ? { executablePath: process.env.RJHUB_CHROMIUM } : {};
const routes = [
    '/', '/about/', '/what-is-rj/', '/blog/', '/projects/', '/resources/', '/maps/',
    '/offerings/', '/community-partners/', '/get-involved/', '/intake/', '/legacy/',
    '/contact/', '/accessibility/', '/privacy/', '/home-alternate/', '/style-guide/',
    '/rjhub-qa-missing-page/',
];
const widths = { compact: 320, mobile: 390, tablet: 768, desktop: 1440 };
const orbitRoutes = ['/what-is-rj/', '/projects/', '/maps/', '/offerings/', '/community-partners/', '/blog/'];
const results = [];

function route(pathname) {
    return new URL(pathname, base).href;
}

async function check(name, test) {
    try {
        const details = await test();
        results.push({ name, status: 'passed', details });
        console.log('PASS ' + name);
    } catch (error) {
        results.push({ name, status: 'failed', error: error.message });
        console.error('FAIL ' + name + '\n' + error.message);
    }
}

async function visit(page, pathname, status = 200) {
    const response = await page.goto(route(pathname), { waitUntil: 'domcontentloaded' });
    assert.equal(response?.status(), status, 'Unexpected status for ' + pathname);
    assert.equal(new URL(page.url()).origin, base.origin, 'Unexpected origin for ' + pathname);
    await page.locator('main h1').first().waitFor({ state: 'visible' });
}

async function submitFilter(page, pathname, field) {
    await visit(page, pathname);
    const input = page.locator('main form input[name="' + field + '"]');
    await input.fill('RJ Hub QA phrase');
    await Promise.all([page.waitForURL((url) => url.searchParams.has(field)), input.press('Enter')]);
    const location = new URL(page.url());
    assert.equal(location.pathname, pathname);
    assert.equal(location.searchParams.get(field), 'RJ Hub QA phrase');
    await page.locator('main h1').first().waitFor({ state: 'visible' });
}

async function runAxe(page, pathname, width) {
    await page.setViewportSize({ width, height: 900 });
    await visit(page, pathname);
    await page.addScriptTag({ path: axePath });
    const result = await page.evaluate(async () => {
        const options = { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'] } };
        return window.axe.run(options);
    });
    const violations = result.violations.map(({ id, impact, nodes }) => ({
        id, impact, nodes: nodes.map(({ target, failureSummary }) => ({ target, failureSummary })),
    }));
    const report = { route: pathname, width, checksPassed: result.passes.length, violations };
    const reportName = 'axe-' + (pathname.split('/')[1] || 'home') + '-' + width + '.json';
    await fs.writeFile(path.join(output, reportName), JSON.stringify(report, null, 2) + '\n');
    assert.deepEqual(violations.map(({ id, nodes }) => id + ' (' + nodes.length + ' nodes)'), []);
    return report;
}

async function main() {
    assert.ok(['127.0.0.1', 'localhost', '[::1]'].includes(base.hostname), 'Run the suite only against a local site.');
    await fs.mkdir(output, { recursive: true });
    const browser = await chromium.launch(launchOptions);
    const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    page.setDefaultTimeout(8000);
    page.setDefaultNavigationTimeout(15000);

    try {
        for (const pathname of routes) {
            await check('Route ' + pathname, async () => {
                await visit(page, pathname, pathname.includes('missing-page') ? 404 : 200);
                assert.equal(await page.locator('main').count(), 1);
                assert.equal(await page.locator('h1').count(), 1);
            });
        }

        await check('Page navigation links resolve locally', async () => {
            const destinations = new Set();
            for (const pathname of routes.filter((name) => !name.includes('missing-page'))) {
                await visit(page, pathname);
                const links = await page.locator('a[href]').evaluateAll((all) => all.map((link) => link.href));
                for (const link of links.map((href) => new URL(href))) {
                    if (link.origin === base.origin) {
                        link.hash = '';
                        destinations.add(link.href);
                    }
                }
            }
            for (const destination of destinations) {
                const response = await page.request.get(destination);
                assert.ok(response.ok(), 'Broken local link: ' + destination);
                await response.dispose();
            }
            return { verified: destinations.size };
        });

        for (const [viewport, width] of Object.entries(widths)) {
            for (const pathname of routes) {
                await check(viewport + ' layout ' + pathname, async () => {
                    await page.setViewportSize({ width, height: 900 });
                    await visit(page, pathname, pathname.includes('missing-page') ? 404 : 200);
                    const geometry = await page.evaluate(() => ({
                        screen: document.documentElement.clientWidth,
                        page: document.documentElement.scrollWidth,
                    }));
                    assert.ok(geometry.page <= geometry.screen + 1, JSON.stringify(geometry));
                    const images = await page.locator('main img').evaluateAll(async (elements) => {
                        await Promise.allSettled(elements.map((image) => image.decode()));
                        return elements.filter((image) => !image.naturalWidth).map((image) => image.currentSrc);
                    });
                    assert.deepEqual(images, [], 'Image failed to load');
                    if (pathname === '/' || pathname === '/home-alternate/') {
                        const capture = (pathname === '/' ? 'home' : 'home-alternate') + '-' + viewport + '.png';
                        await page.screenshot({ path: path.join(output, capture), fullPage: true, animations: 'disabled' });
                    }
                });
            }
        }

        await check('Home skip link moves the tab order past the header', async () => {
            await page.setViewportSize({ width: 1440, height: 900 });
            await visit(page, '/');
            await page.keyboard.press('Tab');
            const focused = page.locator(':focus');
            assert.match(await focused.innerText(), /skip to content/i);
            const target = await focused.getAttribute('href');
            await page.keyboard.press('Enter');
            await page.waitForURL((url) => url.hash === target);
            await page.keyboard.press('Tab');
            assert.equal(await page.locator('main :focus').count(), 1);
        });

        await check('Circular links and the linear alternative cover all six destinations', async () => {
            await visit(page, '/');
            const orbit = page.locator('.rj-orbit a');
            const alternative = page.locator('.rj-orbit-alternative');
            const linkPaths = (links) => links.evaluateAll((all) => all.map((link) => new URL(link.href).pathname).sort());
            assert.deepEqual(await linkPaths(orbit), [...orbitRoutes].sort());
            await alternative.locator('summary').focus();
            await page.keyboard.press('Enter');
            assert.equal(await alternative.evaluate((node) => node.open), true);
            assert.deepEqual(await linkPaths(alternative.locator('a')), [...orbitRoutes].sort());
            const blog = page.locator('.rj-orbit a[href$="/blog/"]');
            await Promise.all([page.waitForURL(route('/blog/')), blog.click()]);
            for (const pathname of orbitRoutes) {
                await visit(page, '/');
                const link = page.locator('.rj-orbit a[href$="' + pathname + '"]');
                await link.focus();
                assert.equal(await link.evaluate((node) => node === document.activeElement), true);
                await Promise.all([page.waitForURL(route(pathname)), page.keyboard.press('Enter')]);
                await page.locator('main h1').first().waitFor({ state: 'visible' });
            }
        });

        await check('Mobile navigation opens and a stacked hub link works by touch', async () => {
            const mobile = await browser.newPage({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
            try {
                await visit(mobile, '/');
                const open = mobile.locator('.rj-hub-primary-navigation .wp-block-navigation__responsive-container-open');
                await open.tap();
                const overlay = mobile.locator('.rj-hub-primary-navigation .wp-block-navigation__responsive-container.is-menu-open');
                await overlay.waitFor({ state: 'visible' });
                await mobile.keyboard.press('Escape');
                await overlay.waitFor({ state: 'hidden' });
                const maps = mobile.locator('.rj-orbit a[href$="/maps/"]');
                await Promise.all([mobile.waitForURL(route('/maps/')), maps.tap()]);
                await mobile.locator('main h1').first().waitFor({ state: 'visible' });
            } finally {
                await mobile.close();
            }
        });

        for (const archive of ['/blog/', '/resources/']) {
            await check('Search submits on ' + archive, () => submitFilter(page, archive, 'rj-search'));
        }

        await check('Header search returns the starter resource', async () => {
            await page.setViewportSize({ width: 1440, height: 900 });
            await visit(page, '/');
            const input = page.locator('header input[name="s"]');
            await input.fill('Questions for Restorative Reflection');
            await Promise.all([page.waitForURL((url) => url.searchParams.has('s')), input.press('Enter')]);
            const result = page.locator('main').getByRole('link', { name: 'Questions for Restorative Reflection' });
            await result.waitFor({ state: 'visible' });
        });

        await check('Initial map presents no unverified historical records', async () => {
            await visit(page, '/maps/');
            assert.equal(await page.locator('.rjh-map-marker').count(), 0);
            assert.match(await page.locator('main').innerText(), /review|verified/i);
        });

        await check('Intake disclosures accept no personal data and send no requests', async () => {
            await visit(page, '/intake/');
            const requests = [];
            const capture = (request) => {
                if (!['GET', 'HEAD', 'OPTIONS'].includes(request.method())) requests.push(request.method());
            };
            page.on('request', capture);
            try {
                assert.equal(await page.locator('main form, main input, main textarea, main select, main [contenteditable]').count(), 0);
                const summaries = page.locator('main summary');
                assert.ok(await summaries.count(), 'Expected informational disclosures');
                for (const summary of await summaries.all()) {
                    await summary.focus();
                    await page.keyboard.press('Enter');
                    assert.equal(await summary.evaluate((element) => element.parentElement.open), true);
                }
                assert.deepEqual(requests, []);
            } finally {
                page.off('request', capture);
            }
        });

        for (const pathname of ['/', '/home-alternate/', '/blog/', '/resources/', '/maps/', '/intake/', '/style-guide/']) {
            await check('Desktop axe ' + pathname, () => runAxe(page, pathname, 1440));
        }
        await check('Mobile axe /', () => runAxe(page, '/', 390));

        const nonScripted = await browser.newPage({ javaScriptEnabled: false });
        await check('Map location search works without JavaScript', () => submitFilter(nonScripted, '/maps/', 'rj-map-search'));
        await nonScripted.close();
    } finally {
        await browser.close();
        await fs.writeFile(path.join(output, 'results.json'), JSON.stringify(results, null, 2) + '\n');
    }

    const failures = results.filter(({ status }) => status === 'failed').length;
    console.log((results.length - failures) + ' passed, ' + failures + ' failed. Reports in ' + output);
    if (failures) process.exitCode = 1;
}

main().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
