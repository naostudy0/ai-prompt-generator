# Character LoRA Variant Groups Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 人物LoRA差し替え候補のチェック集合を「人物LoRA＋特定トリガー」単位の名前付きグループとしてブラウザーへ保存し、呼び出して一括送信対象を完全に切り替えられるようにする。

**Architecture:** グループ名・候補キー・保存形式の検証と更新は新しい純粋JavaScriptモジュールへ分離し、既存のページコントローラーはDOM、確認ダイアログ、現在のチェック状態との同期だけを担当する。保存済みグループは既存チェック状態とは別の`localStorage`キーへバージョン付きJSONで保存し、Laravel API・DB・永続ストレージは変更しない。

**Tech Stack:** ES Modules、JSDOM、Node.js test runner、Laravel Blade、CSS、Vite（すべて既存依存のみ）

**Spec:** `docs/design/features/F21-人物LoRA差し替えグループ.md`

## Global Constraints

- 候補キーは`lora:{LoRA ID}:trigger:{トリガーID}`、トリガーなしは`lora:{LoRA ID}:trigger:none`とする。
- グループ名は前後空白を除いた1文字以上100文字以下とし、同名比較では大文字小文字を区別しない。
- 空のグループを許可し、候補キーは重複を除いて順序を保持する。
- 呼び出しは検索・系統絞り込み・ページ位置に関係なく、取得済み全候補の現在チェックを完全に置き換える。
- 保存対象はグループ名と候補キーだけとし、プロンプト、人物名、ファイル名、トリガー名・文面、画像、生成結果を含めない。
- DB、サーバー内ストレージ、Cookie、Laravel API、追加依存を使用しない。
- ブラウザー自動操作と実データ参照を行わず、JSDOMと架空データで検証する。
- フロントエンド変更後は`docker compose run --rm -T node npm run build`と`sh scripts/check.sh`を実行する。

## Review Focus

- 壊れたJSON、未知のバージョン、不正なグループ要素を読み込んでも例外で画面を停止せず、グループなしとして扱うテストをTask 1へ置く。
- 大文字小文字と前後空白だけが異なる名前を同名として検出し、確認キャンセル時に保存内容を変えない統合テストをTask 2へ置く。
- 保存後に削除・非表示となった候補を呼び出した場合、利用可能な候補だけを選択し不足件数を通知する統合テストをTask 2へ置く。
- `localStorage`の読み書き失敗時も現在ページのチェック操作を継続でき、グループのメモリ状態を誤って更新しない統合テストをTask 2へ置く。
- 検索・系統絞り込み・ページ分割中の呼び出しでも全候補を置換し、絞り込み状態と既存のコピー・一括送信を壊さない回帰テストをTask 2へ置く。

---

### Task 1: グループ規則と保存形式

**Files:**
- Create: `resources/js/character-lora-variant-groups.js`
- Create: `tests/JavaScript/character-lora-variant-groups.test.js`
- Modify: `resources/js/character-lora-variant.js`
- Modify: `tests/JavaScript/character-lora-variant.test.js`

**Interfaces:**
- Consumes: `getCharacterLoraCandidates(options, searchText)`が返す`{ lora, trigger }`候補。
- Produces: `createCharacterLoraCandidateKey(lora, trigger): string`、`parseCharacterLoraVariantGroups(serialized): Array<{name: string, candidateKeys: string[]}>`、`serializeCharacterLoraVariantGroups(groups): string`、`prepareCharacterLoraVariantGroup(name, candidateKeys): {name: string, candidateKeys: string[]}`、`findCharacterLoraVariantGroupIndex(groups, name): number`、`replaceCharacterLoraVariantGroup(groups, group, index): Array`、`removeCharacterLoraVariantGroup(groups, index): Array`、`resolveCharacterLoraVariantGroup(candidateKeys, availableKeys): {selectedKeys: Set<string>, unavailableCount: number}`。

- [ ] **Step 1: 候補キーとグループ規則の失敗テストを書く**

`tests/JavaScript/character-lora-variant-groups.test.js`へ、正常なバージョン1 JSONの復元、壊れたJSON・未知バージョン・不正な名前/候補配列の拒否、名前のtrimと1/100/101文字境界、候補キーの重複除去と順序保持、大文字小文字を無視する同名検索、追加・指定位置上書き・削除が元配列を変更しないこと、利用可能キーとの積集合と不足件数を検証するテストを書く。`tests/JavaScript/character-lora-variant.test.js`へ、数値トリガーIDとトリガーなしの候補キーを検証するテストを書く。

