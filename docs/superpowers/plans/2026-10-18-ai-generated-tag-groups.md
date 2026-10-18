# AI生成タググループ保存 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** AIプロンプト生成時に日本語ラベルとDanbooruタグ群の再利用候補を提示し、利用者が選んだ候補だけを既存オプションとして保存できるようにする。

**Architecture:** F20の外部LLM境界と生成DTOを候補一覧まで拡張し、保存は専用の更新ユースケースが既存のオプショングループ・項目集約をトランザクション内で操作する。`system_key`で保存先を識別して既存の参照APIへシステム管理状態を公開し、生成モーダルとオプション管理UIが同じ一覧再取得機構を共有する。

**Tech Stack:** PHP 8.5、Laravel、Eloquent、Blade、ES Modules、JSDOM、PHPUnit、Node test runner、Docker Compose

**Spec:** `docs/design/features/F22-AI生成タググループの保存.md`

## Global Constraints

- ブラウザー操作、実DBデータ、アップロード済み永続ストレージ、実ログは参照しない。Fake、factory、テストDB、JSDOMだけを使う。
- 候補は同一のLLM応答に1〜10件含め、`natural_language`選択時も候補タグはDanbooru形式とする。
- ラベルはtrim後1〜100文字、タグはカンマと空白以外を1文字以上含む。
- 候補は全件未選択で表示し、利用者が選択した候補だけを保存する。
- 保存先は`system_key = ai_prompt_suggestions`、表示名`AIプロンプト`、未分類、複数選択のグループとする。
- システム管理グループ自体の名前・分類・順序・選択方式の変更を拒否し、項目操作は許可する。
- 保存成功後は一覧を再取得するが、新規項目を自動選択しない。保存失敗時はプレビューと候補選択を保持する。
- 指示、候補、positive／negative、上流生応答をログへ記録しない。
- PHP・Composer・Node.js・npmはDockerサービス内で実行する。
- フロントエンド変更後は`docker compose run --rm -T node npm run build`、全変更後は`sh scripts/check.sh`を成功させる。
- コードレビュースキルは利用者の指定により使用しない。最終確認はテスト、静的検査、差分の自己確認で行う。

## Review Focus

- LLMが候補配列を欠落、空配列、11件、非オブジェクト項目で返した場合、positiveだけを成功扱いせず502になることをTask 1で固定する。
- 同一タグ集合の空白・大小文字・順序・要求内重複が二重保存されず、異なる候補だけが提案順で保存されることをTask 3で固定する。
- 最初の保存失敗がグループ作成を含めてロールバックされ、再試行可能であることをTask 3で固定する。
- システム管理グループが通常APIやドラッグ操作から変更されず、同名の利用者グループは通常どおり変更できることをTask 2とTask 4で固定する。
- 保存API成功後の一覧再取得に失敗しても保存済みと案内し、保存API自体の失敗では候補チェックを保持することをTask 5で固定する。

---

### Task 1: LLM生成契約へ再利用候補を追加する

**Files:**
- Create: `app/Application/PromptPreparation/DTO/ReusableIllustrationTagGroup.php`
- Modify: `app/Application/PromptPreparation/DTO/GeneratedIllustrationPrompt.php`
- Modify: `app/Application/PromptPreparation/Writes/GenerateIllustrationPrompt/GenerateIllustrationPromptResult.php`
- Modify: `app/Application/PromptPreparation/Writes/GenerateIllustrationPrompt/GenerateIllustrationPromptHandler.php`
- Modify: `app/Infrastructure/PromptPreparation/External/IllustrationLlm/OllamaIllustrationPromptGenerator.php`
- Modify: `app/Http/Controllers/PromptPreparation/Writes/GenerateIllustrationPromptController.php`
- Modify: `resources/prompts/illustration/danbooru_tags.txt`
- Modify: `resources/prompts/illustration/natural_language.txt`
- Test: `tests/Unit/Application/PromptPreparation/GenerateIllustrationPromptHandlerTest.php`
- Test: `tests/Feature/Infrastructure/PromptPreparation/OllamaIllustrationPromptGeneratorTest.php`
- Test: `tests/Feature/Http/PromptPreparation/IllustrationPromptGenerationApiTest.php`

