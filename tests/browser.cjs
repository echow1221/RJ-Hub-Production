/* Validate a freshly seeded, anonymous WordPress preview. See tests/README.md. */
const assert = require('node:assert/strict');
const fs = require('node:fs/promises');
const path = require('node:path');
const { chromium } = require('playwright');

const base = new URL(process.env.RJHUB_TEST_URL || 'http://127.0.0.1:8080');
const output = process.env.RJHUB_TEST_OUTPUT || '/tmp/rjhub-browser-qa';
const axePath = process.env.RJHUB_AXE_PATH || require.resolve('axe-core/axe.min.js');
const launchOptions = process.env.RJHUB_CHROMIUM ? { executablePath: process.env.RJHUB_CHROMIUM } : {};
const guides = [
    '/resource/questions-for-restorative-reflection/',
    '/resource/planning-an-accessible-discussion/',
    '/resource/planning-a-restorative-circle/',
];
const core = ['/', '/about/', '/what-is-rj/', '/resources/', '/accessibility/', '/privacy/', ...guides];
const incomplete = [
    ['/blog/', 'Blog'], ['/projects/', 'Projects'], ['/research/', 'Research'], ['/maps/', 'Maps'],
    ['/offerings/', 'Offerings'], ['/community-partners/', 'Community Partners'], ['/get-involved/', 'Get Involved'],
    ['/intake/', 'Intake'], ['/legacy/', 'Legacy'], ['/contact/', 'Contact'],
    ['/home-alternate/', 'Homepage Preview'], ['/style-guide/', 'Brand System'],
];
const approved = new Set((process.env.RJHUB_TEST_APPROVED_ROUTES || '').split(',').filter(Boolean));
const hidden = new Map(incomplete.filter(([pathname]) => !approved.has(pathname)));
const publicRoutes = [...core, ...approved];
const missing = '/rjhub-qa-missing-page/';
const sitemapPath = '/wp-sitemap.xml';
const widths = { compact: 320, mobile: 390, tablet: 768, desktop: 1440 };
const captures = new Map([
    ['/', 'home'], ['/what-is-rj/', 'what-is-rj'], ['/resources/', 'resources'], ['/research/', 'research'],
    ['/projects/', 'research-projects'],
]);
const placeholders = /lorem ipsum|placeholder text|private preview|publication rights (?:and event context )?pending|under review|coming soon|awaiting (?:approval|verification|publication)|no historical locations have been verified|prototype/i;
const results = [];

function route(pathname) {
    return new URL(pathname, base).href;
}

function canonical(pathname) {
    return pathname.replace(/\/+$/, '') + '/';
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
    const heading = page.locator('main h1');
    await heading.waitFor({ state: 'visible' });
    assert.equal(await page.locator('main').count(), 1);
    assert.equal(await page.locator('h1').count(), 1);
    return heading;
}

async function linkChecks(page) {
    const destinations = new Set();
    for (const pathname of [...publicRoutes, missing]) {
        await visit(page, pathname, pathname === missing ? 404 : 200);
        const links = await page.locator('a[href]').evaluateAll((all) => all.map((link) => link.href));
        for (const link of links.map((href) => new URL(href))) {
            if (link.origin !== base.origin) continue;
            assert.ok(!hidden.has(canonical(link.pathname)), 'Unpublished link on ' + pathname + ' to ' + link.pathname);
            if (canonical(link.pathname) !== missing) destinations.add(link.origin + link.pathname + link.search);
        }
    }
    for (const destination of destinations) {
        const response = await page.request.get(destination, { timeout: 15000 });
        assert.ok(response.ok(), 'Broken local link: ' + destination);
        await response.dispose();
    }
    return { verified: destinations.size };
}

