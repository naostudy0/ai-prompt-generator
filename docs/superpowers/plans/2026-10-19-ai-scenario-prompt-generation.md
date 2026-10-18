# AI Scenario Prompt Generation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 利用者が編集した3〜10場面のシナリオから、継続性を保った画像生成用positive／negative候補をまとめて作り、一場面ずつ既存出力へ反映できるようにする。

**Architecture:** 既存の`PromptPreparation`文脈に、シナリオ案生成、全場面生成、一場面再生成の3つのWriteユースケースを追加する。Application所有の2つの外部AIポートをOllamaアダプターで実装し、ブラウザー内だけに保持する編集状態を専用JavaScriptモジュールが管理する。既存出力への合成はF20とF23で同じコールバックを共有し、保存用集約・Repository・DBテーブルは追加しない。

**Tech Stack:** PHP 8.5、Laravel、Blade、JavaScript ES Modules、CSS/Tailwind、Ollama Chat API互換HTTP、PHPUnit、Node.js test runner＋JSDOM、Docker Compose

**Spec:** `docs/design/features/F23-AIシナリオプロンプト生成.md`

## Global Constraints

- 場面数は3〜10、画面初期値は5。説明は1〜2,000文字、タイトルは1〜100文字、場面説明は1〜1,000文字、変更内容は0〜1,000文字、継続性メモは0〜2,000文字、一時IDは1〜100文字かつリクエスト内で一意とする。
- プロンプト形式は`danbooru_tags`または`natural_language`、初期値は`danbooru_tags`とする。
- 変更指示が空の場面は前場面と継続性メモを引き継ぎ、値がある場面は指定項目だけを変更するよう外部LLMへ指示する。
- シナリオ、場面、生成候補、個別再生成指示はDB、ファイル、`localStorage`、通常ログへ保存しない。
- 外部AI呼び出しを自動再試行せず、呼び出し中にDBトランザクションを保持しない。
- 実DBの内容、アップロード済み永続データ、実際の外部LLMをテスト・確認に使用しない。ブラウザー自動操作とスクリーンショット確認も行わない。
- PHP、Composer、Node.js、npmはDockerサービス内で実行する。フロントエンド変更後は`docker compose run --rm -T node npm run build`と`sh scripts/check.sh`を成功させる。

## Review Focus

- Unicode空白だけの説明・タイトル・場面説明を422にし、正規化後の空文字列をLLMへ渡さないことをTask 4のHTTPテストで固定する。
- 同じ一時ID、順序違い、件数違いを含む上流応答を部分採用せず502へ変換することをTask 3とTask 4で固定する。
- シナリオ編集後に古い全場面生成応答が返っても、編集後の状態や新しい候補を上書きしないことをTask 6のJavaScriptテストで固定する。
- 1件の個別再生成中に別場面を操作しても、対象外の場面・候補・ボタン状態を変えないことをTask 6で固定する。
- モーダル破棄確認は作業状態がある場合だけ出し、API失敗後も入力・シナリオ・既存候補を保持することをTask 6で固定する。

---

### Task 1: シナリオ案生成のApplication境界

**Files:**
- Create: `app/Application/PromptPreparation/DTO/IllustrationScenarioScene.php`
- Create: `app/Application/PromptPreparation/DTO/PlannedIllustrationScenario.php`
- Create: `app/Application/PromptPreparation/Ports/IllustrationScenarioPlanner.php`
- Create: `app/Application/PromptPreparation/Ports/IllustrationScenarioInstructions.php`
- Create: `app/Application/PromptPreparation/Writes/PlanIllustrationScenario/PlanIllustrationScenarioInput.php`
- Create: `app/Application/PromptPreparation/Writes/PlanIllustrationScenario/PlanIllustrationScenarioHandler.php`
- Create: `app/Application/PromptPreparation/Writes/PlanIllustrationScenario/PlanIllustrationScenarioResult.php`
- Test: `tests/Unit/Application/PromptPreparation/PlanIllustrationScenarioHandlerTest.php`