**Interfaces:**
- Consumes: 既存の`IllustrationPromptGenerator::generate(string, string): GeneratedIllustrationPrompt`。
- Produces: `ReusableIllustrationTagGroup::__construct(string $label, string $tags)`、`GeneratedIllustrationPrompt::$reusableTagGroups`、`GenerateIllustrationPromptResult::$reusableTagGroups`。HTTPでは`reusableTagGroups: list<{label: string, tags: string}>`を返す。

- [ ] **Step 1: ApplicationとHTTPの失敗テストを書く**

`GenerateIllustrationPromptHandlerTest`でFakeが返す候補が結果へ同じ順序で伝播することを、`IllustrationPromptGenerationApiTest`でcamelCaseの候補配列が既存項目とともに返ることを追加する。期待値は「黒髪ロングの女の子 / `1girl, solo, long black hair`」と「夕暮れの教室 / `classroom, sunset`」をリテラルで使う。

- [ ] **Step 2: 対象テストを実行してREDを確認する**

Run: `docker compose run --rm -T app php artisan test tests/Unit/Application/PromptPreparation/GenerateIllustrationPromptHandlerTest.php tests/Feature/Http/PromptPreparation/IllustrationPromptGenerationApiTest.php`

Expected: 候補DTOまたは`reusableTagGroups`が未定義のためFAIL。

- [ ] **Step 3: Application DTOとHTTP変換を最小実装する**

`ReusableIllustrationTagGroup`をreadonly DTOとして追加し、生成DTO・結果DTO・Handler・Controllerへ`list<ReusableIllustrationTagGroup>`を通す。Controllerだけで`label`と`tags`の配列へ変換する。

- [ ] **Step 4: ApplicationとHTTPテストのGREENを確認する**

Run: Step 2と同じ。

Expected: PASS。

- [ ] **Step 5: Ollama構造化出力と応答境界の失敗テストを書く**

正常テストへ`reusable_tag_groups`とSchemaの`minItems: 1`、`maxItems: 10`、項目の必須`label`・`tags`を追加する。不正応答DataProviderへ、配列欠落、空、11件、リストでない値、非連想配列項目、空・101文字ラベル、文字列でないタグ、カンマとUnicode空白だけのタグを追加し、すべて`InvalidIllustrationPromptResponse`になることを検証する。成功ログにラベル・タグが含まれないことも検証する。

- [ ] **Step 6: Infrastructureテストを実行してREDを確認する**

Run: `docker compose run --rm -T app php artisan test tests/Feature/Infrastructure/PromptPreparation/OllamaIllustrationPromptGeneratorTest.php`

Expected: Schemaと候補解析が未実装のためFAIL。

- [ ] **Step 7: Schema、厳格な応答検証、System Promptを実装する**

Schemaへ`reusable_tag_groups`を必須追加する。各項目を型、trim後のUnicode文字数、`/[^,\s]/u`、件数で検証し、不正なら既存の安全な`invalidResponse()`を使う。両System Promptに、主要な意味単位を日本語ラベルとDanbooruタグ群で1〜10件返す指示を加える。

- [ ] **Step 8: Task 1の全テストをGREENにする**

Run: `docker compose run --rm -T app php artisan test tests/Unit/Application/PromptPreparation/GenerateIllustrationPromptHandlerTest.php tests/Feature/Infrastructure/PromptPreparation/OllamaIllustrationPromptGeneratorTest.php tests/Feature/Http/PromptPreparation/IllustrationPromptGenerationApiTest.php`

Expected: PASS。

- [ ] **Step 9: Task 1をコミットする**

