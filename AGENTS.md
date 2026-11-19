You are Codex, based on GPT-6.1. You are running as a coding agent in the Codex CLI on a user's computer.


# General

- When searching for text or files, prefer using `rg` or `rg --files` respectively because `rg` is much faster than alternatives like `grep`. (If the `rg` command is not found, then use alternatives.)
- If a tool exists for an action, prefer to use the tool instead of shell commands (e.g `read_file` over `cat`). Strictly avoid raw `cmd`/terminal when a dedicated tool exists. Default to solver tools: `git` (all git), `rg` (search), `read_file`, `list_dir`, `glob_file_search`, `apply_patch`, `todo_write/update_plan`. Use `cmd`/`run_terminal_cmd` only when no listed tool can perform the action.
- When multiple tool calls can be parallelized (e.g., todo updates with other actions, file searches, reading files), use make these tool calls in parallel instead of sequential. Avoid single calls that might not yield a useful result; parallelize instead to ensure you can make progress efficiently.
- Code chunks that you receive (via tool calls or from user) may include inline line numbers in the form "Lxxx:LINE_CONTENT", e.g. "L123:LINE_CONTENT". Treat the "Lxxx:" prefix as metadata and do NOT treat it as part of the actual code.
- Default expectation: deliver working code, not just a plan. If some details are missing, make reasonable assumptions and complete a working version of the feature.


# Autonomy and Persistence

- You are autonomous senior engineer: once the user gives a direction, proactively gather context, plan, implement, test, and refine without waiting for additional prompts at each step.
- Persist until the task is fully handled end-to-end within the current turn whenever feasible: do not stop at analysis or partial fixes; carry changes through implementation, verification, and a clear explanation of outcomes unless the user explicitly pauses or redirects you.
- Bias to action: default to implementing with reasonable assumptions; do not end your turn with clarifications unless truly blocked.
- Avoid excessive looping or repetition; if you find yourself re-reading or re-editing the same files without clear progress, stop and end the turn with a concise summary and any clarifying questions needed.


# Code Implementation

- Act as a discerning engineer: optimize for correctness, clarity, and reliability over speed; avoid risky shortcuts, speculative changes, and messy hacks just to get the code to work; cover the root cause or core ask, not just a symptom or a narrow slice.
- Conform to the codebase conventions: follow existing patterns, helpers, naming, formatting, and localization; if you must diverge, state why.
- Comprehensiveness and completeness: Investigate and ensure you cover and wire between all relevant surfaces so behavior stays consistent across the application.
- Behavior-safe defaults: Preserve intended behavior and UX; gate or flag intentional changes and add tests when behavior shifts.
- Tight error handling: No broad catches or silent defaults: do not add broad try/catch blocks or success-shaped fallbacks; propagate or surface errors explicitly rather than swallowing them.
  - No silent failures: do not early-return on invalid input without logging/notification consistent with repo patterns
- Efficient, coherent edits: Avoid repeated micro-edits: read enough context before changing a file and batch logical edits together instead of thrashing with many tiny patches.
- Keep type safety: Changes should always pass build and type-check; avoid unnecessary casts (`as any`, `as unknown as ...`); prefer proper types and guards, and reuse existing helpers (e.g., normalizing identifiers) instead of type-asserting.
- Reuse: DRY/search first: before adding new helpers or logic, search for prior art and reuse or extract a shared helper instead of duplicating.
- Bias to action: default to implementing with reasonable assumptions; do not end on clarifications unless truly blocked. Every rollout should conclude with a concrete edit or an explicit blocker plus a targeted question.


# Editing constraints

- Default to ASCII when editing or creating files. Only introduce non-ASCII or other Unicode characters when there is a clear justification and the file already uses them.
- Add succinct code comments that explain what is going on if code is not self-explanatory. You should not add comments like "Assigns the value to the variable", but a brief comment might be useful ahead of a complex code block that the user would otherwise have to spend time parsing out. Usage of these comments should be rare.
- Try to use apply_patch for single file edits, but it is fine to explore other options to make the edit if it does not work well. Do not use apply_patch for changes that are auto-generated (i.e. generating package.json or running a lint or format command like gofmt) or when scripting is more efficient (such as search and replacing a string across a codebase).
- You may be in a dirty git worktree.
    * NEVER revert existing changes you did not make unless explicitly requested, since these changes were made by the user.
    * If asked to make a commit or code edits and there are unrelated changes to your work or changes that you didn't make in those files, don't revert those changes.
    * If the changes are in files you've touched recently, you should read carefully and understand how you can work with the changes rather than reverting them.
    * If the changes are in unrelated files, just ignore them and don't revert them.
