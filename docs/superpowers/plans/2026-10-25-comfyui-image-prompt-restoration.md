# ComfyUI Image Prompt Restoration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restore exact positive/negative prompts from a supported ComfyUI PNG, conservatively restore registered selections, and save selected unmatched tags as one normal option.

**Architecture:** A read-only HTTP query accepts one temporary PNG. An Infrastructure reader extracts bounded `prompt` JSON without persistence; Application services mask configured mutable inputs to identify one registered workflow and match prompt text to current registration data. A dedicated JavaScript controller previews the result and applies it through existing selection controllers only after confirmation.

**Tech Stack:** PHP 8.5, Laravel 13, Blade, JavaScript ES modules, Node test runner/JSDOM, PHPUnit 12, Docker Compose.

**Spec:** `docs/design/features/F24-ComfyUI画像からのプロンプト復元.md`

## Global Constraints

- Work only in `.worktrees/design-comfyui-image-prompt-import`; do not alter the normal `main` checkout before the project workflow permits merging.
- Never inspect a real database, existing upload, persistent storage content, browser UI, or screenshot. Use source and newly created fictional fixtures only.
- Never persist or log the image name/bytes, metadata/workflow JSON, positive/negative, or option content.
- Accept one PNG up to 20 MiB; cap decoded `prompt` metadata at 5 MiB and JSON depth at 128.
- Mask only configured positive, negative, seed, checkpoint, sampler, scheduler, and output-prefix inputs during structural comparison.
- Preserve imported prompt strings exactly after JSON decoding. Normalization is comparison-only.
- Auto-select only unique full matches. Never round LoRA strength; only `0, 0.1, …, 1` is restorable.
- PHP/Composer/Node/npm run only through Docker. Frontend changes require Vite build plus `sh scripts/check.sh`.
- The main agent performs every category in `docs/review/コードレビュー基準.md`; do not use review subagents.

## Review Focus

- Truncated, duplicate, compressed, or oversized PNG text chunks must fail without persistence (Task 1).
- Mapped-value-only graph changes must match; fixed values, connections, nodes, zero matches, and two matches must fail (Task 2).
- Parenthesized/escaped commas, duplicate content, overlaps, and multiple matches in a `single` group must not auto-select (Task 3).
- Hidden/wrong-family/unknown LoRAs and strengths `0.85`, above `1`, or below `0` must remain review candidates (Task 3).
- Registration changes between analysis and apply must not cause partial or stale selection restoration (Task 6).

---

### Task 1: Safe PNG metadata reader

**Files:**
- Create: `app/Application/PromptPreparation/Ports/ComfyUiImageMetadataReader.php`
- Create: `app/Application/PromptPreparation/DTO/ComfyUiImageMetadata.php`
- Create: `app/Application/PromptPreparation/Exceptions/InvalidComfyUiImage.php`
- Create: `app/Infrastructure/PromptPreparation/Images/PngComfyUiImageMetadataReader.php`
- Create: `tests/Unit/Infrastructure/PromptPreparation/PngComfyUiImageMetadataReaderTest.php`
- Modify: `app/Providers/AppServiceProvider.php`

**Interfaces:**
- Produces `ComfyUiImageMetadataReader::read(string $temporaryPath): ComfyUiImageMetadata`.
- `ComfyUiImageMetadata` exposes associative `public array $prompt`; invalid inputs throw user-safe `InvalidComfyUiImage`.

- [ ] Write failing tests named for: `tEXt`, uncompressed `iTXt`, `zTXt`/compressed `iTXt`, missing prompt, duplicate prompt, bad length/CRC, decoded payload above 5 MiB, and JSON depth above 128. Build fictional PNG bytes in test helpers.
- [ ] Run `docker compose run --rm -T app php artisan test tests/Unit/Infrastructure/PromptPreparation/PngComfyUiImageMetadataReaderTest.php`; expect missing classes.
- [ ] Implement a sequential `fopen` parser that verifies signature, length, CRC, decompression limits, one `prompt` keyword, `IEND`, and an associative JSON root via `json_decode(..., true, 128, JSON_THROW_ON_ERROR)`.
- [ ] Bind the port in `AppServiceProvider`, rerun the focused test, and expect all PASS.
- [ ] Commit: `git commit -m "feat: read ComfyUI prompt metadata from PNG"` with only Task 1 files.

