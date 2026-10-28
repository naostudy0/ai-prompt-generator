# Favorite LoRA Batch Queue Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Allow a named browser-local group of saved favorites to receive the currently selected character LoRA, trigger, and strength, then queue the prepared favorites to ComfyUI in one confirmed batch.

**Architecture:** Keep favorite groups and the current checkbox set in validated `localStorage` snapshots containing favorite keys only. Build each queue item in the browser with pure prompt-transformation functions, while retaining the existing Laravel batch endpoint for authoritative LoRA, model-family, generation-setting, workflow, and partial-failure checks.

**Tech Stack:** PHP 8.5, Laravel 13, Blade, vanilla JavaScript ES modules, JSDOM/node:test, PHPUnit, CSS, Docker Compose, Vite.

**Spec:** `docs/superpowers/specs/2026-10-28-favorite-lora-batch-queue-design.md`

## Global Constraints

- Read `docs/design/コーディング規約.md` before changing application code and `docs/review/コードレビュー基準.md` before final review.
- Work only in `.worktrees/feature-favorite-lora-batch` on `feature/favorite-lora-batch`; do not alter the normal `main` checkout.
- Do not browse the application, inspect real database contents, or inspect uploaded/persistent storage data; use source, Fake objects, fixtures, factories, and newly created fictional test data only.
- Do not add a scenario-aware favorite format, database migration, server-side group API, dependency, or authentication concept.
- Store only versioned group names and `favorite:{id}` keys in `localStorage`; never store prompt text, image data, or LoRA names there.
- Keep the existing `POST /comfyui/prompts/batch` limit of 1–100 items and do not auto-split larger batches.
- Treat saved favorite positive/negative text as canonical; never update a favorite while preparing or sending a batch.
- If a saved character LoRA or saved trigger cannot be located uniquely, exclude that favorite; do not guess or auto-retry an unknown ComfyUI result.
- Do not push this branch or `main`, per the user's explicit instruction.

## Review Focus

- Corrupt or unavailable `localStorage` must fall back to an in-memory unchecked state without losing the ability to send; Task 2 pins this with parser and storage-failure tests.
- A favorite with a saved trigger whose exact section was manually changed or duplicated must be excluded rather than silently appending a second trigger; Task 3 pins strict-location tests.
- A favorite saved without a character LoRA must receive the selected LoRA after its saved default positive section and before remaining sections; Task 3 pins insertion-order tests.
- Deleting favorites after a group was saved must remove them from the active selection and report the unavailable count; Tasks 2 and 4 pin reconciliation/UI tests.
- A prepared set of 101 favorites, a cancelled confirmation, or an unknown batch response must issue no repeat request and must preserve item-level status; Task 4 pins boundary and failure tests.

---

### Task 1: Record the project feature contract

**Files:**
- Create: `docs/design/features/F26-お気に入りグループへの人物LoRA適用と一括送信.md`
- Modify: `docs/spec/機能概要.md`
- Modify: `docs/spec/用語集.md`
- Reference: `docs/design/features/F04-お気に入りプロンプト.md`
- Reference: `docs/design/features/F05-LoRAの登録と選択.md`
- Reference: `docs/design/features/F13-ComfyUIへのキュー送信.md`
- Reference: `docs/design/features/F21-人物LoRA差し替えグループ.md`

**Interfaces:**
- Consumes: approved design at `docs/superpowers/specs/2026-10-28-favorite-lora-batch-queue-design.md`.
- Produces: F26 requirements, behaviors, acceptance criteria, and glossary terms used by every later task.

- [ ] **Step 1: Add F26 to the feature catalog**

Add an F26 row whose purpose is applying one currently selected character LoRA to a checked, named group of existing favorites and queueing the prepared prompts to ComfyUI.

- [ ] **Step 2: Define the project terms**

Add Japanese/English entries for `FavoritePromptGroup`, `favoritePromptCandidateKey`, and `favoriteLoraBatchQueue`, explicitly distinguishing a browser-local group from the persisted `FavoritePrompt` aggregate.

- [ ] **Step 3: Write the detailed F26 design**

Translate the approved design into the project feature template: purpose, requirements, scope, checkbox/group behavior, strict replace-or-insert rules, 100-item limit, UI, API reuse, failures, implementation boundary, and acceptance criteria. Link F04, F05, F13, and F21.

- [ ] **Step 4: Verify document links and terminology**

Run: `git diff --check && test -f docs/design/features/F26-お気に入りグループへの人物LoRA適用と一括送信.md && rg -n "F26|FavoritePromptGroup|favoriteLoraBatchQueue" docs/spec docs/design/features/F26-お気に入りグループへの人物LoRA適用と一括送信.md`