**Interfaces:**
- Consumes: `PromptStyle` from `GenerateIllustrationPrompt`; instruction text from `IllustrationScenarioInstructions::forPlanning(): string`.
- Produces: `IllustrationScenarioPlanner::plan(string $systemPrompt, string $description, int $sceneCount): PlannedIllustrationScenario`; readonly `IllustrationScenarioScene(string $id, string $title, string $description, string $changesFromPrevious)`; readonly `PlannedIllustrationScenario(string $continuityNotes, list<IllustrationScenarioScene> $scenes, string $model, int $generationTimeMs)`; `PlanIllustrationScenarioHandler::handle(PlanIllustrationScenarioInput): PlanIllustrationScenarioResult`.

- [ ] **Step 1: Write the failing handler tests**

  Add `test_説明と場面数を指示文とともにPlannerへ渡す` and `test_Plannerのシナリオ案と計測値をResultへ変換する`. Capture the exact description and scene count `5`; assert scene order and IDs remain unchanged.

- [ ] **Step 2: Run the focused test and confirm RED**

  Run: `docker compose run --rm -T app php artisan test tests/Unit/Application/PromptPreparation/PlanIllustrationScenarioHandlerTest.php`

  Expected: FAIL because the DTOs, ports, and handler do not exist.

- [ ] **Step 3: Implement the minimal readonly DTOs, ports, Input, Handler, and Result**

  `PlanIllustrationScenarioInput` holds `string $description` and `int $sceneCount`. The planning call does not consume `PromptStyle`; style remains browser state until prompt generation. Use `list<IllustrationScenarioScene>` PHPDoc on list properties.

- [ ] **Step 4: Run the focused test and confirm GREEN**

  Run: `docker compose run --rm -T app php artisan test tests/Unit/Application/PromptPreparation/PlanIllustrationScenarioHandlerTest.php`

  Expected: PASS.

- [ ] **Step 5: Commit the Application planning boundary**

  ```bash
  git add app/Application/PromptPreparation/DTO app/Application/PromptPreparation/Ports/IllustrationScenarioPlanner.php app/Application/PromptPreparation/Ports/IllustrationScenarioInstructions.php app/Application/PromptPreparation/Writes/PlanIllustrationScenario tests/Unit/Application/PromptPreparation/PlanIllustrationScenarioHandlerTest.php
  git commit -m "feat: add scenario planning use case"
  ```

### Task 2: 全場面生成と個別再生成のApplication境界

**Files:**
- Create: `app/Application/PromptPreparation/DTO/ScenarioIllustrationPrompt.php`
- Create: `app/Application/PromptPreparation/DTO/GeneratedScenarioPrompts.php`
- Create: `app/Application/PromptPreparation/Ports/ScenarioIllustrationPromptGenerator.php`
- Create: `app/Application/PromptPreparation/Writes/GenerateScenarioPrompts/GenerateScenarioPromptsInput.php`
- Create: `app/Application/PromptPreparation/Writes/GenerateScenarioPrompts/GenerateScenarioPromptsHandler.php`
- Create: `app/Application/PromptPreparation/Writes/GenerateScenarioPrompts/GenerateScenarioPromptsResult.php`
- Create: `app/Application/PromptPreparation/Writes/RegenerateScenarioScenePrompt/RegenerateScenarioScenePromptInput.php`
- Create: `app/Application/PromptPreparation/Writes/RegenerateScenarioScenePrompt/RegenerateScenarioScenePromptHandler.php`
- Create: `app/Application/PromptPreparation/Writes/RegenerateScenarioScenePrompt/RegenerateScenarioScenePromptResult.php`
- Test: `tests/Unit/Application/PromptPreparation/GenerateScenarioPromptsHandlerTest.php`
- Test: `tests/Unit/Application/PromptPreparation/RegenerateScenarioScenePromptHandlerTest.php`

