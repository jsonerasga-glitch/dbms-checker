# DBMS Student Activity Checker & Log Verifier

## Summary

A PHP web app for instructors to grade MySQL/DBMS class activities. It
connects directly to a shared MySQL server, inspects each student's database
(schema, data, and — for SQL-based activities — live re-execution of their
logged queries against a reference answer key), and produces a scored report
per student or for a whole class in one batch pass. Students can also log in
with their own MySQL credentials via a separate portal to see their own live
score. It currently supports four activities with progressively different
verification strategies, from DDL/schema inspection to live SQL re-execution
and comparison.

## Who it's for

- **Instructors** grading a class section on a shared MySQL server, who need
  to check dozens of student databases quickly without manually inspecting
  each one.
- **Students** who want to self-check their own progress/score without
  waiting for the instructor to run a batch check.

## Core capabilities

- **Single Check** — score one student database and see a full task-by-task
  breakdown with pass/fail detail.
- **Class Batch Checker** — auto-discover student databases (by section
  prefix) or paste a list, and score them all in one pass. A failure on one
  student's database never aborts the batch for the rest of the class.
- **MySQL Log Explorer** — browse/filter the server's general query log
  (used to verify Activity 1's DDL history).
- **Demo Generator** — spins up sample student databases to sanity-check the
  checker itself.
- **Student Portal** — a self-check page where students log in with their
  own MySQL credentials and see their own score live.
- **CSV / multi-sheet Excel export** of results, with or without query-log
  proofs.

## Activities supported

| Activity | What's graded | How it's verified |
|---|---|---|
| 1 — Library Database | `tbl_authors`, `tbl_members`, `tbl_books`, `tbl_borrow_transactions` (DDL, ALTER, foreign keys) | Final schema state via `INFORMATION_SCHEMA` |
| 2 — Suppliers & Items CRUD | `suppliers` / `items` tables, INSERT/UPDATE/DELETE tasks | Final schema + data state (no query log dependency) |
| 3 — SELECT Statements (Registrar) | 10 SELECT tasks logged into the student's own `activity_20260805` table | Each logged query is re-run live against `dbms_activity` and compared to the instructor's reference query |
| 4 — Logical Operators & Aggregates (Resort) | 10 SELECT tasks logged into `activity_20260817` | Same live-comparison approach, against `dbms_activity_answer_key.activity4_answerkey` |

Activities 3 and 4 execute student-submitted SQL text to verify it. That
execution is restricted to a single validated `SELECT`/`WITH` statement (no
stacked statements, no DDL/DML keywords) on a connection opened with
`SET SESSION TRANSACTION READ ONLY`, so a malformed or malicious submission
can't affect the shared source database.

## Success criteria

- An instructor can batch-check an entire class section's databases against
  any of the four activities in one pass, with per-student failures isolated
  (one bad database never aborts the batch).
- A student can self-verify their own score without instructor involvement,
  seeing only their own database.
- Scoring is deterministic and reproducible: given the same database state,
  the checker always produces the same score and task breakdown.

## Out of scope (for now)

- Multi-instructor / multi-course support — the app assumes a single MySQL
  server and a single active section/config at a time (`config.json`).
- Automated activity onboarding — new activities currently require adding a
  new method to `src/Checker.php` by hand.
