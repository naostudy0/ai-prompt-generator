# Docker開発環境

ホストにPHP・Composerをインストールせず、Docker Composeで実行します。
PHP 8.5-FPMとNginxを使用し、DBはSQLite（`database/database.sqlite`）です。
Laravel標準ディレクトリと同じ階層に`compose.yaml`と`docker/`を配置しています。

## 起動・停止

```sh
docker compose up -d --build
docker compose down
```

起動後は http://localhost:8080 にアクセスします。ローカル開発専用設定です。
ポート変更時は`.env`に`APP_PORT=8081`などを追加し、`APP_URL`も合わせて変更して再起動します。

## 各種コマンド

```sh
docker compose exec app php artisan --version
docker compose exec app php artisan migrate
docker compose exec app php artisan test
docker compose exec app composer install
docker compose logs -f
```

## 別の環境で初回セットアップする場合

```sh
cp .env.example .env
docker compose build app
docker compose run --rm app composer install
docker compose run --rm app php artisan key:generate
docker compose run --rm app touch database/database.sqlite
docker compose run --rm app php artisan migrate
docker compose run --rm app sh -c 'chown -R www-data:www-data storage bootstrap/cache database && chmod -R ug+rwX storage bootstrap/cache database'
docker compose up -d
```

DBとソースはホスト側に保存され、`docker compose down`後も残ります。
現在はLaravel初期画面までのセットアップです。Node.js 24は`node`サービスで利用できます。AIプロンプト生成機能は未実装です。

## 静的チェック

初回は`docker compose run --rm node npm ci`でJavaScriptの依存をインストールします。
PHPの依存は上記の`composer install`でインストールします。
開発後は`sh scripts/check.sh`を必ず実行してください。
個別の検査・整形方法は[コーディング規約](docs/design/コーディング規約.md)を参照してください。
`node`はツール用サービスのため通常の`docker compose up -d`では常駐しません。
