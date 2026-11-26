# Editorial refinement validation

Validated November 25, 2026 using WordPress 7.0.2, PHP 8.3, MariaDB 11.4, Docker Compose v2 and Chromium. Functional tests ran on loopback WordPress instances. Tests that change data used disposable databases.

| Area | Result |
| --- | --- |
| Existing site upgrade | Backed up the original local database and uploads. Updated the original site in place and retained the existing Home and core page IDs, structured records, and private-review attachments. The 11 original unfinished pages became drafts. A separate upgrade test retained a staff-edited page, preserved the existing Home ID, and confirmed the second seed was a no-op. |
| Fresh install | A clean install produced six public pages, three educational resource details and 11 editorial drafts. Default mode imported zero attachments. The private mode showed the curated consent-pending hero and review caption. |
| Routes and navigation | Visitor tests checked all nine public routes, internal links, desktop and mobile menus, and the intentional 404s for withheld pages. Search, public REST responses, and sitemap entries excluded the draft pages when exercised. |
| Resource archive | Verified text search, category filtering, descriptions, resource detail routes and empty-result behavior. Isolated seeded data exercised additional archive pagination and filter persistence. |
| News and research | Public Home omits empty feeds. In a test instance, published sample records appeared with the expected category, date and link. Draft or password-protected items stayed out. Archive calls to action appeared only when their archive page was published. |
| Editor role | The Editor published a test news article and resource, edited Home, replaced its hero through the Media Library, published a draft archive page when it had content, and updated the public menu. Access to user management was denied. |
| Intake and map | Visitor distribution excludes both routes. The editing architecture is preserved. There is no intake submission backend or verified public historical map record in the site. |
| Import and redirects | An isolated WXR fixture exercised a dated 2026 article, approved image and PDF, draft protection and original path resolution. Redirect fixtures confirmed verified local redirects, exclusion of a withheld target, and existing-route precedence. No legacy test rows or attachments were left in the review instance. |
| Responsive and accessibility | The visitor suite passed 84 checks, and six isolated fixture checks passed. The browser suite ran at 320, 390, 768 and 1440 CSS pixels. Keyboard navigation, a skip link, mobile menu open and Escape focus return, touch navigation and page overflow were checked. Ten axe scans reported zero violations. |
| Media and packaging | The default package leaves out consent-pending images. The separate private-photo ZIP contains the four WebP derivatives and provisional mark, used only after opting in. Screenshots for comparison mask private media in the old layout and use safe-mode pages for the redesign. |

## Source and release checks still open

- The workbook checklist accounts for 149 records. The proxy denied both Berkeley sites, and no authorized body or asset export was available. Zero legacy records were imported. Confirm original bylines, dates, full bodies, links, rights and redirect paths against an approved export. See [Content migration](CONTENT_MIGRATION_REPORT.md).
- Obtain institutional accessibility, privacy, photo and brand approvals. Test with a screen reader and inspect approved PDFs before the public launch. Automated results do not establish accessibility conformance.
- Verify the actual Pantheon staging environment, restore a backup there and rehearse approved redirects before any cutover. Read the [Pantheon guide](PANTHEON_MIGRATION.md).
