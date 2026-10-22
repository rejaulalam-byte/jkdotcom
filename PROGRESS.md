## HAND-OVER FOR A NEW CHAT (end of 2026-10-22, night) — read this first
- Latest commit on main: see `git log` (last: `ac2edff`). Staging https://new.jorurikaj.com is on the same commit. Migrations 001-009 are run on staging (009 = `users.service_custom`).
- LIVE on staging: Home, About, Services, Post-a-job, Today list, 3 registration-rule pages, login x2 (e-mail or mobile), registration forms x2 (gender + e-mail required, searchable profession and area fields, own profession allowed -> `service_custom`), PROFILE page `/profile` (login lands here), change-password `/account/password`, forgot-password pop-up (e-mail 6-digit code), avatar menu (পাসওয়ার্ড পরিবর্তন / প্রোফাইল / লগআউট), login lock (5 wrong = soft, 10 = hard), success cards stay 20 s then go to the home page. All rules + owner decisions: `CLAUDE.md` (bottom sections 2026-10-22).
- OWNER WORKING MODE: he fixes ONE page in Figma and sends its link; do exactly that page, push to main, update staging (he logs in to cPanel himself in the Browser pane; never type/ask passwords; the `cpsess…` token in the URL changes at every login; if the pane is hidden use `show_pane preview`; cPanel flow = Git Version Control > Manage > Pull or Deploy > "Update from Remote", then "Deploy HEAD Commit"; phpMyAdmin SQL: set the CodeMirror value then Ctrl+Enter, or POST to `index.php?route=/sql&ajax_request=true` with the page token). He wants Bangla answers and wants to be TOLD FIRST before code is changed when he asks a question ("আগে আমাকে বলো").
- WAITING FOR THE OWNER (he is designing in Figma now): (1) the total WORKERS LIST page (`/services/<slug>`, list per service) and each worker's personal page; (2) the CONTACT page (Figma link); (3) whether an AGENTS list page is wanted; (4) whether the visible field labels (done on /profile) should also come to the registration / post-a-job forms (they keep Figma placeholders now).
- OPEN TECH TODO: admin panel (approve users: today by hand in phpMyAdmin `users.status='approved'` — edit the STATUS cell only, an owner accident put "1234" in `password_hash` once; custom professions `service_custom` -> add to `services`; texts/pop-ups per page; ads; feedback; leads; release locks (needs lock data findable by id/phone: today `storage/ratelimit/*.json` is hashed, empty the folder in File Manager to release everybody); e-mail/gender for old workers), old-data import (~1364 workers, one common initial password, `must_change_password` -> /account/password; also make them ADD an e-mail at first login), old URL redirects, change-photo from the avatar click, delete test rows in staging DB (`users` ids 1-5 and any test ones, job_posts, feedback, subscribers, contact_*), mailbox no-reply@ (optional), rotate cPanel/DB passwords, real-phone checks (photo shrink, Banglish keyboard, forgot-password mail really arriving — NOT verified on the real server yet), the welcome page `/welcome` is no longer linked (decide: delete or keep).
- Tips: the preview screenshot of the 1920 emulation is unreliable, measure with `getBoundingClientRect`; local demo mode (no DB) writes `storage/demo-users.json`, `storage/demo-mail.log` (forgot-password codes) — delete them after tests; PHP here has no GD; bash heredocs with many quotes can fail, use the Write tool for big files.

## STEP 1 (2026-10-22) — login, registration forms, welcome built (needs owner review)
- Pages: /login, /login/agent, /registration/professional/form, /registration/agent/form, /welcome (see CLAUDE.md "step 1" for the rules and additions beyond Figma). Migration 005_users.sql must be run on every database (done on staging after this commit). Desktop 1920 measured against Figma (fields 652x77, card 1059x494, photo block 506 wide, preview 249x273); mobile 390 checked, no sideways scroll.
- Test accounts may exist in the staging DB (users table): delete before real use. Worker/agent applications stay pending until an admin approves (admin panel not built: approve in phpMyAdmin by setting status='approved').
- NEXT: professionals list per service (/services/<slug>) + personal info page, dashboard (incl. change password), admin panel, old-data import, redirects, contact page (owner sends Figma link).