```bash
git add app/Application/PromptPreparation app/Infrastructure/PromptPreparation/External/IllustrationLlm app/Http/Controllers/PromptPreparation/Writes/GenerateIllustrationPromptController.php resources/prompts/illustration tests/Unit/Application/PromptPreparation/GenerateIllustrationPromptHandlerTest.php tests/Feature/Infrastructure/PromptPreparation/OllamaIllustrationPromptGeneratorTest.php tests/Feature/Http/PromptPreparation/IllustrationPromptGenerationApiTest.php
git commit -m "feat: return reusable AI tag groups"
```

### Task 2: システム管理オプショングループを永続化して保護する

**Files:**
- Create: `database/migrations/2026_10_18_000000_add_system_key_to_option_prompt_groups.php`
- Create: `app/Domain/PromptPreparation/Exceptions/SystemManagedOptionPromptGroup.php`
- Modify: `app/Domain/PromptPreparation/Models/OptionPromptGroup.php`
- Modify: `app/Domain/PromptPreparation/Repositories/OptionPromptGroupRepository.php`
- Modify: `app/Infrastructure/PromptPreparation/Persistence/Eloquent/Models/OptionPromptGroupRecord.php`
- Modify: `app/Infrastructure/PromptPreparation/Persistence/Eloquent/Repositories/EloquentOptionPromptGroupRepository.php`
- Modify: `app/Application/PromptPreparation/Writes/SaveOptionPromptGroup/SaveOptionPromptGroupHandler.php`
- Modify: `app/Application/PromptPreparation/Writes/ChangeOptionPromptGroupCategory/ChangeOptionPromptGroupCategoryHandler.php`
- Modify: `app/Application/PromptPreparation/Writes/MoveOptionPromptGroup/MoveOptionPromptGroupHandler.php`
- Modify: `app/Http/Controllers/PromptPreparation/Writes/SaveOptionPromptGroupController.php`
- Modify: `app/Http/Controllers/PromptPreparation/Writes/ChangeOptionPromptGroupCategoryController.php`
- Modify: `app/Http/Controllers/PromptPreparation/Writes/MoveOptionPromptGroupController.php`
- Test: `tests/Unit/Domain/PromptPreparation/NamedPromptModelsTest.php`
- Test: `tests/Feature/Http/PromptPreparation/PromptOptionApiTest.php`

**Interfaces:**
- Consumes: Task 3が`OptionPromptGroup`とRepositoryを使って専用グループを取得・作成する。
- Produces: `OptionPromptGroup::$systemKey`、`OptionPromptGroup::isSystemManaged(): bool`、`OptionPromptGroup::assertUserManageable(): void`、`OptionPromptGroupRepository::findBySystemKeyForUpdate(string): ?OptionPromptGroup`。既存のグループ更新APIは保護対象に`409 Conflict`を返す。

- [ ] **Step 1: ドメイン不変条件とAPI保護の失敗テストを書く**

`NamedPromptModelsTest`で通常グループは変更可能、`ai_prompt_suggestions`付きグループは`assertUserManageable()`と`changeCategory()`を拒否することを追加する。`PromptOptionApiTest`でシステム管理グループの名前・選択方式、カテゴリ、順序変更が409、同名で`system_key = null`のグループは変更可能であることをテストDBで検証する。

- [ ] **Step 2: REDを確認する**

Run: `docker compose run --rm -T app php artisan test tests/Unit/Domain/PromptPreparation/NamedPromptModelsTest.php tests/Feature/Http/PromptPreparation/PromptOptionApiTest.php`

Expected: `systemKey`と保護処理がなくFAIL。

- [ ] **Step 3: migration、ドメイン、Repositoryを実装する**

nullable uniqueな`system_key`を追加し、Eloquentのfillable・property、Domain再構築・保存へ通す。`SystemManagedOptionPromptGroup`は業務例外として内部キーを外部へ露出しないメッセージを持つ。Repositoryへロック付き内部キー検索を追加する。

- [ ] **Step 4: 既存更新ユースケースとControllerで保護する**

Save更新、カテゴリ変更、移動は対象グループ取得後に`assertUserManageable()`を呼ぶ。移動はRepository直呼び前に`getForUpdate()`する。3 Controllerは業務例外を`409`の一般メッセージへ変換する。通常グループ作成時の`systemKey`は常に`null`とする。

