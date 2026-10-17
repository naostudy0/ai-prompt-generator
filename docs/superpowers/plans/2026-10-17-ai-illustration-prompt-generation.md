# AI Illustration Prompt Generation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a modal workflow that turns one Japanese illustration description into an English positive/negative prompt through a configured external LLM API, then explicitly applies it to the existing prompt output while preserving character-LoRA replacement support.

**Architecture:** A `GenerateIllustrationPrompt` write handler depends on Application-owned instruction and generation ports. Infrastructure loads style-specific prompt files and adapts an Ollama Chat API-compatible endpoint; the existing page calls a same-origin JSON endpoint and passes the validated preview through one output-application callback that also records character-section metadata.

**Tech Stack:** PHP 8.5, Laravel 13 HTTP client/FormRequest/Blade, JavaScript ES modules, JSDOM/Node test runner, CSS/Vite, Docker Compose.

**Spec:** `docs/design/features/F20-AIプロンプト生成.md`

## Global Constraints

- Do not read the real database, uploaded files, persistent storage contents, application logs, or real user data; use only source, configuration structure, and newly created fake test data.
- Do not use browser automation, developer tools, screenshots, or browser-based verification.
- Do not install, start, configure, or document an LLM runtime; the feature calls the server-configured external API.
- Keep `ILLUSTRATION_LLM_BASE_URL`, model, connect timeout, and response timeout in server configuration; never accept an arbitrary upstream URL from the request.
- Store the two long system prompts in `resources/prompts/illustration/`, not in PHP literals.
- Accept `description` after trimming only when it contains 1–2,000 characters; accept only `danbooru_tags` and `natural_language`, defaulting to `danbooru_tags`.
- Never log the full description, system prompt, generated positive/negative prompt, or raw upstream response.
- Do not add DB tables, repositories, transactions, queues, scenario parsing, multiple-scene generation, or generation-history persistence.
- Preserve the current output until the user explicitly applies a valid preview; negative may be empty, positive may not.
- Compose only the currently selected character LoRA and trigger into the AI positive output; do not automatically compose defaults, clothing LoRAs, outfits, or option prompts.
- Run PHP, Composer, Node, and npm commands only through the repository's Docker services.
- Because Blade, JavaScript, and CSS change, completion requires `docker compose run --rm -T node npm run build` and `sh scripts/check.sh` in addition to related tests.

## Review Focus

- A description containing only multibyte whitespace must fail as empty after normalization; pin this in the HTTP validation tests in Task 3.
- A late response arriving after modal close/abort must not restore a preview or alter existing output; pin this in the modal tests in Task 4.
- A generated positive that already contains text equal to a selected trigger must still keep the separately tracked character section unambiguous; pin the exact section ranges in Task 5.
- Upstream HTTP 200 with valid outer chat JSON but invalid `message.content` must map to 502 without leaking the raw body; pin this in Tasks 2 and 3.
- Metrics with zero or absent `eval_duration` must omit tokens/sec instead of dividing by zero or inventing a value; pin this in Task 2.

---

## File Structure

### Server-side files

- Create `app/Application/PromptPreparation/Writes/GenerateIllustrationPrompt/PromptStyle.php` for the two accepted style values and default-selection helper.
- Create `GenerateIllustrationPromptInput.php`, `GenerateIllustrationPromptResult.php`, and `GenerateIllustrationPromptHandler.php` in the same directory for immutable use-case input/result and orchestration.
- Create `app/Application/PromptPreparation/Ports/IllustrationPromptInstructions.php` and `IllustrationPromptGenerator.php` for style instruction loading and external generation.
- Create `app/Application/PromptPreparation/DTO/GeneratedIllustrationPrompt.php` for the provider-neutral generated pair, model, elapsed time, and optional metrics.
- Create `app/Application/PromptPreparation/Exceptions/IllustrationPromptServiceUnavailable.php` and `InvalidIllustrationPromptResponse.php` for controller-safe failure classification.
- Create `app/Infrastructure/PromptPreparation/External/IllustrationLlm/FileIllustrationPromptInstructions.php` and `OllamaIllustrationPromptGenerator.php` for prompt-file loading and HTTP adaptation.
- Create `resources/prompts/illustration/danbooru_tags.txt` and `natural_language.txt` for the approved generation rules.
- Create `app/Http/Requests/PromptPreparation/GenerateIllustrationPromptRequest.php` and `app/Http/Controllers/PromptPreparation/Writes/GenerateIllustrationPromptController.php` for validation and HTTP mapping.
- Modify `app/Providers/AppServiceProvider.php`, `config/services.php`, `routes/web.php`, and `app/Http/Controllers/PromptPreparation/PromptPreparationPageController.php` for bindings, configuration, route, and generated page URL.