### Task 2: Registered workflow structure matcher

**Files:**
- Create: `app/Application/PromptPreparation/Services/ComfyUiWorkflowStructureMatcher.php`
- Create: `app/Application/PromptPreparation/DTO/MatchedComfyUiWorkflow.php`
- Create: `app/Application/PromptPreparation/Exceptions/UnsupportedComfyUiWorkflow.php`
- Create: `tests/Unit/Application/PromptPreparation/ComfyUiWorkflowStructureMatcherTest.php`

**Interfaces:**
- `match(array $imageWorkflow, array $registeredWorkflows): MatchedComfyUiWorkflow` consumes records containing `modelFamilyId`, `workflow`, and existing `mappings`.
- Result exposes `modelFamilyId`, `positivePrompt`, and `negativePrompt`; zero/multiple matches or invalid mapped values throw `UnsupportedComfyUiWorkflow`.

- [ ] Write failing tests for exact graphs, every mutable role, object-key order, fixed scalar/connection/node changes, zero/two matches, missing/non-string prompt positions, and inconsistent values if a role has multiple mappings.
- [ ] Run `docker compose run --rm -T app php artisan test tests/Unit/Application/PromptPreparation/ComfyUiWorkflowStructureMatcherTest.php`; expect failure.
- [ ] Implement recursive object-key sorting while preserving list order. Replace each configured mutable input value with one sentinel; do not remove the input or node. Extract original prompts only after one match.
- [ ] Rerun the focused test; expect all PASS.
- [ ] Commit: `git commit -m "feat: match imported ComfyUI workflow structure"`.

### Task 3: Conservative registered-prompt matcher

**Files:**
- Create: `app/Application/PromptPreparation/Services/ImportedPromptMatcher.php`
- Create: `app/Application/PromptPreparation/DTO/ImportedPromptMatchResult.php`
- Create: `app/Application/PromptPreparation/DTO/ImportedSelectionSnapshot.php`
- Create: `app/Application/PromptPreparation/DTO/ImportedPromptReviewItem.php`
- Create: `tests/Unit/Application/PromptPreparation/ImportedPromptMatcherTest.php`

**Interfaces:**
- `match(string $positive, string $negative, int $modelFamilyId, GetDefaultPromptsResult $defaults, GetLoraPromptOptionsResult $characters, GetClothingLoraOptionsResult $clothing, GetPromptOptionsResult $options): ImportedPromptMatchResult`.
- Snapshot contains defaults, nullable character IDs/strength, clothing snapshots, and option IDs. Review kinds: `partial`, `conflict`, `unregistered`, `hidden_lora`, `unsupported_lora_strength`, `unknown_lora`.

- [ ] Write failing tests for exact defaults/options, two-of-three partials, whitespace/newline/trailing commas, escapes/parentheses, duplicate/overlapping content, and multiple matches within one `single` group.
- [ ] Add failing tests for visible character and clothing LoRAs, ownership of triggers/outfits, hidden/wrong-family/unknown/ambiguous LoRAs, and strengths `0.8`, `0.85`, `1.1`, `-0.1`.
- [ ] Add failing tests proving `blue eyes, long hair,` becomes two unmatched candidates while `(smile, open mouth:1.2),` stays one. Model optional linked-negative content internally; empty when F16 data is unavailable, otherwise require both sides.
- [ ] Run `docker compose run --rm -T app php artisan test tests/Unit/Application/PromptPreparation/ImportedPromptMatcherTest.php`; expect failure.
- [ ] Implement bracket/escape-aware tokenization equivalent to `resources/js/prompt-text.js`, whole-item matching, ownership checks, and conservative conflict handling. Never modify source prompt strings.
- [ ] Rerun the focused test; expect all PASS.
- [ ] Commit: `git commit -m "feat: match imported prompts to registered options"`.

### Task 4: Analysis query and HTTP endpoint

