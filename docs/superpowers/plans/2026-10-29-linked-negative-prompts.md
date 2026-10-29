# Linked Negative Prompts Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement F16 so selected options, character outfits, and clothing-LoRA triggers can contribute optional negative prompt sections without altering completed output implicitly.

**Architecture:** Extend the three existing aggregates and persistence records with an optional normalized `PromptText`, project it through the existing query DTOs, and compose negative sections in browser-side pure functions from current selections. Existing completed-output, favorite, character-variant, and ComfyUI flows continue to consume the current text areas unchanged.

**Tech Stack:** PHP 8.5, Laravel 13, SQLite migrations, Blade, ES modules, Node test runner.

**Spec:** `docs/design/features/F16-選択項目に連動するネガティブ.md`

## Global Constraints

- Do not read real database or uploaded storage data; use only test databases and fixtures.
- Do not use browser automation or screenshots.
- Character-LoRA triggers must reject non-empty linked negative content; clothing-LoRA triggers may save it.
- Omitted update fields preserve the stored linked negative; explicit null or empty clears it.
- Completed output is changed only by the existing “show prompt” action.

## Review Focus

- Omitted versus explicit null `negativeContent` on update preserves versus clears the value.
- Character-trigger endpoints reject non-empty `negativeContent` without changing stored content.
- Duplicate option negatives are removed across option groups while default/clothing/outfit boundaries remain separate.
- Special syntax containing commas in parentheses remains intact.
- Character variants and ComfyUI send the current edited negative output without recomputing registered negatives.

---

### Task 1: Persistence and domain contracts

**Files:**
- Create: `database/migrations/2026_10_29_000000_add_negative_content_to_selectable_prompts.php`
- Modify: the three prompt domain models, repositories, Eloquent records, save inputs/results, and request mapping.
- Test: `tests/Unit/Domain/PromptPreparation/NamedPromptModelsTest.php`
- Test: the three existing HTTP API test files.

**Interfaces:**
- Produces: `negativeContent: string` on saved option/outfit/trigger results and `negative_content` text columns defaulting to `''`.

- [ ] Write failing domain and HTTP persistence tests for normalize/save/clear/preserve/reject behavior.
- [ ] Run the focused tests and verify failure is caused by the missing field/columns.
- [ ] Implement the minimal migration, model, repository, input, request, and result changes.
- [ ] Re-run focused tests and commit.

### Task 2: Query projections

**Files:**
- Modify: the three query result DTOs and Eloquent query services.
- Test: the three HTTP API test files.

**Interfaces:**
- Consumes: Task 1 columns.
- Produces: `negativeContent: string` for option items, outfits, and triggers.

- [ ] Add failing response-shape tests.
- [ ] Verify the response lacks `negativeContent`.
- [ ] Add projections and precise PHPDoc shapes.
- [ ] Re-run focused tests and commit.

### Task 3: Management forms

**Files:**
- Modify: `resources/views/prompt-preparation.blade.php`, `resources/views/prompt-option-management.blade.php`.
- Modify: `resources/js/prompt-option-management-page.js`, `resources/js/lora-options-page.js`, `resources/js/clothing-lora-options-page.js` and API helpers.
- Test: corresponding JavaScript tests and `tests/Feature/TopPageTest.php`.

**Interfaces:**
- Consumes: Tasks 1–2 API contracts.
- Produces: optional linked-negative fields that retain input on failure and update local state on success.

- [ ] Add failing UI and request-payload tests.
- [ ] Verify the missing controls/payload cause failure.
- [ ] Implement minimal controls, loading, saving, and failure retention.
- [ ] Re-run focused tests and commit.

### Task 4: Negative prompt composition

**Files:**
- Modify: `resources/js/prompt-text.js`, selection page modules, and `resources/js/app.js`.
- Test: `tests/JavaScript/prompt-text.test.js`, selection page tests, and prompt preparation integration tests.

**Interfaces:**
- Consumes: selected clothing trigger, character outfit, and option `negativeContent` values.
- Produces: ordered negative sections: family default, clothing triggers, character outfit, deduplicated options.

- [ ] Add failing pure-function and page integration tests covering order, empty default, dedupe, special syntax, and edited-output preservation.
- [ ] Verify failures are caused by missing linked-negative composition.
- [ ] Implement the pure composition and wire selection snapshots to the existing explicit display action.
- [ ] Re-run focused and character-variant/ComfyUI regression tests and commit.

### Task 5: Documentation, status, build, and review

**Files:**
- Modify: related F02/F05/F08/F09/F16 design wording where superseded.
- Modify: `docs/機能実装状況.md` for F16 and the already-implemented F17.

**Interfaces:**
- Consumes: Tasks 1–4 final contracts.
- Produces: aligned design/status, built assets, and verified branch.

- [ ] Align related specifications without adding implementation progress to feature specs.
- [ ] Run `docker compose run --rm -T node npm run build`.
- [ ] Run `sh scripts/check.sh`.
- [ ] Run `docker compose run --rm -T -e APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= app composer test`.
- [ ] Review all categories in `docs/review/コードレビュー基準.md`, fix findings with RED→GREEN evidence, and commit.
