# 人物LoRAの非表示 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 人物LoRAの登録情報を残したまま日常の選択・差し替え候補から外し、管理画面から安全に再表示できるようにする。

**Architecture:** `Lora` の表示状態をドメインモデルとRepositoryで保存し、既存Queryへ明示的な表示条件を渡す。通常の人物選択は常に表示中だけを取得し、管理専用ダイアログだけが非表示を含む一覧を取得する。差し替え送信はApplication層で最新状態を再確認し、ブラウザー側も既知候補キーと未チェック集合を分けて保持する。

**Tech Stack:** PHP 8.5、Laravel、SQLite、Blade、ES Modules、Node.js test runner、Docker Compose

**Spec:** `docs/design/features/F17-人物LoRAの非表示.md`

## Global Constraints

- 実データ、アップロード済み永続ストレージ、ブラウザーを確認しない。テストは架空データとFakeだけを使う。
- 更新は`Writes`、参照は既存QueryServiceを介し、ControllerからDBへ直接アクセスしない。
- 非表示は人物LoRAだけに許可し、衣装LoRAは常に表示中として扱う。
- 通常送信は非表示を理由に拒否せず、人物差し替え送信だけを最新状態で拒否する。
- フロントエンド変更後は`sh scripts/check.sh`、`composer test`、ViteビルドをDocker内で実行する。
- ブラウザー確認は行わず、JSDOMとHTTP／Applicationテストで検証する。

## Review Focus

- 通常のLoRA編集で既存の`is_hidden`が誤って`false`へ戻らないことをTask 1のHTTPテストで固定する。
- `visibility=hidden|all`でも衣装LoRAや非対象の子データが混入しないことをTask 2のQueryテストで固定する。
- 非表示操作中の保存失敗で選択や画面状態を先に破壊しないことをTask 3のJSDOMテストで固定する。
- 既知候補を再表示した際に「初回候補」と誤認して自動チェックしないことをTask 4のストレージ単体テストで固定する。
- 一括送信中に一件が非表示でも残りの候補を送信し続けることをTask 5のApplication／HTTPテストで固定する。

---

### Task 1: 表示状態のドメイン・永続化・更新API

**Files:**
- Create: `database/migrations/2026_10_24_000000_add_is_hidden_to_loras.php`
- Create: `app/Application/PromptPreparation/Writes/ChangeCharacterLoraVisibility/ChangeCharacterLoraVisibilityInput.php`
- Create: `app/Application/PromptPreparation/Writes/ChangeCharacterLoraVisibility/ChangeCharacterLoraVisibilityHandler.php`
- Create: `app/Application/PromptPreparation/Writes/ChangeCharacterLoraVisibility/ChangeCharacterLoraVisibilityResult.php`
- Create: `app/Http/Requests/PromptPreparation/ChangeCharacterLoraVisibilityRequest.php`
- Create: `app/Http/Controllers/PromptPreparation/Writes/ChangeCharacterLoraVisibilityController.php`
- Modify: `app/Domain/PromptPreparation/Models/Lora/Lora.php`
- Modify: `app/Domain/PromptPreparation/Repositories/LoraRepository.php`
- Modify: `app/Infrastructure/PromptPreparation/Persistence/Eloquent/Models/LoraRecord.php`
- Modify: `app/Infrastructure/PromptPreparation/Persistence/Eloquent/Repositories/EloquentLoraRepository.php`
- Modify: `app/Application/PromptPreparation/Writes/SaveLora/SaveLoraHandler.php`
- Modify: `routes/web.php`
- Test: `tests/Unit/Domain/PromptPreparation/LoraTest.php`
- Test: `tests/Feature/Http/PromptPreparation/LoraPromptOptionApiTest.php`

**Interfaces:**
- Produces: `Lora::withVisibility(bool $isHidden): self`、`LoraRepository::find(int $id, LoraKind $kind): Lora`、`PATCH /loras/{lora}/visibility`。
- Produces: `{id: int, isHidden: bool}`。不正booleanと衣装用途は422、存在しないIDは404。