**Interfaces:**
- Consumes: Task 1 `IllustrationScenarioScene`, `IllustrationScenarioInstructions`; existing `PromptStyle`.
- Produces: readonly `ScenarioIllustrationPrompt(string $id, string $positivePrompt, string $negativePrompt)`; readonly `GeneratedScenarioPrompts(list<ScenarioIllustrationPrompt> $scenes, string $model, int $generationTimeMs)`; `ScenarioIllustrationPromptGenerator::generateAll(string $systemPrompt, string $description, string $continuityNotes, array $scenes, PromptStyle $promptStyle): GeneratedScenarioPrompts`; `ScenarioIllustrationPromptGenerator::regenerateOne(string $systemPrompt, string $description, string $continuityNotes, array $scenes, array $currentPrompts, string $targetId, string $instruction, PromptStyle $promptStyle): ScenarioIllustrationPrompt`.

- [ ] **Step 1: Write failing tests for all-scene generation**

  Add tests that assert the handler passes the ordered scenario, continuity notes, and style to `generateAll`, and returns the generator result without changing IDs or order.

- [ ] **Step 2: Write failing tests for one-scene regeneration**

  Add tests that assert the handler passes the complete ordered scenario, all current prompts, target ID, and latest instruction to `regenerateOne`, and returns only the target prompt.

- [ ] **Step 3: Run both focused tests and confirm RED**

  Run: `docker compose run --rm -T app php artisan test tests/Unit/Application/PromptPreparation/GenerateScenarioPromptsHandlerTest.php tests/Unit/Application/PromptPreparation/RegenerateScenarioScenePromptHandlerTest.php`

  Expected: FAIL because the new contracts and handlers do not exist.

- [ ] **Step 4: Implement the DTOs, port, Inputs, Handlers, and Results**

  Add `IllustrationScenarioInstructions::forPromptStyle(PromptStyle $style): string` and `forSceneRegeneration(PromptStyle $style): string`. Preserve ordered `list<>` values; do not add persistence or domain aggregates.

- [ ] **Step 5: Run both focused tests and confirm GREEN**

  Run the Step 3 command. Expected: PASS.

- [ ] **Step 6: Commit the prompt-generation use cases**

  ```bash
  git add app/Application/PromptPreparation/DTO app/Application/PromptPreparation/Ports app/Application/PromptPreparation/Writes/GenerateScenarioPrompts app/Application/PromptPreparation/Writes/RegenerateScenarioScenePrompt tests/Unit/Application/PromptPreparation
  git commit -m "feat: add scenario prompt generation use cases"
  ```

### Task 3: Ollamaシナリオアダプターと構造化応答検証

**Files:**
- Create: `app/Infrastructure/PromptPreparation/External/IllustrationLlm/FileIllustrationScenarioInstructions.php`
- Create: `app/Infrastructure/PromptPreparation/External/IllustrationLlm/OllamaIllustrationScenarioGenerator.php`
- Create: `resources/prompts/illustration/scenario_plan.txt`
- Create: `resources/prompts/illustration/scenario_danbooru_tags.txt`
- Create: `resources/prompts/illustration/scenario_natural_language.txt`
- Create: `resources/prompts/illustration/scenario_scene_danbooru_tags.txt`
- Create: `resources/prompts/illustration/scenario_scene_natural_language.txt`
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `config/services.php`
- Modify: `.env.example`
- Test: `tests/Feature/Infrastructure/PromptPreparation/OllamaIllustrationScenarioGeneratorTest.php`

**Interfaces:**
- Consumes: Tasks 1–2 ports and DTOs; existing `IllustrationPromptServiceUnavailable`, `InvalidIllustrationPromptResponse`, and `services.illustration_llm` settings.
- Produces: one concrete class implementing both `IllustrationScenarioPlanner` and `ScenarioIllustrationPromptGenerator`; file-backed instruction provider; optional `services.illustration_llm.scenario_num_predict` configured by `ILLUSTRATION_LLM_SCENARIO_NUM_PREDICT`, default `4096`.