Expected: exit 0; every new term and feature link appears in the intended documents.

- [ ] **Step 5: Commit**

```bash
git add docs/spec/機能概要.md docs/spec/用語集.md docs/design/features/F26-お気に入りグループへの人物LoRA適用と一括送信.md
git commit -m "docs: specify favorite LoRA batch queue"
```

### Task 2: Implement favorite group snapshots

**Files:**
- Create: `resources/js/favorite-prompt-groups.js`
- Create: `tests/JavaScript/favorite-prompt-groups.test.js`

**Interfaces:**
- Consumes: favorite IDs from the existing favorite list API.
- Produces:
  - `createFavoritePromptKey(id: number): string`
  - `parseFavoritePromptGroups(serialized: string|null): Array<{name:string,favoriteKeys:string[]}>`
  - `serializeFavoritePromptGroups(groups): string`
  - `parseFavoritePromptChecks(serialized: string|null): Set<string>`
  - `serializeFavoritePromptChecks(keys: Iterable<string>): string`
  - `prepareFavoritePromptGroup(name: string, favoriteKeys: Iterable<string>): {name:string,favoriteKeys:string[]}`
  - `findFavoritePromptGroupIndex(groups, name: string): number`
  - `replaceFavoritePromptGroup(groups, group, index): Array<Group>`
  - `removeFavoritePromptGroup(groups, index): Array<Group>`
  - `resolveFavoritePromptGroup(favoriteKeys, availableKeys): {selectedKeys:Set<string>,unavailableCount:number}`

- [ ] **Step 1: Write failing validation and normalization tests**

Test version `1`, the exact `favorite:12` key shape, 1–100 character trimmed names, case-insensitive duplicate lookup, duplicate-key removal, empty groups, malformed JSON, wrong versions, non-string keys, and preservation of group/key order.

- [ ] **Step 2: Run the focused test and confirm failure**

Run: `docker compose run --rm -T node node --test tests/JavaScript/favorite-prompt-groups.test.js`

Expected: FAIL because `resources/js/favorite-prompt-groups.js` does not exist.

- [ ] **Step 3: Implement the versioned pure group/check functions**

Reject keys that do not match `/^favorite:[1-9]\d*$/`; parsers return an empty collection on invalid input. Serializers emit only `{version:1,...}` structures and never attach favorite display data.

- [ ] **Step 4: Run focused tests**

Run: `docker compose run --rm -T node node --test tests/JavaScript/favorite-prompt-groups.test.js`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/js/favorite-prompt-groups.js tests/JavaScript/favorite-prompt-groups.test.js
git commit -m "feat: add favorite prompt groups"
```

### Task 3: Prepare strict character LoRA applications

**Files:**
- Create: `resources/js/favorite-character-lora-application.js`
- Create: `tests/JavaScript/favorite-character-lora-application.test.js`
- Modify: `resources/js/character-lora-variant.js`
- Modify: `tests/JavaScript/character-lora-variant.test.js`

**Interfaces:**
- Consumes:
  - favorite detail `{positivePrompt,negativePrompt,selectionSnapshot}` from `GET /favorite-prompts/{id}`;
  - current LoRA option snapshot `{loras,triggers,...}` from `loraOptionsController.getVariantCandidates()`;
  - current selection `{loraId,strength,triggerId}` and current rendered sections `{lora,trigger}`.
- Produces:
  - `createStrictCharacterLoraVariant(positive: string, original: CharacterLoraSourceMetadata, replacementTag: string, replacementTrigger: string): string`, which throws Japanese user-safe errors for missing/ambiguous saved ranges;
  - `prepareFavoriteCharacterLoraQueueItem({favorite,key,options,selection,selectedSections}): {candidateKey,modelFamilyId,loraId,positive,negative}`.

- [ ] **Step 1: Write failing strict replacement tests**

Cover exact LoRA+trigger replacement, selected trigger removal, a saved trigger edited out, duplicate saved triggers, deleted saved LoRA/trigger IDs, and preservation of negative/other positive sections. Assert unsafe cases throw instead of falling back to tag-adjacent append.

- [ ] **Step 2: Write failing no-LoRA insertion tests**

Using fictional defaults, clothing LoRA, outfit, and option sections, assert that a favorite whose snapshot has `loraId:null` receives the selected LoRA and trigger after `selectionSnapshot.defaultSections.positive`, retains every remaining section byte-for-byte, and supports an empty default section.

- [ ] **Step 3: Run focused tests and confirm failure**

Run: `docker compose run --rm -T node node --test tests/JavaScript/character-lora-variant.test.js tests/JavaScript/favorite-character-lora-application.test.js`

Expected: FAIL because the strict functions/module are absent.

- [ ] **Step 4: Implement strict replacement in `character-lora-variant.js`**

Reuse `locateCharacterLoraSourceMetadata`, but require both the saved LoRA range and any non-empty saved trigger range to match exactly once. Accept replacement strings directly so the caller controls the selected strength.

- [ ] **Step 5: Implement `prepareFavoriteCharacterLoraQueueItem`**

Resolve saved IDs against current options, create saved metadata from `selectionSnapshot.defaultSections.positive`, saved LoRA strength, and saved trigger content, then strictly replace. If `loraId` is null, use `createCharacterLoraSourceMetadata(defaultPositive, '', '')` plus the structured insertion path. Return the favorite key and unchanged negative prompt; do not mutate the favorite or selection.

- [ ] **Step 6: Run focused tests**

Run: `docker compose run --rm -T node node --test tests/JavaScript/character-lora-variant.test.js tests/JavaScript/favorite-character-lora-application.test.js`

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add resources/js/character-lora-variant.js resources/js/favorite-character-lora-application.js tests/JavaScript/character-lora-variant.test.js tests/JavaScript/favorite-character-lora-application.test.js
git commit -m "feat: prepare favorite character LoRA prompts"
```

