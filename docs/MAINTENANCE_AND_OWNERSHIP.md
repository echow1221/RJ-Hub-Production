# RJ Hub maintenance and ownership

The custom block theme handles the site's design. The RJ Hub plugin provides structured content and review controls. WordPress stores pages, entries, menus and editor changes in its database and uploaded media in its files. A recoverable handoff includes the code, database and uploads from the same point in time.

## Who does what

| Role | Responsibilities |
| --- | --- |
| Institutional site owner | Maintains sponsorship and the host account, designates a primary and backup steward, authorizes access and renewals, and approves changes affecting public content, privacy, security or the domain. |
| Managing editor | Reviews editorial accuracy and rights, keeps navigation and content current, arranges second review of sensitive entries, and triages public corrections. |
| Editor | Writes, schedules and revises content, updates homepage and navigation, manages accessible media, and routes questions about permissions, sources and current offerings to the managing editor. |
| Technical steward | Manages WordPress users, supports the Site Editor, maintains code and runtime, tests updates, runs and verifies backups, restores sites, coordinates deployments and handles technical incidents. |
| Pantheon platform support | Supports hosting infrastructure and issues covered by the selected agreement. The institutional site owner and steward maintain application code, editorial content, site policies and approved integrations. |
| Campus web, DNS, accessibility and security contacts | Review changes within their remit and retain control of the campus domain and institutional approvals. |

Give routine staff named WordPress Editor accounts. Assign Administrator access and host deployment access only to the people responsible for those tasks. Contributors can prepare drafts for an editor. Follow the institution's account and multifactor authentication policy for privileged accounts. Have two authorized people who can recover access.

## Maintenance calendar

| Frequency | Work | Owner |
| --- | --- | --- |
| Weekly | Check the public homepage and high-traffic routes. Review broken links, accessibility issues, unreviewed map records and expiring offering text. Confirm the latest backup completed. Triage security notices promptly. | Managing editor and steward |
| Monthly | Review WordPress core, plugin, theme and PHP updates. Apply them in a private test environment and run the release checklist. Confirm a backup can be read and list any issues with the provider. Review new privileged accounts. | Steward |
| Quarterly | Restore a representative backup to a protected environment. Check access and recovery contacts, photo rights, links, redirects and navigation. Review upcoming hosting, domain and account renewals. Run keyboard and screen-reader checks on representative routes. | Owner, steward and managing editor |
| At staff changes | Transfer account ownership and recovery contacts through institutional channels, check successor access, remove departing access, and rotate shared recovery material if policy requires. | Owner and steward |

### Update and restore procedure

1. Record the current code revision, WordPress and PHP versions, and deployment owner. Generate and retain matched backups of code, database and uploads. Store backup copies in approved access-controlled storage with an expiration date.
2. Copy data into protected Test only if allowed by the site's privacy rules. Use a sanitized copy when possible. Disable any external notifications and keep the demo intake disabled.
3. Apply updates in Dev, stage the resulting code in Test, and check the homepage, both menus, post and resource archives, contact and intake status, map list and approved entry, Editor publishing, key redirects and PDFs.
4. Check browser responsiveness and keyboard focus. Ask the managing editor to review the Test build. Record issues and get release approval before deploying.
5. Create a fresh recovery point before the release. Deploy through the authorized workflow, clear the host cache, and repeat smoke checks against the public domain.
6. If the release fails, stop further changes and contact the owner. Restore the smallest affected component when safe. Database rollback can discard new editorial work, so capture the current database before restoring and reconcile any changes made after the selected backup.

### Incident response

The person who discovers an incident records the time, URL, visible symptoms and scope, then contacts the steward and institutional owner. Keep credentials, private records and affected personal data out of issue trackers. The owner coordinates with the assigned security and privacy contacts and approves public communications. The steward preserves relevant evidence through approved channels, follows campus containment instructions and validates recovery before reopening the affected service.

Julie can assign contacts here using organizational role addresses where possible.

| Contact | Name or role address | Escalation channel |
| --- | --- | --- |
| Institutional owner |  |  |
| Managing editor |  |  |
| Technical steward |  |  |
| Backup steward |  |  |
| Campus security and incident response |  |  |
| Campus web hosting and DNS |  |  |
| Accessibility review |  |  |
| Privacy review |  |  |
| Pantheon billing and support owner |  |  |

## Staff transition

The owner and incoming steward review access to the host dashboard, repository, WordPress, backups and campus domain support together using approved channels. Confirm the successor can restore a test backup and find the release instructions. Ask the incoming editor to complete the [30-minute editor walkthrough](EDITOR_HANDBOOK.md#30-minute-walkthrough). Record a primary and backup owner for both editorial and technical work.
