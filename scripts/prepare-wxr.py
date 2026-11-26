#!/usr/bin/env python3
"""Convert an authorized WordPress export into an unpublished review bundle."""

import argparse
import csv
from datetime import datetime
from html.parser import HTMLParser
from pathlib import Path
import re
import tempfile
from urllib.parse import urljoin, urlsplit
from xml.etree import ElementTree as ET
from zoneinfo import ZoneInfo, ZoneInfoNotFoundError


CONTENT = "{http://purl.org/rss/1.0/modules/content/}encoded"
EXCERPT = "{http://wordpress.org/export/1.2/excerpt/}encoded"
CREATOR = "{http://purl.org/dc/elements/1.1/}creator"
PUBLIC_TYPES = {"post", "page", "rj_project", "rj_resource", "rj_map_entry", "rj_partner", "rj_offering"}
MEDIA_SUFFIX = {".jpeg", ".jpg", ".gif", ".png", ".webp", ".pdf"}
MEDIA_TYPES = MEDIA_SUFFIX | {".svg", ".doc", ".docx", ".ppt", ".pptx", ".mp4", ".mov", ".mp3", ".zip"}
STATUSES = {"publish", "private", "draft", "pending", "future"}
TEMPLATES = Path(__file__).resolve().parent.parent / "content"


class BodyMedia(HTMLParser):
    def __init__(self, base):
        super().__init__(convert_charrefs=True)
        self.base = base
        self.items = {}

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag not in {"img", "a", "object", "embed", "video", "audio", "source"}:
            return
        value = attrs.get("src") or attrs.get("href") or attrs.get("data")
        if not value:
            return
        url = urljoin(self.base, value)
        parsed = urlsplit(url)
        if parsed.scheme in {"https", "http"} and (tag == "img" or Path(parsed.path).suffix.lower() in MEDIA_TYPES):
            self.items.setdefault(url, attrs.get("alt") or "")


class ExportTreeBuilder(ET.TreeBuilder):
    def doctype(self, name, public_id, system_id):
        raise ValueError("DTD declarations are outside the supported WordPress export format.")


def source_date(item, wp, field, timezone):
    for suffix, zone in (("_gmt", ZoneInfo("UTC")), ("", timezone)):
        raw = item.findtext(wp + field + suffix, "").strip()
        if not raw or raw.startswith("0000-"):
            continue
        parsed = datetime.strptime(raw, "%Y-%m-%d %H:%M:%S").replace(tzinfo=zone)
        return parsed.isoformat()
    return ""


def read_header(name):
    with (TEMPLATES / name).open(encoding="utf-8", newline="") as handle:
        return next(csv.reader(handle))


def write_rows(path, fields, rows):
    with path.open("x", encoding="utf-8", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=fields)
        writer.writeheader()
        writer.writerows(rows)


def meta(item, wp):
    return {
        entry.findtext(wp + "meta_key", ""): entry.findtext(wp + "meta_value", "")
        for entry in item.findall(wp + "postmeta")
    }