### Frontend files

- Create `resources/js/illustration-prompt-generator.js` for request serialization, modal state, preview validation/editing, abort, and apply callback.
- Modify `resources/views/prompt-preparation.blade.php` for the launch button and accessible large dialog.
- Modify `resources/js/app.js` only at the initialization boundary and output-application helper needed to integrate existing state.
- Modify `resources/js/character-lora-variant.js` only if a focused helper is needed to create metadata for an arbitrary AI base section without changing existing normal-output behavior.
- Modify `resources/css/app.css` for the modal layout and responsive behavior.

### Test files

- Create `tests/Unit/Application/PromptPreparation/GenerateIllustrationPromptHandlerTest.php`.
- Create `tests/Feature/Infrastructure/PromptPreparation/OllamaIllustrationPromptGeneratorTest.php`.
- Create `tests/Feature/Http/PromptPreparation/IllustrationPromptGenerationApiTest.php`.
- Create `tests/JavaScript/illustration-prompt-generator.test.js`.
- Modify `tests/JavaScript/prompt-preparation-page.test.js`, `tests/JavaScript/character-lora-variant.test.js`, and `tests/Feature/TopPageTest.php` for page integration and output provenance.

---

### Task 1: Application contract and style-specific instructions

**Files:**
- Create: `app/Application/PromptPreparation/Writes/GenerateIllustrationPrompt/PromptStyle.php`
- Create: `app/Application/PromptPreparation/Writes/GenerateIllustrationPrompt/GenerateIllustrationPromptInput.php`
- Create: `app/Application/PromptPreparation/Writes/GenerateIllustrationPrompt/GenerateIllustrationPromptResult.php`
- Create: `app/Application/PromptPreparation/Writes/GenerateIllustrationPrompt/GenerateIllustrationPromptHandler.php`
- Create: `app/Application/PromptPreparation/DTO/GeneratedIllustrationPrompt.php`
- Create: `app/Application/PromptPreparation/Ports/IllustrationPromptInstructions.php`
- Create: `app/Application/PromptPreparation/Ports/IllustrationPromptGenerator.php`
- Create: `app/Infrastructure/PromptPreparation/External/IllustrationLlm/FileIllustrationPromptInstructions.php`
- Create: `resources/prompts/illustration/danbooru_tags.txt`
- Create: `resources/prompts/illustration/natural_language.txt`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Unit/Application/PromptPreparation/GenerateIllustrationPromptHandlerTest.php`

**Interfaces:**
- Produces: `PromptStyle: string` backed enum with `DanbooruTags = 'danbooru_tags'` and `NaturalLanguage = 'natural_language'`.
- Produces: `IllustrationPromptInstructions::for(PromptStyle $style): string`.
- Produces: `IllustrationPromptGenerator::generate(string $systemPrompt, string $description): GeneratedIllustrationPrompt`.
- Produces: `GenerateIllustrationPromptHandler::handle(GenerateIllustrationPromptInput $input): GenerateIllustrationPromptResult`.
- Produces: result properties `positivePrompt`, `negativePrompt`, `model`, `promptStyle`, and `generationTimeMs`.

- [ ] **Step 1: Write the failing Application tests**

Add tests named `test_danbooru形式の指示を選び生成結果を返す`, `test_natural_language形式の指示を選ぶ`, and `test_説明とスタイル以外を外部生成ポートへ漏らさない`. Use anonymous Fake ports to assert the exact system instruction and description received, and assert all five result properties.

- [ ] **Step 2: Run the Application test and verify it fails**

Run: `docker compose run --rm -T app php artisan test tests/Unit/Application/PromptPreparation/GenerateIllustrationPromptHandlerTest.php`

Expected: FAIL because the enum, ports, DTOs, and handler do not exist.

- [ ] **Step 3: Add the minimal Application types and handler**

Use `final readonly` input/result/DTO classes. The handler obtains the instruction by style, passes only instruction and description to `IllustrationPromptGenerator`, and copies the provider-neutral result into the use-case result with `$input->promptStyle->value`.

- [ ] **Step 4: Add the prompt files and filesystem instruction adapter**

Write both approved prompt variants in English. Each must require only a JSON object containing string keys `positive_prompt` and `negative_prompt`, preserve explicit user facts, prohibit extra people, and state its style-specific formatting rules. `FileIllustrationPromptInstructions::for()` maps the enum to the fixed resource filename, rejects unreadable/empty content with `RuntimeException`, and never accepts a caller-provided path.

- [ ] **Step 5: Bind the instruction port and rerun the test**

Bind `IllustrationPromptInstructions` to `FileIllustrationPromptInstructions` in `AppServiceProvider`.

Run: `docker compose run --rm -T app php artisan test tests/Unit/Application/PromptPreparation/GenerateIllustrationPromptHandlerTest.php`

Expected: PASS.

- [ ] **Step 6: Commit the application contract**

```bash
git add app/Application/PromptPreparation app/Infrastructure/PromptPreparation/External/IllustrationLlm/FileIllustrationPromptInstructions.php app/Providers/AppServiceProvider.php resources/prompts/illustration tests/Unit/Application/PromptPreparation/GenerateIllustrationPromptHandlerTest.php
git commit -m "feat: add illustration prompt generation contract"
```

### Task 2: Ollama-compatible HTTP adapter, validation, and safe metrics

**Files:**
- Create: `app/Application/PromptPreparation/Exceptions/IllustrationPromptServiceUnavailable.php`
- Create: `app/Application/PromptPreparation/Exceptions/InvalidIllustrationPromptResponse.php`
- Create: `app/Infrastructure/PromptPreparation/External/IllustrationLlm/OllamaIllustrationPromptGenerator.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `config/services.php`
- Test: `tests/Feature/Infrastructure/PromptPreparation/OllamaIllustrationPromptGeneratorTest.php`

