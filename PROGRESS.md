# Progress (read CLAUDE.md first)

## Home page tweaks, owner round 1 (2026-10-20) — DONE, all 9 items pushed
Decisions recorded in CLAUDE.md ("Home tweaks round 1"). Service names now follow the owner's
Figma style (no "... করার লোক"). Waiting for the owner's review of the result; next = "Next steps" below.

Owner reviewed Home. Do these in order, test desktop 1920 + mobile 390, commit + push after each group.
Reference images: docs/references/service-card-reference.png (wanted service-card look), docs/references/steps-number-bug.png (bug).

1. **Today's-jobs list (hero right box):** a batch of 8 rows currently pops in too fast. Make each new batch rise from the bottom SLOWLY (about 4 s, smooth, so rows can be read while rising). Keep the rest time (~5.5 s). The next batch also rises slowly the same way. (js/app.js ticker; currently 900 ms enter, 450 ms exit.)
2. **Ad strip (under hero):** reduce the white space above and below the pictures to ONE THIRD (currently 47px top/46px bottom -> ~16px). Halve the gap between pictures (54px -> 27px). The section's TOP must stay exactly where it starts now (overlapping the hero by 71px, no vertical jumping); only its bottom edge moves up, so the teal bar below moves up too. Picture height stays 278px.
3. **Teal text bar under the ads ("বাংলাদেশ জুড়ে আমাদের নিজস্ব প্রতিনিধি…") becomes a MARQUEE:** text moves continuously from the far right of the page to the left (endless loop; pause on hover; respect reduced-motion). FUTURE (admin panel): admin must be able to colour some words of this text — design the text storage with simple markup now (e.g. [[color:gold|word]] or {braces}) and render it safely (escaped, only allowed colours).
4. **"সহজ সেবা, সহজ জীবন" services section — bigger change (see reference image):**
   - card corner radius 40px -> 20px; card look like the reference (light card, thin teal border, name in teal bold under the icon);
   - the white rounded-square icon box becomes a CIRCLE (reference: ~80px, solid teal-blue circle) with a subject-related white icon inside;
   - TWO rows of cards, BOTH rows scroll continuously right -> left, but independently (different speed/start offset, not in sync); pause on hover/focus; reduced-motion = normal scrollable rows;
   - show ALL services (not just 8) so a visitor who waits sees every service slowly pass by;
   - every card is a link to that service's detail/list page (e.g. /services/<slug>; page to be built later — until then a coming-soon page); the button "সেবাসমূহ দেখুন" goes to /services (all services page).
5. **Service icons (owner asked Claude to find them):** owner cannot find suitable icons. Find/download a matching icon for EVERY service name (the 8 on the home page + all 93 in the old DB `services` table, backup in E:\0000 Claude-All JK- BACKUP Old Site - Rokib Bhai\jorurika_jk.sql.gz; extract into the scratchpad, never commit it). Use a permissively licensed set (e.g. Tabler Icons MIT / Lucide ISC / Phosphor MIT / Material Symbols Apache-2.0), self-hosted SVGs in public/assets/img/service-icons/, a mapping table service -> icon file, and tell the owner which services had no good match. ASK the owner's permission before downloading (rule: downloads need explicit yes) and show the license. Store icon in services.icon_path (admin can change later).
6. **Bug – "আপনার জন্য, আপনার প্রয়োজনেই" step cards:** the number inside the teal circle is hidden/clipped (see steps-number-bug.png). Debug (.step-card__no: font, line-height, glyph clipping with Bangla digit) and fix; the number must be big and clear.
7. **FAQ ("আপনার জিজ্ঞাসা"):** on page load ALL answers are hidden — only the question + "+" is visible. Click "+" (or the question) to open: the answer shows and the WHOLE box turns colour (teal background, white text, "−" sign). Opening another one closes the previous (exclusive accordion). After refresh everything is closed again. Keyboard accessible.
8. **Feedback form:** the owner forgot to make the fields required: make ALL five fields (name, email, phone, subject, message) required, show a red/gold * on each label/placeholder, and update server-side validation (FeedbackController) + the Bangla error messages to match.
9. **Footer layout:** on the right side, the link columns ("হোম" column, "আমাদের সম্পর্কে" column) and the "সামাজিক যোগাযোগ মাধ্যম" box must start at the SAME top line as the address block on the left (the "H-100, New Airport Road…" line), i.e. move up. FUTURE (admin panel): the items of the "হোম" and "আমাদের সম্পর্কে" footer columns must be admin-editable (add/remove items, each with a link) — prepare data structure (e.g. footer_links table: column, label, url, sort_order) when the admin is built.

Also record in CLAUDE.md after finishing: these decisions (marquee text colouring, footer link editing, services scrolling rows) so the admin panel plan includes them.

