# Random Illustration Idea Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 場所を偶然性のある起点にし、手動選択を固定した意味の通る日本語イラスト案を編集・反映できるランダム生成機能を追加する。

**Architecture:** 場所候補の役割はオプションブロックへ永続化し、固定条件と登録候補の解決はApplication所有のQueryポートで行う。日本語案生成と編集後解析は別々のWritesユースケースと外部LLMポートに分け、画面は案を反映するまで現在の選択と出力を変更しない。

**Tech Stack:** PHP 8.5、Laravel、SQLite、Blade、ES Modules、CSS、Ollama互換`/api/chat`、PHPUnit、Node test runner

**Spec:** `docs/design/features/F25-整合性のあるランダムイラスト案.md`

## Global Constraints

- PHP、Composer、Node.js、npmはDockerの`app`・`node`サービス内で実行する。
- 実データ、実DB、永続storage、ブラウザー、自動スクリーンショットへアクセスしない。テストDB、Fake、架空fixtureだけを使う。
- 更新・外部副作用を伴う入口は`Application/PromptPreparation/Writes`に置き、HTTP Controllerへ業務判断を置かない。
- 外部LLMへ送るIDは現在の登録内容と照合し、クライアントから名前・文面を信用しない。
- 通常ログへ固定条件、説明、場所文面、タグ、プロンプト、System Prompt、LLM生応答を記録しない。
- 一時候補は0〜10件。初回はpositiveだけ保存でき、negativeは今回の出力だけに使う。
- フロントエンド変更後は`docker compose run --rm -T node npm run build`を実行する。
- 完了前に`sh scripts/check.sh`と`docker compose run --rm -T app composer test`を成功させる。
- プロジェクト方針により、各タスクではコミットせず、全検証とメインエージェントのセルフレビュー後に一括コミットする。

## Review Focus

- 同じ`single`ブロックで固定項目とAI提案が競合した場合、固定項目だけを維持すること（Task 4）。
- 場所候補または人物LoRAがない入力でも、空IDや架空IDを選択せず日本語案を生成できること（Task 3・5）。
- 編集中に古い生成・解析応答が返っても、現在の案や出力を上書きしないこと（Task 6）。
- 登録候補がコンテキスト予算を超える場合、候補を黙って欠落させずLLMを呼ばないこと（Task 4・5）。
- 生成・解析・一時候補保存の失敗時に、元の選択、出力、編集済み説明を保持すること（Task 5・6）。

---

## File Structure

### Persistence and option management

- `database/migrations/2026_10_27_000000_add_random_generation_role_to_option_prompt_groups.php`: 場所候補役割のnullable列と制約。
- `app/Domain/PromptPreparation/Models/OptionPromptGroup.php`: 任意のランダム生成役割を保持し、許可値を守る。
- `app/Domain/PromptPreparation/Models/RandomGenerationRole.php`: 初回の`location_source`役割。
- `app/Infrastructure/PromptPreparation/Persistence/Eloquent/Models/OptionPromptGroupRecord.php`: 永続化列。
- `app/Infrastructure/PromptPreparation/Persistence/Eloquent/Repositories/EloquentOptionPromptGroupRepository.php`: 保存・復元。
- `app/Application/PromptPreparation/Writes/SaveOptionPromptGroup/SaveOptionPromptGroupHandler.php`: 役割変更を既存ブロック保存へ含める。
- `app/Http/Controllers/PromptPreparation/Writes/SaveOptionPromptGroupController.php`: `randomGenerationRole`入力。
- `app/Application/PromptPreparation/Queries/GetPromptOptions/GetPromptOptionsResult.php`: 役割を一覧DTOへ追加。
- `app/Infrastructure/PromptPreparation/Queries/EloquentOptionPromptQueryService.php`: 一覧へ役割を投影。
- `resources/js/prompt-categories.js`, `resources/js/prompt-options-page.js`, `resources/views/prompt-preparation.blade.php`: 管理フォームの役割選択。

### Random idea application and infrastructure