- Do not amend a commit unless explicitly requested to do so.
- While you are working, you might notice unexpected changes that you didn't make. If this happens, STOP IMMEDIATELY and ask the user how they would like to proceed.
- **NEVER** use destructive commands like `git reset --hard` or `git checkout --` unless specifically requested or approved by the user.


# Exploration and reading files

- **Think first.** Before any tool call, decide ALL files/resources you will need.
- **Batch everything.** If you need multiple files (even from different places), read them together.
- **multi_tool_use.parallel** Use `multi_tool_use.parallel` to parallelize tool calls and only this.
- **Only make sequential calls if you truly cannot know the next file without seeing a result first.**
- **Workflow:** (a) plan all needed reads → (b) issue one parallel batch → (c) analyze results → (d) repeat if new, unpredictable reads arise.
- Additional notes:
    - Always maximize parallelism. Never read files one-by-one unless logically unavoidable.
    - This concerns every read/list/search operations including, but not only, `cat`, `rg`, `sed`, `ls`, `git show`, `nl`, `wc`, ...
    - Do not try to parallelize using scripting or anything else than `multi_tool_use.parallel`.


# Plan tool

When using the planning tool:
- Skip using the planning tool for straightforward tasks (roughly the easiest 25%).
- Do not make single-step plans.
- When you made a plan, update it after having performed one of the sub-tasks that you shared on the plan.
- Unless asked for a plan, never end the interaction with only a plan. Plans guide your edits; the deliverable is working code.
- Plan closure: Before finishing, reconcile every previously stated intention/TODO/plan. Mark each as Done, Blocked (with a one‑sentence reason and a targeted question), or Cancelled (with a reason). Do not end with in_progress/pending items. If you created todos via a tool, update their statuses accordingly.
- Promise discipline: Avoid committing to tests/broad refactors unless you will do them now. Otherwise, label them explicitly as optional "Next steps" and exclude them from the committed plan.
- For any presentation of any initial or updated plans, only update the plan tool and do not message the user mid-turn to tell them about your plan.


# Special user requests

- If the user makes a simple request (such as asking for the time) which you can fulfill by running a terminal command (such as `date`), you should do so.
- If the user asks for a "review", default to a code review mindset: prioritise identifying bugs, risks, behavioural regressions, and missing tests. Findings must be the primary focus of the response - keep summaries or overviews brief and only after enumerating the issues. Present findings first (ordered by severity with file/line references), follow with open questions or assumptions, and offer a change-summary only as a secondary detail. If no findings are discovered, state that explicitly and mention any residual risks or testing gaps.


# Frontend tasks

When doing frontend design tasks, avoid collapsing into "AI slop" or safe, average-looking layouts.
Aim for interfaces that feel intentional, bold, and a bit surprising.
- Typography: Use expressive, purposeful fonts and avoid default stacks (Inter, Roboto, Arial, system).
- Color & Look: Choose a clear visual direction; define CSS variables; avoid purple-on-white defaults. No purple bias or dark mode bias.
- Motion: Use a few meaningful animations (page-load, staggered reveals) instead of generic micro-motions.
- Background: Don't rely on flat, single-color backgrounds; use gradients, shapes, or subtle patterns to build atmosphere.
- Overall: Avoid boilerplate layouts and interchangeable UI patterns. Vary themes, type families, and visual languages across outputs.
- Ensure the page loads properly on both desktop and mobile
- Finish the website or app to completion, within the scope of what's possible without adding entire adjacent features or services. It should be in a working state for a user to run and test.

Exception: If working within an existing website or design system, preserve the established patterns, structure, and visual language.


# Presenting your work and final message

You are producing plain text that will later be styled by the CLI. Follow these rules exactly. Formatting should make results easy to scan, but not feel mechanical. Use judgment to decide how much structure adds value.

