# Photo usage and rights review

The starter kit contains 20 original photographs and screenshots. All 20 were inspected visually at contact-sheet scale and matched to the kit inventory. The originals are retained in the source collection. **Zero photographs have documented publication clearance.** The standard ZIP includes the photo-free layout and the media inventory. A separate private-review ZIP may include four optimized photographs and the provisional logo. Keep the source originals and provisional derivatives out of any public deployment.

The [media inventory](../media/INVENTORY.csv) records every source filename, original width and height, byte count, SHA-256, review disposition and next action. The [import manifest](../media/MANIFEST.json) records attachment paths, dimensions, focal positions, descriptions, captions, credit fields and rights state. Filenames are inventory identifiers. Staff should verify dates, attribution and event context with the records owner.

## Private preview placements

These placements apply when private media is enabled for a fresh local install. The default install uses the photo-free editorial layout. Alternate Home, Brand System, Offerings and Legacy remain editorial drafts under the current publication decisions. Publishing one of those drafts requires a separate content and media review.

| Original | Placement and crop | Alternative text | Caption in private preview |
| --- | --- | --- | --- |
| `2019FallDeannaClass.jpg` | Home hero in both compositions. Keep the wide view of the group and table on compact screens. | People seated in a circle around a low table in a sunlit room. | Private preview · Archival circle photograph. Publication rights and event context pending. |
| `COSATraining1.jpg` | Home's About section, the alternate layout, What Is RJ and Brand System. A loose landscape crop shows the group and the shared materials. | People seated in a circle near a plant and colorful materials. | Private preview · Archival circle photograph. Publication rights and event context pending. |
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
2. Upload a cleared copy through **Media → Add New**. Open it in the Media Library and enter concise alternative text and the approved caption and credit. Use empty alternative text for decorative images.
3. To add an optional homepage photo, open **Pages → Home → Edit**. In List View, open **Homepage hero → Hero text and photograph**. Add an Image block below **Hero text and buttons**, at the same level as that group, and select the cleared photo from the Media Library. For an existing **Hero photograph** block, choose **Replace → Open Media Library**. Check the caption and crop at phone and desktop widths.
4. Build a public handoff from the photo-free instance. If using a database prepared for private review, replace or remove provisional blocks and the Site Logo, delete the provisional attachments, and verify that their original upload paths and generated sizes are gone. WordPress may serve attachments directly even when the page using them is a draft. Inspect the database, uploads and any screenshots before sharing the handoff.
5. Use the photo-free composition while imagery is awaiting clearance. Keep historical context and verified dates in captions even after clearance.

## Regenerating private preview assets

The optional maintainer script reads the original kit and writes only the four private-preview derivatives and the provisional mark. It applies EXIF orientation, converts color-managed originals to sRGB, drops source EXIF and XMP metadata, and produces WebP at up to 1,600 px wide. WordPress creates its usual attachment sizes during import.

Use the separate private-review ZIP if one was provided to your review group. To regenerate the ignored `media/preview/` directory from the original kit, install Pillow, PyMuPDF and pypdf in a Python virtual environment and run the following from the project root.

```bash
python media/build-preview.py --kit /path/to/extracted/kit
```

For authorized local reviewers, seed the generated media into a separate fresh instance bound to loopback.

```bash
COMPOSE_PROJECT_NAME=rjhubprivate RJHUB_PORT=8081 bash scripts/setup.sh --private-media
```

The initial seed records which photo mode is in use. Keep the private database, uploads and screenshots within the authorized review group. The [setup guide](../README.md) covers local access and the default installation.