- [ ] **Step 5: Task 2をGREENにする**

Run: Step 2と同じ。

Expected: PASS。

- [ ] **Step 6: Task 2をコミットする**

```bash
git add database/migrations app/Domain/PromptPreparation app/Infrastructure/PromptPreparation/Persistence/Eloquent app/Application/PromptPreparation/Writes/SaveOptionPromptGroup app/Application/PromptPreparation/Writes/ChangeOptionPromptGroupCategory app/Application/PromptPreparation/Writes/MoveOptionPromptGroup app/Http/Controllers/PromptPreparation/Writes tests/Unit/Domain/PromptPreparation/NamedPromptModelsTest.php tests/Feature/Http/PromptPreparation/PromptOptionApiTest.php
git commit -m "feat: protect system prompt groups"
```

### Task 3: 選択候補のトランザクション一括保存APIを追加する

**Files:**
- Create: `app/Application/PromptPreparation/Writes/SaveIllustrationPromptTagGroups/IllustrationPromptTagGroupInput.php`
- Create: `app/Application/PromptPreparation/Writes/SaveIllustrationPromptTagGroups/SaveIllustrationPromptTagGroupsInput.php`
- Create: `app/Application/PromptPreparation/Writes/SaveIllustrationPromptTagGroups/SaveIllustrationPromptTagGroupsResult.php`
- Create: `app/Application/PromptPreparation/Writes/SaveIllustrationPromptTagGroups/SaveIllustrationPromptTagGroupsHandler.php`
- Create: `app/Http/Requests/PromptPreparation/SaveIllustrationPromptTagGroupsRequest.php`
- Create: `app/Http/Controllers/PromptPreparation/Writes/SaveIllustrationPromptTagGroupsController.php`
- Modify: `app/Domain/PromptPreparation/Repositories/OptionPromptRepository.php`
- Modify: `app/Infrastructure/PromptPreparation/Persistence/Eloquent/Repositories/EloquentOptionPromptRepository.php`
- Modify: `routes/web.php`
- Test: `tests/Unit/Application/PromptPreparation/SaveIllustrationPromptTagGroupsHandlerTest.php`
- Test: `tests/Feature/Http/PromptPreparation/IllustrationPromptTagGroupApiTest.php`

**Interfaces:**
- Consumes: Task 2の`findBySystemKeyForUpdate()`、`OptionPromptGroup::$systemKey`、既存の`TransactionManager`・`OptionPrompt`・`PromptText`。
- Produces: `SaveIllustrationPromptTagGroupsHandler::handle(SaveIllustrationPromptTagGroupsInput): SaveIllustrationPromptTagGroupsResult`、`OptionPromptRepository::getAllInGroupForUpdate(int): list<OptionPrompt>`、`POST /illustration-prompt-tag-groups`の`{groupId, savedCount, duplicateCount}`。

- [ ] **Step 1: Handlerの重複判定と保存順の失敗テストを書く**

RepositoryとTransactionManagerのインメモリFakeを使い、初回グループ作成、既存グループ再利用、空白・大小文字・タグ順を無視した既存重複、要求内重複は先頭だけ保存、異なる候補は入力順で末尾保存、別グループへ移動済みの同一内容は保存可能、例外時はFake状態がロールバックされることを検証する。保存内容は`PromptText::fromInput()`による提案順の`tag one, tag two,`を期待する。

- [ ] **Step 2: HandlerテストのREDを確認する**

Run: `docker compose run --rm -T app php artisan test tests/Unit/Application/PromptPreparation/SaveIllustrationPromptTagGroupsHandlerTest.php`

Expected: 保存ユースケースが未定義でFAIL。

- [ ] **Step 3: 入力・結果DTO、正規化、Handlerを最小実装する**