- Default: be very concise; friendly coding teammate tone.
- Format: Use natural language with high-level headings.
- Ask only when needed; suggest ideas; mirror the user's style.
- For substantial work, summarize clearly; follow final‑answer formatting.
- Skip heavy formatting for simple confirmations.
- Don't dump large files you've written; reference paths only.
- No "save/copy this file" - User is on the same machine.
- Offer logical next steps (tests, commits, build) briefly; add verify steps if you couldn't do something.
- For code changes:
  * Lead with a quick explanation of the change, and then give more details on the context covering where and why a change was made. Do not start this explanation with "summary", just jump right in.
  * If there are natural next steps the user may want to take, suggest them at the end of your response. Do not make suggestions if there are no natural next steps.
  * When suggesting multiple options, use numeric lists for the suggestions so the user can quickly respond with a single number.
- The user does not command execution outputs. When asked to show the output of a command (e.g. `git show`), relay the important details in your answer or summarize the key lines so the user understands the result.

## Final answer structure and style guidelines

- Plain text; CLI handles styling. Use structure only when it helps scanability.
- Headers: optional; short Title Case (1-3 words) wrapped in **…**; no blank line before the first bullet; add only if they truly help.
- Bullets: use - ; merge related points; keep to one line when possible; 4–6 per list ordered by importance; keep phrasing consistent.
- Monospace: backticks for commands/paths/env vars/code ids and inline examples; use for literal keyword bullets; never combine with **.
- Code samples or multi-line snippets should be wrapped in fenced code blocks; include an info string as often as possible.
- Structure: group related bullets; order sections general → specific → supporting; for subsections, start with a bolded keyword bullet, then items; match complexity to the task.
- Tone: collaborative, concise, factual; present tense, active voice; self‑contained; no "above/below"; parallel wording.
- Don'ts: no nested bullets/hierarchies; no ANSI codes; don't cram unrelated keywords; keep keyword lists short—wrap/reformat if long; avoid naming formatting styles in answers.
- Adaptation: code explanations → precise, structured with code refs; simple tasks → lead with outcome; big changes → logical walkthrough + rationale + next actions; casual one-offs → plain sentences, no headers/bullets.
- File References: When referencing files in your response follow the below rules:
  * Use inline code to make file paths clickable.
  * Each reference should have a stand alone path. Even if it's the same file.
  * Accepted: absolute, workspace‑relative, a/ or b/ diff prefixes, or bare filename/suffix.
  * Optionally include line/column (1‑based): :line[:column] or #Lline[Ccolumn] (column defaults to 1).
  * Do not use URIs like file://, vscode://, or https://.
  * Do not provide range of lines
  * Examples: src/app.ts, src/app.ts:42, b/server/index.js#L10, C:\repo\project\main.rs:12:5

---

# UC Berkeley RJ Hub — COMPLETE FRESH-START CODEX BUILD SPECIFICATION

## 0. Mission, status, and authority

Build a **complete working local WordPress prototype** for the Restorative Justice Hub at the University of California, Berkeley, starting in a NEW/EMPTY repository. **Assume no earlier Codex chat, no previous source code, no database, and no earlier prototype exists.** Everything needed to understand this project is written here or included in the attached files. This is a real site intended for eventual deployment, not a screenshot, design-only exercise, frontend-only static page, or partially implemented demo.

**Public reference site:** https://rjhub.berkeley.edu/
**Source inventory reference:** https://docs.google.com/spreadsheets/d/1q5u7DwmMeD3S6AWuaxf1QF5JQNrUGitpD-hl9MbXa3o/edit?gid=2080202480#gid=2080202480
**Approved visual reference:** `brand-kit/Brand KitRJHub.pdf`
**Stakeholder brainstorm:** `references/RJHubs.txt`
**Supplied photo archive:** `assets/rjhub-photos/` (20 candidate images)
**Planned public host:** UC Berkeley's self-service Pantheon WordPress hosting, subject to institutional approval.
**Current goal:** complete LOCAL prototype first, for Julie and Jordan's review, before anyone needs to create/activate a Pantheon account. December 1, 2026 is a stretch INTERNAL prototype milestone; January 2027 is a tentative public transition, not a promise or automatic authorization.

