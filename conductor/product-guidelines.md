# Product Guidelines

## Visual identity

Dark "glassmorphism" dashboard aesthetic — deep navy background with subtle
indigo/cyan radial glow, translucent frosted-glass cards, and a gradient
brand mark.

- **Background:** `#0f172a` base with soft indigo (`rgba(99,102,241,.15)`)
  and cyan (`rgba(6,182,212,.12)`) radial glows, fixed attachment.
- **Cards/surfaces:** translucent `rgba(30,41,59,.7)` with `blur(12px)`
  backdrop filter, 1px `rgba(255,255,255,.1)` border, large soft shadow
  (`--shadow-lg`), rounded corners (16px cards, 10px controls, 6px chips).
- **Accent gradient:** indigo `#6366f1` → cyan `#06b6d4`, used for the brand
  icon and primary CTAs/highlighted headings.
- **Typography:** 'Outfit' (Google Fonts) as the primary typeface, system
  sans-serif fallback stack. Headings/brand text may use a white→cyan text
  gradient for emphasis.
- **Iconography:** Font Awesome 6 (free, solid style) for all UI icons —
  keep using it rather than introducing a second icon set.
- **Status colors (semantic, non-negotiable):** green `#10b981` = pass,
  amber `#f59e0b` = warn, red `#f43f5e` = fail — each with a matching
  low-opacity background tint for badges/pills. Any new status UI must
  reuse these three, not invent new colors for the same meanings.

## Voice & tone

- Direct, technical, instructor-facing. Copy speaks in domain terms (task
  numbers, table names, schema/DDL, query logs) rather than generic
  "gamified" education language.
- Action labels are imperative and specific ("Run Verification & Score",
  "Generate & Check Demo Database"), not vague ("Submit", "Go").
  Icon + label pairing on every primary button.
- Errors surface the underlying cause (e.g. raw exception message from a
  failed check) rather than a generic "something went wrong" — this is a
  grading tool for instructors debugging student databases, so precision
  beats friendliness.

## UX principles

- **Tabbed single-page dashboard.** One admin view (`index.php`) with four
  tabs (Single Check, Batch Checker, Log Explorer, Demo Generator); no
  multi-page navigation. New admin-facing capabilities should extend this
  tab set rather than spawning separate pages.
- **Isolate failures.** A single student's malformed/missing database must
  never abort a batch operation — always catch per-item and continue,
  surfacing the error inline for that row only (see `api.php`'s
  `batch_check`).
- **Never block on missing data.** Every view should degrade gracefully
  (e.g. "Loading logs...", empty-state cards with a call-to-action) instead
  of erroring out when a section has no data yet.
- **Config is user-editable, not just file-edited.** Connection/scoring
  settings exposed in `config.json` should also be editable from the
  Settings modal in the UI — don't add a config knob that's file-only.
- **Student vs. admin surfaces stay separate.** The student portal
  (`student-portal/`) only ever exposes the logged-in student's own data;
  admin-only actions (batch check, log explorer, demo generator, raw config)
  must never be reachable from the student portal.