- [ ] **Step 1: 表示状態、冪等更新、衣装用途拒否、通常編集時の状態維持を表す失敗テストを書く**
- [ ] **Step 2: 対象テストを実行し、カラム・API・メソッド未実装が理由で失敗することを確認する**
- [ ] **Step 3: 既定`false`のDB制約、ドメイン操作、Repository復元、Write、Request、Controller、Routeを最小実装する**
- [ ] **Step 4: 対象テストを再実行して成功を確認する**
- [ ] **Step 5: Task 1をコミットする**

### Task 2: 表示条件付き人物LoRA Query

**Files:**
- Create: `app/Application/PromptPreparation/Queries/GetLoraPromptOptions/CharacterLoraVisibility.php`
- Create: `app/Http/Requests/PromptPreparation/GetLoraPromptOptionsRequest.php`
- Modify: `app/Application/PromptPreparation/Ports/LoraPromptOptionQueryService.php`
- Modify: `app/Application/PromptPreparation/Queries/GetLoraPromptOptions/GetLoraPromptOptionsHandler.php`
- Modify: `app/Infrastructure/PromptPreparation/Queries/EloquentLoraPromptOptionQueryService.php`
- Modify: `app/Http/Controllers/PromptPreparation/Queries/GetLoraPromptOptionsController.php`
- Test: `tests/Feature/Http/PromptPreparation/LoraPromptOptionApiTest.php`

**Interfaces:**
- Consumes: Task 1の`loras.is_hidden`。
- Produces: `GET /lora-prompt-options?visibility=visible|hidden|all`。省略時は`visible`で、各人物に`isHidden`を返す。

- [ ] **Step 1: 省略時・hidden・allの人物／トリガー／服装投影と不正値422の失敗テストを書く**
- [ ] **Step 2: 対象テストを実行し、非表示人物が通常応答へ混入する失敗を確認する**
- [ ] **Step 3: enum、Request、Handler引数、DB絞り込みを最小実装する**
- [ ] **Step 4: 対象テストを再実行して成功を確認する**
- [ ] **Step 5: Task 2をコミットする**

### Task 3: 通常選択と表示状態管理UI

**Files:**
- Modify: `resources/views/prompt-preparation.blade.php`
- Modify: `resources/js/lora-options.js`
- Modify: `resources/js/lora-options-page.js`
- Modify: `resources/css/app.css`
- Test: `tests/JavaScript/lora-options.test.js`
- Test: `tests/JavaScript/lora-options-page.test.js`
- Test: `tests/Feature/TopPageTest.php`

**Interfaces:**
- Consumes: Task 1のvisibility更新APIとTask 2の`visibility` Query。
- Produces: 通常選択は表示中だけ、管理ダイアログは表示中／非表示／すべての検索・編集・非表示／再表示操作。
- Produces: 選択中人物を非表示にした成功後だけ人物・トリガー・服装選択を解除し、完成済み出力は変更しない通知。

- [ ] **Step 1: APIレスポンス検証、管理フィルター、冪等更新、選択解除、保存失敗時維持の失敗テストを書く**
- [ ] **Step 2: JavaScriptとTopPageの対象テストを実行し、管理UI未実装で失敗することを確認する**
- [ ] **Step 3: 管理ダイアログと通信処理を実装し、通常選択用stateと管理一覧stateを分離する**
- [ ] **Step 4: 対象テストを再実行して成功を確認する**
- [ ] **Step 5: Task 3をコミットする**

### Task 4: お気に入り復元と差し替えチェック状態

**Files:**
- Modify: `resources/js/lora-options-page.js`
- Modify: `resources/js/app.js`
- Modify: `resources/js/character-lora-variant-page.js`
- Modify: `resources/js/character-lora-variant.js`
- Test: `tests/JavaScript/lora-options-page.test.js`
- Test: `tests/JavaScript/prompt-preparation-page.test.js`
- Test: `tests/JavaScript/character-lora-variant-page.test.js`
- Test: `tests/JavaScript/character-lora-variant.test.js`