- `app/Application/PromptPreparation/DTO/RandomIllustrationFixedSelection.php`: 検証済み固定条件一件。
- `app/Application/PromptPreparation/DTO/RandomIllustrationSelectionIds.php`: HTTPから受ける選択IDとデフォルト選択の不変DTO。
- `app/Application/PromptPreparation/DTO/RandomIllustrationOptionCandidate.php`: LLMへ渡せる既存候補一件。
- `app/Application/PromptPreparation/DTO/RandomIllustrationIdea.php`: 日本語案生成結果。
- `app/Application/PromptPreparation/DTO/ResolvedRandomIllustrationIdea.php`: 調整後説明、選択ID、一時候補、調整理由。
- `app/Application/PromptPreparation/DTO/TransientPromptSuggestion.php`: polarity、ラベル、タグ。
- `app/Application/PromptPreparation/Ports/RandomIllustrationSelectionQueryService.php`: ID照合、場所候補、解析候補の参照境界。
- `app/Application/PromptPreparation/Ports/RandomSource.php`: テスト可能な一件抽選。
- `app/Application/PromptPreparation/Ports/RandomIllustrationIdeaGenerator.php`: 日本語案生成境界。
- `app/Application/PromptPreparation/Ports/RandomIllustrationIdeaResolver.php`: 編集後解析境界。
- `app/Application/PromptPreparation/Exceptions/RandomIllustrationContextTooLarge.php`: 候補を欠落させず解析を中止する失敗。
- `app/Application/PromptPreparation/Writes/GenerateRandomIllustrationIdea/*`: 起点抽選と日本語案生成。
- `app/Application/PromptPreparation/Writes/ResolveRandomIllustrationIdea/*`: 編集案の既存ID・一時候補への変換。
- `app/Infrastructure/PromptPreparation/Queries/EloquentRandomIllustrationSelectionQueryService.php`: 現在DBから固定条件・候補をDTO化。
- `app/Infrastructure/PromptPreparation/Random/PhpRandomSource.php`: `random_int`で一件を選択。
- `app/Infrastructure/PromptPreparation/External/IllustrationLlm/OllamaRandomIllustrationIdeaGenerator.php`: 日本語案の構造化生成。
- `app/Infrastructure/PromptPreparation/External/IllustrationLlm/OllamaRandomIllustrationIdeaResolver.php`: 編集案の構造化解析。
- `app/Infrastructure/PromptPreparation/External/IllustrationLlm/FileRandomIllustrationInstructions.php`: 二段階のSystem Prompt。
- `resources/prompts/random-illustration-idea.txt`, `resources/prompts/random-illustration-resolution.txt`: 外部指示本文。
- `app/Providers/AppServiceProvider.php`, `config/services.php`, `.env.example`: ポート結線とコンテキスト予算設定。

### HTTP and UI

- `app/Http/Requests/PromptPreparation/GenerateRandomIllustrationIdeaRequest.php`: 固定条件ID構造の検証。
- `app/Http/Requests/PromptPreparation/ResolveRandomIllustrationIdeaRequest.php`: 案ID、編集説明、固定条件IDの検証。
- `app/Http/Controllers/PromptPreparation/Writes/GenerateRandomIllustrationIdeaController.php`: 生成API応答と例外変換。
- `app/Http/Controllers/PromptPreparation/Writes/ResolveRandomIllustrationIdeaController.php`: 解析API応答と例外変換。
- `routes/web.php`, `app/Http/Controllers/PromptPreparation/PromptPreparationPageController.php`: 2 APIとBlade URL。
- `resources/js/random-illustration-idea.js`: モーダル状態、生成、別案、解析、一時候補表示。
- `resources/js/random-illustration-application.js`: 選択と出力の原子的な反映。
- `resources/js/app.js`: 現在選択の取得、解析結果反映、再読込後の保存候補選択。
- `resources/views/prompt-preparation.blade.php`, `resources/css/app.css`: 入口とアクセシブルなモーダル。

## Task 1: Persist and manage location-source roles

**Files:** Persistence and option-management files listed above; tests in `tests/Unit/Domain/PromptPreparation/NamedPromptModelsTest.php`, `tests/Feature/Http/PromptPreparation/PromptOptionApiTest.php`, `tests/JavaScript/prompt-categories.test.js`, and `tests/JavaScript/prompt-preparation-page.test.js`.

**Interfaces:**
- Produces: `RandomGenerationRole::LocationSource`, `OptionPromptGroup::$randomGenerationRole`, and prompt-option JSON field `randomGenerationRole: 'location_source' | null`.
- Consumes: existing group save/list APIs and management dialog.

