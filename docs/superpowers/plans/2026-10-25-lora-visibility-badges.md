# Character LoRA Visibility Badges Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 人物LoRA管理一覧で、表示状態と次の操作を文字と配色の両方から即座に区別できるようにする。

**Architecture:** 既存の管理一覧レンダリングは維持し、生成するDOMを人物名・状態バッジ・操作ボタンに分ける。専用CSSクラスで状態と操作の意味を表現し、APIや保存処理は変更しない。

**Tech Stack:** JavaScript ES Modules、DOM API、CSS、Node.js test runner、JSDOM、Vite

**Spec:** `docs/superpowers/specs/2026-10-25-lora-visibility-badges-design.md`

## Global Constraints

- ブラウザー操作、実データベース、永続ストレージ内の実データは確認しない。
- 状態や操作を色だけで表現せず、確定した日本語文言を併用する。
- API、保存処理、検索、絞り込みの振る舞いは変更しない。
- フロントエンド変更後はDockerのnodeサービスでViteビルドを実行する。
- 完了前に`sh scripts/check.sh`と`docker compose run --rm -T app composer test`を実行する。

## Review Focus

- `isHidden=false`で「表示中」バッジと「非表示にする」操作が同時に表示されること。
- `isHidden=true`で「非表示」バッジと「表示する」操作が同時に表示されること。
- バッジと操作ボタンが、それぞれ状態別の専用クラスを持つこと。
- 人物名から状態文言を分離しても、検索と一覧更新が従来どおり動くこと。
- 操作ボタンのクリックが既存PATCH処理と選択解除を維持すること。

---

### Task 1: 状態バッジと意味別操作ボタン

**Files:**
- Modify: `tests/JavaScript/lora-options-page.test.js`
- Modify: `resources/js/lora-options-page.js`
- Modify: `resources/css/app.css`

**Interfaces:**
- Consumes: `state.managedLoras`の`{ id, name, isHidden }`と既存`changeCharacterLoraVisibility(...)`
- Produces: `.lora-visibility-badge--visible`、`.lora-visibility-badge--hidden`、`.lora-visibility-action--show`、`.lora-visibility-action--hide`を持つ管理行DOM

- [ ] **Step 1: 表示中・非表示のDOM契約を検証する失敗テストを書く**

`tests/JavaScript/lora-options-page.test.js`へ、両状態を管理一覧へ読み込み、次を検証するテストを追加する。

- 表示中: バッジ文言`表示中`、クラス`.lora-visibility-badge--visible`、ボタン文言`非表示にする`、クラス`.lora-visibility-action--hide`
- 非表示: バッジ文言`非表示`、クラス`.lora-visibility-badge--hidden`、ボタン文言`表示する`、クラス`.lora-visibility-action--show`

- [ ] **Step 2: テストが現行の括弧付き文字列と共通ボタンで失敗することを確認する**

Run: `docker compose run --rm -T node node --test tests/JavaScript/lora-options-page.test.js`

Expected: 新しいバッジ要素または専用クラスが存在せずFAIL。

- [ ] **Step 3: 管理行DOMを人物名・状態バッジ・操作ボタンへ分離する**

`resources/js/lora-options-page.js`の`renderManagedLoras()`で、人物名は独立した`span`、状態は`.lora-visibility-badge`と状態別modifierを持つ`span`にする。操作ボタンには`.small-button`と操作別modifierを付け、文言を`表示する`／`非表示にする`にする。既存イベント処理は変更しない。

- [ ] **Step 4: 状態バッジと操作ボタンのCSSを追加する**

`resources/css/app.css`へ、ピル形状、太字、十分な余白を持つ共通バッジと、緑系の表示中、グレー系の非表示、青系の表示操作、控えめな赤系の非表示操作を追加する。`:hover`と`:disabled`でも意味が失われない配色にする。

- [ ] **Step 5: 関連JavaScriptテストを実行する**

Run: `docker compose run --rm -T node node --test tests/JavaScript/lora-options-page.test.js`

Expected: 全テストPASS。

- [ ] **Step 6: 全検証を実行する**

Run: `docker compose run --rm -T node npm run test:js`

Expected: 全JavaScriptテストPASS。

Run: `sh scripts/check.sh`

Expected: Pint、PHPStan、ESLint、PrettierがすべてPASS。

Run: `docker compose run --rm -T app composer test`

Expected: 全PHPテストPASS。

Run: `docker compose run --rm -T node npm run build`

Expected: Vite buildがexit code 0。

- [ ] **Step 7: セルフレビュー後にコミットする**

`docs/review/コードレビュー基準.md`の全カテゴリに沿って変更差分を確認し、問題がなければコミットする。

```bash
git add resources/js/lora-options-page.js resources/css/app.css tests/JavaScript/lora-options-page.test.js
git commit -m "feat: distinguish character LoRA visibility states"
```
