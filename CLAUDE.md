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