**Interfaces:**
- Consumes: `IllustrationPromptGenerator::generate(string $systemPrompt, string $description): GeneratedIllustrationPrompt` from Task 1.
- Produces: configuration keys `services.illustration_llm.base_url`, `model`, `connect_timeout`, and `timeout` sourced from the four `ILLUSTRATION_LLM_*` environment variables.
- Produces: `IllustrationPromptServiceUnavailable` for connection, timeout, and non-success HTTP failures.
- Produces: `InvalidIllustrationPromptResponse` for invalid outer chat JSON or invalid structured `message.content`.

- [ ] **Step 1: Write failing adapter tests for a valid request and response**

Use `Http::fake()` and assert one `POST {base_url}/api/chat` with configured `model`, `stream: false`, system/user messages, and a JSON schema requiring string `positive_prompt` and `negative_prompt`. Fake an outer response with `message.content` containing the structured JSON plus model and duration/token fields. Assert trimmed positive, empty negative acceptance, model, nonnegative measured milliseconds, and converted metric values.

- [ ] **Step 2: Write failing adapter tests for every failure class**

Add separate tests for connection failure, timeout-shaped `ConnectionException`, HTTP 4xx/5xx, non-object outer JSON, missing `message.content`, invalid content JSON, missing keys, wrong types, whitespace-only positive, and redirect response. Assert service-unavailable versus invalid-response exception classes and ensure exception messages do not contain the raw body or configured URL.

- [ ] **Step 3: Add edge-case metrics tests from Review Focus**

Test absent metrics, `eval_duration = 0`, and positive duration/count. Assert tokens/sec is absent for missing/zero duration and correctly calculated for positive values. Use `Log::spy()` or an injected logger-compatible test seam to assert fields include request/trace ID, outcome, model, elapsed milliseconds, and available numeric metrics but exclude description, system prompt, generated prompts, and raw response.

- [ ] **Step 4: Run the adapter tests and verify they fail**

Run: `docker compose run --rm -T app php artisan test tests/Feature/Infrastructure/PromptPreparation/OllamaIllustrationPromptGeneratorTest.php`

Expected: FAIL because the adapter, exceptions, config, and binding do not exist.