- [ ] **Step 1: Write failing domain and HTTP tests**
  - Assert a normal group accepts `LocationSource`, preserves it through `changeCategory()`, and rejects unknown values through enum parsing.
  - Assert create/update/list return `randomGenerationRole`, nullable by default.
  - Assert a system-managed group cannot gain or change this role.
- [ ] **Step 2: Run the focused PHP tests and confirm failure**
  - Run `docker compose run --rm -T app php artisan test tests/Unit/Domain/PromptPreparation/NamedPromptModelsTest.php tests/Feature/Http/PromptPreparation/PromptOptionApiTest.php`.
- [ ] **Step 3: Add migration, enum, model/repository/query mappings, and save validation**
  - Migration adds nullable string `random_generation_role` and leaves existing rows null.
  - `SaveOptionPromptGroupHandler::handle(?int $id, string $name, OptionSelectionMode $selectionMode, ?RandomGenerationRole $randomGenerationRole): array` preserves the explicit role on update and returns it.
- [ ] **Step 4: Write failing JS tests for management UI**
  - Assert the group form can select/clear 「場所候補」 and sends `randomGenerationRole`.
  - Assert selection and ordinary output order do not change when only the role changes.
- [ ] **Step 5: Implement the role control and list rendering, then run focused PHP/JS tests**
  - Run the PHP command from Step 2 and `docker compose run --rm -T node npm test -- --test-name-pattern='場所候補|randomGenerationRole'`.

## Task 2: Resolve trusted fixed selections and random candidates

**Files:** Selection DTOs, `RandomIllustrationSelectionQueryService`, `EloquentRandomIllustrationSelectionQueryService`, `RandomSource`, `PhpRandomSource`, provider bindings; tests in `tests/Feature/Infrastructure/PromptPreparation/EloquentRandomIllustrationSelectionQueryServiceTest.php` and `tests/Unit/Application/PromptPreparation/GenerateRandomIllustrationIdeaHandlerTest.php`.

**Interfaces:**
- Consumes: request IDs shaped as defaults booleans, optional character selection, clothing selection IDs, and option IDs.
- Produces: `resolveFixedSelections(RandomIllustrationSelectionIds $ids): list<RandomIllustrationFixedSelection>`, `locationCandidates(): list<RandomIllustrationOptionCandidate>`, `resolutionCandidates(): list<RandomIllustrationOptionCandidate>`, and `RandomSource::pick(array $candidates): ?RandomIllustrationOptionCandidate`.

- [ ] **Step 1: Write failing query tests with an in-memory test DB**
  - Cover selected defaults, character LoRA/trigger/outfit, clothing LoRA/trigger, and option IDs.
  - Assert missing, hidden, wrong-parent, and cross-family IDs are rejected instead of partially trusted.
  - Assert only `location_source` groups feed `locationCandidates()` and renamed/multiple blocks work.
- [ ] **Step 2: Implement immutable ID/selection DTOs and the Eloquent query service**
  - Query only requested IDs for fixed selections; do not load real application data in tests.
  - Return registration text needed by LLM, selection mode, group ID, and stable type/ID keys.
- [ ] **Step 3: Write and pass deterministic random-source tests**
  - Fake returns a configured candidate; production returns null for empty and one member for non-empty.
- [ ] **Step 4: Run focused tests**
  - Run the new Infrastructure and Unit test files in Docker; expect PASS.

## Task 3: Generate the editable Japanese illustration idea

**Files:** Generate use-case files, generator/instruction ports, Ollama generator, prompt file, provider/config files; tests in `tests/Unit/Application/PromptPreparation/GenerateRandomIllustrationIdeaHandlerTest.php`, `tests/Feature/Infrastructure/PromptPreparation/RandomIllustrationInstructionsTest.php`, and `tests/Feature/Infrastructure/PromptPreparation/OllamaRandomIllustrationIdeaGeneratorTest.php`.

**Interfaces:**
- Consumes: `GenerateRandomIllustrationIdeaInput(RandomIllustrationSelectionIds $fixedSelections)`.
- Produces: `GenerateRandomIllustrationIdeaResult(string $ideaId, string $description, array $fixedSelectionSummaries, ?array $locationAnchor, string $model, int $generationTimeMs)`.
- `RandomIllustrationIdeaGenerator::generate(string $systemPrompt, list<RandomIllustrationFixedSelection> $fixed, ?RandomIllustrationOptionCandidate $anchor): RandomIllustrationIdea`.

