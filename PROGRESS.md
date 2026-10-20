# Progress (read CLAUDE.md first)

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