- [ ] **Step 5: Implement the HTTP adapter and configuration**

Use Laravel `Http::acceptJson()->asJson()->connectTimeout()->timeout()->withoutRedirecting()`. Measure only the outbound HTTP exchange with `hrtime(true)`. Validate outer response and decoded `message.content` explicitly; do not repair fences or infer missing values. Convert nanoseconds to milliseconds and calculate tokens/sec only when `eval_count` and positive `eval_duration` are numeric. Log a safe structured summary for success and classified failure.

- [ ] **Step 6: Bind the generation port and rerun tests**

Bind `IllustrationPromptGenerator` to `OllamaIllustrationPromptGenerator` in `AppServiceProvider`.

Run: `docker compose run --rm -T app php artisan test tests/Feature/Infrastructure/PromptPreparation/OllamaIllustrationPromptGeneratorTest.php`

Expected: PASS.

- [ ] **Step 7: Commit the external adapter**

```bash
git add app/Application/PromptPreparation/Exceptions app/Infrastructure/PromptPreparation/External/IllustrationLlm/OllamaIllustrationPromptGenerator.php app/Providers/AppServiceProvider.php config/services.php tests/Feature/Infrastructure/PromptPreparation/OllamaIllustrationPromptGeneratorTest.php
git commit -m "feat: connect illustration prompts to external LLM"
```

### Task 3: Same-origin generation endpoint

