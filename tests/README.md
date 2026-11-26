# Browser validation

Use the freshly seeded local site with the default media setting. The tests use an anonymous browser and create no content. Keep captured private-review images on the originating machine.

With Node.js 20+, install the optional QA dependencies in a temporary directory. Choose a directory writable on your machine.

```bash
qa_tools="$(mktemp -d)"
npm install --prefix "$qa_tools" --no-audit --no-fund --ignore-scripts playwright@1.62.1 axe-core@4.10.3
export NODE_PATH="$qa_tools/node_modules"
export RJHUB_CHROMIUM=/usr/bin/chromium
node tests/browser.cjs
```

Set `RJHUB_CHROMIUM` to an installed Chromium executable. To use Playwright's Chromium, install it with `"$qa_tools/node_modules/.bin/playwright" install chromium` and leave `RJHUB_CHROMIUM` unset. The main suite defaults to `http://127.0.0.1:8080`. It saves desktop/mobile screenshots and test reports under `/tmp/rjhub-browser-qa`. Override the loopback URL and output using `RJHUB_TEST_URL` and `RJHUB_TEST_OUTPUT`. A failing assertion exits nonzero.

The default suite expects the Home, About, What Is RJ, Resources, Accessibility, and Privacy pages and the three seeded learning guides. It checks unpublished destinations by direct URL, site navigation, search, REST and sitemap. When an editorially approved route has been published with substantive content, add its canonical path to the comma-separated `RJHUB_TEST_APPROVED_ROUTES` list and rerun the suite. An unpublished Research or Projects URL is captured as an intentional 404 comparison.

## Editorial fixtures

Run these commands from the project directory against a separate local stack with its own database volume. Set up the main local site first so its local credential files are already present.

```bash
(
    set -e
    export COMPOSE_PROJECT_NAME=rjhubqa RJHUB_PORT=8081
    bash scripts/setup.sh
    trap 'scripts/wp eval-file /workspace/project/tests/fixtures.php clean; docker compose down' EXIT
    scripts/wp eval-file /workspace/project/tests/fixtures.php seed
    node tests/fixtures.cjs
)
```

The fixture loader checks for loopback port 8081, creates separate Journal and Map fixture endpoints, and labels synthetic records for cleanup. The seeded Blog and Maps pages keep their existing status. The checks cover archive filters, pagination, map review, source redirects and what the homepage feeds reveal. Historical claims still require editorial verification and approval.

## Manual review

Test keyboard focus and Escape behavior on navigation. Review each homepage screenshot for clipping and text contrast in desktop, tablet and mobile layouts. For publication, use a screen reader and complete the photo, archive, privacy, and institutional checks documented in the handoff guides.