async function validateSearchVisibility(page) {
    for (const [pathname, title] of hidden) {
        const search = '/?s=' + encodeURIComponent(title);
        await visit(page, search);
        const links = await page.locator('main a[href]').evaluateAll((all) => all.map((node) => new URL(node.href).pathname));
        assert.ok(!links.some((link) => hidden.has(canonical(link))), 'Search exposes a hidden link for ' + pathname);
        const rest = '/?rest_route=/wp/v2/search&search=' + encodeURIComponent(title);
        const response = await page.request.get(route(rest));
        if (response.ok()) {
            const results = await response.json();
            const leaked = results.filter((item) => item.url && hidden.has(canonical(new URL(item.url, base).pathname)));
            assert.deepEqual(leaked, []);
        } else {
            assert.ok([401, 403, 404].includes(response.status()), 'Unexpected public search API response ' + response.status());
        }
        await response.dispose();
        const slug = pathname.split('/')[1];
        const pageResponse = await page.request.get(route('/?rest_route=/wp/v2/pages&slug=' + encodeURIComponent(slug)));
        if (pageResponse.ok()) {
            assert.deepEqual(await pageResponse.json(), [], 'Public REST page lookup exposes ' + pathname);
        } else {
            assert.ok([401, 403, 404].includes(pageResponse.status()), 'Unexpected REST page response ' + pageResponse.status());
        }
        await pageResponse.dispose();
    }
}

async function validateSitemaps(page) {
    const index = await page.request.get(route(sitemapPath));
    if (index.status() === 404) {
        await index.dispose();
        return { enabled: false };
    }
    assert.ok(index.ok(), 'Unexpected sitemap response ' + index.status());
    const extract = (xml) => [...xml.matchAll(/<loc>\s*([^<]+)\s*<\/loc>/g)].map((match) => match[1]);
    const urls = extract(await index.text());
    await index.dispose();
    assert.ok(urls.length, 'Expected entries in the sitemap index');
    for (const url of urls) {
        const target = new URL(url, base);
        assert.equal(target.origin, base.origin, 'Sitemap references an external index');
        const item = await page.request.get(target.href);
        assert.ok(item.ok(), 'Invalid sitemap entry ' + target.pathname);
        for (const listed of extract(await item.text())) {
            assert.ok(!hidden.has(canonical(new URL(listed, base).pathname)), 'Sitemap exposes ' + listed);
        }
        await item.dispose();
    }
    return { enabled: true, sitemapCount: urls.length };
}

async function validateLayout(page, pathname, viewport, width) {
    await page.setViewportSize({ width, height: viewport === 'mobile' ? 844 : 900 });
    await visit(page, pathname, pathname === missing || hidden.has(pathname) ? 404 : 200);
    const geometry = await page.evaluate(() => ({ screen: document.documentElement.clientWidth, page: document.documentElement.scrollWidth }));
    assert.ok(geometry.page <= geometry.screen + 1, JSON.stringify(geometry));
    const broken = await page.locator('img').evaluateAll(async (elements) => {
        await Promise.allSettled(elements.map((image) => image.decode()));
        return elements.filter((image) => !image.naturalWidth).map((image) => image.currentSrc);
    });
    assert.deepEqual(broken, [], 'Image failed to load');
    const capture = captures.get(pathname);
    if (capture && ['mobile', 'desktop'].includes(viewport)) {
        await page.screenshot({ path: path.join(output, capture + '-' + viewport + '.png'), fullPage: true, animations: 'disabled' });
    }
}

async function runAxe(page, pathname, width) {
    await page.setViewportSize({ width, height: 900 });
    await visit(page, pathname);
    await page.addScriptTag({ path: axePath });
    const result = await page.evaluate(() => window.axe.run({
        runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'] },
    }));
    const violations = result.violations.map(({ id, impact, nodes }) => ({
        id, impact, nodes: nodes.map(({ target, failureSummary }) => ({ target, failureSummary })),
    }));
    const report = { route: pathname, width, checksPassed: result.passes.length, violations };
    const routeName = pathname.split('/').filter(Boolean).join('-') || 'home';
    const file = 'axe-' + routeName + '-' + width + '.json';
    await fs.writeFile(path.join(output, file), JSON.stringify(report, null, 2) + '\n');
    assert.deepEqual(violations.map(({ id, nodes }) => id + ' (' + nodes.length + ' nodes)'), []);
    return report;
}