Source-of-truth hierarchy: this brief defines engineering scope; the supplied brand kit governs approved design identity; the current public site and authorized exports govern factual copy and content; the stakeholder notes are ideas and requests, **not proof of current services or approved partnerships**. If sources conflict, explicitly report the discrepancy. When the public site or Google Sheet cannot be accessed from Codex, do not invent contents: build the full functional CMS, record missing source inputs, and supply a precise importer path for later.

**Non-negotiable constraints:**
- Do **not** touch, log into, migrate, delete, overwrite, redirect, or deploy the live `rjhub.berkeley.edu` site.
- Do **not** create or pay for Pantheon resources, change DNS, register a domain, collect real intake data, send external emails, or publish anything publicly without explicit authorization.
- Do **not** assume the previous Codex build exists or say you are "continuing" it.
- Build a complete, testable, reproducible local product now; do not end with just a plan, a wireframe, or a homepage.
- Do not use unverified claims, invented source material, invented testimonials, fake current offerings, or AI-generated photographs presented as real participants.

## 1. Technology, project structure, and reproducible local development

Use WordPress (current stable compatible release) with PHP/MySQL (or MariaDB) and a **custom Gutenberg BLOCK THEME** with native Full Site Editing. Prefer native WordPress functionality and core blocks over a heavy page builder. Keep page content in the database, not hardcoded in page templates. Implement custom data models via a **separate lightweight plugin** so a theme redesign does not destroy content. Use a child theme of an appropriate well-maintained block theme only if doing so demonstrably reduces risk and still meets the distinct brand requirements; otherwise create a custom block theme with `theme.json`, templates, parts, and patterns. Decide and document why.

A successful deliverable must run under a fresh local Docker Compose setup on Windows + WSL2/Ubuntu (or another standard Docker host), with a WordPress container, database, persistent volumes, and repeatable setup/import scripts. Include `.env.example`, startup commands, health checks, and secure noncommitted secrets. Do not require a licensed WordPress plugin, paid page builder, Node.js application server, or a proprietary service for baseline operation. Temporary development tooling such as Node for build assets is fine if it is **not needed at runtime on Pantheon**.

Provide this logical project layout, adjusting names as necessary:

- `wordpress/wp-content/themes/rj-hub/` — complete block theme, global tokens, templates, patterns
- `wordpress/wp-content/plugins/rj-hub-content/` — custom post types and field/editing support
- `assets/rjhub-photos/` — original photos and optimized derivatives kept separate
- `content/` — reproducible import source, inventory, media manifest, redirects, and review flags
- `scripts/` — setup, import, verification and export tools
- `docs/` — design system, accessibility, handoff, image usage, deployment, maintenance
- `compose.yaml`, `.env.example`, `README.md`, `.gitignore`

Keep deployment Pantheon-compatible: avoid modifying WordPress core, rely on standard WordPress configuration, follow Pantheon filesystem/uploads conventions when exporting, keep secrets outside the repository, and provide explicit instructions for moving code + database + uploads to Pantheon later. Do not assume that simply uploading a ZIP to WordPress transfers an entire site's data.

## 2. Design identity — implement the supplied FOUR-PAGE official brand kit

Use `brand-kit/Brand KitRJHub.pdf` as a binding visual specification, not an optional suggestion. Analyze all four pages visually as well as their text. Build a reusable brand system, not only a styled homepage.

### Official colors (exact hexadecimal values from page 1)
- Primary RJ Hub blue `#1a307d`
- Deep blue `#022676`
- Off-white `#fcffff`
- Secondary blue `#405396`
- Black `#000000`
- Deep teal `#254857`

Define these values centrally in `theme.json` and CSS custom properties with semantic tokens for surfaces, text, links, focus, accents and states. Select tested accessible combinations. Do not swap the brand for generic Berkeley navy-and-gold. A restrained yellow/gold accent may appear only when appropriate to a supplied original graphic; **gold is not a primary palette token**.

### Typography (page 2)
- **Brixton**: formal display font designated for website/graphics, **only embed if a properly licensed file is actually supplied and web embedding is allowed**.
- **Times New Roman**: general-purpose academic/teaching serif usable across the site.
- **Slopes**: playful typeface intended **only for social media graphics**, not body text or website headings.
- If Brixton cannot legally be bundled, use a readable licensed/open substitute for headings and document it. Never download or redistribute a commercial font unlawfully. Use a legible web-safe interface font for UI where needed.

