# Quiz Checker — Spec

## Overview

Instructors currently grade Activities 1–4 by inspecting a student's live
MySQL database and (for Activities 3/4) re-executing SQL the student *typed
into a running MySQL session*, which got captured in the general query log.
**Quiz Checker** extends this to a different scenario: a **paper-based
quiz**, where a student handwrites SQL on paper (no computer, no query log).

The instructor photographs each student's paper, uploads it through a new
admin tab, the app OCRs the handwriting into editable text (per question),
the instructor reviews/corrects the transcription, and the app then executes
each transcribed query read-only against the shared `dbms_activity` source
database and compares its result set to a reference answer key query — the
same execute-and-compare approach Activities 3/4 already use, just fed from
a scanned paper instead of a query log.

This reuses `ActivityChecker`'s existing safe-execution machinery
(`a34ValidateSelect`, `a34RunQuery`, `a34RowsMatch`, `a34ColumnsMatch`,
`a34SourceConnection`) rather than duplicating it.

## Functional Requirements

### 1. Answer key

- New table `dbms_activity_answer_key.quiz_answerkey` with columns
  `task_number` (int) and `sql_syntax` (text) — same shape as
  `activity3_answerkey` / `activity4_answerkey`, so the instructor authors
  reference queries the same way they already do for Activities 3/4.
- The number of questions is driven by however many rows exist in
  `quiz_answerkey` (not hardcoded to a fixed count).

### 2. Admin UI — new "Quiz Checker" tab

Added to `index.php`'s tab set (`nav-tabs`), following the existing tab
pattern (glass cards, status badges, icon+label buttons per
`product-guidelines.md`).

Workflow within the tab, per student:

1. **Identify student** — enter/select a student database name (same
   `section_lastname` convention used elsewhere), so results can be
   attributed and (optionally) cross-referenced with their existing scores.
2. **Upload paper photo** — a single image file (JPEG/PNG) of the student's
   handwritten quiz paper.
3. **OCR extraction** — the photo is run through **Tesseract OCR** (local,
   invoked via `shell_exec`/`proc_open`, not a cloud API) to produce a raw
   text transcription.
4. **Review & correct** — the raw OCR text is shown in an editable textarea
   per question/task number. The instructor splits/edits the transcription
   into one SQL statement per `task_number` before anything executes. No
   query runs against the database until the instructor confirms.
5. **Execute & score** — on confirm, each edited statement is validated
   (`a34ValidateSelect`) and executed read-only against `dbms_activity`,
   compared to the matching `quiz_answerkey` row's reference query output
   via `a34RowsMatch`/`a34ColumnsMatch`. Each task is pass/fail (100% or 0%,
   consistent with how `a34Result` scores Activities 3/4).
6. **Result display** — task-by-task breakdown (title, pass/fail, detail
   message) plus total score, shown the same way Single Check results are
   shown today.

### 3. Storage

- Uploaded photos are saved to a new `storage/quiz_uploads/` directory
  (created if missing), named to avoid collisions (e.g.
  `{db_name}_{timestamp}.{ext}`), and are **not** deleted automatically —
  they're the grading evidence of record for that paper.
- `storage/quiz_uploads/` must be excluded from git (add to `.gitignore`)
  since it holds student-submitted images.

### 4. Backend

- New `api.php` actions:
  - `quiz_upload` — accepts the image, saves it, runs Tesseract, returns raw
    OCR text for the review step.
  - `quiz_check` — accepts the instructor-edited per-task SQL text plus the
    student db name, executes/scores against `quiz_answerkey`, returns the
    same task-breakdown shape Activities 3/4 return.
- New method(s) on `ActivityChecker` (`src/Checker.php`) reusing the
  existing `a34*` helpers rather than re-implementing execution/comparison.

## Non-Functional Requirements

- **Tesseract is a new system dependency.** `tech-stack.md`'s "keep the app
  dependency-free" constraint is about Composer/npm packages, not system
  binaries — Tesseract must be documented in `README.md`/setup docs as a
  required system install (like MySQL already is), not silently assumed.
- Read-only execution safety must match Activities 3/4 exactly: single
  `SELECT`/`WITH` statement only, `SET SESSION TRANSACTION READ ONLY`
  connection, no stacked statements, no DDL/DML keywords.
- OCR must never auto-score without the human review/edit step (Section 2,
  step 4) — this is a hard requirement, not a toggle, given handwriting OCR
  error rates.

## Acceptance Criteria

- Instructor can upload a photo of a handwritten quiz paper for a named
  student and get an editable OCR transcription back, split by question.
- Instructor can correct the transcription and submit it for scoring.
- Each submitted answer is executed read-only against `dbms_activity` and
  scored pass/fail against `quiz_answerkey`, with per-task detail shown.
- A malformed/unreadable transcription for one task fails only that task
  (0%) — it doesn't abort scoring of the other tasks on the same paper.
- The uploaded photo is retained on disk as grading evidence.
- `quiz_uploads/` is git-ignored.

## Out of Scope (for now)

- Bulk/batch photo upload for a whole class in one pass (v1 is one paper at
  a time, matching how the instructor already works through papers
  individually).
- Student self-upload via the student portal.
- A UI for authoring `quiz_answerkey` rows — instructor edits that table
  directly (same as `activity3_answerkey`/`activity4_answerkey` today).
- Automatic image preprocessing/deskew beyond whatever Tesseract does by
  default.
- CSV/Excel export of quiz results (existing batch export flow is
  untouched; can be added later).