- [ ] **Step 2: 新規テストが未実装で失敗することを確認する**

Run: `docker compose run --rm -T node node --test tests/JavaScript/character-lora-variant-groups.test.js tests/JavaScript/character-lora-variant.test.js`
Expected: FAIL（新規モジュールまたはexportが存在しない）。

- [ ] **Step 3: 候補キー生成を既存モジュールへ実装する**

`resources/js/character-lora-variant.js`へ`createCharacterLoraCandidateKey(lora, trigger)`をexportし、既存と同じ候補キー形式を一箇所で生成できるようにする。

- [ ] **Step 4: グループの純粋関数を実装する**

`resources/js/character-lora-variant-groups.js`へInterfaces記載の関数を実装する。読み込み時の不正データは空配列へフォールバックし、保存用JSONは`{ version: 1, groups }`だけを含める。名前違反は利用者向け日本語メッセージを持つ`Error`として返し、更新関数は入力配列を直接変更しない。

- [ ] **Step 5: グループ規則のテストが通ることを確認する**

Run: `docker compose run --rm -T node node --test tests/JavaScript/character-lora-variant-groups.test.js tests/JavaScript/character-lora-variant.test.js`
Expected: PASS。

- [ ] **Step 6: Task 1をコミットする**

```bash
git add resources/js/character-lora-variant.js resources/js/character-lora-variant-groups.js tests/JavaScript/character-lora-variant.test.js tests/JavaScript/character-lora-variant-groups.test.js
git commit -m "feat: add character variant group rules"
```

### Task 2: モーダルのグループ操作とチェック同期

**Files:**
- Modify: `resources/js/character-lora-variant-page.js`
- Modify: `tests/JavaScript/character-lora-variant-page.test.js`

**Interfaces:**
- Consumes: Task 1の全exportと、既存の`comfyui-character-variant-unchecked-v1`チェック状態。
- Produces: `initializeCharacterLoraVariantPage(...)`が`data-character-variant-group-*`要素を制御し、`comfyui-character-variant-groups-v1`へグループを保存する。外部公開するコントローラー形状は既存の`{ updateAvailability }`を維持する。

- [ ] **Step 1: 保存・上書き・削除の失敗テストを書く**

`tests/JavaScript/character-lora-variant-page.test.js`へ、現在チェックから新規グループを保存して一覧へ反映すること、空グループを保存できること、空/101文字名を拒否すること、大文字小文字・前後空白が異なる同名保存で対象名と件数を確認しキャンセル時は不変・承認時だけ表示名と内容を更新すること、削除も確認後だけ実行し現在チェックを変えないことをJSDOMで検証する。

- [ ] **Step 2: 呼び出しと失敗時動作の失敗テストを書く**

同テストへ、グループ呼び出しが未保存変更を破棄して全候補を置換すること、検索・系統絞り込み・ページ位置を維持すること、存在しない候補を除外して不足件数を表示すること、新規候補を自動追加しないこと、呼び出した選択だけが一括送信要求へ入ること、壊れた保存値・読み取り失敗では全選択から操作可能なこと、グループ書き込み失敗では一覧を元に戻す一方でチェック変更は画面内で続けられることを検証する。

- [ ] **Step 3: ページ統合テストが未実装で失敗することを確認する**

Run: `docker compose run --rm -T node node --test tests/JavaScript/character-lora-variant-page.test.js`
Expected: FAIL（グループUI制御が存在しない）。

- [ ] **Step 4: グループ状態の読み込みと描画を実装する**

`resources/js/character-lora-variant-page.js`でTask 1の関数をimportし、候補キーのローカル実装を置き換える。初期化時にグループを安全に読み込み、選択欄、呼び出し・削除ボタン、グループ名入力、選択件数を現在状態から描画する。読み取り失敗はグループなし・チェック全選択として開始し、状態欄へ永続化不可を表示する。

- [ ] **Step 5: 保存・呼び出し・削除とロールバックを実装する**

