# Browser validation

Use the running, freshly seeded prototype. These tests use a local browser and create no content on that site. Test outputs can contain screenshots of private prototype photos if you seed those photos. Keep those outputs on the machine.

With Node.js 20+, install the optional QA dependencies in a temporary directory. Choose a directory writable on your machine.

```bash
qa_tools="$(mktemp -d)"
npm install --prefix "$qa_tools" --no-audit --no-fund --ignore-scripts playwright@1.62.1 axe-core@4.10.3
export NODE_PATH="$qa_tools/node_modules"
export RJHUB_CHROMIUM=/usr/bin/chromium
node tests/browser.cjs
```

Set `RJHUB_CHROMIUM` to an installed Chromium executable. To use Playwright's Chromium, install it with `"$qa_tools/node_modules/.bin/playwright" install chromium` and leave `RJHUB_CHROMIUM` unset. The main suite defaults to `http://127.0.0.1:8080`. It saves per-viewport screenshots and test reports under `/tmp/rjhub-browser-qa`. Override the loopback URL and output using `RJHUB_TEST_URL` and `RJHUB_TEST_OUTPUT`. A failing assertion exits nonzero.

## Search, pagination and map review gating

Run these commands from the project directory against a separate local stack with its own database volume. Set up the main local site first so its local credential files are already present.

```bash
export COMPOSE_PROJECT_NAME=rjhubqa
export RJHUB_PORT=8081
bash scripts/setup.sh
scripts/wp eval-file /workspace/project/tests/fixtures.php seed
node tests/fixtures.cjs
scripts/wp eval-file /workspace/project/tests/fixtures.php clean
docker compose down
unset COMPOSE_PROJECT_NAME RJHUB_PORT
```

Run cleanup and shutdown even when a test assertion fails. The fixture loader checks for loopback port 8081 and labels all synthetic records for cleanup. The fixture results establish filter and review behavior with intentionally synthetic test data. Historical claims still require editorial verification and approval.

## Manual review

Test keyboard focus and Escape behavior on navigation. Review each homepage screenshot for clipping and text contrast in desktop, tablet and mobile layouts. For publication, use a screen reader and complete the photo, archive, privacy, and institutional checks documented in the handoff guides.
