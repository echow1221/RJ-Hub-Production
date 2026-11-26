# RJ Hub brand system

The four-page RJ Hub brand kit is the design reference. Page 1 defines six exact colors. Page 2 separates formal site typography from teaching text and social graphics. Page 3 pairs the painted circle with playful lettering. Page 4 uses waves, calm blue fields and a community-oriented visual rhythm. The homepage uses a decorative circle and waves beside its opening text, with ordinary links to reach the main sections.

## Palette and contrast

The six official colors are defined in the block theme's `theme.json`. WordPress exposes them in Styles and generates CSS variables in the form `--wp--preset--color--<slug>`. Contrast values use the WCAG sRGB formula and compare each dark color to Off-white. Use Off-white text when reversing these combinations.

| Color | Slug | Hex | Recommended use | Contrast with Off-white |
| --- | --- | --- | --- | --- |
| RJ Hub Blue | `rj-blue` | `#1a307d` | Primary identity, headings and buttons | 11.85:1 |
| Deep Blue | `deep-blue` | `#022676` | Dark brand surfaces, links and focus on light backgrounds | 13.55:1 |
| Off-white | `off-white` | `#fcffff` | Reading surfaces and text on dark backgrounds | — |
| Secondary Blue | `secondary-blue` | `#405396` | Secondary accents or text on Off-white | 7.21:1 |
| Black | `black` | `#000000` | Text where neutral ink is needed | 20.89:1 |
| Deep Teal | `deep-teal` | `#254857` | Secondary surface, text and wayfinding accent | 9.75:1 |

Keep links recognizable through underlines or a clearly identifiable navigation or button style. Keep a visible keyboard outline on interactive controls. Dark-on-dark pairings among the brand blues and teal need separate contrast evaluation. The theme adds mist `#edf3fa` and rule `#d0dded` for pale surfaces and decorative borders. Gold appears in the supplied art and stays out of the WordPress palette. The ratios in this table document color pairs. The [QA report](QA_REPORT.md) records checks on rendered pages.

## Typography

Brixton appears in the brand specification for formal site and graphics use. A separate licensed webfont file was absent from the kit. The prototype uses the locally bundled Noto Serif Regular for display headings with Georgia and generic serif fallbacks. Noto Serif is distributed under SIL Open Font License 1.1 with a license copy in the theme's `assets/fonts` directory. Times New Roman is the teaching and long-form stack, and navigation and controls use a system sans-serif. Slopes remains reserved for social graphics.

Add Brixton only after the rights owner confirms that a supplied WOFF2 can be embedded for the intended domains and audience. A theme maintainer can install the approved file in the theme, register its local URL in the global font family definition and keep a readable serif fallback. Check headings, wrap points, form labels and keyboard focus at narrow widths after switching.

## Mark and logo replacement

With private review media enabled, the local prototype uses a transparent raster of the combined mark from page 3 of the PDF. The derivative retains the original lettering, paint and blue center and appears alongside the linked site title. The standard photo-free layout uses the linked text title. Keep the provisional mark within the authorized private review environment until the brand owner supplies a web-approved asset.

Ask the brand owner for an approved transparent PNG or deployment-reviewed SVG. In the WordPress dashboard, an administrator can use **Appearance → Editor → Patterns → Header**, select **Site Logo**, and choose **Replace**. Keep clear space around the painted circle and preview the header at mobile widths. Check the style guide and update its logo guidance after replacing the provisional mark. Keep the official mark, a short readable site title and menu controls easy to distinguish.

## Components and editor use

- Open **Pages → Brand System → Preview** for headings, buttons, swatches, cards, tables, quotes and image treatments. This draft and the alternate homepage are available to signed-in reviewers.
- Use **Styles → Colors** for the named palette, and WordPress block controls for spacing and alignment. Reserve tighter spacing for labels within a component and wider spacing for separate sections.
- Use a standard button for a primary action, a descriptive text link for supplementary reading and group headings for navigation destinations.
- For articles and project notes, start with a reading-width column, an ordinary heading hierarchy and a source list. For archives, use full-width content cards with clear titles and restrained decorative accents.
- For an image-and-text section, keep both regions readable on narrow screens and supply alt text and an approved caption. Use the [photo usage report](PHOTO_USAGE_REPORT.md) for placement and clearance.
- Use the curved line and circle motifs as decorative shapes and preserve meaning when decoration and motion are reduced.
