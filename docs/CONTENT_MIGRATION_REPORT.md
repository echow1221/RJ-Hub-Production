# Content Migration

The current package installs 17 editable pages and two starter resources. The starter copy gives reviewers working routes and editorial examples. The approval queue covers the official mission statement, campus relationships, active programs, current partner profiles, historical records, and news.

## Source Audit

Read-only HTTPS requests for the public homepage, its robots policy, and the inventory CSV export received an HTTP 403 CONNECT response from the cloud proxy. No origin response, records, or inventory rows were available. No changes were made to the public site.

| Source | Evidence available in this build |
| --- | --- |
| Public RJ Hub site | The supplied brief identifies the canonical domain. The page bodies, exact path inventory, dates, and public bylines are pending. |
| Google Sheets inventory | The earlier 149-entry benchmark from the brief is 108 published and 41 unpublished. These counts require reconciliation against an authorized copy. |
| Brand kit and photo archive | The supplied files informed the theme and optional private preview media. See the photo usage report for individual review status. |
| Stakeholder notes | The notes describe proposed content and features. Current services, partnerships, events, quotations, and historical claims require separate sources. |

[Public reference site](https://rjhub.berkeley.edu/) · [Inventory reference](https://docs.google.com/spreadsheets/d/1q5u7DwmMeD3S6AWuaxf1QF5JQNrUGitpD-hl9MbXa3o/edit?gid=2080202480#gid=2080202480)

## Current Counts

| Record group | Published in local demo | Imported from source |
| --- | ---: | ---: |
| Local starter pages | 17 | 0 |
| Local starter resources | 2 | 0 |
| Prior published posts, projects, or content pages | 0 | 0 |
| Prior drafts, pending, private, or unpublished records | 0 | 0 |
| Verified map locations and current partner and offering records | 0 | 0 |

The empty archives identify missing published records. The map will show published locations once the research is verified. The prototype uses general educational copy without attributed quotes. The archive, offering, and student participation pages include their review state. The public site might contain duplicate About and Get Involved pages, dated service descriptions, and recent articles from 2026 mentioned in the brief. Those are audit work items in `content/CONTENT_INVENTORY.csv`.

## Reconcile an Authorized Export

A content owner should obtain a current authorized content export and the inventory sheet. Include full bodies, original public bylines, exact publication and revision dates with time zone if known, URLs, status, categories, image alt text, credits and releases, citations, and linked PDFs. Keep drafts and private source files in a restricted location. Compare counts separately for source-published, source-unpublished, excluded, duplicate, and imported records. Resolve the canonical About and Get Involved pages, service descriptions, and 2026 news before mapping their destinations.

Review a representative sample and every flagged item against its original. Compare the complete text, dates, bylines, citations, categories, and attachments in the editor and rendered preview. Capture exceptions and reasons in the inventory. WordPress uses its local edit history for the new site's revision tracking. The original revision date is retained as separate source metadata.

## Import Reviewed Records

Copy `content/AUTHORIZED_IMPORT_TEMPLATE.csv` into an authorized working folder outside the project directory. Put each source body in its own HTML file under the same folder. Retain backups of the authorized exports separately in the owner's restricted storage.

Fill the template with one reviewed record per row.

| Fields | Entry |
| --- | --- |
| `source_id`, `source_url`, `source_status` | Use the original stable ID and original URL whenever available. Give IDs a namespace if combining exports. Supported statuses are `publish`, `published`, `draft`, `pending`, `private`, `future`, and `unpublished`. Only the first two can be eligible for publication. |
| `title`, `byline`, `published_at`, `modified_at` | Preserve the original public values. Date values use YYYY-MM-DD or ISO 8601 with time and offset. Leave an unknown byline blank. |
| `wordpress_author_login` | Optional existing WordPress account for workflow ownership. The original byline is preserved separately. |
| `target_type`, `target_slug` | Choose `post`, `page`, `rj_project`, `rj_resource`, `rj_map_entry`, `rj_partner`, or `rj_offering`. Use an explicit lowercase slug with dashes. Canonical conflicts with a starter page need a manual merge in Gutenberg. |
| `body_file`, `body_format`, `excerpt` | Give the path of the full text relative to the manifest. Use `classic` for a Classic editor block or `blocks` for a Gutenberg export. Supply the reviewed excerpt when available. Compare any sanitizer warnings to the source before release. |
| `review_status`, `rights_status`, `approved_for_preview` | Review status is `unreviewed`, `in_review`, or `verified`. Rights status is `review_required` or `cleared`. Preview approval is `yes` or `no`. The importer publishes only source-published items with all three clearances and a known non-future date. Other items enter WordPress as drafts. |
| `source_attachment_urls`, `citation_url`, `pdf_url` | Give the original attachment links separated by pipes. The importer records the links and preserves approved HTML. Upload cleared attachment files to the Media Library through the editor and replace origin file links after verifying the copies. Set the structured citation and PDF fields for relevant records. |
| `terms`, `tags` | Pipe-separated category names for posts, resource type names for resources, and topic names for projects. Posts also accept pipe-separated tags. |
| `location`, `latitude`, `longitude`, `event_date`, `nation`, `content_warning` | Provide the map fields and a citation URL for a verified historical location. Use a date range or note the uncertainty supported by the citation. Partner and offering publication also requires a source URL. |

Run from the prototype directory. Set `rjhub_review_dir` to the absolute path to the working folder. The CLI container must be able to read the mounted files. Review the dry-run output before applying. Use an isolated local installation with restricted access while working with unpublished material.

```bash
rjhub_review_dir=/path/to/authorized-review
docker compose run --rm --no-deps -v "$rjhub_review_dir:/source:ro" cli wp \
  eval-file /workspace/project/scripts/import.php /source/reviewed.csv
docker compose run --rm --no-deps -v "$rjhub_review_dir:/source:ro" cli wp \
  eval-file /workspace/project/scripts/import.php /source/reviewed.csv apply
```

Existing records are matched by original ID or original URL and kept as edited in WordPress. For subsequent content revisions, use the editor and retain a source comparison in the inventory. Review the command's result and verify created IDs, actual publication status, canonical URL, public byline, dates, and body. A validation failure will leave contents unchanged. A failure after writes begin can leave earlier successful records in place. Correct the issue and rerun to continue.

The importer was exercised on a separate local WordPress installation with four synthetic records. Two approved records published, while the source-pending article and unverified map entry stayed drafts and returned 404 at their public URLs. The test preserved the approved article's visible byline, complete body, source URL, original date and time, stored revision date, category, and tag. Reapplying the manifest preserved all four existing records. A manifest pointing outside its working folder failed preflight without writing a record. The synthetic test records are outside the site package.

## Redirects

`content/REDIRECTS.csv` is a review queue with zero active redirects. Replace blank legacy paths with exact authorized source paths after reconciliation. Keep an original path where possible. For each confirmed path change, test a permanent redirect to its equivalent published destination on an authorized test environment before scheduling a cutover. Keep the root URL as the root.
