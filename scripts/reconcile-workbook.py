#!/usr/bin/env python3
"""Create a private row-level reconciliation from the RJ Hub XLSX inventory."""

import argparse
import csv
import os
import re
import sys
import unicodedata
from collections import Counter, defaultdict
from pathlib import Path
from urllib.parse import unquote, urlsplit

from openpyxl import load_workbook

SOURCE_HOST = "rjhub.berkeley.edu"
SHEET = "Pre-migration content inventory"
HEADERS = (
    "Content Type", "Page Title", "Page ID", "Author", "Published?", "Created",
    "Updated", "Link", "WPS Recommendation", "Status", "Issues", "Notes",
)
DEFAULT_TYPES = {"Content Page": "page", "Landing Page": "page", "News Item": "post"}
EXISTING_PATHS = {
    6: "/",
    12: "/projects/",
    15: "/about/",
    16: "/what-is-rj/",
    25: "/contact/",
    185: "/get-involved/",
    203: "/resources/",
    218: "/legacy/",
}
LEGACY_SERVICE_IDS = {7, 68, 69, 73}
FIELDS = (
    "workbook_row", "original_id", "original_title", "old_url", "source_type",
    "inventory_publication_flag", "inventory_author", "inventory_created",
    "inventory_updated", "inventory_recommendation", "inventory_status",
    "inventory_issues", "inventory_notes", "verified_source_byline",
    "verified_publication_date", "verified_source_revision_date", "source_access",
    "body_status", "link_and_citation_status", "media_and_documents_status",
    "candidate_wp_type", "candidate_wp_url", "candidate_url_status", "actual_wp_url",
    "migration_status", "reason", "manual_review", "redirect_candidate",
)
BASE_CHECKS = (
    "Obtain authorized full body",
    "Verify public byline, publication date, and revisions",
    "Reconcile hyperlinks, citations, image rights, and documents",
)


def read_workbook(path):
    workbook = load_workbook(path, read_only=True, data_only=True)
    try:
        if SHEET not in workbook.sheetnames:
            raise ValueError(f"Missing workbook tab {SHEET!r}.")
        entries = workbook[SHEET].iter_rows(max_col=len(HEADERS), values_only=True)
        if next(entries, None) != HEADERS:
            raise ValueError("Workbook columns differ from the expected inventory schema.")
        rows = []
        ids = set()
        for index, values in enumerate(entries, start=2):
            if all(value is None for value in values):
                continue
            source_type, title, raw_id, author, publish_flag, created, updated, old_url, *optional = values
            try:
                source_id = int(raw_id)
            except (TypeError, ValueError, OverflowError) as error:
                raise ValueError(f"Invalid source ID at workbook row {index}.") from error
            if source_id != raw_id or source_id < 1 or source_id in ids:
                raise ValueError(f"Invalid or duplicate source ID at workbook row {index}.")
            if source_type not in DEFAULT_TYPES or publish_flag not in {"Yes", "No"}:
                raise ValueError(f"Unrecognized type or publication flag at workbook row {index}.")
            if not title or not isinstance(old_url, str):
                raise ValueError(f"Missing title or source URL at workbook row {index}.")
            url = urlsplit(old_url)
            if url.scheme != "https" or url.netloc != SOURCE_HOST:
                raise ValueError(f"Unexpected source host or URL scheme at workbook row {index}.")
            ids.add(source_id)
            rows.append({
                "workbook_row": index,
                "original_id": source_id,
                "original_title": title,
                "old_url": old_url,
                "source_type": source_type,
                "inventory_publication_flag": publish_flag,
                "inventory_author": author,
                "inventory_created": created,
                "inventory_updated": updated,
                **dict(zip(("inventory_recommendation", "inventory_status", "inventory_issues", "inventory_notes"), optional)),
            })
        if not rows:
            raise ValueError("Workbook contains no inventory records.")
        return rows
    finally:
        workbook.close()


def title_key(title):
    return "".join(ch for ch in unicodedata.normalize("NFKC", title).casefold() if ch.isalnum())


def candidate_route(row):
    if row["inventory_publication_flag"] == "No":
        return "", "unpublished_hold"
    source_id = row["original_id"]
    if source_id in EXISTING_PATHS:
        return EXISTING_PATHS[source_id], "prototype_route_only"
    path = unquote(urlsplit(row["old_url"]).path.rstrip("/"))
    tail = path.rsplit("/", 1)[-1]
    ascii_tail = unicodedata.normalize("NFKD", tail).encode("ascii", errors="ignore").decode("ascii")
    slug = re.sub(r"[^a-z0-9]+", "-", ascii_tail.lower()).strip("-")
    return (f"/{slug}/", "candidate_only") if slug else ("", "needs_manual_path")


