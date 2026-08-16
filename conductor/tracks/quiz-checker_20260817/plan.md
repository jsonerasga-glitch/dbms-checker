# Quiz Checker — Implementation Plan

Reference: [spec.md](./spec.md) · Workflow: [../../workflow.md](../../workflow.md)

This plan follows the project's TDD workflow. The codebase currently has no
test framework (`tech-stack.md` notes this and the "dependency-free"
constraint), so Phase 1 introduces **PHPUnit** as the project's first
Composer dependency — a deliberate, documented deviation from that
constraint, required to satisfy `workflow.md`'s TDD mandate for anything
beyond pure manual verification. Frontend logic is kept in small pure
functions so it can be unit-tested with Node's built-in test runner
(`node --test`), with no build step or bundler added.

---

## Phase 1: Test Harness & Dependency Setup

- [ ] Task: Introduce PHPUnit as the project's first Composer dependency
    - [ ] Document the deviation in `tech-stack.md` (dated note: adding
      Composer + PHPUnit as a dev-only dependency to satisfy TDD workflow
      requirements; app runtime itself remains dependency-free)
    - [ ] Add `composer.json` (dev dependency: `phpunit/phpunit`), run
      `composer install`, add `vendor/` to `.gitignore`
    - [ ] Add `phpunit.xml` pointing at a new `tests/` directory
    - [ ] Write one trivial smoke test (`tests/SmokeTest.php`) asserting
      `true === true` to confirm the harness runs
    - [ ] Run `vendor/bin/phpunit` and confirm the smoke test passes (Green)
- [ ] Task: Set up the Node test runner for frontend pure-function tests
    - [ ] Confirm `node --test` runs against a placeholder
      `assets/js/__tests__/smoke.test.js` (no npm dependency added — uses
      Node's built-in `node:test`/`node:assert`)
- [ ] Task: Phase Verification & Checkpoint (Refer to workflow.md)

## Phase 2: Answer Key Schema

- [ ] Task: `quiz_answerkey` migration
    - [ ] Write a test that asserts a migration helper produces the correct
      `CREATE TABLE IF NOT EXISTS` statement text (columns: `task_number`
      INT, `sql_syntax` TEXT) — Red
    - [ ] Add `migrations/quiz_answerkey.sql` (mirrors the shape of
      `activity3_answerkey`/`activity4_answerkey`) and a small PHP helper
      that can apply it — Green
    - [ ] Document in README how the instructor seeds `quiz_answerkey` rows
      (same manual pattern as Activities 3/4's answer-key tables)
- [ ] Task: Phase Verification & Checkpoint (Refer to workflow.md)

## Phase 3: Backend — Upload & OCR Extraction

- [ ] Task: Upload validation
    - [ ] Write unit tests for an image-upload validator (accepts
      JPEG/PNG, rejects other mime types/oversized files, rejects missing
      `db_name`) — Red
    - [ ] Implement the validator as a pure, mockable function — Green
- [ ] Task: File storage
    - [ ] Write a unit test asserting the storage-path builder produces a
      collision-avoiding path under `storage/quiz_uploads/`
      (`{db_name}_{timestamp}.{ext}`) — Red
    - [ ] Implement the path builder and directory-creation logic — Green
    - [ ] Add `storage/quiz_uploads/` to `.gitignore`
- [ ] Task: Tesseract OCR wrapper
    - [ ] Write a unit test for a `TesseractRunner`-style wrapper using an
      injectable command executor (so the test doesn't actually shell out),
      asserting it builds the correct `tesseract <image> stdout` invocation
      and returns its output, and throws a clear exception if the
      `tesseract` binary is missing/fails — Red
    - [ ] Implement the wrapper (`shell_exec`/`proc_open`) — Green
    - [ ] Document the Tesseract system-binary requirement in `README.md`
      (per spec's Non-Functional Requirements)
- [ ] Task: `quiz_upload` API action
    - [ ] Write a test (PHPUnit, mocking the validator/storage/OCR wrapper)
      for the `quiz_upload` handler's success and failure JSON shapes — Red
    - [ ] Wire the handler into `api.php`'s `switch` — Green
- [ ] Task: Phase Verification & Checkpoint (Refer to workflow.md)

## Phase 4: Backend — Scoring & Execution

- [ ] Task: `ActivityChecker::checkQuiz()` scoring method
    - [ ] Write unit tests (mock PDO) for a `quizTask()`-style method
      mirroring `a34Task()`'s behavior: missing answer-key row, broken
      reference query, missing student submission, matching/non-matching
      result sets — each asserted against `a34RowsMatch`/`a34ColumnsMatch`
      — Red
    - [ ] Implement `checkQuiz()`/`quizTask()` on `ActivityChecker`, reusing
      `a34ValidateSelect`, `a34RunQuery`, `a34SourceConnection`,
      `a34RowsMatch`, `a34ColumnsMatch`, `a34Result` rather than
      duplicating them — Green
    - [ ] Confirm one bad/unreadable task only zeroes that task, not the
      whole paper (matches Activities 3/4 isolation behavior)
- [ ] Task: `quiz_check` API action
    - [ ] Write a test for the `quiz_check` handler's request/response
      shape (per-task db name + edited SQL text in, task breakdown +
      total score out) — Red
    - [ ] Wire the handler into `api.php` — Green
- [ ] Task: Phase Verification & Checkpoint (Refer to workflow.md)

## Phase 5: Frontend — Quiz Checker Tab

- [ ] Task: Pure-function helpers (unit-testable, no DOM)
    - [ ] Write `node --test` tests for a function that splits raw OCR text
      into per-`task_number` editable fields — Red
    - [ ] Write tests for a function that formats the `quiz_check` response
      into the same task-breakdown render model Single Check already uses
      — Red
    - [ ] Implement both pure functions in `assets/js/app.js` — Green
- [ ] Task: Tab markup & wiring
    - [ ] Add the "Quiz Checker" tab button/panel to `index.php`'s
      `nav-tabs`/`app-container`, following existing glass-card/status-badge
      conventions from `product-guidelines.md`
    - [ ] Wire upload form → `quiz_upload` → editable review fields →
      confirm → `quiz_check` → result display, using the Phase 5 pure
      functions (DOM-wiring itself is exercised via manual verification,
      not unit tests, consistent with the rest of `app.js`)
- [ ] Task: Phase Verification & Checkpoint (Refer to workflow.md)

## Phase 6: Docs & Cleanup

- [ ] Task: Update `README.md`
    - [ ] Add Tesseract system-binary requirement to Requirements section
    - [ ] Add `quiz_answerkey` setup to the Setup section
    - [ ] Add Quiz Checker to the Features list and project structure table
      (`storage/quiz_uploads/`, `migrations/`)
- [ ] Task: Full regression pass
    - [ ] Run `vendor/bin/phpunit` and `node --test` together, confirm all
      green
    - [ ] Confirm `git status` shows `vendor/` and `storage/quiz_uploads/`
      correctly ignored
- [ ] Task: Phase Verification & Checkpoint (Refer to workflow.md)