### Task 4: Add favorite selection, groups, and batch-send UI

**Files:**
- Modify: `resources/views/prompt-preparation.blade.php`
- Modify: `resources/js/favorite-prompts-page.js`
- Modify: `tests/JavaScript/favorite-prompts-page.test.js`
- Modify: `resources/css/app.css`

**Interfaces:**
- Consumes Task 2 group/check functions and Task 3 `prepareFavoriteCharacterLoraQueueItem`.
- Adds initializer dependencies:
  - `getCharacterLoraApplication(): {options,selection,selectedSections}|null`
  - `confirmAction(message: string): boolean`
  - existing `page.dataset.comfyUiPromptBatchUrl`.
- Produces existing favorite behaviors plus checked keys, browser-local named groups, and one confirmed batch request of at most 100 prepared items.

- [ ] **Step 1: Extend the JSDOM fixture and write failing checkbox/group tests**

Add exact `data-favorite-group-*`, `data-favorite-select-all`, `data-favorite-clear-all`, `data-favorite-selected-count`, and `data-favorite-batch-send` controls. Test unchecked first load, check persistence, all/none, group save/load/delete, case-insensitive overwrite confirmation, deleted favorite reconciliation with unavailable count, and corrupt/throwing storage fallback.

- [ ] **Step 2: Write failing preparation and batch tests**

Test LoRA-not-selected blocking, detail fetch per selected favorite, local preparation exclusions, confirmation text with selected LoRA/trigger/strength and prepared/excluded counts, cancellation, exact JSON request, item-level accepted/failed/result-unknown labels, processing-time control locking, 0 prepared items, and 101 prepared items issuing no batch POST.

- [ ] **Step 3: Run the focused UI test and confirm failure**

Run: `docker compose run --rm -T node node --test tests/JavaScript/favorite-prompts-page.test.js`

Expected: FAIL because the new controls and initializer dependencies are not implemented.

- [ ] **Step 4: Add accessible Blade controls and card checkboxes**

Place the group fieldset, all/none controls, count, selected-LoRA summary, status, and batch-send button before the favorite grid. Keep edit/load/delete actions independent from selection. Use labels and live status text; do not add inline scripts.

- [ ] **Step 5: Implement storage-safe group and checkbox behavior**

Use distinct keys `favorite-prompt-checks-v1` and `favorite-prompt-groups-v1`. Roll group mutations back in memory when writes fail; retain current in-memory checks when check persistence fails. On reload, intersect active checks with API favorites and never auto-select newly added favorites.

- [ ] **Step 6: Implement prepare-confirm-send-result flow**

Freeze one `getCharacterLoraApplication()` snapshot, fetch selected details in list order, prepare all items, reject counts above 100, then confirm and POST once. Disable close/backdrop, group, checkbox, edit, and delete controls while processing. On top-level fetch failure, warn that some items may already be accepted and do not retry automatically.

- [ ] **Step 7: Add focused styles**

Add layout rules for the group fieldset, checkbox/card selected state, count/summary, and per-card batch result without changing the established favorite grid breakpoint or color system.

- [ ] **Step 8: Run focused UI tests**

