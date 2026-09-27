# DBMS Student Activity Checker & Log Verifier

A PHP web app for grading MySQL/DBMS class activities. It connects to a
MySQL server, inspects each student's database (schema and data), and
produces a scored report per student or for a whole class in one batch.

Built for six activities that use progressively different verification
strategies — from schema/DDL checks to live re-execution of student-submitted
SQL against a shared answer key.

## Features

- **Single Check** — score one student database and see a full task-by-task
  breakdown with pass/fail detail.
- **Class Batch Checker** — auto-discover student databases (by section
  prefix) or paste a list, and score them all in one pass. A failure on one
  student's database never aborts the batch for the rest of the class.
- **MySQL Log Explorer** — browse/filter the server's general query log
  (used by Activity 1).
- **Demo Generator** — spins up a sample student database to sanity-check
  the checker itself.
- **Student Portal** (`student-portal/`) — a self-check page where students
  log in with their own MySQL credentials and see their own score live.
- **CSV / multi-sheet Excel export** of results.

## Activities

| Activity | What's graded | How it's verified |
|---|---|---|
| **1 — Library Database** | `tbl_authors`, `tbl_members`, `tbl_books`, `tbl_borrow_transactions` (DDL, ALTER, foreign keys) | Final schema state via `INFORMATION_SCHEMA` |
| **2 — Suppliers & Items CRUD** | `suppliers` / `items` tables, INSERT/UPDATE/DELETE tasks | Final schema + data state (no query log dependency) |
| **3 — SELECT Statements (Registrar)** | 10 SELECT tasks logged into the student's own `activity_20260805` table | Each logged query is re-run live against `dbms_activity` and compared to the instructor's reference query |
| **4 — Logical Operators & Aggregates (Resort)** | 10 SELECT tasks logged into `activity_20260817` | Same live-comparison approach, against `dbms_activity_answer_key.activity4_answerkey` |
| **5 — SELECT Statements (Bookstore)** | 10 SELECT tasks logged into `activity_20260916` | Same live-comparison approach, against `dbms_activity_answer_key.activity5_answerkey` |
| **6 — Aggregate Functions & Set Operators (Pasalubong Center)** | 10 SELECT tasks logged into `activity_20260928` | Same live-comparison approach, against `dbms_activity_answer_key.activity6_answerkey` |

Activities 3-6 execute student-submitted SQL text to verify it. That
execution is restricted to a single validated `SELECT`/`WITH` statement (no
stacked statements, no DDL/DML keywords) on a connection opened with
`SET SESSION TRANSACTION READ ONLY`, so a malformed or malicious submission
can't affect the shared source database.

## Requirements

- PHP 8+ with the `pdo_mysql` extension
- A MySQL/MariaDB server reachable from the app
- For Activities 3-6: a `dbms_activity` source database and a
  `dbms_activity_answer_key` database (`activity3_answerkey`,
  `activity4_answerkey`, `activity5_answerkey`, `activity6_answerkey` tables
  with `task_number`/`sql_syntax` reference queries)

## Setup

1. Clone the repo and configure the database connection in `config.json`
   (created automatically on first save from the Settings modal, or edit
   directly):

   ```json
   {
     "db_host": "127.0.0.1",
     "db_port": "3306",
     "db_user": "root",
     "db_pass": "",
     "log_check_enabled": true,
     "log_date_enabled": false,
     "section_filter": "2_cs4",
     "score_weights": { "task1": 15, "task2": 15, "task3": 15, "task4": 15, "task5": 20, "task6": 20 }
   }
   ```

   `score_weights` only applies to Activity 1; Activities 2–4 use fixed
   per-task weights.

2. Run the built-in PHP dev server from the project root:

   ```bash
   php -S 127.0.0.1:8000
   ```

3. Open `http://127.0.0.1:8000/` for the admin dashboard, or
   `http://127.0.0.1:8000/student-portal/` for the student self-check page.

## Project structure

```
api.php              Backend router (all AJAX actions)
config.php            Config loader + PDO connection helpers
config.json            DB connection & scoring settings
index.php              Admin dashboard
src/Checker.php        Scoring engine for all four activities
assets/css/style.css   Dashboard styling
assets/js/app.js       Dashboard frontend logic
student-portal/        Student self-check portal
activity/               Activity briefs (Word docs) given to students
```

## Security notes

- `config.json` holds a MySQL user/password in plaintext and is currently
  committed to the repo — treat this repo as private, or move real
  credentials to an untracked config before making it public.
- The Student Portal's login verifies credentials by opening a real MySQL
  connection with the student's own username/password (`api.php`'s
  `student_login` action), so students can only see their own database.