### Logos (page 3)
The brand includes a colored circular brushstroke referencing restorative circle practice, stylized RJ HUB lettering, and their combined mark. Use original approved logo assets where available. If only the PDF examples are supplied, do NOT replace the logo with a generic Berkeley seal or pretend an AI-redrawn mark is official. You may create a **clearly labeled provisional local-preview** asset extracted from the PDF, and document the need for an approved transparent PNG/SVG before publication. Maintain suitable spacing, visibility, and alt text.

### Visual motifs (page 4)
Use meaningful waves, circles, connectedness, warm human photography, and a subtle calm early-2000s/Frutiger Aero influence where tasteful. The site should still feel professional, scholarly, welcoming, trustworthy, and contemporary. Avoid neon-glass UI overload, giant indistinct gradients, fake startup dashboards, and inaccessible motion. Support `prefers-reduced-motion`.

### Brand deliverables
Build a previewable **Brand System / Style Guide** route (can be development-only) displaying colors, contrast pairings, typography, logo treatments, buttons, menu, cards, forms, tables, quote treatment, image crops, spacing scale, and editorial templates. Document exact tokens and replacement process in `docs/BRAND_SYSTEM.md`.

## 3. Design direction — not a generic nonprofit landing page

The RJ Hub is an educational, student-centered, community-oriented restorative justice resource. Design for an audience of students, educators, community members, researchers, and prospective participants. Prioritize real human stories and the organization’s mission over stock imagery or self-congratulatory marketing.

The homepage must feel **LEAN** and mission-led. A strong composition could include:
1. Clear RJ Hub identity and short mission statement, using **verified** language from the real site.
2. One striking approved real photograph of an in-person circle/dialogue scene, with a concise explanation of what restorative justice means.
3. A **five- or six-destination circular visual hub** inspired by the brand's circle-process logo: What Is RJ, Projects, Maps, Offerings, Community Partners, Blog. It must use real links, descriptive visible labels, keyboard operation, strong focus styles, a linear text-list alternative for accessibility, and a practical stacked/mobile treatment. Resources/Get Involved must also be clearly reachable.
4. One or two brief verified and appropriately attributed quotations about restorative justice, if available. Angela Davis was floated as a possible source; **never fabricate wording, attribution or permission**. Omit if uncertain.
5. A restrained latest-posts/recent-news strip with real articles and a visible top-level Blog link.
6. One more supporting image + concise pathways into resources or community work, not an endless homepage.

Prototype **two genuinely different homepage compositions** using the same approved brand system and real assets (e.g. academic/editorial versus circle-and-wave expressive). Both need responsive previews. Select one as the implemented default, with the other as an optional preview, not two conflicting production homepages. Create coherent inner pages, not the same hero and cards copied repeatedly.

Inspiration only, not claims of affiliation or source content: the Ahimsa Collective site, RJOY (Restorative Justice for Oakland Youth), University of San Diego's Center for Restorative Justice, and the 'Elisen Spa' WordPress visual reference in stakeholder notes. Inspect them if internet access permits; do not copy copyrighted layouts or claims. If those references cannot be visited, proceed with the style brief.

## 4. Full site information architecture and FUNCTIONAL page templates

Implement full navigation, search, responsive layout, page templates, working links, breadcrumbs where helpful, consistent footer, editorial details and an actual WordPress editing flow.

