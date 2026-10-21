# Project: jorurikaj.com (জরুরীকাজ.কম) — front-end redesign

Read this file and PROGRESS.md at the start of every session.

## Site
Bangladeshi local-service-provider directory/marketplace. Tradespeople (50+ professions: electrician, plumber, AC repair, driver, event management...) register; people needing help find and call them.
- Two registration paths: (1) self-service form -> admin approval before listing; (2) phone registration by admin (helpline number shown on site — keep it).
- Search/filter by service type and location: বিভাগ -> জেলা -> থানা/উপজেলা cascading dropdowns.
- Currently Dhaka-only, planned nationwide (all Bangladesh থানা/উপজেলা data exists).
- Paid ad slots must be preserved.

## Problems in the old design
Default Bootstrap look; repeated "ফ্রি রেজিস্ট্রেশন" hero banners; plain alphabetical services list; plain DataTables-style listing; empty "কর্ম এলাকা" data; no photos or direct call button; weak hierarchy and mobile UX.

## Goal
International-standard, mobile-first, fast (low-bandwidth) redesign. Bangla-first typography (Hind Siliguri / Noto Sans Bengali), good line-height for Bangla.

## Sources
- Old site (PHP): https://jorurikaj.com/index/ — extensionless numeric URLs: /2001 about, /3000 services, /3001?service_id=N listing, /5001 contact, /6002 login, /6003 profile dashboard, /6004 worker registration, /6005 agent registration, /7000 today's work. Keep these URLs working (redirects) for SEO.
- Figma (OWNER'S DESIGN — follow it; owner is very particular): https://www.figma.com/design/TzuvEKEzb297MFROjbO9UF/RTM-AKTU-Sylhet?node-id=2584-12 (fileKey TzuvEKzb297MFROjbO9UF is in the URL; frame "JoruriKaj.Com-Home", 1920 wide). Only the HOME page is designed so far; other pages must extend the same style (confirm with owner).
- GitHub repo: https://github.com/rejaulalam-byte/jkdotcom (not yet pushed/published).
- Hosting: cPanel. Never ask for/accept passwords in chat; prefer cPanel Git Version Control (.cpanel.yml) or SFTP key.
- Contact data in design: hotline +880 1761 441 911, info@jorurikaj.com, H-100 New Airport Road, Kakoli Bonani, Dhaka 1213. Footer credit: "Developed by Do Something".

## Figma page map (fileKey TzuvEKEzb297MFROjbO9UF; use node-id in get_design_context/get_screenshot)
All pages share: top bar (phone, email, tagline, social icons), white nav (logo, হোম / আমাদের সম্পর্কে / সেবা সমূহ / আজকের প্রকাশিত / রেজিস্ট্রেশন / যোগাযোগ, search icon), teal page-title banner with breadcrumb, teal footer (logo, address, hotline, newsletter email box, link columns, social, copyright "Developed by Do Something").
| Page | node-id | Notes |
|---|---|---|
| Home | 2584-12 | hero + today's job list box, 3 photos, agent-wanted bar, 8 service cards, about+BD map, 4 steps, FAQ + feedback form, Google map, footer |
| About (আমাদের সম্পর্কে) | 2621-103 | long text + BD map |
| Services (সেবা সমূহ) | 2642-54 | notice box, search (service/area/name/mobile), alphabetical Bangla list in 3 columns |
| Today's published – post a job (কাজ সম্পর্কে জানান) | 2674-7 | form: job type (or add new), name, mobile, email, address, upazila/thana, district, division, ad start/end date, description; helpline 01919293483 for help; * = required |
| Today's published list (কাজ সম্পর্কে জানুন) | 2675-148 | accordion list: title, date/time, details, contact (phone masked, click to reveal), email masked |
| Registration rules overview | 2631-395 | who can register: professional / job-seeker / agent; 2 buttons |
| Registration guideline – professional | 2683-148 | rules, eligibility, 2 cards |
| Registration guideline – agent | 2683-305 | rules, duties, 2 cards |
| Professional registration form | 2658-504 | name, profession, experience, mobile, address, upazila, district, division, password x2 (min 12 chars), bio, photo (JPG/PNG/WebP, max 150KB) |
| Professional login | 2660-816 | login id = registered mobile + password |
| Professional logged-in welcome | 2663-981 | header shows avatar; dashboard page not designed yet |
| Agent registration form | 2682-2 | name, NID number, mobile, address, upazila, district, division, password x2, photo |
| Agent login | 2686-619 | same as professional login |
| Agent logged-in welcome | 2686-740 | |
| Registration rules (separate frame) | 2684-437 | not yet reviewed |
| Contact (যোগাযোগ) | pending | owner is finishing; will send link |
Not designed yet (discuss with owner): profile dashboard/edit, search results, stats ("এক নজরে পরিসংখ্যান"), FAQ page, privacy/terms, admin panel, 404, mobile layouts.

## Branding
- Follow the FIGMA palette (dark teal hero/footer + orange accent + light teal-grey cards), not the logo's maroon/gold. Logo files (assets/brand/logo.png, logo.jpg) are the "JK" maroon/gold monogram, used as-is. Extract exact tokens from Figma via get_design_context.
- Light theme. Bangla-first fonts.

## Architecture & security (decided 2026-10-20)
- Plain PHP 8, no heavy framework: front controller (public/index.php), simple router, templates/partials, PDO + prepared statements only, CSRF tokens, password_hash, output escaping, server-side validation, rate-limiting on login/forms, secure session cookies, security headers, uploads validated and stored outside web root. Config/secrets in .env outside web root (never in git). Only public/ is web-served.
- Clean folder structure + README + comments so any PHP developer can continue.
- Old backend source/DB access is NOT yet available — need it (or a backup) before rebuilding data/admin flows.

## Page requirements
- Home: one hero, one headline, prominent search (service + বিভাগ/জেলা/থানা), CTAs "সেবা খুঁজুন" and "ফ্রি রেজিস্ট্রেশন"; how-it-works (3 steps), stats, testimonials placeholder, footer with contact.
- Services: category cards with icons, grouped; keep alphabetical search/filter.
- Listing: provider cards (avatar placeholder, name, profession, area badge, verified badge, tel: "কল করুন" button); sticky sidebar filters (desktop) / bottom-sheet (mobile).
- Details: clean profile, prominent call button, related providers.
- Registration: grouped/multi-step form, Bangla validation messages, helpline visible.
- Ads: header banner, sidebar, between listings; non-intrusive.
- Missing data (blank কর্ম এলাকা) must render gracefully.
- Accessibility: contrast, focus states, large tap targets. Performance: optimized images, minimal JS, lazy-loading. Test mobile/tablet/desktop.

## Constraints
- Do NOT break search/filter logic, admin approval, registration, ad management, location data.
- Keep all Bangla text intact; improve presentation only unless told.

## Process / working rules
1. Inspect existing code/structure first and summarize.
2. Propose design system (color tokens, type scale, spacing, components); wait for approval.
3. Redesign page by page: Home -> Services -> Listing -> Details -> Registration -> Footer/global.
4. After each page: show what changed and how to test.
5. Commit small steps to git. On any push request, state clearly whether the push succeeded/is live, or give exact commands for the user to run.
6. Server deploy: staging first, backup old site, user approves final switch. Never handle passwords/keys in chat. Never delete the old site without explicit user OK.
7. Suggest the cheapest suitable model at the start of each task (Haiku/Sonnet for routine, bigger only for hard problems).

## Owner decisions (2026-10-20)
- EVERYTHING editable by admin: all text, images, logo, colors, animations. Editors (limited role): edit sections/text only; exact permissions TBD.
- Owner builds, hands over, trains the client; Claude assists long-term.
- Home hero right box "আজকের কাজের তালিকা": real data from "কাজ সম্পর্কে জানুন" entries (last N days, N TBD). Animation: rows scroll up from bottom, pause ~4-7s, then move up quickly out, next batch scrolls in, loop.
- Hero: starts as in Figma; later admin can add multiple images + animations.
- Strip below hero (3 photos): becomes AD banner slider for paid advertisers. Fixed height, width flexible; wide images auto-expand width. Scrolling.
- Feedback form: email to admin AND stored in admin panel (admin can read/delete/keep).
- Language: Bangla only for now; English maybe later (build i18n-ready).
- Mobile: adapt the desktop Figma design to all phone sizes (Claude decides).
- Passwords: owner will rotate cPanel/FTP/email passwords. New site needs very strict security (see Architecture & security).
- Geo data: use public dataset (nuhil/bangladesh-geocode on GitHub: divisions, districts, upazilas, unions with bn names; verified reachable) merged with old DB tables. Villages not in public sets — admin-editable / free-text.
- Migrate only worker (কর্মী) data from old DB; do NOT migrate old plaintext passwords.

## Owner decisions (2026-10-20, later)
- "আজকের কাজ" visibility window: 3 days for now (setting `settings.jobs_visible_days`, owner will confirm later).
- Ad strip: images only, with an OPTIONAL link per ad (advertiser's own site) — implemented (`advertisements.link_url`).
- Typos to confirm with owner (kept as in Figma): "সমুহ" (সমূহ), "ম্যানেজম্যান্ট" (ম্যানেজমেন্ট), "যোগাযেগ" (যোগাযোগ).
- Footer link columns in Figma were placeholders (duplicates); I used real pages. Footer "এক নজরে আমাদের পরিসংখ্যান" page not designed — omitted.
- Footer e-mail box ("আপনার ইমেইল - আমাদের সাথে সেতুবন্ধন") = subscribers list (POST /subscribe) — confirm purpose with owner.
- Dev tooling: portable PHP goes in `tools/php` (git-ignored). Fonts self-hosted in public/assets/fonts (Poppins 400/500/600 + Hind Siliguri 400/500/600).

## Owner decisions (2026-10-20, Home tweaks round 1) — the admin panel must include these
- Teal representative bar = endless right->left marquee. Its text (`home.rep.text`) is shown with `colored()` (app/helpers.php): `[[color:NAME|words]]` colours words, allowed NAMEs only (`TEXT_COLORS`: gold, amber, white, red -> CSS `.tc-*`), `{words}` = bold; text is escaped first. Admin editor: a colour picker limited to that list that writes this markup.
- Services on Home = ALL active services in two rows scrolling right->left independently (app.js `[data-service-row]`). Each card links to `/services/<slug>` (coming-soon until the page is built); button -> `/services`. Data: `database/seeds/services.php` (94 services of the old site, names in the owner's Figma style = no "... করার লোক / এর লোক" endings; owner: each provider may rename theirs) + migration `002_services.sql` (adds `slug`, `legacy_sl`, seeds rows with `icon_path`). Never change a live slug.
- Service icons: Tabler Icons 3.24.0 (MIT, licence file beside them), white SVGs in `public/assets/img/service-icons/<slug>.svg`, mapping `database/seeds/service-icons.php` + `docs/service-icons.md` (27 marked "near" = closest match). `services.icon_path` holds `/assets/img/service-icons/x.svg` (default) or an uploaded file name (admin upload, `/uploads/services/`).
- Footer "হোম" and "আমাদের সম্পর্কে" link columns must become admin-editable (add/remove/reorder, label + URL): planned table `footer_links (id, column_key, label, url, sort_order, is_active)`; until then they are hard-coded in app/Views/partials/footer.php.
- FAQ: all closed on load, one open at a time (`<details name="faq">` + app.js fallback), open box teal/white.
- Feedback form: all five fields required (server + red *).
- Step-card numbers use the device's Bangla font (Hind Siliguri's "১" looks like a blob).
- Social icons (top bar + footer): ALL six are shown for now (empty ones link to "#"). The "hide icons without a link" behaviour is built but OFF: setting `settings.social_hide_empty` = '0' (app/Content/defaults.php). Switch it to '1' ONLY when the owner says so (then, in the admin, only icons with a link show). Admin panel must offer: per-icon address + this switch. Footer heading "সামাজিক যোগাযোগ মাধ্যম" always stays.

## Owner decisions (2026-10-21) — "open pages" + rich-text editor (admin panel must include)
- About (and every similar text page: guidelines, rules, privacy, terms, ...) = title banner + ONE open writing area (class `.rich`). No fixed blocks/gaps: headings, paragraphs and images flow like a document; images can float left/right (text wraps) or sit centred. Stored as one HTML value (About: key `page.about.html`, default in `app/Content/pages/about.html`). Figma spacing: heading 156px under banner, 70px to first text, headings ~45px apart, paragraphs about half a line apart.
- Admin editor for these pages needs a FULL formatting toolbar: bold/italic/underline, bullets, numbering, indent, line/paragraph spacing, text wrap around images, image layout options + alignment, drag-and-drop images, resize images by dragging, tables (full table formatting), PNG with transparent edges wrapping text closely, etc. Whoever edits formats freely. (Skill `rtm-aktu-tinymce-toolbar` describes the owner's usual TinyMCE full toolset.)
- Security: the HTML saved from the editor MUST be sanitised server-side (allow-list of tags/attributes/classes, no scripts/events) before it is stored or shown.
- About page: the small badge above the big heading ("আমাদের পথ চলা") was removed by the owner.
- CSP note (app/Core/Security.php: `style-src 'self'`): inline `style="..."` attributes are BLOCKED. Page content must use CSS classes. When the admin editor is built, its image resize/alignment/spacing output must be turned into allow-listed classes (or a sanitiser must convert it), or the CSP must be consciously relaxed (e.g. nonce) — decide then.

## Owner decisions (2026-10-21) — Services page (/services, Figma 2642-54)
- All services listed under Bangla letter headings (অ আ ই ঈ ... ক খ গ ... হ), in alphabet order, as a table-like list: darker heading bar per letter, each service a link to `/services/<slug>`. THREE columns (4 would break long names onto 2 lines). A letter group is NEVER split across columns; the groups are cut in order into 3 columns of about equal height (`app/Models/ServiceDirectory.php`, so vowels-short column gets the first consonants). Order is automatic: admin only adds/renames services; headings come from the first letter of the name.
- Admin panel must offer: edit the heading list (`ServiceDirectory::LETTERS`, planned setting `services.letters`), add/rename/disable services. Texts of the page are in `defaults.php` (`services.*`).
- Search box "সেবা, এলাকা, নাম অথবা মোবাইল দিয়ে খুজুন". "এলাকা" must match the provider's own address/area AND থানা/উপজেলা, জেলা, বিভাগ. NOW: only service names are searched (GET /services?q=) and a note says the rest comes with provider data. LATER (needs workers + geo tables): search providers by service/area/name/mobile.
- Owner gave full freedom (2026-10-21): he often makes Bangla typing mistakes in chat and in Figma; when a spelling mistake is clear, FIX it silently (mention it in one line in the report) and do not ask. Services search spelling is now "খুঁজুন".
- Site name in page text (2026-10-21): "Jorurikaj.com" is shown BOLD + coloured automatically in all page bodies (`View::boldBrand`, class `.brandname`; hero letters via app.js). Colour = teal on light backgrounds, gold (#f0d772, same as Figma's top bar) on dark teal backgrounds. Not applied in header/footer/top bar, e-mail or web addresses. New dark sections need adding to the gold rule in app.css.
- Owner rule (2026-10-21): after finished work, PUSH to origin main yourself and report that it succeeded; do not ask first (never force-push).

## Owner decisions (2026-10-21) — Post-a-job page (/today/post, Figma 2674-7)
- Visitor posts the work they need; saved as `job_posts` row with status 'pending' (admin approves, then it shows on the list page "কাজ সম্পর্কে জানুন" = /today, NOT built yet). E-mail to admin too. After submit: thank-you card (texts `today.post.done.*`). Helpline in the note box: 01919293483.
- Admin panel: the job posts need the full formatting editor too (see rich-editor decision above) so the admin can format the details before publishing. Public form details = plain text.
- Areas: `database/seeds/geo.php` = nuhil/bangladesh-geocode (MIT; 8 divisions, 64 districts, 494 upazilas) + 17 Dhaka city thanas from the old DB (old table was messy elsewhere: wrong parents, test rows). Migration 003 creates divisions/districts/upazilas + new job_posts columns. Figma order of the three selects is upazila/district/division; app.js lets any be picked first and fills the others. Upazila names that repeat ("সদর") show the district in brackets.
- Kind of work = text field with suggestions from the services list (own wording allowed: "না পেলে যোগ করুন").