- [ ] **Step 1: Write failing planning-adapter tests**

  Assert request model, `stream: false`, `think: false`, planning System Prompt, description and count, and JSON Schema `minItems`/`maxItems` equal the requested count. Assert valid output conversion and reject wrong counts, blank titles/descriptions, and malformed JSON.

- [ ] **Step 2: Write failing all-scene and individual-generation tests**

  Assert ordered IDs and all continuity fields are sent. Assert all-scene responses reject missing, duplicate, extra, reordered, unknown, or blank-positive entries. Assert individual responses reject an ID other than the target. Verify connection/upstream errors become 503-class exceptions and invalid content becomes 502-class exceptions.

- [ ] **Step 3: Add logging privacy tests**

  Use unique sentinel text in description, titles, continuity notes, regeneration instruction, and generated prompts. Assert captured log context contains request ID, operation, model, outcome, and timing but none of the sentinels or raw response.

- [ ] **Step 4: Run the focused infrastructure test and confirm RED**

  Run: `docker compose run --rm -T app php artisan test tests/Feature/Infrastructure/PromptPreparation/OllamaIllustrationScenarioGeneratorTest.php`

  Expected: FAIL because the adapter and prompt assets do not exist.

- [ ] **Step 5: Implement instructions, request schemas, parsing, validation, and exception mapping**

  Keep operation-specific private methods focused. Validate the complete response before constructing DTOs; never reorder or partially accept it. Reuse configuration and metric extraction patterns without changing F20 behavior.

- [ ] **Step 6: Bind both ports and add the scenario token setting**

  Bind the two interfaces to `OllamaIllustrationScenarioGenerator` and the instruction port to `FileIllustrationScenarioInstructions`. Add only the scenario-specific output-token setting; do not add dependencies.

- [ ] **Step 7: Run the focused infrastructure test and confirm GREEN**

  Run the Step 4 command. Expected: PASS.

- [ ] **Step 8: Commit the Ollama integration**

  ```bash
  git add app/Infrastructure/PromptPreparation/External/IllustrationLlm app/Providers/AppServiceProvider.php config/services.php .env.example resources/prompts/illustration tests/Feature/Infrastructure/PromptPreparation/OllamaIllustrationScenarioGeneratorTest.php
  git commit -m "feat: integrate scenario generation with Ollama"
  ```

### Task 4: 3つのHTTP APIと入力検証

**Files:**
- Create: `app/Http/Requests/PromptPreparation/PlanIllustrationScenarioRequest.php`
- Create: `app/Http/Requests/PromptPreparation/GenerateScenarioPromptsRequest.php`
- Create: `app/Http/Requests/PromptPreparation/RegenerateScenarioScenePromptRequest.php`
- Create: `app/Http/Controllers/PromptPreparation/Writes/PlanIllustrationScenarioController.php`
- Create: `app/Http/Controllers/PromptPreparation/Writes/GenerateScenarioPromptsController.php`
- Create: `app/Http/Controllers/PromptPreparation/Writes/RegenerateScenarioScenePromptController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Http/PromptPreparation/IllustrationScenarioApiTest.php`

**Interfaces:**
- Consumes: Tasks 1–3 handlers, DTOs, `PromptStyle`, and existing external-LLM exceptions.
- Produces: named routes `illustration-scenarios.plan`, `illustration-scenarios.prompts.generate`, `illustration-scenarios.scenes.prompt`; camelCase JSON matching F23.

- [ ] **Step 1: Write failing success-contract tests for all three endpoints**

  Assert exact request-to-Input mapping, default `danbooru_tags`, trimming of Unicode edge whitespace, JSON keys, scene order, model, style where applicable, and `generationTimeMs`. Assert each route uses POST and web middleware.

- [ ] **Step 2: Write failing boundary-validation tests**

  Cover description lengths 1/2,000/2,001, scene counts 2/3/5/10/11, titles 1/100/101, scene descriptions 1/1,000/1,001, changes 0/1,000/1,001, continuity 0/2,000/2,001, IDs 1/100/101, duplicate IDs, unknown style, and Unicode-whitespace-only required strings.

