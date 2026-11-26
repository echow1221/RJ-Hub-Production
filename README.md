# UC Berkeley Restorative Justice Hub

An editable WordPress site for local review. The public preview includes Home, About, What Is Restorative Justice?, Resources, Accessibility and Privacy. The resource collection contains three original educational guides. Blog & News, Research & Projects and other incomplete sections remain WordPress drafts.

All 149 records from the supplied inventory were reconciled. The cloud proxy denied the public source hostname and no authorized export was available, so the prototype has no migrated legacy articles. The checklist, current limitations and authorized export workflow are documented in the [content migration report](docs/CONTENT_MIGRATION_REPORT.md).

## Start on a Mac

Install and open Docker Desktop. In Finder, open your Downloads folder, locate the prototype ZIP, then double-click it to extract the project. In the Mac **Terminal** app, type `cd` followed by a space, drag the extracted `RJ_Hub_WordPress_Local_Prototype` folder into Terminal, and press Return. Then run:

```bash
bash scripts/setup.sh
open http://127.0.0.1:8080/
```

For a separate private photo review instance, extract the private-photo review ZIP and open its project folder in Terminal. Then run these commands and use port 8081 for the dashboard too:

```bash
COMPOSE_PROJECT_NAME=rjhubprivate RJHUB_PORT=8081 bash scripts/setup.sh --private-media
open http://127.0.0.1:8081/
```

Open `/wp-admin/` on the same port. The local administrator username is `rjhub-owner`. From the project folder use `cat .local/admin-password` to retrieve the generated password.

## Start on Windows with WSL 2 and Docker Desktop

1. In Windows PowerShell, run `wsl --install -d Ubuntu` and restart Windows if prompted. Open Ubuntu and finish creating your Linux user.
2. Install Docker Desktop for Windows. In Docker Desktop, enable the WSL 2 engine and enable **Settings → Resources → WSL Integration → Ubuntu**. Start Docker Desktop and leave it running.
3. In the Ubuntu terminal, install extraction and setup utilities and check that Docker is reachable.

   ```bash
   sudo apt update
   sudo apt install -y unzip curl openssl
   docker compose version
   docker info
   ```

4. Unzip the downloaded website package into your Ubuntu home directory. Use the actual Windows username in the download path.

   ```bash
   mkdir -p ~/rjhub
   unzip /mnt/c/Users/YOUR_WINDOWS_USERNAME/Downloads/RJ_Hub_WordPress_Local_Prototype.zip -d ~/rjhub
   cd ~/rjhub/RJ_Hub_WordPress_Local_Prototype
   bash scripts/setup.sh
   ```

   The first run pulls WordPress 7.0.2 with PHP 8.3 and MariaDB 11.4, starts both services, configures WordPress, and seeds the prototype. Later runs preserve existing site edits. On native Ubuntu, use the same commands with Docker Engine and the Compose v2 plugin installed, and substitute your actual download path.

5. Open `http://127.0.0.1:8080/` in your own browser. Open `http://127.0.0.1:8080/wp-admin/` for the dashboard. Sign in as `rjhub-owner` with the generated password in `.local/admin-password`. Keep that file and `.env` private. Change the password from **Users → Profile** and delete the initial password file after storing the new password securely. To create a staff account, use **Users → Add New**, set the role to **Editor**, and share its initial credentials through your approved secure channel. Local outgoing mail is disabled.

### Private photo review

For a fresh install intended only for authorized local reviewers, use `bash scripts/setup.sh --private-media` in step 4. This imports the curated review derivatives and applies the provisional placements. A ZIP prepared for private review contains those derivatives. To regenerate them from a source checkout, install the optional Pillow, pypdf and PyMuPDF packages in a Python virtual environment and run `python media/build-preview.py` from the project root. Keep the generated review media in private storage. The first successful seed determines the initial photo mode. Later runs preserve existing content, so use a separate fresh local instance to compare seed modes. Avoid distributing screenshots or database/upload backups from the photo-enabled instance without consent. The photo report documents each candidate and the provisional PDF-derived logo.

### Stop, restart, and troubleshoot

```bash
docker compose stop
docker compose up -d --wait db wordpress
docker compose ps
docker compose logs --tail 80 wordpress db
```

For an existing prototype, first back up the original project and database, then run setup with the updated code in that same project directory and with the same Compose project name and port. The editorial upgrade updates original seeded records in place. It preserves edited records and reports them for review. Repeat setup is safe.

The database and uploads persist through stop and restart. On a fresh install, change `RJHUB_PORT` in `.env` if 8080 is occupied, then rerun setup. For installed sites, retain the original port or deliberately migrate the WordPress URLs and database links before changing it. If Docker is unreachable in Ubuntu, start Docker Desktop and recheck Ubuntu integration. If a fresh install is interrupted, rerun setup with the same mode. Make a backup before diagnosing an existing installation.

## Routine editing

Open **Pages** for the homepage, editorial copy, and policy pages. Use **Posts** for news, the structured content menus for projects, resources, partners, offerings and maps, and the RJ Hub navigation screen for menus. Site-wide templates and styles are administered separately. See the [Editor Handbook](docs/EDITOR_HANDBOOK.md) and [demo checklist](docs/DEMO_CHECKLIST.md).

## Import, backup, and future hosting

- Use the [migration report](docs/CONTENT_MIGRATION_REPORT.md) and inventory in `content/` to reconcile source content, redirects and reviewer approvals. See the [authorized WXR and CSV import guide](docs/AUTHORIZED_IMPORT.md) for the export conversion and dry-run workflow.
- Run `bash scripts/backup.sh` while the site is up to save a private SQL dump and uploads archive. Back up the project code separately. Protect these files as they may include staff data, unpublished content and consent-pending media.
- Review the [public page decisions](docs/EDITORIAL_AUDIT.md), [design changes](docs/DESIGN_REFINEMENT.md), [Pantheon migration guide](docs/PANTHEON_MIGRATION.md), [maintenance handoff](docs/MAINTENANCE_AND_OWNERSHIP.md), and [QA report](docs/QA_REPORT.md) before proposing a launch.

The custom Gutenberg block theme governs layout and brand styling. The separate RJ Hub plugin registers the site’s structured records and dynamic features so staff retain content when a theme is changed. The Docker Compose stack and local-only preview controls are development tools. Future Pantheon transfer uses the theme, plugin, reviewed database and reviewed uploads with Pantheon’s own WordPress runtime.