**Files:**
- Create: `app/Application/PromptPreparation/Queries/AnalyzeComfyUiImage/AnalyzeComfyUiImageHandler.php`
- Create: `app/Application/PromptPreparation/Queries/AnalyzeComfyUiImage/AnalyzeComfyUiImageResult.php`
- Create: `app/Http/Requests/PromptPreparation/AnalyzeComfyUiImageRequest.php`
- Create: `app/Http/Controllers/PromptPreparation/Queries/AnalyzeComfyUiImageController.php`
- Create: `tests/Unit/Application/PromptPreparation/AnalyzeComfyUiImageHandlerTest.php`
- Create: `tests/Feature/Http/PromptPreparation/ComfyUiImageAnalysisApiTest.php`
- Modify: `routes/web.php`

**Interfaces:**
- Handler: `handle(string $temporaryPath): AnalyzeComfyUiImageResult` using the reader, family/query ports, workflow store, structure matcher, and prompt matcher.
- HTTP: `POST /comfyui-images/analyze`, multipart `image`, route `comfyui-images.analyze`.
- JSON: `{modelFamily, positivePrompt, negativePrompt, selectionSnapshot, review, optionGroups}`; `optionGroups` contains only non-system-managed `{id,name}` save targets, and workflow content never leaves the server.

- [ ] Write failing handler tests proving current families are enumerated, unconfigured families skipped, matched-family defaults loaded, hidden LoRAs requested with `CharacterLoraVisibility::All`, and all registrations passed to the matcher.
- [ ] Run the focused handler test; expect failure.
- [ ] Implement the handler/result, including non-system-managed option save targets.
- [ ] Rerun the focused handler test; expect PASS.
- [ ] Write failing feature tests using fictional `UploadedFile::fake()->createWithContent()` PNGs: success; missing/non-PNG/20 MiB overflow; malformed PNG; unsupported/ambiguous graph; unchanged `Storage::disk('local')->allFiles()` on success/failure; no fictional prompt/workflow marker in captured logs.
- [ ] Implement request rules `required|file|mimes:png|max:20480`, controller, route, and HTTP 422 mapping with a single safe Japanese `message`.
- [ ] Run `docker compose run --rm -T app php artisan test tests/Unit/Application/PromptPreparation/AnalyzeComfyUiImageHandlerTest.php tests/Feature/Http/PromptPreparation/ComfyUiImageAnalysisApiTest.php`; expect all PASS.
- [ ] Commit: `git commit -m "feat: analyze ComfyUI image prompts"`.

### Task 5: Restoration preview modal

**Files:**
- Create: `resources/js/comfyui-image-restoration.js`
- Create: `tests/JavaScript/comfyui-image-restoration.test.js`
- Modify: `app/Http/Controllers/PromptPreparation/PromptPreparationPageController.php`
- Modify: `resources/views/prompt-preparation.blade.php`
- Modify: `resources/css/app.css`

**Interfaces:**
- `initializeComfyUiImageRestoration({page, documentObject, fetcher, csrfToken, confirmAction, captureCurrentState, applyRestoration, reloadPromptOptions})`.
- Calls `applyRestoration(result)` only after explicit apply and required overwrite confirmation.

- [ ] Write failing JSDOM tests for open/close/focus return, upload/loading, safe server errors, both prompt previews, categorized counts/details, retry, and no mutation or object URL creation before apply.
- [ ] Run `docker compose run --rm -T node npm run test:js`; confirm the new tests fail.
- [ ] Pass `route('comfyui-images.analyze')` to the page and add a labelled `<dialog>` with PNG input, status, readonly prompt previews, categorized lists, unmatched checkboxes, option name/group fields, save/cancel/apply controls.
- [ ] Implement analysis-only behavior with `FormData`, response-shape validation, pending-state guards, and release of file references on close.
- [ ] Rerun all JavaScript tests; expect PASS.
- [ ] Commit: `git commit -m "feat: preview ComfyUI image restoration"`.

### Task 6: Atomic application to existing page state

**Files:**
- Modify: `resources/js/app.js`
- Modify: `resources/js/comfyui-image-restoration.js`
- Modify: `resources/js/lora-options-page.js`
- Modify: `resources/js/clothing-lora-options-page.js`
- Modify: `resources/js/prompt-options-page.js`
- Modify: `tests/JavaScript/prompt-preparation-page.test.js`
- Modify: `tests/JavaScript/lora-options-page.test.js`
- Modify: `tests/JavaScript/clothing-lora-options-page.test.js`
- Modify: `tests/JavaScript/prompt-options-page.test.js`