- [ ] **Step 3: Write failing error-mapping tests**

  For each endpoint, assert service-unavailable maps to 503 and invalid upstream response maps to 502 without exposing endpoint, stack trace, or raw upstream body.

- [ ] **Step 4: Run the focused HTTP test and confirm RED**

  Run: `docker compose run --rm -T app php artisan test tests/Feature/Http/PromptPreparation/IllustrationScenarioApiTest.php`

  Expected: FAIL because routes, Requests, and Controllers do not exist.

- [ ] **Step 5: Implement Request normalization/rules and Controllers**

  Normalize only strings at the HTTP boundary. Convert validated scene arrays into readonly DTOs before calling handlers. Return no partial success payload; use the same Japanese 502/503 message policy as F20.

- [ ] **Step 6: Add the three named POST routes**

  Use exact URLs `/illustration-scenarios/plan`, `/illustration-scenarios/prompts`, and `/illustration-scenarios/scenes/prompt`.

- [ ] **Step 7: Run the focused HTTP test and confirm GREEN**

  Run the Step 4 command. Expected: PASS.

- [ ] **Step 8: Commit the HTTP boundary**

  ```bash
  git add app/Http/Requests/PromptPreparation app/Http/Controllers/PromptPreparation/Writes routes/web.php tests/Feature/Http/PromptPreparation/IllustrationScenarioApiTest.php
  git commit -m "feat: expose illustration scenario APIs"
  ```

### Task 5: F20とF23で共有する既存出力への反映境界

**Files:**
- Create: `resources/js/generated-prompt-application.js`
- Modify: `resources/js/app.js`
- Modify: `resources/js/illustration-prompt-generator.js`
- Test: `tests/JavaScript/generated-prompt-application.test.js`
- Test: `tests/JavaScript/illustration-prompt-generator.test.js`

**Interfaces:**
- Consumes: existing `joinPromptSections`, `createAiCharacterLoraOutput`, active-family/default/LORA accessors, and `applyOutputPair` supplied from `app.js`.
- Produces: `createGeneratedPromptApplication({ getDefaultPrompts, getSelectedCharacterLora, getActiveFamilySource, applyOutputPair })` returning `apply({ positivePrompt, negativePrompt, includeDefaultPrompts }): boolean`; F20 and F23 receive this callback rather than duplicating output composition.

- [ ] **Step 1: Write failing unit tests for the shared application function**

  Cover blank positive rejection; include/exclude defaults; selected and unselected character LoRA; negative composition; metadata/default-section preservation needed by character replacement.

- [ ] **Step 2: Run the focused JS tests and confirm RED**

  Run: `docker compose run --rm -T node node --test tests/JavaScript/generated-prompt-application.test.js`

  Expected: FAIL because the module does not exist.

- [ ] **Step 3: Extract the existing F20 composition without changing behavior**

  Move only the common generated-candidate-to-output orchestration. Keep prompt joining and character-LoRA domain helpers in their current modules; avoid a general utility abstraction.

- [ ] **Step 4: Update F20 initialization to consume the shared callback**

  Keep the public `applyPrompt` payload and every existing F20 test expectation unchanged.

- [ ] **Step 5: Run shared and F20 regression tests**

  Run: `docker compose run --rm -T node node --test tests/JavaScript/generated-prompt-application.test.js tests/JavaScript/illustration-prompt-generator.test.js`

  Expected: PASS.

- [ ] **Step 6: Commit the shared output application boundary**

  ```bash
  git add resources/js/app.js resources/js/illustration-prompt-generator.js resources/js/generated-prompt-application.js tests/JavaScript/generated-prompt-application.test.js tests/JavaScript/illustration-prompt-generator.test.js
  git commit -m "refactor: share generated prompt application"
  ```

### Task 6: シナリオ編集・生成のJavaScript状態管理

**Files:**
- Create: `resources/js/illustration-scenario-generator.js`
- Test: `tests/JavaScript/illustration-scenario-generator.test.js`