def prepare_bundle(source, output, timezone):
    text = source.read_text(encoding="utf-8-sig")
    parser = ET.XMLParser(target=ExportTreeBuilder())
    channel = ET.fromstring(text, parser=parser).find("channel")
    if channel is None:
        raise ValueError("Expected an RSS channel from WordPress Tools → Export.")
    wp = "{http://wordpress.org/export/1.2/}"
    if channel.findtext(wp + "wxr_version") != "1.2":
        raise ValueError("Expected a WordPress WXR export version 1.2.")
    site = channel.findtext(wp + "base_blog_url") or channel.findtext(wp + "base_site_url") or channel.findtext("link", "")
    parts = urlsplit(site)
    if parts.scheme not in {"https", "http"} or not parts.hostname or parts.username or parts.password:
        raise ValueError("The WXR export needs a valid base site URL.")
    source_namespace = f"{parts.netloc}{parts.path.rstrip('/')}:"
    authors = {
        author.findtext(wp + "author_login", ""): author.findtext(wp + "author_display_name", "")
        for author in channel.findall(wp + "author")
    }
    attachments_by_id = {}
    attachments_by_parent = {}
    for item in channel.findall("item"):
        if item.findtext(wp + "post_type") != "attachment":
            continue
        url = item.findtext(wp + "attachment_url", "").strip()
        item_id = item.findtext(wp + "post_id", "")
        if not url or not item_id.isdecimal():
            continue
        attachment = (url, item_id, meta(item, wp).get("_wp_attachment_image_alt", ""), item.findtext(EXCERPT, ""))
        attachments_by_id[item_id] = attachment
        attachments_by_parent.setdefault(item.findtext(wp + "post_parent", ""), []).append(attachment)

    rows, skipped = [], []
    (output / "bodies").mkdir()
    (output / "media-review").mkdir()
    for item in channel.findall("item"):
        kind = item.findtext(wp + "post_type", "")
        status = item.findtext(wp + "status", "")
        item_id = item.findtext(wp + "post_id", "")
        title = item.findtext("title", "").strip()
        body = item.findtext(CONTENT, "")
        if kind == "attachment":
            continue
        if kind not in PUBLIC_TYPES or status not in STATUSES or not item_id.isdecimal() or not title or not body.strip():
            skipped.append({"id": item_id, "type": kind, "status": status, "reason": "Review unsupported type/status or missing body, ID, or title."})
            continue
        if status == "publish" and item.findtext(wp + "post_password", ""):
            status = "protected"
        item_link = item.findtext("link", "").strip()
        slug = item.findtext(wp + "post_name", "").strip()
        if not re.fullmatch(r"[a-z0-9]+(?:-[a-z0-9]+)*", slug):
            slug = f"source-{item_id}"
        body_path = Path("bodies") / f"{item_id}.html"
        (output / body_path).write_text(body, encoding="utf-8")

        scanner = BodyMedia(item_link or site)
        scanner.feed(body)
        fields = meta(item, wp)
        featured_id = fields.get("_thumbnail_id", "")
        related = list(attachments_by_parent.get(item_id, []))
        if featured_id in attachments_by_id and attachments_by_id[featured_id] not in related:
            related.append(attachments_by_id[featured_id])
        attachment_items = {url: (ref, alt, caption) for url, ref, alt, caption in related}
        candidates = dict.fromkeys([*scanner.items, *attachment_items])
        media = []
        for url in candidates:
            extension = Path(urlsplit(url).path).suffix.lower()
            if extension not in MEDIA_SUFFIX:
                continue
            ref, alt, caption = attachment_items.get(url, ("", "", ""))
            role = "document" if extension == ".pdf" else "featured" if ref and ref == featured_id else "inline"
            media.append({"source_url": url, "role": role, "source_attachment_id": ref, "alt_text": alt or scanner.items.get(url, ""), "caption": caption, "rights_status": "review_required"})
        media_path = ""
        if media:
            media_path = f"media-review/{item_id}.csv"
            write_rows(output / media_path, read_header("AUTHORIZED_MEDIA_TEMPLATE.csv"), media)
        taxonomies = {"post": "category", "rj_project": "rj_project_topic", "rj_resource": "rj_resource_type"}
        categories = item.findall("category")
        terms = [term.text or "" for term in categories if term.get("domain") == taxonomies.get(kind, "")]
        tags = [term.text or "" for term in categories if kind == "post" and term.get("domain") == "post_tag"]
        rows.append({
            "source_id": source_namespace + item_id, "source_url": item_link, "source_status": status,
            "title": title, "byline": authors.get(item.findtext(CREATOR, ""), ""),
            "published_at": source_date(item, wp, "post_date", timezone),
            "modified_at": source_date(item, wp, "post_modified", timezone),
            "target_type": kind, "target_slug": slug, "body_file": body_path.as_posix(),
            "body_format": "blocks" if "<!-- wp:" in body else "classic", "excerpt": item.findtext(EXCERPT, ""),
            "review_status": "unreviewed", "rights_status": "review_required", "approved_for_preview": "no",
            "source_attachment_urls": "|".join(candidates), "media_manifest": media_path,
            "citation_url": fields.get("rj_source_url", ""), "pdf_url": fields.get("rj_pdf_url", ""),
            "terms": "|".join(terms), "tags": "|".join(tags),
            **{key: fields.get(f"rj_{key}", "") for key in ("location", "latitude", "longitude", "event_date", "nation", "content_warning")},
        })
    if not rows:
        raise ValueError("The export contains no supported records with full bodies.")
    write_rows(output / "reviewed.csv", read_header("AUTHORIZED_IMPORT_TEMPLATE.csv"), rows)
    write_rows(output / "conversion-exceptions.csv", ["id", "type", "status", "reason"], skipped)
    return len(rows), len(skipped)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("export", type=Path)
    parser.add_argument("review_dir", type=Path, help="A new directory inside restricted storage.")
    parser.add_argument("--timezone", default="America/Los_Angeles", help="Source timezone for dates without GMT values.")
    args = parser.parse_args()
    target = args.review_dir.resolve()
    if target.exists():
        parser.error("The review directory already exists. Choose a new directory.")
    try:
        with tempfile.TemporaryDirectory(prefix="rjhub-review-", dir=target.parent) as temp:
            bundle = Path(temp) / "review"
            bundle.mkdir(mode=0o700)
            converted, skipped = prepare_bundle(args.export, bundle, ZoneInfo(args.timezone))
            bundle.rename(target)
    except (OSError, ValueError, ET.ParseError, ZoneInfoNotFoundError) as error:
        parser.error(str(error))
    print(f"Prepared {converted} records for review. Review {skipped} conversion exceptions. Nothing was published or downloaded.")


if __name__ == "__main__":
    main()