def family_flags(row):
    title = row["original_title"].casefold()
    source_id = row["original_id"]
    path = urlsplit(row["old_url"]).path
    flags = []
    if title.startswith("about ") or source_id in {6, 15}:
        flags.append("Reconcile About variants and verify current Hub wording")
    if "get involved" in title or source_id in {183, 185, 191}:
        flags.append("Reconcile Get Involved variants and confirm current opportunities")
    if path.startswith("/about-rj-center/") or source_id == 218:
        flags.append("Distinguish former Center material from current Hub information")
    if source_id in LEGACY_SERVICE_IDS:
        flags.append("Confirm historical service status and current approval separately")
    if row["source_type"] == "News Item" and re.search(r"\b2026\b", str(row["inventory_created"])):
        flags.append("Prioritize 2026 news and verify chronology and byline")
    return flags


def reconcile(rows, host_denied):
    titles = defaultdict(list)
    urls = defaultdict(list)
    for row in rows:
        titles[title_key(row["original_title"])].append(row["original_id"])
        route, route_status = candidate_route(row)
        row.update(candidate_wp_url=route, candidate_url_status=route_status)
        if route:
            urls[route].append(row["original_id"])

    for row in rows:
        held = row["inventory_publication_flag"] == "No"
        old_path = urlsplit(row["old_url"]).path
        candidate = row["candidate_wp_url"]
        checks = list(BASE_CHECKS)
        duplicate_ids = titles[title_key(row["original_title"])]
        conflicts = urls[candidate]
        if len(duplicate_ids) > 1:
            checks.append("Compare duplicate title group (IDs " + ", ".join(map(str, duplicate_ids)) + ")")
        if len(conflicts) > 1:
            checks.append("Resolve conflicting planned URL (IDs " + ", ".join(map(str, conflicts)) + ")")
        checks.extend(family_flags(row))
        if held:
            checks.append("Retain authorized material only as an unpublished draft")
        row.update(
            source_access="withheld_unpublished" if held else "host_proxy_denied" if host_denied else "not_checked",
            body_status="not_in_workbook_or_fetched",
            link_and_citation_status="not_in_workbook_or_fetched",
            media_and_documents_status="not_in_workbook_or_fetched",
            candidate_wp_type=DEFAULT_TYPES[row["source_type"]],
            migration_status="unpublished_hold" if held else "blocked_source_body" if host_denied else "pending_source_review",
            reason=(
                "Inventory marks this source unpublished. Keep it private pending authorized source review."
                if held else
                "Cloud proxy denies the source host. The workbook provides metadata only."
                if host_denied else
                "The workbook provides metadata only. Verify the complete authorized source."
            ),
            manual_review=" | ".join(checks),
            redirect_candidate=(
                "no_unpublished_redirect"
                if held else
                "same_path_candidate"
                if candidate and old_path.rstrip("/") == candidate.rstrip("/") else
                "pending_exact_redirect_review"
            ),
        )
    return rows


def write_private_csv(path, rows):
    # A new, separate file prevents accidentally overwriting a reviewed copy.
    target = os.open(path, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
    with os.fdopen(target, "w", encoding="utf-8-sig", newline="") as output:
        writer = csv.DictWriter(output, fieldnames=FIELDS, lineterminator="\n")
        writer.writeheader()
        writer.writerows(rows)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("workbook", type=Path)
    parser.add_argument("private_output", type=Path)
    parser.add_argument(
        "--host-proxy-denied", action="store_true",
        help="Record a confirmed hostname-wide proxy denial for otherwise public candidates.",
    )
    args = parser.parse_args()
    if Path(__file__).resolve().parents[1] in args.private_output.resolve().parents:
        parser.error("Write the private reconciliation outside the project checkout.")
    rows = reconcile(read_workbook(args.workbook), args.host_proxy_denied)
    write_private_csv(args.private_output, rows)
    totals = Counter(row["inventory_publication_flag"] for row in rows)
    print(f"Reconciled {len(rows)} records: {totals['Yes']} inventory-published, {totals['No']} unpublished.")


if __name__ == "__main__":
    try:
        main()
    except (ValueError, OSError) as error:
        sys.exit(str(error))