正規化キーはカンマ分割、trim、空除去、`strtolower`、sort、区切り結合で作る。トランザクション内でシステムグループを検索し、なければ固定値で末尾へ作成する。既存項目と要求内のキーSetを使って件数を数え、新規項目だけを連番で保存する。

- [ ] **Step 4: HandlerテストをGREENにする**

Run: Step 2と同じ。

Expected: PASS。

- [ ] **Step 5: HTTP入力・応答・ロールバックの失敗テストを書く**

1件・10件、ラベル1文字・100文字、有効タグを200で受け付ける。欠落、0件、11件、配列でない値、ラベル空白・101文字、タグ空白・カンマだけ・不正型を422にする。利用者作成の同名グループがあっても別の`system_key`付きグループへ保存すること、全件重複でも200と件数を返すこと、DB例外時に作成グループを含めて残らないことをテストDBで検証する。

- [ ] **Step 6: HTTPテストのREDを確認する**

Run: `docker compose run --rm -T app php artisan test tests/Feature/Http/PromptPreparation/IllustrationPromptTagGroupApiTest.php`

Expected: route、Request、ControllerがなくFAIL。

- [ ] **Step 7: Request、Controller、route、Eloquent取得を実装する**

FormRequestで`groups`と各`label`・`tags`を検証し、trim済みInputへ変換する。ControllerはHandler結果を常に200で返す。Eloquent Repositoryは対象グループの項目をposition順、ロック付きでDomainへ復元する。

- [ ] **Step 8: Task 3をGREENにする**

Run: `docker compose run --rm -T app php artisan test tests/Unit/Application/PromptPreparation/SaveIllustrationPromptTagGroupsHandlerTest.php tests/Feature/Http/PromptPreparation/IllustrationPromptTagGroupApiTest.php`

Expected: PASS。

- [ ] **Step 9: Task 3をコミットする**

```bash
git add app/Application/PromptPreparation/Writes/SaveIllustrationPromptTagGroups app/Http/Requests/PromptPreparation/SaveIllustrationPromptTagGroupsRequest.php app/Http/Controllers/PromptPreparation/Writes/SaveIllustrationPromptTagGroupsController.php app/Domain/PromptPreparation/Repositories/OptionPromptRepository.php app/Infrastructure/PromptPreparation/Persistence/Eloquent/Repositories/EloquentOptionPromptRepository.php routes/web.php tests/Unit/Application/PromptPreparation/SaveIllustrationPromptTagGroupsHandlerTest.php tests/Feature/Http/PromptPreparation/IllustrationPromptTagGroupApiTest.php
git commit -m "feat: save reusable AI tag groups"
```

### Task 4: 参照APIと管理UIへシステム管理状態を公開する

**Files:**
- Modify: `app/Application/PromptPreparation/Queries/GetPromptOptions/GetPromptOptionsResult.php`
- Modify: `app/Infrastructure/PromptPreparation/Queries/EloquentOptionPromptQueryService.php`
- Modify: `resources/js/prompt-options-page.js`
- Test: `tests/Feature/Http/PromptPreparation/PromptOptionApiTest.php`
- Test: `tests/JavaScript/prompt-categories-page.test.js`

**Interfaces:**
- Consumes: Task 2の`option_prompt_groups.system_key`。
- Produces: prompt-options一覧の各groupへ`systemManaged: bool`を追加する。`initializePromptOptionsPage()`はTask 5が呼ぶ`reload(): Promise<void>`を返す。

- [ ] **Step 1: 参照契約と管理操作制限の失敗テストを書く**

HTTPテストで通常グループは`systemManaged: false`、内部キー付きグループは`true`を返すことを追加する。JSDOMテストでシステム管理グループには管理表示を付け、グループのドラッグ、上下移動、編集、カテゴリselectを無効または非表示にし、項目追加・項目編集・移動先選択・削除は利用可能なことを検証する。通常の同名グループは制限されないことも固定する。

- [ ] **Step 2: REDを確認する**

Run: `docker compose run --rm -T app php artisan test tests/Feature/Http/PromptPreparation/PromptOptionApiTest.php && docker compose run --rm -T node node --test --test-name-pattern="システム管理" tests/JavaScript/prompt-categories-page.test.js`

