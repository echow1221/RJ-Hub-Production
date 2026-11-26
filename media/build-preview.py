#!/usr/bin/env python3
"""Regenerate private preview media using source files from the supplied kit."""

import argparse
import csv
import hashlib
import json
from io import BytesIO
from pathlib import Path

import fitz
from PIL import Image, ImageCms, ImageOps
from pypdf import PdfReader, PdfWriter
from pypdf.generic import ContentStream, DecodedStreamObject, NameObject


ROOT = Path(__file__).resolve().parent.parent
MEDIA = ROOT / "media"
PREVIEW = MEDIA / "preview"
BRAND_PDF = Path("brand-kit/Brand KitRJHub.pdf")

LOGO = {
    "alt": "RJ Hub lettering inside a painted circle",
    "caption": "Provisional mark extracted from the brand kit for private preview. Approved web logo pending.",
    "credit": "RJ Hub brand kit, page 3",
}


def oriented_srgb(source):
    image = ImageOps.exif_transpose(source)
    profile = image.info.get("icc_profile")
    if profile:
        image = ImageCms.profileToProfile(
            image,
            BytesIO(profile),
            ImageCms.createProfile("sRGB"),
            outputMode="RGB",
        )
    else:
        image = image.convert("RGB")
    # A fresh image drops source metadata, including identifying EXIF and XMP.
    return Image.frombytes("RGB", image.size, image.tobytes())


def remove_canvas_paint(stream, rectangle_position):
    operations = stream.operations
    assert operations[rectangle_position][1] == b"re"
    assert list(operations[rectangle_position][0]) == [0, 0, 794, 1123]
    assert operations[rectangle_position + 1][1] == b"f"
    operations[rectangle_position + 1] = ([], b"n")
    stream.operations = operations


def extract_mark(pdf_path):
    # Page 3 contains the original combined mark. Remove the white document
    # canvas at both nesting levels while preserving the art and lettering.
    reader = PdfReader(pdf_path)
    writer = PdfWriter()
    page = writer.add_page(reader.pages[2])
    stream = page.get_contents()
    remove_canvas_paint(stream, 15)
    page.replace_contents(stream)

    objects = page["/Resources"]["/XObject"]
    form = objects["/X29"]
    form_stream = ContentStream(form, writer)
    remove_canvas_paint(form_stream, 12)
    replacement = DecodedStreamObject()
    replacement.set_data(form_stream.get_data())
    for key, value in form.items():
        if key not in ("/Length", "/Filter", "/DecodeParms"):
            replacement[key] = value
    objects[NameObject("/X29")] = writer._add_object(replacement)

    with BytesIO() as pdf:
        writer.write(pdf)
        with fitz.open(stream=pdf.getvalue(), filetype="pdf") as document:
            pixmap = document[0].get_pixmap(
                matrix=fitz.Matrix(3, 3), clip=fitz.Rect(36, 601, 263, 807), alpha=True
            )
    with Image.open(BytesIO(pixmap.tobytes("png"))) as rendered:
        mark = rendered.crop(rendered.getbbox())
    mark.thumbnail((512, 512), Image.Resampling.LANCZOS)
    return mark


def manifest_entry(relative_path, image, alt, caption, credit="", **details):
    return {
        "path": relative_path.as_posix(),
        "width": image.width,
        "height": image.height,
        "alt": alt,
        "caption": caption,
        "credit": credit,
        "rights_status": "review_required",
        "private_only": True,
        **details,
    }


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--kit", type=Path, default=ROOT, help="Extracted starter kit root")
    kit = parser.parse_args().kit
    PREVIEW.mkdir(parents=True, exist_ok=True)
    reviewed = json.loads((MEDIA / "REVIEW.json").read_text(encoding="utf-8"))
    photos = kit / "assets" / "rjhub-photos"
    if set(reviewed) != {path.name for path in photos.iterdir() if path.is_file()}:
        raise SystemExit("The source photo set differs from the reviewed inventory.")

    inventory = []
    manifest = {}
    for filename, review in reviewed.items():
        source = photos / filename
        row = {
            "original": source.relative_to(kit).as_posix(),
            "original_bytes": source.stat().st_size,
            "original_sha256": hashlib.sha256(source.read_bytes()).hexdigest(),
            "rights_status": "review_required",
            "decision": review["decision"],
            "visual_review": review["visible"],
            "follow_up": review["follow_up"],
        }

        with Image.open(source) as raw:
            row["original_width"], row["original_height"] = raw.size
            if review["decision"] == "private_preview":
                image = oriented_srgb(raw)
                image.thumbnail((1600, 1600), Image.Resampling.LANCZOS)
                derivative = PREVIEW / review["filename"]
                image.save(derivative, format="WEBP", quality=83, method=6)
                relative = derivative.relative_to(ROOT)
                row.update(
                    derivative=relative.as_posix(),
                    derivative_width=image.width,
                    derivative_height=image.height,
                    derivative_bytes=derivative.stat().st_size,
                    placement=review["placement"],
                    crop=review["crop"],
                )
                manifest[review["key"]] = manifest_entry(
                    relative, image, review["visible"], review["caption"],
                    source=row["original"], focal_x=review["focal_x"], focal_y=review["focal_y"],
                )
        inventory.append(row)

    mark = extract_mark(kit / BRAND_PDF)
    logo_path = PREVIEW / "rj-hub-mark.png"
    mark.save(logo_path, optimize=True)
    manifest["rj-hub-mark"] = manifest_entry(
        logo_path.relative_to(ROOT), mark, **LOGO,
        source=BRAND_PDF.as_posix(),
    )
    (MEDIA / "MANIFEST.json").write_text(
        json.dumps(manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8"
    )
    columns = list(dict.fromkeys(key for row in inventory for key in row))
    with (MEDIA / "INVENTORY.csv").open("w", encoding="utf-8", newline="") as target:
        writer = csv.DictWriter(target, fieldnames=columns, lineterminator='\n')
        writer.writeheader()
        writer.writerows(inventory)


if __name__ == "__main__":
    main()