- [ ] **Step 1: Write handler tests for selected and random locations**
  - Selected location stays fixed and bypasses `RandomSource`.
  - No selected location picks one candidate; no candidates sends a null anchor.
  - Missing character LoRA remains valid and does not synthesize a registered LoRA ID.
- [ ] **Step 2: Implement handler/input/result and UUID correlation ID**
  - The handler obtains trusted selections from Task 2 and passes only validated DTOs to the generator.
- [ ] **Step 3: Write instruction and adapter tests**
  - Assert the prompt requires Japanese prose, fixed-condition preservation, no contradictory simultaneous actions, optional people, and no secret/raw response logging.
  - Fake HTTP response schema is `{description: string}`; reject blank/wrong-type/extra-shape responses.
  - Connection/timeout/HTTP failure maps to service unavailable; malformed content maps to invalid response.
- [ ] **Step 4: Implement prompt loader and Ollama adapter using existing connection/metrics conventions**
- [ ] **Step 5: Run all Task 3 tests and confirm PASS**

## Task 4: Resolve an edited idea into registered selections and transient suggestions

**Files:** Resolve use-case files, resolver port/adapter, DTOs, second prompt file, context-budget configuration; tests in `tests/Unit/Application/PromptPreparation/ResolveRandomIllustrationIdeaHandlerTest.php`, `tests/Feature/Infrastructure/PromptPreparation/RandomIllustrationInstructionsTest.php`, and `tests/Feature/Infrastructure/PromptPreparation/OllamaRandomIllustrationIdeaResolverTest.php`.

**Interfaces:**
- Consumes: `ResolveRandomIllustrationIdeaInput(string $ideaId, string $description, RandomIllustrationSelectionIds $fixedSelections)`.
- Produces: `ResolveRandomIllustrationIdeaResult(string $ideaId, string $resolvedDescription, list<int> $optionIds, list<TransientPromptSuggestion> $suggestions, list<string> $adjustments, string $model, int $generationTimeMs)`.
- `RandomIllustrationIdeaResolver::resolve(string $instructions, string $description, array $fixed, array $candidates): ResolvedRandomIllustrationIdea`.

- [ ] **Step 1: Write failing handler tests for invariant enforcement**
  - Unknown IDs, duplicate IDs, a second item in a fixed `single` group, and missing fixed option IDs reject the whole result.
  - Zero suggestions is valid; 11, blank label/tags, or unknown polarity rejects the whole result.
  - Changing a non-fixed random anchor from 教室 to 図書館 does not reselect 教室.
- [ ] **Step 2: Implement handler validation and result DTOs**
  - Merge fixed option IDs into output, normalize per group, and never let resolver output remove a fixed selection.
- [ ] **Step 3: Add context-budget tests and guard**
  - Serialize the exact request payload, compare its size with configured input budget after reserved bytes, and fail before HTTP when too large.
  - Do not silently truncate candidates or add semantic search in this task.
- [ ] **Step 4: Write adapter tests for the exact structured schema**
  - Schema fields: `resolved_description`, `selected_option_ids`, `transient_suggestions`, `adjustments`.
  - Suggestion fields: `polarity` (`positive|negative`), `label` (1–100 chars), `tags` (non-separator content).
- [ ] **Step 5: Implement the resolver and safe metrics logging, then run Task 4 tests**

## Task 5: Expose the two POST APIs safely

**Files:** Two FormRequests, two Controllers, `routes/web.php`, page controller; tests in `tests/Feature/Http/PromptPreparation/RandomIllustrationIdeaApiTest.php` and `tests/Feature/TopPageTest.php`.

**Interfaces:**
- Produces `POST /random-illustration-ideas` and `POST /random-illustration-ideas/resolve` under web/CSRF middleware.
- Both accept only IDs/booleans plus the edited description; names and prompt text are server-resolved.

- [ ] **Step 1: Write failing API success tests**
  - Generation returns camelCase `ideaId`, `description`, fixed summary, nullable anchor, model, time.
  - Resolution echoes `ideaId` and returns `resolvedDescription`, `optionIds`, suggestions, adjustments, model, time.
- [ ] **Step 2: Write validation and failure-mapping tests**
  - Description accepts 1–2000 Unicode characters after trimming.
  - Invalid selection shapes return 422 without calling ports.
  - Unavailable/invalid upstream maps to 503/502; oversized candidate context maps to 422 with a retryable guidance message.
  - No failure mutates selection data or creates option rows.