Expected: `systemManaged`とUI制限がなくFAIL。

- [ ] **Step 3: Query DTO・SQL変換・管理UI制限を実装する**

Queryは`system_key`を取得してbooleanだけを外部へ返し、内部キー文字列は返さない。管理描画は`group.systemManaged`でグループ単位操作を無効化し「システム管理」を表示するが、項目単位操作には条件を付けない。公開`reload()`は既存の`load()`を呼び、選択済みIDを保持できる既存動作を再利用する。

- [ ] **Step 4: Task 4をGREENにする**

Run: `docker compose run --rm -T app php artisan test tests/Feature/Http/PromptPreparation/PromptOptionApiTest.php && docker compose run --rm -T node node --test tests/JavaScript/prompt-categories-page.test.js tests/JavaScript/prompt-preparation-page.test.js`

Expected: PASS。

- [ ] **Step 5: Task 4をコミットする**

```bash
git add app/Application/PromptPreparation/Queries/GetPromptOptions/GetPromptOptionsResult.php app/Infrastructure/PromptPreparation/Queries/EloquentOptionPromptQueryService.php resources/js/prompt-options-page.js tests/Feature/Http/PromptPreparation/PromptOptionApiTest.php tests/JavaScript/prompt-categories-page.test.js
git commit -m "feat: expose system managed prompt groups"
```

### Task 5: 生成モーダルで候補を選択保存し既存一覧を更新する

**Files:**
- Modify: `app/Http/Controllers/PromptPreparation/PromptPreparationPageController.php`
- Modify: `resources/views/prompt-preparation.blade.php`
- Modify: `resources/js/app.js`
- Modify: `resources/js/illustration-prompt-generator.js`
- Modify: `resources/css/app.css`
- Modify: `tests/Feature/TopPageTest.php`
- Modify: `tests/JavaScript/illustration-prompt-generator.test.js`
- Modify: `tests/JavaScript/prompt-preparation-page.test.js`

**Interfaces:**
- Consumes: Task 1の`reusableTagGroups`、Task 3の保存API、Task 4の`promptCategoriesController.reload(): Promise<void>`。
- Produces: `initializeIllustrationPromptGenerator()`が候補の描画、選択、保存、結果通知を担当する。保存成功後に注入された`reloadPromptOptions(): Promise<void>`を呼ぶ。

- [ ] **Step 1: Blade配線と候補表示・初期未選択の失敗テストを書く**

Page Feature testで保存URLが`data-illustration-prompt-tag-groups-url`へ出ることを検証する。JSDOM fixtureへ候補section、候補list、保存button、候補statusを追加し、生成成功後に日本語ラベルとタグが`textContent`で表示され、全件未選択、未選択時は保存不可であることを検証する。既存の生成成功fixtureにも有効な`reusableTagGroups`を加える。

- [ ] **Step 2: REDを確認する**

Run: `docker compose run --rm -T app php artisan test tests/Feature/TopPageTest.php && docker compose run --rm -T node node --test tests/JavaScript/illustration-prompt-generator.test.js`

Expected: URLと候補DOM処理がなくFAIL。

- [ ] **Step 3: Blade、CSS、生成結果検証と候補描画を実装する**

ページへ保存route URLを渡し、モーダルへ候補領域を追加する。`isGenerationResult`は1〜10件すべての`label`・`tags`型も検証する。描画はDOM APIと`textContent`だけを使い、生成ごとに候補と選択を置き換える。

- [ ] **Step 4: 表示テストをGREENにする**

Run: Step 2と同じ。

Expected: PASS。

- [ ] **Step 5: 保存成功・重複・失敗・二重送信・一覧再取得失敗の失敗テストを書く**