## 2026-10-21 — Home final + About page DONE (owner: "আপাতত শেষ")
- Home approved by owner. Safe copy of it with the Figma colours: git tag `home-final-figma-colors` + branch `backup/home-figma-colors` (pushed). Colour experiments (not merged, owner kept Figma colours): branches `experiment/logo-colors` (maroon), `experiment/colors-navy-gold`.
- About page `/about` built from Figma 2621-103: banner + ONE open writing area (`.rich`, body in `app/Content/pages/about.html`, key `page.about.html`); small badge removed; map 350x446, 10px lower (`img-down-10`). Home button now "বিস্তারিত জানতে ক্লিক করুন" -> /about.
- Owner decisions for open pages + full rich-text admin editor + CSP/inline-style note: see CLAUDE.md (2026-10-21).
- Local commits on main are NOT pushed yet: push only when the owner says so.
- Next page: Services (2642-54), after a short brief from the owner.

## State at end of 2026-10-20 (after Home tweaks round 1 + 2 and later polish) — all pushed to main
Done after "Home tweaks round 1" (commits 925da04 … 7decece):
- Menu logo: new one-picture logo `public/assets/img/site/logo-header.png` (original kept in `assets/brand/logo-header-original.png`); header sits a little lower.
- Today's-jobs list (hero right box): the next page rises the moment the last row of the previous page has left (one endless belt, no gaps); a short last page leaves sooner.
- Ad strip: moves one banner at a time with 5.5 s rests. Teal marquee bar below it is lower.
- Footer rebuilt from the owner's new Figma: logo with the name beside it, e-mail box centred on the logo, 4 link columns on one line; footer logo links to the page top.
- Social icons: all six shown again (top bar + footer). Switch `settings.social_hide_empty` is OFF ('0') — turn it ON only when the owner says so.
- Hero: every letter flies in from a random direction (`[data-fly]` + js/app.js).
- Floating back-to-top button: travels like a scroll thumb from the middle of the screen to the bottom, glides up slowly.
- Menu: "রেজিস্ট্রেশন" has 4 sub-menu items (`/registration/professional`, `/registration/agent`, `/login`, `/dashboard`) — they still show the coming-soon page.

NOT done / NOT tested:
- Real MySQL: run `database/migrations/002_services.sql` after 001 (only demo mode has been used so far).
- Real phone test (only the desktop browser / mobile-size emulation was checked).
- The animations (hero fly-in, list belt, ad strip, back-to-top) were only measured with numbers, because the preview pane draws no frames — the owner must watch them in a real browser.
- Registration, login and dashboard pages are not built (coming-soon).

## State at end of session 1 (2026-10-20)
- GitHub repo https://github.com/rejaulalam-byte/jkdotcom , branch main, all work pushed. Git identity set locally (Kazi Rejaul Alam / rejaulalam@gmail.com).
- Plain-PHP skeleton + HOME page DONE and visually checked (desktop 1920 + mobile 390). See README.md for folder map and security rules.
- Local run: double-click `start-server.bat` (PHP 8.3 portable in tools/php, git-ignored) -> http://127.0.0.1:8000 . Demo mode (no DB): sample jobs/ads/services.
- Not done / not tested: real MySQL, e-mail sending (needs cPanel), admin panel, uploads, all other pages (routes show "coming soon"), server deploy.
- Old-site backup is at E:\0000 Claude-All JK- BACKUP Old Site - Rokib Bhai (never commit). Old site had a backdoor + plaintext passwords -> never copy its code; migrate only worker (কর্মী) data (~1364 rows), geo from nuhil/bangladesh-geocode.
- Owner is rotating cPanel/FTP/email passwords himself. cPanel URL host: cpanel.jorurikaj.com (Claude in Chrome could not load it; guide the owner step by step instead).

## Next steps (in order)
1. Owner reviews Home; apply requested tweaks.
2. Build pages from Figma, one at a time, asking the owner for a short brief first: About (2621-103) -> Services (2642-54) -> Registration overview/guidelines (2631-395, 2683-148, 2683-305) -> Login x2 -> Registration forms x2 (needs DB, geo dropdowns, photo upload <=150KB, passwords >=12 chars hashed) -> Today: post job (2674-7) + list (2675-148) -> Contact (owner still finishing) -> welcome/dashboard pages.
3. Admin panel (roles: admin full; editor = text sections only), editable content via content_blocks, ads manager (image + optional link), feedback inbox, job approval.
4. DB: migration 002+ for geo (division/district/upazila/union), workers, agents, users/admin. Data migration script from old SQL (workers only).
5. cPanel: test subdomain (document root = /public), MySQL DB, Git Version Control deploy (.cpanel.yml), then switch over only after owner approval + fresh backup.
6. Old URL redirects (/3000 services, /6004 worker registration, etc.).

## Open questions for owner
- Typos fixed already: সমূহ, ম্যানেজমেন্ট, যোগাযোগ, গ্রহণযোগ্য.
- Footer newsletter box purpose? "আজকের কাজ" window = 3 days (to confirm). Editor-role permissions TBD. English language later?
