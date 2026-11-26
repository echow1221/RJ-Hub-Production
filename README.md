# UC Berkeley Restorative Justice Hub

A private, editable WordPress prototype for the RJ Hub. It includes an editorial homepage and an alternate composition, a brand guide, searchable archives, structured editorial content, a California historical map with a searchable text view, and an intake preview with no submission capability.

The visitor copy draws on the supplied brand kit and the topics documented in the editorial inventory. The cloud connection to the public site is blocked, so its exact wording, programs, and article bodies still need source verification. See [Content migration](docs/CONTENT_MIGRATION_REPORT.md) for the authorized import workflow.

## Update the original site on a Mac

1. Open Docker Desktop and confirm your original site loads at [127.0.0.1:8092](http://127.0.0.1:8092/).
2. On the GitHub branch `rj-hub/original-wording`, select **Code → Download ZIP**. Open Finder, go to **Downloads**, and double-click the ZIP to extract it.
3. Open the Mac **Terminal** app. Type `cd` followed by a space, drag the newly extracted folder into Terminal, and press **Return**.
4. Run the update and open your site.

   ```bash
   bash scripts/update-original-copy.sh &&
   open http://127.0.0.1:8092/
   ```

The update uses Docker to find the existing `rjhuboriginal` installation. It backs up the local database and uploads, updates the original starter passages, and skips passages or plugin files that have been edited. The updated page text is editable in WordPress. Keep the backups in the original project's `backups/` folder private. Add `--check` to the command to see which page passages are ready to update before making changes.

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

For a fresh install intended only for authorized local reviewers, use `bash scripts/setup.sh --private-media` in step 4. This imports only the curated review derivatives and applies the provisional placements. The first successful seed determines the initial photo mode. Later runs preserve existing content, so use a separate fresh local instance to compare seed modes. Avoid distributing screenshots or database/upload backups from the photo-enabled instance without consent. The photo report documents each candidate and the provisional PDF-derived logo.

### Stop, restart, and troubleshoot

```bash
docker compose stop
docker compose up -d --wait db wordpress
docker compose ps
docker compose logs --tail 80 wordpress db
```

The database and uploads persist through stop and restart. On a fresh install, change `RJHUB_PORT` in `.env` if 8080 is occupied, then rerun setup. For installed sites, retain the original port or deliberately migrate the WordPress URLs and database links before changing it. If Docker is unreachable in Ubuntu, start Docker Desktop and recheck Ubuntu integration. If a fresh install is interrupted, rerun setup with the same mode. Make a backup before diagnosing an existing installation.

## Routine editing

Open **Pages** for the homepage, editorial copy, and policy pages. Use **Posts** for news, the structured content menus for projects, resources, partners, offerings and maps, and the RJ Hub navigation screen for menus. Site-wide templates and styles are administered separately. See the [Editor Handbook](docs/EDITOR_HANDBOOK.md) and [demo checklist](docs/DEMO_CHECKLIST.md).

## Import, backup, and future hosting

- Use the [migration report](docs/CONTENT_MIGRATION_REPORT.md) and inventory in `content/` to reconcile source content, redirects and reviewer approvals.
- Run `bash scripts/backup.sh` while the site is up to save a private SQL dump and uploads archive. Back up the project code separately. Protect these files as they may include staff data, unpublished content and consent-pending media.
- Review the [Pantheon migration guide](docs/PANTHEON_MIGRATION.md), [maintenance handoff](docs/MAINTENANCE_AND_OWNERSHIP.md), and [QA report](docs/QA_REPORT.md) before proposing a launch.

The custom Gutenberg block theme governs layout and brand styling. The separate RJ Hub plugin registers the site’s structured records and dynamic features so staff retain content when a theme is changed. The Docker Compose stack and local-only preview controls are development tools. Future Pantheon transfer uses the theme, plugin, reviewed database and reviewed uploads with Pantheon’s own WordPress runtime.
