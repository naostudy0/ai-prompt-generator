# AI開発エージェント向け指示

## 必ず参照する開発方針

作業開始時に[設計書ファースト・目的駆動開発の方針](docs/spec/開発方針.md)を必ず読み、その手順と判断基準に従う。
あわせて[アーキテクチャ設計](docs/design/アーキテクチャ.md)を必ず読み、配置・依存関係・更新と参照の分離ルールに従う。
実装時は[コーディング規約](docs/design/コーディング規約.md)も必ず読む。
コードレビュー時は[コードレビュー基準](docs/review/コードレビュー基準.md)を必ず読み、カテゴリごとに独立したサブエージェントへ分担して実装を確認する。
親エージェントが全カテゴリの結果と未確認事項を集約する。検査・テスト実行は専任の担当に集約し、静的チェックと`docker compose run --rm -T app composer test`の実行結果を確認する。
リンクを案内するだけでなく、実際に内容を確認してから作業する。

- 関連する機能概要、用語集、詳細設計、設計判断が存在する場合は、それらも読む。
- 目的→要件→振る舞いの仕様→実装の順で具体化し、設計書を更新してから実装する。
- 設計のみの依頼では、アプリケーションの実装や依存パッケージ追加は行わない。
- 重要な未決事項を推測で確定しない。確認の要否、検証・完了基準は開発方針に従う。

## 技術スタックと画面構成

PHP/Laravel、Blade、HTML、CSS、JavaScriptを使用する。画面は当面1ページで完結させ、JavaScriptから同一アプリのAPIを呼び出して取得・更新する。CSSはTailwindの利用も可とする。
業務処理の入口はHTTPとする。更新ユースケースは`Writes`、参照ユースケースは`Queries`に分ける。詳細はアーキテクチャ設計に従う。

## 実行環境

PHP 8.5とComposerはDockerの`app`サービス内で実行する。ホストにはインストールしない。
起動・初期設定の手順は[DOCKER.md](DOCKER.md)を参照する。

```sh
docker compose exec app php -v
docker compose exec app composer -V
docker compose exec app php artisan test
```

コンテナが停止している場合は、必要に応じて`docker compose up -d`で起動する。
文書のみの作業ではコンテナ起動やLaravel Boostの導入を前提にしない。
Laravel Boostなどの開発支援ツールを導入する場合もコンテナ内で実行し、本書の開発方針へのリンクとDocker利用ルールを維持する。

## フロントエンドのビルド

`resources/js/`、`resources/css/`、またはBlade上のクラス・フロントエンドUIを変更した場合は、完了前にDockerの`node`サービスでViteビルドを実行する。

```sh
# node_modulesが未準備の場合だけ実行
docker compose run --rm -T node npm ci

# 配信用アセットをpublic/buildへ出力
docker compose run --rm -T node npm run build
```

本アプリは`public/build/manifest.json`が存在すると、そこに記録されたビルド済みJavaScript・CSSを配信する。ソースだけを変更してビルドしない場合、Bladeの新しいボタンは表示されても古いJavaScriptが読み込まれ、クリックしても反応しないことがある。
ビルド後はブラウザーをハードリロードし、変更した操作が実際の配信画面で動くことを確認する。ビルドを実行できない場合は、未実行理由と、配信画面が古い可能性を完了報告に明記する。

## 開発後の必須チェック

コード・設定・依存を変更したら`sh scripts/check.sh`を必ず実行し、成功を確認してから完了とする。
このスクリプトはDocker内でPint（PSR-12）、PHPStan＋Larastan、ESLint、Prettierを実行する。
失敗は修正して再検査する。検査を通すためにルールの無効化や解析レベルの引き下げを行わない。
振る舞いを変えた場合は関連テストも実行する。未実行の検査は理由を明記し、成功扱いにしない。
文書だけの変更はリンクと整合性の確認でよい。Node.js・npmもDockerの`node`サービス内で実行する。
フロントエンドを変更した場合、`sh scripts/check.sh`は静的検査であり配信用アセットを生成しないため、上記の`npm run build`も別途必須とする。