**Interfaces:**
- Consumes: page data URLs, `fetcher`, CSRF token, `documentObject`, `confirmAction(message): boolean`, `createId(): string`, and Task 5 `applyPrompt` callback.
- Produces: `initializeIllustrationScenarioGenerator({ page, documentObject, fetcher, csrfToken, confirmAction, createId, applyPrompt })` returning `{ abort(): void }`.

- [ ] **Step 1: Write failing tests for input and planning state**

  Cover initial count 5, client validation 3–10 and 2,000 characters, POST payload, processing lock, valid plan rendering, retry after failure, and preservation of input. Use deterministic `createId` values; assert no browser storage calls.

- [ ] **Step 2: Write failing tests for scene editing**

  Cover edit, add at fewer than 10, delete at more than 3, blocked edge operations, up/down reorder, drag-equivalent reorder through an exported pure helper `moveScenarioScene(scenes, sourceIndex, targetIndex)`, and stable IDs.

- [ ] **Step 3: Write failing tests for all-scene generation and result validation**

  Assert current edited order and values are sent; reject wrong count/order/ID, blank positive, and invalid metadata; keep the scenario on failure. Assert a stale response after an edit or newer request is ignored.

- [ ] **Step 4: Write failing tests for individual regeneration and application**

  Assert the complete scenario/current candidates plus target ID and instruction are sent; only the target changes; target failure preserves its old candidate; another scene remains interactive; edited positive/negative are passed one at a time to Task 5 callback without closing the modal.

- [ ] **Step 5: Write failing tests for cancellation and discard confirmation**

  Assert close without work does not confirm; close with a plan or candidate confirms; rejection keeps the dialog open; acceptance aborts active requests; responses after abort/close do not mutate state.

- [ ] **Step 6: Run the focused JS test and confirm RED**

  Run: `docker compose run --rm -T node node --test tests/JavaScript/illustration-scenario-generator.test.js`

  Expected: FAIL because the module does not exist.

- [ ] **Step 7: Implement the smallest state machine and DOM renderer that passes the tests**

  Keep scene state in arrays of plain objects, replace dynamic DOM with `replaceChildren`, write untrusted strings through `textContent`/form values, and maintain separate request tokens/controllers for planning, all-scene generation, and target regeneration.

- [ ] **Step 8: Run the focused JS test and confirm GREEN**

  Run the Step 6 command. Expected: PASS.

- [ ] **Step 9: Commit the scenario UI behavior**

  ```bash
  git add resources/js/illustration-scenario-generator.js tests/JavaScript/illustration-scenario-generator.test.js
  git commit -m "feat: add scenario prompt editor behavior"
  ```

### Task 7: Blade、CSS、ページ配線

**Files:**
- Modify: `app/Http/Controllers/PromptPreparation/PromptPreparationPageController.php`
- Modify: `resources/views/prompt-preparation.blade.php`
- Modify: `resources/css/app.css`
- Modify: `resources/js/app.js`
- Modify: `tests/Feature/TopPageTest.php`
- Modify: `tests/JavaScript/prompt-preparation-page.test.js`

**Interfaces:**
- Consumes: Task 4 named routes; Tasks 5–6 initialization APIs.
- Produces: three `data-illustration-scenario-*-url` attributes; accessible “シナリオを作成” modal markup with input/edit/result stages; responsive scene cards and controls; page startup wiring.

- [ ] **Step 1: Write failing page-response tests**

  Assert the page contains the scenario button, dialog landmarks, labels, status regions, stage containers, scene template/hooks, and all three route URLs. Do not assert visual pixels.

- [ ] **Step 2: Write failing page-initialization JS test**

  Extend the page fixture with scenario URLs and minimum modal hooks; assert startup initializes the module and page teardown aborts its active request.

- [ ] **Step 3: Run focused page tests and confirm RED**

  Run: `docker compose run --rm -T app php artisan test tests/Feature/TopPageTest.php`

  Run: `docker compose run --rm -T node node --test tests/JavaScript/prompt-preparation-page.test.js`

  Expected: FAIL because the view and page wiring are absent.