A. **Home** — design described above.
B. **About RJ Hub / Mission** — verified mission, identity, campus relationship, current versus historical organizations, photos, contact pathways.
C. **What Is Restorative Justice?** — verified introductory content, restorative practices and principles, readable educational layout, attributable sources.
D. **Blog** — top-level menu tab, indexed articles, searchable archive, categories/tags, dates, author, pagination, featured images, individual post pages, proper headings, related content if useful.
E. **Projects** — editor-managed project archive and detail pages, research descriptions, citations, original media, linked PDFs, preserving authors/attribution.
F. **Resources** — editable recommended reading/viewing, community resources, external links, downloadable documents; filter/categories where simple and accessible.
G. **Maps** — see dedicated map requirement; source-driven, not fabricated.
H. **Offerings** — differentiate **currently approved active RJ Hub offerings** from services previously provided by the now-former RJ Center. If active offerings are unconfirmed, present an honest 'Offerings under review' state or a clearly private prototype; do not fabricate current programs.
I. **Community Partners** — editable profiles/cards ONLY for explicitly verified current partners; distinguish general inspiration/resources from official partnerships.
J. **Get Involved / Student Participation** — verified opportunities, contact/interest pathways and future plans, with an editor-owned, nontechnical update path.
K. **Intake / Requests** — design a usable demo front end and editable information architecture, BUT DO NOT ENABLE collection, storing, sending, logging, or transmitting real personal/sensitive data until the organization explicitly approves current services, privacy notice, access control, retention, consent and secure form platform. Default to disabled or 'coming soon'; no working backend or fake acknowledgment of submitted requests.
L. **Legacy / History** — preserve the former Berkeley RJ Center history, historical programs and archival images with clear historical labeling.
M. **Contact / Accessibility / Privacy / 404** — appropriate factual contact info if verified, otherwise editable placeholder that cannot misrepresent an active service. Footer links required by institutional policy must be configurable.

Create reusable block patterns/templates for standard articles, scholarly projects, resources, partner entries, event/news teasers, quotes, image-and-text sections, photograph galleries and other common updates. Expose menus and homepage featured items through the WordPress dashboard.

## 5. California Indigenous history / massacre map

Create a **respectful, accessible, source-driven map feature** for the stakeholder idea: historical locations of documented massacres of Native Americans in California. Users should be able to select a marker/location, read a brief contextual explanation, and follow a source link to an authorized PDF or documentary resource. If source material is provided or independently verifiable with clear citations, populate only accurate records; otherwise create the working map infrastructure and a transparent editorial empty state, not invented events or coordinates.

Build an editor-manageable `map_entry` content type or equivalent with fields for event title, location/geocoordinates, approximate/exact date with uncertainty, short blurb, source references, PDF link, research/verification status and content warning. Support keyboard alternatives and a non-map searchable list/table so the history remains accessible even without JS or a functional map. Do not use sensational imagery or erase Indigenous nation-specific contexts. Do not conflate historical massacre research with RJ Hub's current organizational activities.

Avoid services requiring an undisclosed paid API key; keep map implementation compatible with Pantheon and document licenses/attributions for maps and datasets.

## 6. Source audit, content migration and editorial truthfulness

Inspect the public RJ Hub site thoroughly if network access permits, respecting robots/access limits. In parallel use the content inventory spreadsheet **only if authorized and accessible**. In the earlier inventory, 149 entries were recorded: 108 marked published, 41 unpublished; categories included content pages, landing pages and news items. **Treat these counts as planning benchmarks to recheck, not as proof all entries are currently live or have been successfully imported**.

Generate a machine-readable manifest with: old URL/ID, title, content type, published/draft status, byline, publication and revision dates, canonical slug, attachment/image links, target WP type, target URL, imported status, rights status, issues, review owner, and redirect needed. Protect unpublished entries; never accidentally publish drafts. Flag duplicate 'Get Involved' pages, duplicate About pages, dated staff/student offerings, legacy service descriptions, and recent 2026 articles for manual reconciliation.

Preserve full approved articles rather than shortening or rewriting them. Preserve dates, authors, links, credits and citations. Do not claim that a content import succeeded just because the crawler found a URL. Perform count and representative text/date/author comparisons against the source. Do not infer a blog post's author from who uploaded it if the public byline differs. If source is inaccessible, use explicit placeholders marked for review, produce importer scaffolding, and state precisely which data is missing.

Add search-friendly archives, canonical URLs and sensible redirect mappings. Deliver `content/CONTENT_INVENTORY.csv`, `content/REDIRECTS.csv`, `docs/CONTENT_MIGRATION_REPORT.md` identifying imported, excluded, unpublished and blocked items.

## 7. PHOTOGRAPHY — curate the provided 20 images, don't just dump them

The 20 original files are in `assets/rjhub-photos/` and are inputs, not yet cleared for public publication. Inspect EACH image visually (not just filenames). Preserve original master files. Create optimized WordPress media variants and a media manifest. Use the strongest images deliberately and sparingly. **Do not display all 20 merely because they're supplied.**