保存は現在選択キーのスナップショットを作り、同名時だけ`view.confirm()`を通して上書きする。呼び出しは取得済み全候補から`unchecked`を再構築し、既存チェック用キーへ保存して一覧と件数を再描画する。削除は確認後だけ行う。グループ書き込みは新状態の永続化成功後にメモリへ反映し、失敗時は操作前のグループを維持する。

- [ ] **Step 6: ページ統合と既存回帰テストが通ることを確認する**

Run: `docker compose run --rm -T node node --test tests/JavaScript/character-lora-variant-page.test.js tests/JavaScript/prompt-preparation-page.test.js`
Expected: PASS。既存の一件コピー、negativeコピー、単独送信、ページ分割、一括送信もPASS。

- [ ] **Step 7: Task 2をコミットする**

```bash
git add resources/js/character-lora-variant-page.js tests/JavaScript/character-lora-variant-page.test.js
git commit -m "feat: manage character variant groups"
```

### Task 3: グループ管理UIと全体検証

**Files:**
- Modify: `resources/views/prompt-preparation.blade.php`
- Modify: `resources/css/app.css`
- Modify: `tests/Feature/TopPageTest.php`
- Modify: `docs/機能実装状況.md`

**Interfaces:**
- Consumes: Task 2が参照する`data-character-variant-group-select`、`data-character-variant-group-load`、`data-character-variant-group-delete`、`data-character-variant-group-name`、`data-character-variant-group-save`。
- Produces: 人物LoRA差し替えモーダル内のラベル付きグループ操作UIと、既存画面幅で操作欄を折り返せるスタイル。

- [ ] **Step 1: Blade表示の失敗テストを書く**

`tests/Feature/TopPageTest.php`へ、グループ選択、呼び出し、削除、名前入力（`maxlength="100"`）、現在チェックで保存、全選択、全解除、選択件数の各要素とラベルがトップページHTMLに含まれることを検証する。テストDBは既存のテスト用初期化だけを使用し、実データを参照しない。

- [ ] **Step 2: Bladeテストが未実装で失敗することを確認する**

Run: `docker compose run --rm -T app php artisan test tests/Feature/TopPageTest.php`
Expected: FAIL（グループ管理要素が存在しない）。

- [ ] **Step 3: グループ管理UIとスタイルを実装する**

`resources/views/prompt-preparation.blade.php`で候補一覧より前へ設計書どおりのグループ選択行、名前付き保存行、全選択・全解除・件数行を配置する。`resources/css/app.css`では既存ボタン・入力スタイルを再利用し、グループ管理欄と各操作行が狭い幅で折り返す最小限のレイアウトだけを追加する。

- [ ] **Step 4: 実装状況を更新する**

`docs/機能実装状況.md`のF21を「実装済み」へ変更する。仕様変更はせず、実装中に設計との差異が生じた場合だけF21詳細設計も同じコミットで整合させる。

- [ ] **Step 5: 個別テストと全JavaScriptテストを実行する**

Run: `docker compose run --rm -T app php artisan test tests/Feature/TopPageTest.php`
Expected: PASS。

Run: `docker compose run --rm -T node npm run test:js`
Expected: PASS。

- [ ] **Step 6: 配信用アセットをビルドする**

Run: `docker compose run --rm -T node npm run build`
Expected: Vite buildが終了コード0で完了し、`public/build/manifest.json`が更新される。

- [ ] **Step 7: 必須静的チェックと全PHPテストを実行する**

Run: `sh scripts/check.sh`
Expected: Pint、PHPStan/Larastan、ESLint、PrettierがすべてPASS。

Run: `docker compose run --rm -T app composer test`
Expected: 全PHPテストがPASS。

- [ ] **Step 8: 差分と禁止対象へアクセスしていないことを確認する**

Run: `git diff --check && git status --short`
Expected: whitespace errorがなく、変更がF21関連ソース、テスト、文書、Vite生成物だけである。ブラウザー自動操作、実DB内容、アップロード済みstorage内容を確認していないことも作業記録と照合する。

- [ ] **Step 9: Task 3をコミットする**

```bash
git add resources/views/prompt-preparation.blade.php resources/css/app.css tests/Feature/TopPageTest.php docs/機能実装状況.md public/build
git commit -m "feat: add character variant group controls"
```