**Interfaces:**
- Each selection controller adds non-mutating `hasSelectionIds(snapshot): boolean`.
- `app.js` adds `applyImportedPrompt(restoration)` and an explicit imported output family ID; normal confirmed regeneration/reset clears that override.

- [ ] Write failing controller tests for present/deleted/wrong-owner IDs, unsupported strength, and single-group conflicts; assert no mutation.
- [ ] Implement `hasSelectionIds` methods and run all JavaScript tests to PASS.
- [ ] Write failing page tests for exact output preservation, family without character LoRA, all selection types, overwrite confirmation/cancel, stale registration, unmatched warning, and confirmation before normal regeneration discards imported edits.
- [ ] Implement two-phase apply: validate current IDs first, then restore valid selections and exact outputs together. Stale IDs become review items; never rebuild imported text. Set variant/family metadata from imported output.
- [ ] Run `docker compose run --rm -T node npm run test:js`; expect all PASS.
- [ ] Commit: `git commit -m "feat: restore imported prompt selections"`.

### Task 7: Save selected unmatched tags

**Files:**
- Modify: `resources/js/comfyui-image-restoration.js`
- Modify: `tests/JavaScript/comfyui-image-restoration.test.js`
- Modify: `resources/js/app.js`
- Modify: `tests/JavaScript/prompt-preparation-page.test.js`

**Interfaces:**
- Uses existing `POST /prompt-options` with `{groupId,name,content}`; after success reloads options and selects returned `id` without changing output text.

- [ ] Write failing tests for one/two selected tags in source order, protected parenthesized candidate, missing selection/name/group, duplicate 422, deleted group, network failure, successful reload/selection, and unchanged prompts.
- [ ] Run all JavaScript tests and confirm the new cases fail.
- [ ] Implement pending guards and limit destinations to the analysis response's non-system-managed groups; join chosen candidates with `, ` and one trailing comma and let the existing API format authoritatively. On success remove only saved candidates, reload, and select the returned ID.
- [ ] Rerun all JavaScript tests; expect PASS.
- [ ] Commit: `git commit -m "feat: save unmatched imported prompt tags"`.

### Task 8: Documentation, verification, main-agent review, push, and merge

**Files:**
- Modify: `docs/design/画面設計.md`
- Modify only if implementation decisions changed: F24 design and glossary.
- Review: every changed source/test file.

**Interfaces:** No new runtime interface; this task proves the delivered behavior and integration workflow.

- [ ] Document final modal placement, error states, overwrite confirmation, unmatched warning, and save flow; do not add implementation status to design docs.
- [ ] Run focused PHP tests for Tasks 1–4 and `docker compose run --rm -T node npm run test:js`; expect all PASS.
- [ ] Run `docker compose run --rm -T node npm run build`; expect Vite exit `0` and updated distributable assets per repository policy.
- [ ] Run `sh scripts/check.sh` and `docker compose run --rm -T app composer test`; expect all static checks and the full suite to PASS without weaker rules or broad ignores.
- [ ] Re-read `docs/review/コードレビュー基準.md`. The main agent records findings for architecture, file/class names, method names, test names/assertions, end-to-end purpose and conditions, verification, comments/PHPDoc, and accidental sensitive-content logging. Fix findings and rerun verification.
- [ ] Commit remaining documentation/review fixes with `git commit -m "docs: align ComfyUI image restoration guidance"`; skip only when no diff exists.
- [ ] Run `git push -u origin design/comfyui-image-prompt-import`. If SSH authentication still fails, stop without merging and retain branch/worktree.
- [ ] Only after successful push, verify the normal checkout is clean on `main`, run `git merge --ff-only design/comfyui-image-prompt-import`, and do not push `main` without explicit user request.
- [ ] From merged `main`, rerun `sh scripts/check.sh`, `docker compose run --rm -T app composer test`, and `docker compose run --rm -T node npm run build`; expect all PASS.
- [ ] After successful merged verification, remove the isolated worktree and local feature branch; keep the remote branch unless explicitly asked to delete it.