選択した候補だけをCSRF付きPOSTで送ること、保存中の二重送信を防ぐこと、200成功で保存件数・重複件数を通知して対象チェックだけ外し`reloadPromptOptions()`を一度呼ぶこと、新規項目を選択しないことを検証する。保存API失敗ではチェック・プレビューを保持する。一覧再取得だけ失敗した場合はチェックを外し「保存済みだが一覧更新に失敗した」案内を出す。候補保存中の「出力へ反映」は独立して動作することも検証する。

- [ ] **Step 6: 保存テストのREDを確認する**

Run: `docker compose run --rm -T node node --test tests/JavaScript/illustration-prompt-generator.test.js tests/JavaScript/prompt-preparation-page.test.js`

Expected: 保存処理とreload注入がなくFAIL。

- [ ] **Step 7: 保存処理と一覧再取得の配線を実装する**

生成モジュールへ`reloadPromptOptions`を注入し、候補保存専用のpending状態でbuttonを制御する。`app.js`はTask 4の`reload()`を渡す。生成要求のabort状態と保存要求を混同せず、モーダルを閉じても既に完了した保存結果を巻き戻さない。

- [ ] **Step 8: Task 5をGREENにする**

Run: `docker compose run --rm -T app php artisan test tests/Feature/TopPageTest.php && docker compose run --rm -T node node --test tests/JavaScript/illustration-prompt-generator.test.js tests/JavaScript/prompt-preparation-page.test.js`

Expected: PASS。

- [ ] **Step 9: Task 5をコミットする**

```bash
git add app/Http/Controllers/PromptPreparation/PromptPreparationPageController.php resources/views/prompt-preparation.blade.php resources/js/app.js resources/js/illustration-prompt-generator.js resources/css/app.css tests/Feature/TopPageTest.php tests/JavaScript/illustration-prompt-generator.test.js tests/JavaScript/prompt-preparation-page.test.js
git commit -m "feat: save AI tag suggestions from generator"
```

### Task 6: 文書更新と全体検証

**Files:**
- Modify: `docs/機能実装状況.md`
- Modify: `docs/operations/AIプロンプト生成の操作方法.md`
- Test: 全PHP・JavaScriptテスト、静的検査、Viteビルド

**Interfaces:**
- Consumes: Tasks 1〜5の完成した画面・API動作。
- Produces: 利用者向け操作説明とF22の実装済み状態。

- [ ] **Step 1: 人間向け操作説明と実装状況を更新する**

操作方法へ、候補は日本語名とDanbooruタグ群で表示されること、初期未選択、必要な候補だけ保存すること、保存先「AIプロンプト」、重複スキップ、保存後の選択方法、Danbooruタグの短い説明を追記する。F22を実装済みに変更する。

- [ ] **Step 2: PHP全テストを実行する**

Run: `docker compose run --rm -T app composer test`

Expected: 全テストPASS。

- [ ] **Step 3: JavaScript全テストを実行する**

Run: `docker compose run --rm -T node npm run test:js`

Expected: 全テストPASS。

- [ ] **Step 4: 静的検査を実行する**

Run: `sh scripts/check.sh`

Expected: Pint、PHPStan/Larastan、ESLint、PrettierがすべてPASS。失敗時はルール無効化せず修正し再実行する。

- [ ] **Step 5: 配信用アセットをビルドする**

Run: `docker compose run --rm -T node npm run build`

Expected: Vite build成功、`public/build/manifest.json`更新。

- [ ] **Step 6: 差分とプライバシーを自己確認する**

Run: `git diff --check && git status --short`

Expected: 空白エラーなし。実データ、秘密情報、入力・候補・生成本文を記録するログ、無関係な変更がない。

- [ ] **Step 7: Task 6をコミットする**

```bash
git add docs/機能実装状況.md docs/operations/AIプロンプト生成の操作方法.md
git commit -m "docs: explain reusable AI tag groups"
```

- [ ] **Step 8: 完成ブランチの最終確認を行う**

設計F22、Review Focus、各タスクのテスト結果とブランチ全差分を照合する。利用者指定によりコードレビュースキルやレビュー用サブエージェントは使わず、自己確認でCritical/Importantの未解決がないことを確認する。