async function main() {
    assert.ok(['127.0.0.1', 'localhost', '[::1]'].includes(base.hostname), 'Run the suite only against a local site.');
    for (const pathname of approved) assert.ok(incomplete.some(([slug]) => slug === pathname), 'Unknown approved route: ' + pathname);
    await fs.mkdir(output, { recursive: true });
    const browser = await chromium.launch(launchOptions);
    const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    page.setDefaultTimeout(8000);
    page.setDefaultNavigationTimeout(15000);

    try {
        for (const pathname of publicRoutes) {
            await check('Visitor route and published copy ' + pathname, async () => {
                await visit(page, pathname);
                const main = await page.locator('main').innerText();
                assert.ok(main.length > 55, 'Expected more than a heading or empty page');
                assert.doesNotMatch(main, placeholders);
            });
        }
        await check('Unknown URL shows the 404 template', () => visit(page, missing, 404).then(() => undefined));
        for (const pathname of hidden.keys()) {
            await check('Unpublished direct URL ' + pathname, () => visit(page, pathname, 404).then(() => undefined));
        }
        await check('No unpublished links in visitor navigation or content', () => linkChecks(page));
        await check('Unpublished pages are absent from site and REST search', () => validateSearchVisibility(page));
        await check('Unpublished pages are absent from sitemaps', () => validateSitemaps(page));

        for (const [viewport, width] of Object.entries(widths)) {
            for (const pathname of [...publicRoutes, missing]) {
                await check(viewport + ' layout ' + pathname, () => validateLayout(page, pathname, viewport, width));
            }
        }
        for (const pathname of ['/research/', '/projects/'].filter((slug) => hidden.has(slug))) {
            for (const viewport of ['mobile', 'desktop']) {
                await check(viewport + ' unpublished comparison ' + pathname, () => validateLayout(page, pathname, viewport, widths[viewport]));
            }
        }

        await check('Keyboard skip link bypasses the header', async () => {
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

        await check('Keyboard activates a homepage editorial path', async () => {
            await visit(page, '/');
            const destination = page.locator('main a[href$="/what-is-rj/"]').first();
            await destination.focus();
            assert.equal(await destination.evaluate((node) => node === document.activeElement), true);
            await Promise.all([page.waitForURL(route('/what-is-rj/')), page.keyboard.press('Enter')]);
        });

        await check('Mobile navigation opens, dismisses and routes to Resources by touch', async () => {
            const mobile = await browser.newPage({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
            try {
                await visit(mobile, '/');
                const open = mobile.locator('header').getByRole('button', { name: /open menu/i });
                await open.tap();
                const overlay = mobile.locator('header .wp-block-navigation__responsive-container.is-menu-open');
                await overlay.waitFor({ state: 'visible' });
                await mobile.keyboard.press('Escape');
                await overlay.waitFor({ state: 'hidden' });
                assert.equal(await open.evaluate((node) => document.activeElement === node), true);
                await open.tap();
                await Promise.all([mobile.waitForURL(route('/resources/')), overlay.getByRole('link', { name: 'Resources', exact: true }).tap()]);
            } finally {
                await mobile.close();
            }
        });

        await check('Resources filter accepts a phrase without JavaScript', async () => {
            const nonScripted = await browser.newPage({ javaScriptEnabled: false });
            try {
                await visit(nonScripted, '/resources/');
                const input = nonScripted.locator('main input[name="rj-search"]');
                await input.fill('RJ Hub QA phrase');
                await Promise.all([nonScripted.waitForURL((url) => url.searchParams.get('rj-search') === 'RJ Hub QA phrase'), input.press('Enter')]);
            } finally {
                await nonScripted.close();
            }
        });

        await check('Published learning guides are linked from Resources and discoverable by site search', async () => {
            await visit(page, '/resources/');
            for (const pathname of guides) {
                assert.equal(await page.locator('main a[href$="' + pathname + '"]').count(), 1);
            }
            await visit(page, '/?s=Planning+a+Restorative+Circle');
            await page.locator('main').getByRole('link', { name: 'Planning a Restorative Circle' }).waitFor({ state: 'visible' });
        });

        for (const pathname of publicRoutes) {
            await check('Desktop axe ' + pathname, () => runAxe(page, pathname, 1440));
        }
        await check('Mobile axe /', () => runAxe(page, '/', 390));
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