**Interfaces:**
- Consumes: 通常Queryから得る現在表示可能な候補。
- Produces: お気に入りの完成文面は常に復元し、非表示人物と子の選択復元だけを失敗扱いとしてラベルを通知する。
- Produces: `unchecked`と`knownCandidateKeys`を別キーで保存し、有効送信対象を「保存チェック ∩ 現在候補」に限定する。

- [ ] **Step 1: 非表示人物を含むお気に入り復元、候補消失時のチェック解除、再表示時未チェック維持の失敗テストを書く**
- [ ] **Step 2: 対象JavaScriptテストを実行して期待どおり失敗することを確認する**
- [ ] **Step 3: 復元結果と候補キー保存処理を最小実装する**
- [ ] **Step 4: 対象テストを再実行して成功を確認する**
- [ ] **Step 5: Task 4をコミットする**

### Task 5: 人物差し替え送信前のサーバー再確認

**Files:**
- Create: `app/Application/PromptPreparation/Exceptions/HiddenCharacterLora.php`
- Modify: `app/Application/PromptPreparation/Writes/QueueComfyUiPrompt/QueueComfyUiPromptHandler.php`
- Modify: `app/Application/PromptPreparation/Writes/QueueComfyUiPromptBatch/QueueComfyUiPromptBatchHandler.php`
- Modify: `app/Http/Requests/PromptPreparation/QueueComfyUiPromptRequest.php`
- Modify: `app/Http/Controllers/PromptPreparation/Writes/QueueComfyUiPromptController.php`
- Modify: `resources/js/comfyui-page.js`
- Modify: `resources/js/character-lora-variant-page.js`
- Test: `tests/Feature/Http/PromptPreparation/ComfyUiApiTest.php`
- Test: `tests/JavaScript/comfyui-page.test.js`
- Test: `tests/JavaScript/character-lora-variant-page.test.js`

**Interfaces:**
- Consumes: Task 1の`LoraRepository::find()`と`Lora::$isHidden`。
- Produces: 通常送信は`source=output`、差し替え候補は`source=characterVariant`。候補送信時だけ非表示人物を外部送信前に失敗させる。

- [ ] **Step 1: 通常送信は非表示でも成功、候補送信は拒否、一括では残り継続を表す失敗テストを書く**
- [ ] **Step 2: HTTP／JavaScript対象テストを実行し、非表示候補が送信される失敗を確認する**
- [ ] **Step 3: source契約とApplication層の最新状態確認を最小実装する**
- [ ] **Step 4: 対象テストを再実行して成功を確認する**
- [ ] **Step 5: Task 5をコミットする**

### Task 6: 文書整合・全体検証・セルフレビュー

**Files:**
- Modify: `docs/design/features/F17-人物LoRAの非表示.md`
- Modify: 必要な場合のみ `docs/spec/機能概要.md`、`docs/spec/用語集.md`

**Interfaces:**
- Consumes: Tasks 1〜5の確定した契約。
- Produces: 文書と実装の一致、配信用アセット、全レビューカテゴリの確認結果。

- [ ] **Step 1: 設計案だった契約を実装結果へ合わせ、進捗表現を置かず仕様だけを更新する**
- [ ] **Step 2: `docker compose run --rm -T node npm run build`を実行する**
- [ ] **Step 3: `sh scripts/check.sh`を実行する**
- [ ] **Step 4: `docker compose run --rm -T app composer test`を実行する**
- [ ] **Step 5: 差分をレビュー基準の全カテゴリでセルフレビューし、必要な修正後に検証を再実行する**
- [ ] **Step 6: 最終コミット、作業ブランチのorigin push、mainへのマージ、main上の再検証を行う**