**Files:**
- Create: `app/Http/Requests/PromptPreparation/GenerateIllustrationPromptRequest.php`
- Create: `app/Http/Controllers/PromptPreparation/Writes/GenerateIllustrationPromptController.php`
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/PromptPreparation/PromptPreparationPageController.php`
- Modify: `resources/views/prompt-preparation.blade.php`
- Test: `tests/Feature/Http/PromptPreparation/IllustrationPromptGenerationApiTest.php`
- Test: `tests/Feature/TopPageTest.php`

**Interfaces:**
- Consumes: `GenerateIllustrationPromptHandler::handle(GenerateIllustrationPromptInput): GenerateIllustrationPromptResult` from Task 1 and classified exceptions from Task 2.
- Produces: named route `illustration-prompts.generate` at `POST /illustration-prompts/generate`.
- Produces: page data attribute `data-illustration-prompt-generation-url` generated by Laravel routing.
- Produces: camelCase success JSON with `positivePrompt`, `negativePrompt`, `model`, `promptStyle`, `generationTimeMs`.

- [ ] **Step 1: Write failing validation and success API tests**

Cover missing, `null`, array, empty, ASCII whitespace, multibyte whitespace, 2,000-character, and 2,001-character descriptions; missing/default, both allowed, and invalid styles. Bind a Fake generator/instruction port and assert exact camelCase JSON without DB access.

- [ ] **Step 2: Write failing failure-mapping and security tests**

Assert `IllustrationPromptServiceUnavailable` returns 503, `InvalidIllustrationPromptResponse` returns 502, messages omit upstream URL/raw body, and the route uses `web` middleware plus CSRF protection. Assert an unexpected exception remains a 500 JSON response without internal details under non-debug test configuration.

- [ ] **Step 3: Run the HTTP tests and verify they fail**

Run: `docker compose run --rm -T app php artisan test tests/Feature/Http/PromptPreparation/IllustrationPromptGenerationApiTest.php`

Expected: FAIL because the request, controller, and route do not exist.

- [ ] **Step 4: Implement normalization, validation, controller mapping, and route**

`prepareForValidation()` trims only string descriptions, including Unicode whitespace via a bounded Unicode regular expression. Rules are `required|string|min:1|max:2000` and `sometimes|string|in:danbooru_tags,natural_language`; controller selects `danbooru_tags` when absent. Map only the two known external exceptions; let Laravel handle unexpected failures.

- [ ] **Step 5: Expose the route-generated URL to the page**

Add `illustrationPromptGenerationUrl` in `PromptPreparationPageController` and `data-illustration-prompt-generation-url` on the page root. Extend `TopPageTest` to assert the launch control/dialog skeleton and the generated URL, without making an external request.

- [ ] **Step 6: Run the HTTP and page tests**

Run: `docker compose run --rm -T app php artisan test tests/Feature/Http/PromptPreparation/IllustrationPromptGenerationApiTest.php tests/Feature/TopPageTest.php`

Expected: PASS.

- [ ] **Step 7: Commit the HTTP boundary**

```bash
git add app/Http/Requests/PromptPreparation/GenerateIllustrationPromptRequest.php app/Http/Controllers/PromptPreparation/Writes/GenerateIllustrationPromptController.php app/Http/Controllers/PromptPreparation/PromptPreparationPageController.php routes/web.php resources/views/prompt-preparation.blade.php tests/Feature/Http/PromptPreparation/IllustrationPromptGenerationApiTest.php tests/Feature/TopPageTest.php
git commit -m "feat: expose illustration prompt generation API"
```

### Task 4: Isolated modal generation workflow

**Files:**
- Create: `resources/js/illustration-prompt-generator.js`
- Create: `tests/JavaScript/illustration-prompt-generator.test.js`
- Modify: `resources/views/prompt-preparation.blade.php`
- Modify: `resources/css/app.css`

**Interfaces:**
- Consumes: page root `data-illustration-prompt-generation-url`, CSRF token, and Task 3 success/error JSON.
- Produces: `initializeIllustrationPromptGenerator({ page, documentObject, fetcher, csrfToken, applyPrompt }): { abort(): void }`.
- Calls: `applyPrompt({ positivePrompt: string, negativePrompt: string }): void` only after explicit apply of a valid preview.

- [ ] **Step 1: Write the dialog fixture and failing happy-path tests**

Test open/focus, default `danbooru_tags`, request URL/method/headers/body, disabled controls while pending, model/time rendering, editable preview, explicit apply, and close after apply. Assert the existing output callback is untouched when generation merely succeeds.

- [ ] **Step 2: Write failing validation, failure, retry, and abort tests**

Cover empty/over-limit input, non-JSON error response fallback, 422 field errors, 502/503 messages, retry preserving input, whitespace-only edited positive blocking apply, empty negative acceptance, double-submit prevention, close-triggered abort, and a deferred late response after abort not updating preview or calling `applyPrompt`.

- [ ] **Step 3: Run JavaScript tests and verify they fail**

Run: `docker compose run --rm -T node npm run test:js`

Expected: FAIL because the module and complete dialog markup do not exist.

- [ ] **Step 4: Implement the module and accessible dialog markup**

Keep request/response type guards separate from DOM rendering. Use one `AbortController` per request and a monotonically increasing request token so aborted/stale completions are ignored. Use the native `dialog`, labelled controls, `aria-live` status, and explicit close button. Do not use browser persistence for descriptions or previews.

- [ ] **Step 5: Add modal CSS**

Reuse existing `.option-dialog` conventions where possible. Add only feature-specific layout for a wide but viewport-bounded dialog, two labelled preview textareas, metadata/status, and responsive single-column behavior. Do not change unrelated page layout.

- [ ] **Step 6: Run JavaScript tests**

Run: `docker compose run --rm -T node npm run test:js`

Expected: PASS.

- [ ] **Step 7: Commit the isolated modal**

```bash
git add resources/js/illustration-prompt-generator.js resources/views/prompt-preparation.blade.php resources/css/app.css tests/JavaScript/illustration-prompt-generator.test.js
git commit -m "feat: add AI prompt generation modal"
```

### Task 5: Existing output, character-LoRA, and clip integration

**Files:**
- Modify: `resources/js/app.js`
- Modify: `resources/js/character-lora-variant.js`
- Modify: `tests/JavaScript/character-lora-variant.test.js`
- Modify: `tests/JavaScript/prompt-preparation-page.test.js`

**Interfaces:**
- Consumes: `initializeIllustrationPromptGenerator(..., applyPrompt)` from Task 4.
- Consumes: `loraOptionsController.getSelections()`, `getSelectedLoraId()`, and `getSelectedModelFamilyId()`.
- Produces: one internal `applyOutputPair({ positive, negative, variantMetadata, variantFamily })` path used by AI apply and compatible with existing output `input` listeners.
- Produces: AI variant-family metadata with the current model family and LoRA ID, `defaults` both false, and empty `defaultSections`, so cross-family replacement never rewrites the AI base.

- [ ] **Step 1: Write failing character metadata tests**

Given AI base `sunset classroom,` and selected LoRA/trigger, assert the composed positive is three sections in the order AI base, LoRA tag, trigger. Assert `loraRange` and `triggerRange` point only to the appended character sections even when the AI base contains text equal to the trigger. Assert replacement changes only those ranges. Add a no-selected-LoRA case with null variant metadata.

- [ ] **Step 2: Write failing page integration tests**

Extend the page fixture with the generation dialog and URL. Fake a generated pair, edit its preview, apply it, and assert both output textareas update together; current UI selections remain unchanged; copy/clip/favorite/ComfyUI availability reads the new output; selected character LoRA/trigger are appended; and character replacement uses the AI output without inserting or switching family defaults. Assert a later normal `data-display` click replaces the AI output through existing behavior.

- [ ] **Step 3: Run JavaScript tests and verify they fail**

Run: `docker compose run --rm -T node npm run test:js`

Expected: FAIL because `app.js` does not initialize or apply AI results.

- [ ] **Step 4: Implement the narrow integration boundary**

Import and initialize the Task 4 module in `app.js`. On apply, trim only the positive validity check, preserve the user's preview text otherwise, compose current `lora` and `trigger` as separate sections with existing helpers, calculate exact source metadata, set `variantFamilySource` with empty defaults/default sections, update `previousPositiveOutput`, assign both textareas, and dispatch the same `input` events used by clip restore. Do not change current selection controllers.

- [ ] **Step 5: Run all JavaScript tests**

Run: `docker compose run --rm -T node npm run test:js`

Expected: PASS.

- [ ] **Step 6: Commit the existing-feature integration**

```bash
git add resources/js/app.js resources/js/character-lora-variant.js tests/JavaScript/character-lora-variant.test.js tests/JavaScript/prompt-preparation-page.test.js
git commit -m "feat: apply AI prompts to existing output workflow"
```

### Task 6: Full verification and documentation alignment

**Files:**
- Modify if implementation differs: `docs/design/features/F20-AIプロンプト生成.md`
- Modify if a boundary decision changes: `docs/decisions/0005-外部LLM連携の境界.md`

**Interfaces:**
- Consumes: all prior task deliverables.
- Produces: a verified distributable frontend and an implementation matching F20.

- [ ] **Step 1: Run focused PHP tests together**

Run: `docker compose run --rm -T app php artisan test tests/Unit/Application/PromptPreparation/GenerateIllustrationPromptHandlerTest.php tests/Feature/Infrastructure/PromptPreparation/OllamaIllustrationPromptGeneratorTest.php tests/Feature/Http/PromptPreparation/IllustrationPromptGenerationApiTest.php tests/Feature/TopPageTest.php`

Expected: PASS with zero failures.

- [ ] **Step 2: Run the full test suite**

Run: `docker compose run --rm -T app composer test`

Expected: PASS with zero failures.

Run: `docker compose run --rm -T node npm run test:js`

Expected: PASS with zero failures.

- [ ] **Step 3: Build distributable frontend assets**

If `node_modules` is absent, run: `docker compose run --rm -T node npm ci`

Run: `docker compose run --rm -T node npm run build`

Expected: Vite exits 0 and updates `public/build/manifest.json` plus hashed assets.

- [ ] **Step 4: Run mandatory static checks**

Run: `sh scripts/check.sh`

Expected: Pint, PHPStan/Larastan, ESLint, and Prettier all exit 0 without disabling or weakening rules.

- [ ] **Step 5: Review implementation against F20 without real data or browser automation**

Confirm every F20 requirement maps to a passing automated test, no device/LLM-install instructions were added, no actual DB/storage/log contents were read, no full prompts are logged, and no unrequested persistence/scenario/queue feature entered the diff. Record real external-LLM connectivity and output quality as unverified unless the user separately authorizes a controlled fake-free check at implementation completion.

- [ ] **Step 6: Commit verification-driven documentation adjustments, if any**

```bash
git add docs/design/features/F20-AIプロンプト生成.md docs/decisions/0005-外部LLM連携の境界.md
git commit -m "docs: align AI prompt generation implementation"
```

Commit tracked `public/build` outputs separately only if this repository's existing convention tracks them and `git status --short public/build` shows changes produced by Step 3. Skip the documentation commit when neither design document changed. Do not create an empty commit.