## START HERE (written 2026-10-21 night, for a fresh chat)
## STAGING (2026-10-22) — https://new.jorurikaj.com is LIVE on cPanel with real MySQL
- Repo cloned by cPanel Git Version Control at `/home4/jorurikaj/repositories/jkdotcom` (HTTPS clone, public repo); sub-domain `new.jorurikaj.com` document root = its `public` folder; `.cpanel.yml` creates storage folders. UPDATE FLOW: push to main -> cPanel > Git Version Control > Manage > Pull or Deploy > "Update from Remote" (files are live at once) > "Deploy HEAD Commit".
- Database `jorurika_jkn` + user `jorurika_jkn` (password only in the server `.env`, typed by the owner). Migrations 001-004 imported (11 tables; 94 services, 8 divisions, 64 districts, 511 upazilas). The OLD site DB `jorurika_jk` / user `jorurika_rzaman` were not touched.
- `.env` lives at `/home4/jorurikaj/repositories/jkdotcom/.env` (APP_ENV=production, APP_URL=https://new.jorurikaj.com). HTTPS works and the redirect is enabled in `public/.htaccess`.
- Tested on the real DB: post-a-job (job saved + shown in /today), feedback, subscribe, interest pop-up (click + lead saved, number revealed). NOT verified: e-mail delivery (check the info@jorurikaj.com inbox; mailbox no-reply@ does not exist), real phone keyboards.
- Test rows (1 job, 1 feedback, 1 subscriber, 1 click, 1 lead) are still in the DB: delete them before real use. The two old sub-domains bar-clays-uk / bzst-de (not the owner's) were removed; their folders `/home4/jorurikaj/bar-clays-uk` and `bzst-de` still exist: inspect and delete.
- `client-preview/` (git-ignored) = static HTML copy of the finished pages for showing the client; regenerate after page changes.

Read CLAUDE.md first (all rules and owner decisions), then this section. Owner Reja works in Bangla; reply in Bangla; he reviews every change himself.
- Run locally: double-click `start-server.bat` (portable PHP in tools/php) -> http://127.0.0.1:8000 . No database yet: demo mode (jobs posted in the form are saved in storage/demo-jobs.json and appear at once; clicks/leads in storage/demo-contact-log.json).
- DONE and approved/under review: Home `/`, About `/about`, Services `/services`, Post-a-job `/today/post`, Job list `/today` (accordion, hidden phone/e-mail + "interested?" pop-up + click/lead tracking), sticky main menu, Banglish typing (data-bn), Bangladeshi phone format (+88 fixed, 11 digits), geo dropdowns nationwide.
- STANDING RULES: new pages = body text 24px, other sizes Figma +2px (desktop; body class page-*); fonts stay Poppins + Hind Siliguri (do NOT swap); fix Bangla typos silently; push to main after finished work and say it succeeded; verify on desktop (1920/1440) AND mobile (390) with measurements; keep Bangla texts in app/Content/defaults.php; every new text field gets `data-bn` and the switch partial.
- DONE 2026-10-21 (needs owner review): `/registration` = rules overview (Figma 2631-395): banner + open `.rich` text (`app/Content/pages/registration-rules.html`, key `page.registration-rules.html`) + 2 buttons -> `/registration/professional` and `/registration/agent` (these are meant to become the GUIDELINE pages 2683-148 / 2683-305; the forms then need their own URLs, e.g. `/registration/professional/form` — decide with owner). Fixed in text: "oruriKaj" -> Jorurikaj, "রেজিস্টেশন" -> রেজিস্ট্রেশন. Body class `page-regrules`.
- DONE 2026-10-21 (needs owner review): `/registration/professional` = professional GUIDELINE (Figma 2683-148): rules text (`app/Content/pages/registration-professional.html`), 2 buttons, tinted band with 2 photo cards (`reg-*` classes, photos `public/assets/img/site/reg-*.jpg` resized from Figma). The form will live at `/registration/professional/form` (route = coming-soon for now); card 2 links to `/today/post`. Typos fixed (পেশাজীবীদের, নির্দিষ্ট, অংশগ্রহণ).
- DONE 2026-10-21/22 (needs owner review): the 3 rules pages share `partials/reg-tabs.php` (3 buttons above the text, open page's button inactive; texts `reg.tab.*`), new breadcrumbs, menu item "রেজিস্ট্রেশন সম্পর্কিত তথ্যাবলী" first under রেজিস্ট্রেশন. `/registration/agent` = agent GUIDELINE (Figma 2683-305, text `app/Content/pages/registration-agent.html`); the band with 2 photo cards is `partials/reg-join.php` (shared). Form URLs `/registration/professional/form` and `/registration/agent/form` are still coming-soon.
- 2026-10-22: HANDOVER CLEAN-UP done (all comments in Bangla, dead CSS removed, docs/CODING-STANDARD.md, README updated) — see CLAUDE.md "2026-10-22". Registration rules pages (3) + menu behaviour + post-a-job pop-up done; job notices publish at once.
- TOMORROW'S ORDER (owner, see CLAUDE.md "2026-10-22, evening"): (0) cPanel staging + real MySQL first, (1) login, worker+agent registration forms, contact page, (2) professionals list per service + personal info page, then dashboard, admin panel, old-data import (common initial password), redirects, go-live. Login Figma (2660-816 / 2686-619) already read: banner "আপনার অ্যাকাউন্টে প্রবেশ করুন", crumbs হোম / রেজিস্ট্রেশন / লগইন, heading "দক্ষ পেশাদার / পেশাজীবীর নিজস্ব একাউন্ট লগইন পেইজ", teal card 1059x492: "লগইন আইডি" (placeholder রেজিস্ট্রার্ড মোবাইল নাম্বার), "পাসওয়ার্ড" (with show/hide eye), yellow button "প্রবেশ করুন".
- NEXT (owner, 2026-10-23): LOGIN section (Figma 2660-816 professional, 2686-619 agent), then the two registration FORMS (2658-504, 2682-2; need DB, geo dropdowns, photo <=150KB, password >=12 chars hashed), welcome pages (2663-981, 2686-740).
- (older list) remaining registration pages (Figma 2631-395 overview DONE, 2683-148 professional guideline, 2683-305 agent guideline, 2658-504 professional form, 2682-2 agent form, 2660-816 / 2686-619 logins) -> contact page (owner will send the link) -> admin panel (big; decisions are all in CLAUDE.md; start it in a fresh chat) -> real MySQL (run migrations 001-004 in order), e-mail, cPanel deploy.
- NOT TESTED yet: real MySQL, e-mail sending, real phone keyboards (Banglish), the animations in a real browser (preview pane draws no frames).
- Git tags: home-final-figma-colors (home with Figma colours), before-bangla-font-change. Colour experiments are on branches experiment/logo-colors and experiment/colors-navy-gold (not merged).
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

## 2026-10-21 (night) — state
- Pages done (owner reviewing): Home, About `/about`, Services `/services`, Post-a-job `/today/post`. Next: list page "কাজ সম্পর্কে জানুন" `/today` (Figma 2675-148), then registration pages.
- Done today besides pages: Banglish typing (Avro, `data-bn` fields + switch), site-name bold/gold, fonts experiment REVERTED (Poppins + Hind Siliguri stay; see CLAUDE.md), desktop text 24px on Home/About/Services/Post pages via `body.page-*` classes (other pages 22px), geo data nationwide, `Bangla Digits` fix for ১.
- Not tested: real MySQL (run migrations 001, 002, 003 in order), e-mail sending, real phone keyboard for Banglish, admin panel (nothing built; all admin decisions are in CLAUDE.md).
- A new chat is useful before starting the admin panel (big, separate area): read CLAUDE.md + PROGRESS.md first.

## 2026-10-21 (evening) — Post-a-job page `/today/post` built (needs owner review)
- Form from Figma 2674-7 + validation + spam guard + admin e-mail + thank-you card; saves 'pending' rows (demo mode without DB logs it). Needs `database/migrations/003_today_jobs.sql` on the real MySQL (not run yet — no real DB tested).
- Geo dropdowns work nationwide now (public MIT dataset + Dhaka thanas). Date inputs use the browser's own format.
- NOT done: list page "কাজ সম্পর্কে জানুন" (/today, Figma 2675-148) — next; admin approval; e-mail sending untested (needs cPanel).

## 2026-10-21 (later) — Services page `/services` basic work DONE (owner: "প্রাথমিক কাজ হয়ে গেছে")
- Built from Figma 2642-54: banner, intro, teal notice box, search pill, all services A-Z under letter headings in 3 balanced columns (`app/Models/ServiceDirectory.php`, view `app/Views/pages/services.php`). Letter groups never split; each service links to `/services/<slug>`.
- Search now matches service NAMES only (`/services?q=`). Area (গ্রাম/থানা-উপজেলা/জেলা/বিভাগ), name, mobile search needs workers + geo tables (not built).
- 3 services renamed (blood sample, 2 tutors; slugs unchanged). Bangla typos fixed silently (owner's permission). Site name "Jorurikaj.com" bold+coloured in page text (teal / gold on dark).
- Open: very long service names may wrap on small laptops; search focus frame removed on purpose; admin editing of letter headings/services not built.
- Local commits not pushed yet (push when owner says). Next: pick the next Figma page (Registration overview/guidelines 2631-395, 2683-148, 2683-305 / Contact when ready).

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
