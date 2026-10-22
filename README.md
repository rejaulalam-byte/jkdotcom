# jorurikaj.com

Bangladeshi service-provider website (জরুরীকাজ.কম). Plain **PHP 8.1+** and **MySQL**, no framework,
built so any PHP developer can pick it up quickly. Design source: Figma file `RTM-AKTU-Sylhet`
(frames named `JoruriKaj.Com-*`). Project notes and decisions: [CLAUDE.md](CLAUDE.md), progress: [PROGRESS.md](PROGRESS.md).
**Coding rules (comments in Bangla, CSS/JS/PHP conventions): [docs/CODING-STANDARD.md](docs/CODING-STANDARD.md) — read this first.**

## Folder map

```
public/            <- the ONLY folder the web server serves (document root)
  index.php        front controller: every URL enters here
  assets/          css/app.css (one stylesheet), js/app.js, fonts, img, vendor (third-party, unchanged)
  uploads/         files uploaded through the admin (PHP can never run here)
app/
  bootstrap.php    start-up: autoload, .env, error handling, security headers, session
  helpers.php      e() escape, bn() Bangla digits, asset(), content(), csrf_field() ...
  Core/            Router, View, Database (PDO), Security, Session, Mailer, RateLimiter, Content, Logger
  Controllers/     one class per page/feature
  Models/          database access (repositories); DemoData = sample data when no DB is set
  Views/           layouts/, partials/ (header, footer...), pages/
  Content/defaults.php   default wording of every editable text
  Content/pages/   long "open page" texts as HTML (About, registration rules ...)
config/routes.php  list of all URLs
database/migrations/     SQL to create tables (run 001 to 006 in order in phpMyAdmin)
database/seeds/          data lists (services, areas, icons) used by the migrations and by demo mode
docs/                    CODING-STANDARD.md and design references
tools/php/               portable PHP for Windows (git-ignored); start-server.bat starts the site with it
storage/           logs, sessions, rate-limit files (private, not in git)
.env.example       copy to .env and fill in (never commit .env)
```

## Run locally

1. Windows: double-click `start-server.bat`. Elsewhere install PHP 8.1+ and run `php -S localhost:8000 -t public`
2. Open <http://localhost:8000>. With no `.env` the site runs in **demo mode** (sample jobs, ads, services).
   In demo mode posts made in the form are kept in `storage/demo-jobs.json` (not in git).
3. To use a real database: copy `.env.example` to `.env`, set `DB_*`, and run all files in `database/migrations/` in order.

## Deploy on cPanel (summary)

1. Create the sub-domain (e.g. `new.jorurikaj.com`) and point its **Document Root at the `public` folder** of this project.
   (If that is impossible, the root `.htaccess` still keeps everything outside `public/` unreachable.)
2. Create a MySQL database + user, import the migrations, copy `.env.example` to `.env`, fill it in, set `APP_ENV=production`.
3. Make sure `storage/` and `public/uploads/` are writable by PHP (permissions 755/750, never 777).
4. Install the SSL certificate, then enable the HTTPS redirect in `public/.htaccess`.

## Security rules for every developer

* Output: wrap every dynamic value in `e(...)` (HTML-escape). Never echo raw user input.
* Database: only PDO **prepared statements** with bound parameters — never build SQL from user input.
* Forms: every POST form has `<?= csrf_field() ?>`; controllers call `Security::verifyCsrf()`. Add a honeypot + `RateLimiter` for public forms.
* Passwords: store only `password_hash()`; check with `password_verify()`. Never store or log plain passwords.
* Uploads: check real type (`finfo`), size and extension; save with a random name into `public/uploads/`; never execute them.
* Redirects: only to internal paths via `redirect()`.
* Secrets live only in `.env` (outside git). Errors are logged to `storage/logs/`, never shown to visitors.
* Content-Security-Policy allows only our own domain: no inline `<script>`, no inline `style=""`, no external CDNs.
  Add new CSS/JS as files under `public/assets/`.

## Adding a page

1. `app/Controllers/XController.php` method that gathers data and calls `View::render('pages/x', [...])`.
2. `app/Views/pages/x.php` (start inner pages with the `partials/page-banner.php` title banner).
3. One line in `config/routes.php`.
4. Put editable wording in `app/Content/defaults.php` and read it with `content('key')`.