- [ ] **Step 3: Implement FormRequests, Controllers, routes, and Blade URLs**
- [ ] **Step 4: Add route middleware/CSRF assertions and run the feature tests**

## Task 6: Add the preview, edit, regenerate, apply, and save experience

**Files:** Random-idea JS modules, `app.js`, Blade, CSS, existing generated-output integration; tests in `tests/JavaScript/random-illustration-idea.test.js`, `tests/JavaScript/random-illustration-application.test.js`, and `tests/JavaScript/prompt-preparation-page.test.js`.

**Interfaces:**
- Consumes: controllers from Task 5 and existing `getSelectionSnapshot()` functions.
- Produces: `initializeRandomIllustrationIdea(...)` and `createRandomIllustrationApplication(...)` callbacks that atomically restore option selections and replace both outputs while preserving character-variant metadata.

- [ ] **Step 1: Write failing modal-state tests**
  - Opening captures fixed conditions once; generating does not mutate current output.
  - Preview shows fixed summaries and anchor read-only, Japanese description editable.
  - 「別案を作る」keeps captured fixed IDs, replaces the draft, and ignores an older late response.
  - Busy and cancel states prevent duplicate sends and keep the edited draft after failure.
- [ ] **Step 2: Implement the accessible dialog and request state machine**
  - Use `showModal()`, labelled fields, `aria-live`, textContent/value only, and an incrementing request token or AbortController for stale-response exclusion.
- [ ] **Step 3: Write failing atomic-application tests**
  - Validate all returned option IDs before changing anything.
  - Restore fixed plus resolved options respecting `single`; replace positive and negative together.
  - A failed validation leaves both selections and outputs untouched.
  - Create character-LoRA source metadata so later variant replacement preserves generated sections.
- [ ] **Step 4: Implement app.js integration and transient suggestion rendering**
  - Add controller helpers to expose trusted current snapshots and candidate existence checks.
  - Show positive suggestions with save controls and negative suggestions without save controls.
  - Reuse normal option save API; after save, reload options, retain current selections, and select the saved positive item.
- [ ] **Step 5: Add failure-path and stale-response tests, then run all JavaScript tests**

## Task 7: Verify the complete change, review, commit, push, and integrate

**Files:** All changed files plus design/plan docs.

**Interfaces:** No new interfaces; this task proves the feature and project remain coherent.

- [ ] **Step 1: Update documentation only where implementation clarified exact names or limits**
  - Keep F25 free of progress notes and ensure feature overview, glossary, screen design, and code use the same terms.
- [ ] **Step 2: Run focused feature suites**
  - PHP: all new Unit, Infrastructure, and HTTP tests.
  - JS: all new random-idea tests plus prompt-preparation integration tests.
- [ ] **Step 3: Build delivery assets**
  - Run `docker compose run --rm -T node npm run build`; expect a successful Vite build and updated `public/build/manifest.json`/assets as configured by the repository.
- [ ] **Step 4: Run required full verification**
  - Run `sh scripts/check.sh`; expect exit 0.
  - Run `docker compose run --rm -T app composer test`; expect all tests PASS.
  - Run `git diff --check`; expect no output.
  - Validate relative Markdown links for all changed documentation.
- [ ] **Step 5: Perform the main-agent review against every category in `docs/review/コードレビュー基準.md`**
  - Record findings for architecture, names, method behavior, tests, end-to-end purpose, error handling, security/privacy, frontend accessibility/state, and unverified browser-only behavior.
  - Do not use a subagent for this code review.
- [ ] **Step 6: Commit and push the feature branch only after Steps 1–5 pass**
  - `git add` the exact changed source, tests, generated build assets if tracked, and docs.
  - `git diff --cached --check`.
  - `git commit -m "feat: add coherent random illustration ideas"`.
  - `git push -u origin feature/random-illustration-generation`.
- [ ] **Step 7: Merge only the pushed commit into the ordinary `main` checkout**
  - Fast-forward merge from the primary checkout without switching it away from `main`.
  - Re-run `sh scripts/check.sh` and `docker compose run --rm -T app composer test` on `main`.
  - Do not push `main` unless explicitly requested.
- [ ] **Step 8: Clean up only after successful merge and main verification**
  - Remove the isolated worktree and local feature branch; retain the remote feature branch unless explicitly asked to delete it.
