# Tech Stack

## Language / Runtime

- **PHP 8+** — no framework. Plain procedural/OOP PHP, no Composer, no
  autoloader beyond manual `require_once`.

## Database

- **MySQL / MariaDB**, accessed via **PDO** (`pdo_mysql` extension).
  - `PDO::ATTR_ERRMODE` set to `PDO::ERRMODE_EXCEPTION` everywhere.
  - No ORM, no query builder, no migrations — raw SQL via `PDO::query`/
    `PDO::prepare`.
  - `INFORMATION_SCHEMA` queries used for schema/DDL verification.
  - `mysql.general_log` (table output) used for query-log verification.

## Backend architecture

- **`api.php`** — single JSON router, dispatches on an `action` query/POST
  param via a `switch`. All AJAX endpoints live here.
- **`config.php`** — config loader (`config.json` ⇄ defaults merge) and PDO
  connection factories (admin connection + per-student credential
  connection for the student portal).
- **`src/Checker.php`** — `ActivityChecker` class, the scoring engine; one
  method per activity (activity1–activity4).
- **Session-based auth** for the student portal (`session_start()`,
  `$_SESSION['student_db']`), no auth framework/JWT.

## Frontend

- **Vanilla JavaScript** (`assets/js/app.js`, `student-portal/portal.js`) —
  no framework (no React/Vue/etc.), no bundler/build step, no npm.
- **Vanilla CSS** (`assets/css/style.css`) — CSS custom properties for
  theming, no preprocessor.
- **CDN dependencies only:** Font Awesome 6 (icons), Google Fonts 'Outfit'
  (typeface). No other third-party JS/CSS libraries.

## Config & data

- **`config.json`** — flat JSON file for DB connection + scoring settings,
  read/written directly by `config.php` (no env vars, no secrets manager).
  Currently committed to the repo (see README security notes).

## Dev / run

- **PHP built-in server** (`php -S 127.0.0.1:8000`) — no Docker, no
  containerization, no CI/CD pipeline present.
- No test framework/suite currently in the repo.

## Constraints to respect

- Keep the app dependency-free (no Composer/npm) unless a track explicitly
  calls for adding one — this is a deliberate zero-build-step tool.
- New activities extend `ActivityChecker` in `src/Checker.php`; new backend
  endpoints extend the `switch` in `api.php`. Don't introduce a routing
  framework or split this into microservices.