- [ ] **Step 4: Add route URLs and semantic Blade markup**

  Use labels for every input, buttons for operations, `aria-live` status, explicit up/down controls in addition to drag handles, and `hidden` stage sections. Do not embed behavior in Blade.

- [ ] **Step 5: Add scoped responsive CSS**

  Use `.illustration-scenario-dialog` and child classes. Ensure cards remain usable at `max-width: 640px`; do not alter unrelated controls.

- [ ] **Step 6: Initialize the scenario module in `app.js`**

  Pass the shared generated-prompt application callback, native confirm wrapper, and `crypto.randomUUID` wrapper. Register its `abort()` with the existing page teardown path.

- [ ] **Step 7: Run focused page and full JavaScript tests**

  Run the two Step 3 commands, then `docker compose run --rm -T node npm run test:js`.

  Expected: PASS.

- [ ] **Step 8: Build distributable frontend assets**

  Run: `docker compose run --rm -T node npm run build`

  Expected: Vite exits 0 and updates `public/build/manifest.json` plus hashed assets. Stage generated build outputs only if this repository already tracks them.

- [ ] **Step 9: Commit the integrated page**

  ```bash
  git add app/Http/Controllers/PromptPreparation/PromptPreparationPageController.php resources/views/prompt-preparation.blade.php resources/css/app.css resources/js/app.js tests/Feature/TopPageTest.php tests/JavaScript/prompt-preparation-page.test.js
  git commit -m "feat: add scenario prompt generation interface"
  ```

### Task 8: 操作文書と全体検証

**Files:**
- Modify: `docs/operations/AIプロンプト生成の操作方法.md`
- Modify if implementation decisions changed: `docs/design/features/F23-AIシナリオプロンプト生成.md`
- Verify: all files changed by Tasks 1–7

**Interfaces:**
- Consumes: completed F23 behavior.
- Produces: user-facing instructions and evidence that the implementation, specification, tests, build, and static checks agree.

- [ ] **Step 1: Update the operation guide**

  Document input → scenario editing → all-scene generation → individual regeneration → one-scene application → existing LoRA adjustment/ComfyUI flow. State that closing or reloading discards scenario work and that no batch ComfyUI action is provided.

- [ ] **Step 2: Check design-to-implementation consistency**

  Compare every F23 requirement and acceptance row against code/tests. Update the F23 document only for implementation decisions that became concrete; do not record progress or test results in the feature spec.

- [ ] **Step 3: Run all PHP tests**

  Run: `docker compose run --rm -T app composer test`

  Expected: PASS with no failures or errors.

- [ ] **Step 4: Run all JavaScript tests**

  Run: `docker compose run --rm -T node npm run test:js`

  Expected: PASS.

- [ ] **Step 5: Run the required static checks**

  Run: `sh scripts/check.sh`

  Expected: Pint/PSR-12, PHPStan/Larastan level 8, ESLint, and Prettier all pass without disabled rules or reduced analysis.

- [ ] **Step 6: Rebuild frontend assets after any formatting correction**

  Run: `docker compose run --rm -T node npm run build`

  Expected: Vite exits 0 and the manifest points to the final JavaScript/CSS assets.

- [ ] **Step 7: Run repository hygiene checks**

  Run: `git diff --check && git status --short`

  Expected: no whitespace errors; only intentional F23 changes/build artifacts remain.

- [ ] **Step 8: Commit documentation and final consistency fixes**

  ```bash
  git add docs/operations/AIプロンプト生成の操作方法.md docs/design/features/F23-AIシナリオプロンプト生成.md
  git commit -m "docs: explain AI scenario prompt workflow"
  ```

- [ ] **Step 9: Request the project-required categorized code review**

  Follow `docs/review/コードレビュー基準.md`: assign each review category to an independent subagent, centralize static checks and `docker compose run --rm -T app composer test` in one inspection owner, aggregate findings, fix confirmed issues, and rerun Steps 3–7 before declaring completion.
