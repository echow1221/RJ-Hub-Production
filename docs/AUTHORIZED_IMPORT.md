# Import an authorized source export

Use a separate local WordPress installation to review exports that contain drafts, private entries, or restricted files. Keep the original export and review folder outside the website checkout and downloadable package. A WordPress export can include private articles and author information. Keep access limited to the migration team. For Pantheon, the reviewed WordPress database, theme, plugin, and cleared uploads are the deployment inputs.

## Prepare the source

For WordPress, open **Tools → Export** on an account authorized to export the source. Export the applicable content, including full bodies and supported attachment references. Obtain authorized local copies of cleared images and PDFs separately. For another platform, start with `content/AUTHORIZED_IMPORT_TEMPLATE.csv` and place each full HTML or Gutenberg body in its own file inside the review directory.

On Ubuntu or WSL, the bundled Python 3 tool converts a WXR 1.2 file into the same review format. It reads the local export, creates a new folder, and records unsupported items in `conversion-exceptions.csv`. Review that report against the original export. The converter leaves the publication and rights fields unapproved, records available WXR author display names and timestamps, and lists linked media for review. Compare the display names to the public bylines and fill any missing or different bylines from an authorized copy of the original article. Use the source timezone if the export supplies local dates without UTC timestamps.

```bash
python3 scripts/prepare-wxr.py "$rjhub_export_file" "$rjhub_review_dir" \
  --timezone America/Los_Angeles
```

The CSV importer accepts the original column layout. The two optional columns in the current template are `legacy_path` and `media_manifest`.
If editing the CSV in a spreadsheet, import columns as text to preserve the exact dates, IDs, and text.

## Review records and files

- Preserve the source ID, title, complete body, public byline, publication date and time, revision date, status, categories and tags. Set `target_slug` to the original slug where available. For conflicts with a starter page or shared path, resolve the canonical target with the editor before import. For nested source paths, record a reviewed legacy redirect when the target path differs.
- A record publishes in the isolated preview only when the source status is published, `review_status` is `verified`, `rights_status` is `cleared`, `approved_for_preview` is `yes`, and its original publication date is valid. Other records enter as WordPress drafts with the source status saved separately. Originally published pages with a source password are classified as `protected` and stay drafts. The converter leaves source passwords out of the review bundle. Keep disputed and unavailable records in the review queue.
- To copy cleared media into WordPress, reference a CSV below the review folder in `media_manifest`. Use `content/AUTHORIZED_MEDIA_TEMPLATE.csv`. Set each media row's `source_url`, `local_file`, `role`, rights status, alt text, caption, and source credit. `local_file` is relative to the main manifest folder. Use `inline` or `featured` for JPEG, PNG, GIF, and WebP, or `document` for PDFs. `source_attachment_id` remaps Gutenberg image and file identifiers when available. Preserve an approved credit in the body or figure caption where the source requires public credit.
- For a published record with a media bundle, all listed files must be cleared and readable. The importer associates the files with that record in the Media Library, uses the featured image in archive cards, and rewrites matching original image and file URLs in supported HTML attributes and block settings. Review responsive images, embedded files, galleries, and source links in the visual editor. Windows paths or unsupported remote embeds may need editorial replacement. With no media bundle, the importer records original attachment links for follow-up. Draft attachments stay in restricted storage because WordPress uploads can be served directly.
- For a reviewed path change, enter the old path from `source_url` in `legacy_path`, such as `/news/original-slug`. The local site keeps existing working URLs and redirects only from a matching 404 to the published canonical record. Core routes, traversal segments, query-based legacy URLs, and external redirects require a separate reviewed deployment mapping. A draft or password-protected entry never receives an active redirect.

## Dry-run, apply, and verify

In the prototype directory, set `rjhub_review_dir` to an absolute path. The isolated CLI container runs as UID 33 and needs read access to the mounted files. Use an appropriate ACL in restricted Linux storage and keep the mount read-only. The command performs a full manifest preflight before it writes content.

```bash
docker compose run --rm --no-deps -v "$rjhub_review_dir:/source:ro" cli wp \
  eval-file /workspace/project/scripts/import.php /source/reviewed.csv
docker compose run --rm --no-deps -v "$rjhub_review_dir:/source:ro" cli wp \
  eval-file /workspace/project/scripts/import.php /source/reviewed.csv apply
```

Review the counts and open both the rendered page and Gutenberg record for representative imported articles, projects, documents, and all disputed entries. Test the exact legacy and destination paths as a signed-out visitor. Keep the original response status, full byline, publication date, image credit, document link, and original revision date in the migration comparison. Existing imports are retained on retry, including editorial changes. After a command interrupted mid-write, inspect any draft identified as an incomplete import before reconciling it. If the Blog or Projects landing page is a draft, review it in **Pages**, then publish it when ready to expose its working collection. Verified, published research and posts populate the featured homepage sections automatically.
