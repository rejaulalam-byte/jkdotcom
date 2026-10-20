# Progress

## Done
- 2026-10-20: Project folder set up; logos copied to assets/brand/; CLAUDE.md written; git init.

## Next
- Get: existing site source / how it runs (PHP? WordPress?), server access type, Figma link, GitHub repo URL.
- Design system proposal -> approval.

## Open questions
- Is this a static front-end rebuild or edits on the existing backend templates?

## 2026-10-20 Old-site backup audit (backup stored OUTSIDE repo: E:\0000 Claude-All JK- BACKUP Old Site - Rokib Bhai)
- Backup: files (tar.gz, ~30MB) + DB jorurika_jk.sql.gz (55 tables). Never commit these.
- DB: worker_info ~1364 rows, services 93, upazila 253, district 63, division 7; ajker_kaaj/agent_info empty. Tables: worker_*, agent_info, ajker_kaaj, services, division/district/upazila/post_office, p2001..p7001 (page content), user (admin).
- SECURITY FINDINGS on old site: (1) public_html/index.php has an obfuscated base64 `@include` of hidden `.auth.*` files = backdoor loader; host had malware quarantine (Aug 2026). (2) Passwords stored PLAINTEXT (worker_login_info.password, user.pass). (3) Raw SQL built from input (mysqli_real_escape_string only), ~168 files. (4) Old code must NOT be copied; reuse DB data only, re-hash/reset passwords.
- Decision: rebuild clean; migrate data via script; force password reset (or hash on first login).
- Old live site is likely still compromised -> clean it / rotate cPanel + DB passwords soon.