Run: `docker compose run --rm -T node node --test tests/JavaScript/favorite-prompts-page.test.js`

Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add resources/views/prompt-preparation.blade.php resources/js/favorite-prompts-page.js resources/css/app.css tests/JavaScript/favorite-prompts-page.test.js
git commit -m "feat: queue favorite groups with selected LoRA"
```

### Task 5: Wire the current LoRA snapshot and protect existing HTTP behavior

**Files:**
- Modify: `resources/js/app.js`
- Modify: `resources/js/lora-options-page.js`
- Modify: `tests/JavaScript/lora-options-page.test.js`
- Modify: `tests/JavaScript/prompt-preparation-page.test.js`
- Modify: `tests/Feature/Http/PromptPreparation/ComfyUiApiTest.php`

**Interfaces:**
- Consumes the Task 4 initializer dependency.
- Produces `loraOptionsController.getCharacterLoraApplication(): {options,selection,selectedSections}|null`, returning copied data after options load and `null` without a selected visible character LoRA.
- Keeps `POST /comfyui/prompts/batch` request/response shape unchanged.

- [ ] **Step 1: Write failing controller snapshot tests**

Assert no selection returns `null`; a selected fictional LoRA returns its `modelFamilyId`, ID, current 0.1-step strength, selected trigger ID/content, rendered tag, and a copied option snapshot that callers cannot mutate back into controller state.

- [ ] **Step 2: Run the focused controller test and confirm failure**

Run: `docker compose run --rm -T node node --test tests/JavaScript/lora-options-page.test.js`

Expected: FAIL because `getCharacterLoraApplication` is absent.

- [ ] **Step 3: Implement and wire the snapshot**

Add the controller method, pass it to `initializeFavoritePromptsPage`, and pass `view.confirm` as `confirmAction`. Keep the existing save/restore initializer arguments unchanged.

- [ ] **Step 4: Add page integration assertions**

Update the page test fixture to include the new favorite group controls and batch URL, then assert the batch button is disabled until both favorite loading and a character LoRA selection are available.

- [ ] **Step 5: Pin the unchanged server boundary**

Extend `ComfyUiApiTest.php` with fictional `favorite:12` candidate keys, an accepted plus failed partial result, distinct-key validation, and the 101-item 422 boundary. Assert no favorite/group persistence endpoint or payload field is required.

- [ ] **Step 6: Run focused integration tests**

Run: `docker compose run --rm -T node node --test tests/JavaScript/lora-options-page.test.js tests/JavaScript/prompt-preparation-page.test.js && docker compose run --rm -T app php artisan test tests/Feature/Http/PromptPreparation/ComfyUiApiTest.php`

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add resources/js/app.js resources/js/lora-options-page.js tests/JavaScript/lora-options-page.test.js tests/JavaScript/prompt-preparation-page.test.js tests/Feature/Http/PromptPreparation/ComfyUiApiTest.php
git commit -m "test: cover favorite LoRA batch integration"
```

### Task 6: Complete project review and verification

**Files:**
- Review all files changed in Tasks 1–5.
- Modify only files needed to correct review or verification findings.

**Interfaces:**
- Consumes the complete F26 implementation.
- Produces a reviewed, committed local branch ready for fast-forward merge without any push.

- [ ] **Step 1: Run all JavaScript tests**

Run: `docker compose run --rm -T node npm run test:js`

Expected: all tests pass with 0 failures.

- [ ] **Step 2: Build the frontend assets**

Run: `docker compose run --rm -T node npm run build`

Expected: Vite exits 0 and writes an updated `public/build/manifest.json` plus hashed assets. Include tracked build artifacts in the final commit only if this repository already tracks them.

- [ ] **Step 3: Run mandatory static checks**

Run: `sh scripts/check.sh`

Expected: Pint, PHPStan/Larastan, ESLint, and Prettier all pass.

- [ ] **Step 4: Run the mandatory full test suite**

Run: `docker compose run --rm -T app composer test`

Expected: PHPUnit and JavaScript tests pass with 0 failures.

- [ ] **Step 5: Perform the main-agent review against every category**

Read `docs/review/コードレビュー基準.md` completely, then review purpose/spec traceability, correctness, data integrity, security/privacy, architecture/dependencies, errors/external side effects, maintainability, tests, operations, and documentation. Record findings in the completion report; fix every blocking finding before continuing.

- [ ] **Step 6: Inspect the final diff without real data or browser use**

Run: `git status --short && git diff --check && git diff main...HEAD --stat && git diff main...HEAD`

Expected: only F26-related source, tests, documentation, and expected build artifacts; no secrets, real data, uploaded files, or unrelated user changes.

- [ ] **Step 7: Commit verification fixes or remaining artifacts**

```bash
git add <only reviewed F26 files>
git diff --cached --check
git commit -m "chore: verify favorite LoRA batch queue"
```

Skip this commit when the worktree is already clean.

- [ ] **Step 8: Confirm local branch state**

Run: `git status --short --branch && git log --oneline --decorate -6`

Expected: clean `feature/favorite-lora-batch`; do not push. Report that browser visual/interaction verification remains for the user because browser operation is prohibited.