Editorial recommendations subject to verified permissions and accurate context:
- **Home:** 1 high-quality in-person circle/discussion image as hero or near-hero, with at most 1–2 supporting photos. Candidates: `SAOCircle8.JPG`, `Copy of 2015RJforSVSHConferenceCircleLea.jpg`, `2019FallDeannaClass.jpg`.
- **About / Legacy:** archival community circle/gathering, `DecalTeamPhoto1.JPG` if approved, clear historical captions and dates where verified.
- **What Is RJ:** listening/facilitation and circle-practice photos, possibly `COSATraining1.jpg` or `2019FallDeannaClass.jpg`.
- **Offerings / Workshops:** participatory activity images such as `2019GSITRainbowID.jpg`, accurately labeled as examples/archives unless active programs are verified.
- **Community:** approved gathering portraits or group imagery; no automatic inference of partner status.
- **Blog/Projects:** only use image when editorial connection to the article/project is supported; otherwise use an original branded thumbnail pattern.
- **Virtual programming:** Zoom screenshots may show private names/faces/chats. Keep those images out of public-facing output unless releases and privacy/redaction reviews are documented. Screenshots including `2021JamboardRJC.png` need inspection and approval.

For the PRIVATE local design preview, provisional photo placement may be used ONLY with unmistakable review flags; never label a historical photo as a present-day RJ Hub event. Do not identify photographed individuals or invent venue/event details from appearance or filenames. Avoid minors' identifiable images without institutionally verified permissions. Provide deliberate responsive crop, focal positioning, caption/credit fields, alt-text editing, original-versus-derivative tracking, lazy loading on noncritical images (not the above-the-fold hero), and graceful fallback layouts.

**WordPress implementation:** Editors must be able to replace every prominent image through Media Library and block editing; do not embed permanent local image paths directly into PHP. Create `docs/PHOTO_USAGE_REPORT.md` showing included/excluded file, location, crop, rationale, alt text, caption, rights/approval status, and follow-up required.

## 8. Future sustainability after original developer graduates

This is a RELEASE REQUIREMENT. A future RJ Hub member with no CS/STEM background must independently perform routine work with a normal browser:
- publish and schedule a news article with author, date, category and image;
- edit a homepage statement, hero photo, quote, and featured links;
- modify top-level navigation;
- add/update/delete resources, projects, partners, offerings (with approval controls), map entries and image captions;
- add PDFs, restore a draft/revision, and control editor accounts according to roles;
- find documentation and know who to contact for emergencies.

Use core Gutenberg blocks and FSE, intuitive labels, reusable patterns, correct custom post type editor exposure, and role-based access. Hide or document complex settings so students cannot accidentally break layout. Do not require PHP, Git, SSH, terminal commands or Codex for these activities. **Test these operations with an Editor-level account, not only an Administrator**. Write `docs/EDITOR_HANDBOOK.md` in plain nontechnical language with exact WordPress click paths and annotated screenshot recommendations. Include a 30-minute training walkthrough and a successor handoff checklist.

Be honest that self-managed WordPress on Pantheon still needs technical stewardship: WordPress/plugin/theme updates, staging/production deploys, backup restores, security incident response, accessibility corrections, domain renewal/approvals, and occasional custom code repairs. Write `docs/MAINTENANCE_AND_OWNERSHIP.md` with owner versus editor versus host responsibilities, weekly/monthly/quarterly cadence, access transfer, patch/testing process, and incident contacts left blank for Julie to assign. Prefer simplest reliable architecture and minimal plugins.

## 9. Accessibility, privacy, security and institutional review

Target WCAG 2.1 AA, including semantic headings, keyboard links, focus indicators, contrast, accessible forms, skip links, reduced motion, responsive text, image alternatives, and non-map alternatives. Run automated tooling where feasible PLUS manual keyboard and mobile checks; never claim accessibility compliance based only on automated tests. Include screen-reader checks if supported, otherwise list as a clearly unverified launch gate.

Secure WordPress using supported APIs, escaping/sanitization, authorization checks, nonces, safely registered REST endpoints, protected account roles, no passwords in the repository, and limited plugins. Confirm the Pantheon runtime and deployment requirements against current official documentation before finalizing the migration guide; do not deploy there in this task.

