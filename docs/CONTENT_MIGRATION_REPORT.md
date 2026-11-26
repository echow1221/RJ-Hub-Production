# Content Migration

The supplied workbook was reconciled row by row. The workbook has one populated worksheet called **Pre-migration content inventory**. Its 149 rows contain 149 unique Drupal IDs and source URLs. It records type, title, status, author, creation and update text, URL, and editorial inventory notes. The recorded author could be a CMS account. The recorded creation date could differ from the actual publication date. The worksheet contains no full article bodies, verified public bylines, verified publication dates, images, citations, documents, or downloadable source assets.

The detailed reconciliation is a separate private CSV for the site owner. It includes the exact inventory values, a candidate WordPress route when applicable, source access and migration status, reasons, and review items for every row. It contains 41 unpublished records and their editorial metadata. Keep it in restricted storage. The project and website package contain the aggregate `content/CONTENT_INVENTORY.csv` only.

## Source Access and Counts

Read-only HTTPS requests to the live homepage, robots policy, sitemap, and a representative Home, About, and 2026 news URL all returned HTTP 403 CONNECT from the cloud proxy before reaching the origin. The refusal applies to the hostname. Individual inventory URLs could not be inspected. The cloud workspace contains the workbook, the fresh-start kit, the current local WordPress project, and its own exports. No authorized legacy HTML, database or WXR export, or legacy media export was present. The live website was untouched. [Public reference site](https://rjhub.berkeley.edu/)

| Workbook type | Marked published | Marked unpublished | Total | Imported |
| --- | ---: | ---: | ---: | ---: |
| Content Page | 61 | 11 | 72 | 0 |
| Landing Page | 20 | 13 | 33 | 0 |
| News Item | 27 | 17 | 44 | 0 |
| Total | 108 | 41 | 149 | 0 |

- All 108 rows marked published are blocked on source retrieval. The workbook flag establishes an inventory snapshot. Current availability and public metadata still need source confirmation.
- All 41 rows marked unpublished are held for private handling. They were excluded from origin requests and public output. Use draft status if an authorized export provides their bodies.
- Of the 108 candidate records, eight point to existing prototype routes and 100 have proposed paths. An existing prototype route still requires source content review and replacement. The actual migrated URL field is blank for all 149 records.
- Eight normalized-title duplicate groups cover 16 records. Four records have potential collisions in their proposed WordPress paths. Related About and Get Involved variants, the former Center's service descriptions, and five news items created in 2026 have additional review flags.
- The site starts with original educational starter material for review. Archived news, official mission text, partner claims, and program descriptions remain source-dependent.

## Recover the Source Material

Enable read-only HTTPS access to `rjhub.berkeley.edu` in the current cloud environment, or supply an authorized site export with media. If the public host can be reached, check its robots policy and use the workbook's published candidate URLs. Request public pages only. Obtain unpublished material through a site-owner export.

Include full bodies, actual public bylines, original publication and revision dates with time zone where known, URLs and statuses, categories, alt text, credits, rights, citations, and linked PDFs. Compare the exported IDs to the private reconciliation and resolve discrepancies before import. Start with the 2026 news and pages about the current Hub. Review staff and student services separately as historical material or approved current offerings. Confirm the canonical versions of About and Get Involved.

Review the complete text and attributed metadata in the editor and rendered preview. Match embedded hyperlinks, citations, captions, and links to migrated attachments. Record missing or excluded items and decisions by source ID. WordPress tracks changes made after migration and stores the original revision timestamp as source metadata.

## Reproduce the Workbook Audit

Run the audit script with Python and `openpyxl` 3.1.5. The output must be a new file outside the project checkout. Use the flag when there is a confirmed hostname-wide proxy denial. Omit it for an inventory audit before checking connectivity.

```bash
python scripts/reconcile-workbook.py \
  '/path/to/rj-center content inventory.xlsx' \
  /path/to/private/RJ_Hub_Private_149_Record_Reconciliation.csv \
  --host-proxy-denied
```

The workbook audit was verified by matching all 149 output rows back to the original worksheet fields, for IDs, titles, URLs, author and date metadata, statuses, and notes. It also verified all 41 unpublished holds, blank unknown public bylines and dates, and the private file permissions.

## Import Reviewed Records

Use the [authorized import guide](AUTHORIZED_IMPORT.md) to prepare and validate the full bodies, exact source metadata, and reviewed media. Work in an isolated local installation when handling private sources. An imported status requires a created record, verified source comparison, and confirmation of publication or draft status as appropriate.

The initial importer was exercised in a separate local WordPress instance with four synthetic records. Two approved records published, and a pending article and unverified map entry stayed drafts. Their public URLs returned 404. The test preserved the approved article's byline, body, source URL, publication date and time, stored revision timestamp, category, and tag. Reapplying preserved all four existing records. A body path outside the working directory failed preflight before a write. Those records are outside the site package.

## Redirects

`content/REDIRECTS.csv` contains candidate routes and zero active redirects. Match each original URL to the reviewed equivalent content before activating a redirect on an authorized staging environment. Retain existing source paths when possible and keep the canonical root as the root. The private reconciliation has candidate mappings for individual articles and the other inventory records marked published.
