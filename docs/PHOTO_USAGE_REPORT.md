# Photo usage and rights review

The starter kit contains 20 original photographs and screenshots. All 20 were inspected visually at contact-sheet scale and matched to the kit inventory. The originals remain unchanged in restricted source storage. The portable site includes four optimized photographs for an explicitly enabled private preview. All four need documented permission before any public display.

The [media inventory](../media/INVENTORY.csv) records every source filename, original width and height, byte count, SHA-256, review disposition and next action. The [import manifest](../media/MANIFEST.json) records attachment paths, dimensions, focal positions, descriptions, captions, credit fields and rights state. Filenames are inventory identifiers. Staff should verify dates, attribution and event context with the records owner.

## Private preview placements

| Original | Placement and crop | Alternative text | Caption in private preview |
| --- | --- | --- | --- |
| `2019FallDeannaClass.jpg` | Home hero on both compositions. The wide photograph shows most of the group and the table. Keep a wide crop on compact screens and avoid cutting away the circle. | People seated in a circle around a low table in a sunlit room. | Private preview · Archival circle photograph. Publication rights and event context pending. |
| `COSATraining1.jpg` | Supporting home image, What Is RJ and Style Guide. A loose landscape crop shows the group and the shared materials. | People seated in a circle near a plant and colorful materials. | Private preview · Archival circle photograph. Publication rights and event context pending. |
| `2019GSITRainbowID.jpg` | Offerings. Use a 4:3 crop that keeps the floor activity visible and presents it in an archival context. | People seated on the floor with colored sticks arranged between them. | Private preview · Archival workshop image. Publication rights and event context pending. |
| `Copy of 2015RJforSVSHConferenceCircleLea.jpg` | Legacy. Consider for About after clearance. Preserve a wide view to show the large circle of chairs and couches. | People seated in a large circle of chairs and couches. | Private preview · Archival gathering photograph. Publication rights and event context pending. |

## Held or excluded

| Files | Editorial disposition and follow-up |
| --- | --- |
| `081518SororityTraining.jpg`, `2021GSITDay2ConCircles copy.jpg`, `DeannaClass6.jpg`, `DeannasClass.jpg`, `SAOCircle8.JPG` | Hold. Revisit permission, context and credit when a specific editorial placement is approved. |
| `2018SpringJohannaWaysofBeingSquare copy.jpeg` | Hold. Confirm releases and whether handwritten board material can be shared. |
| `DecalTeamPhoto1.JPG` | Hold. Verify historical team context and obtain releases for all pictured individuals. |
| `RJOYTrainingKyle.JPG` | Hold. Verify context, permission and attribution. A filename supplies no evidence of a current partnership. |
| `20190926_115422 copy.jpg` | Exclude duplicate view. The framing and participants match the proposed home hero. Reconcile the verified master and credits if this source is ever considered. |
| `2018FallKIPPTraining4.jpg`, `Screen Shot 2018-11-13 at 12.40.45 PM copy.png` | Exclude school setting. Institute a documented permission review for potential school-age participants before reconsideration. |
| `2021JamboardRJC.png`, `Screen Shot 2018-02-21 at 2.40.45 PM copy.png`, `Screen Shot 2020-11-10 at 8.04.54 PM.png`, `Screen Shot 2020-11-18 at 11.02.41 AM.png`, `Screen Shot 2020-11-18 at 9.29.06 AM.png` | Restrict to the original source collection. Virtual meeting names, chat, faces and working material require explicit privacy review. The 2018 screenshot also needs provenance verification. |

## Media Library and publication

1. Gather photographer permission, participant releases as applicable, event context and the approved credit through the project's institutional review. Record the approval in the private rights register and set the approved scope.
2. Upload a cleared copy through **Media → Add New**. Open it in the Media Library and enter concise alternative text and the approved caption and credit. Use an empty alternative text for an image that is strictly decorative.
3. In **Pages → Edit**, select the image block and choose **Replace → Open Media Library**. Adjust the visible caption and any available crop controls. Use Preview to check the result at phone and desktop widths.
4. For a public handoff from a private preview database, replace or remove review-only blocks and the provisional site logo. Remove the provisional attachments from the Media Library and verify their upload files and generated sizes are absent from the handoff. Build a clean deployment package and inspect the WordPress export and uploads before sharing them.
5. Use the project-branded fallback layout or publish text-only while an image is awaiting clearance. Avoid implying that historical subjects are currently participating in programs.

## Regenerating private preview assets

The optional maintainer script reads the original kit and writes only the four private-preview derivatives and the provisional mark. It applies EXIF orientation, converts color-managed originals to sRGB, drops source EXIF and XMP metadata, and produces WebP at up to 1,600 px wide. WordPress creates its usual attachment sizes during import.

Use the supplied preview assets for ordinary WordPress setup. To regenerate them from the kit in a Python virtual environment, install Pillow, PyMuPDF and pypdf, then run the following from the project root.

```bash
python media/build-preview.py --kit /path/to/extracted/kit
```