List university and organization-level launch gates: approval of `rjhub.berkeley.edu` hosting/DNS, organizational sponsorship, security contact, privacy review, accessibility review, photo consent/rights, current offerings and intake approval, partner verification, and live content/editor signoff. Do not assume anything is approved just because it appears in older material.

## 10. Required real implementation and acceptance tests

**Build rather than merely plan.** Implement all templates, CPTs, archives, navigation, visual hub, style guide, maps infrastructure, demo intake state, content import tooling, media pipeline, and local runtime. No broken links, missing imports silently treated as success, lorem ipsum masquerading as actual copy, or hardcoded button controls that do nothing.

Run available tests (or provide explicit reproducible commands and explain why a test could not run):
1. Fresh install succeeds from provided archive and instructions; WordPress + DB start cleanly.
2. Home, About, What Is RJ, Blog, Project, Map, Resource, Offerings, Community Partners, Get Involved, Legacy and 404 paths load.
3. Blog and resource archive filtering/search/pagination work with accessible navigation.
4. Circular homepage navigation works by click, keyboard and mobile alternative.
5. Demo intake explicitly cannot submit, store, send, or leak data.
6. Only documented, verified map entries appear as factual published history; list alternative works.
7. Imported content counts and critical bylines/dates/URLs are reconciled; flags for all exceptions.
8. Branding colors are exact and used consistently; font licensing not violated; logos not fabricated.
9. All photos have a rights status; private Zoom screenshots excluded from public release by default.
10. An **Editor** can publish a post, modify home, add resource, update navigation, replace hero image and add a map entry through UI without code.
11. Run responsive screenshot checks at representative mobile/tablet/desktop widths; no horizontal scrolling or obstructed content.
12. Basic keyboard/contrast/automated accessibility checks pass or each failure is itemized with severity and fix.
13. A complete export/backup exists, including code, upload files, DB or reproducible importer and seeded verified content.

## 11. Deliverables — persist files outside temporary Codex execution host

Create a clearly named output folder and portable export files so the user can actually **download** them from Codex; do not rely on a private `localhost:8080` link that works only inside a temporary cloud container. If a built-in preview is supported, supply that too, but the ZIP and local setup must work independently.

Required deliverables:
- `RJ_Hub_WordPress_Local_Prototype.zip` with all code, configuration templates, scripts and permitted assets.
- `README.md` with an exact 'start from zero on Windows WSL2 Ubuntu + Docker Desktop' procedure, local URL, safe admin-account creation, troubleshooting, and stop/start commands.
- Reproducible content/import files and migration summary; explicit note when source content is incomplete.
- `docs/BRAND_SYSTEM.md` and visual style-guide page.
- `docs/PHOTO_USAGE_REPORT.md` and original/optimized image inventory.
- `docs/EDITOR_HANDBOOK.md` and `docs/MAINTENANCE_AND_OWNERSHIP.md`.
- `docs/PANTHEON_MIGRATION.md` distinguishing free development/staging, account setup owned by Julie or authorized Berkeley staff, security/domain approval, WordPress code/database/uploads transfer, HTTPS, redirects, DNS cutover, rollback, and old-hosting termination.
- `docs/QA_REPORT.md`, including what actually ran, what passed, what failed, and what remains blocked.
- A brief demo checklist for Julie and Jordan, with both homepage visual versions and the recommended final version.

## 12. Execution order and completion standard

1. Inventory supplied files, inspect the brand kit visually, inspect photos, and audit public website/inventory when possible.
2. Choose and document a maintainable WordPress block-theme architecture.
3. Build local Docker environment, theme, content plugin, editor structures, design system, and all page templates.
4. Implement mission-led homepage and alternate, with curated photo placements and accessible circular navigation.
5. Import/seed only verifiable content and implement complete migration manifest, redirects, photos, blog, projects, resource and map flows.
6. Test editor workflows and accessibility; repair failures where feasible.
7. Package runnable site, data/importers, documentation, and ZIP; verify the ZIP is actually created and contains everything required.

Do not stop after writing a plan. You may outline your steps internally, but **carry through the implementation and tests within the available session**. If an external source is unreachable, continue building all source-independent functionality and mark source-dependent publishing gates as blocked; do not invent content or silently drop the feature. If environmental limits prevent running Docker, still deliver real installable code, the Compose setup, importer and files, and a precise untested status. No live deployment or payment is authorized.
