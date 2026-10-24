# PHP Security Updates Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** PHPを8.5.12へ更新し、現在のメジャーバージョン内でComposer・npm依存を最新化して既知の脆弱性を解消する。

**Architecture:** DockerfileでPHPパッチバージョンを固定し、Docker内のComposerとnpmでロックファイルを更新する。アプリケーションの設計や機能コードは変更せず、監査・静的検査・全テスト・ビルドで互換性を確認する。

**Tech Stack:** Docker Compose、PHP 8.5、Composer、Laravel 13、Node.js 24、npm、Vite 8

**Spec:** `docs/superpowers/specs/2026-10-24-php-security-updates-design.md`

## Global Constraints

- PHPベースイメージは`php:8.5.12-fpm-bookworm`へ固定する。
- Composer・npmは現在のメジャーバージョン内だけで更新する。
- PHPUnit 13、PHP 8.6、アプリケーション機能変更は対象外とする。
- 実データ、永続ストレージ、ブラウザを使わない。

## Review Focus

- 再ビルド後のPHPが8.5.12以外なら完了扱いにしない。
- Composerまたはnpm監査に脆弱性が残る場合は、内容と解消不能な理由を明示する。
- ロックファイル更新が既存のバージョン制約を越えていないことを確認する。
- PHP拡張のビルド失敗や不足を、全テストと静的解析で検出する。
- フロントエンド依存更新によるビルド・JavaScriptテストの失敗を検出する。

---

### Task 1: PHP 8.5.12イメージへ更新

**Files:**
- Modify: `docker/php/Dockerfile`

- [ ] `FROM`を`php:8.5.12-fpm-bookworm`へ変更する。
- [ ] `docker compose build --pull app`でイメージを再ビルドする。
- [ ] `docker compose run --rm -T app php -v`がPHP 8.5.12を表示することを確認する。

### Task 2: Composer・npm依存を互換範囲内で更新

**Files:**
- Modify: `composer.lock`
- Modify: `package-lock.json`

- [ ] Dockerの`app`サービスで`composer update --with-all-dependencies --no-interaction`を実行する。
- [ ] Dockerの`node`サービスで`npm update`を実行する。
- [ ] 直接依存が既存のメジャーバージョンを維持していることを差分で確認する。
- [ ] `composer audit --locked --no-interaction`と`npm audit`を実行し、既知の脆弱性が残っていないことを確認する。

### Task 3: 全検証と統合

**Files:**
- Verify: `docker/php/Dockerfile`
- Verify: `composer.lock`
- Verify: `package-lock.json`

- [ ] `sh scripts/check.sh`を実行する。
- [ ] `docker compose run --rm -T app composer test`を実行する。
- [ ] `docker compose run --rm -T node npm run test:js`を実行する。
- [ ] `docker compose run --rm -T node npm run build`を実行する。
- [ ] メインエージェントがコードレビュー基準の全カテゴリをセルフチェックする。
- [ ] 変更をコミットして`main`へマージする。
- [ ] マージ後の`main`でPHPバージョン、監査、静的検査、全テスト、Viteビルドを再確認する。
