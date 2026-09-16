# AI開発エージェント向け指示

## 必ず参照する開発方針

作業開始時に[設計書ファースト・目的駆動開発の方針](docs/spec/開発方針.md)を必ず読み、その手順と判断基準に従う。
あわせて[アーキテクチャ設計](docs/design/アーキテクチャ.md)を必ず読み、配置・依存関係・更新と参照の分離ルールに従う。
リンクを案内するだけでなく、実際に内容を確認してから作業する。

- 関連する機能概要、用語集、詳細設計、設計判断が存在する場合は、それらも読む。
- 目的→要件→振る舞いの仕様→実装の順で具体化し、設計書を更新してから実装する。
- 設計のみの依頼では、アプリケーションの実装や依存パッケージ追加は行わない。
- 重要な未決事項を推測で確定しない。確認の要否、検証・完了基準は開発方針に従う。

## 技術スタックと画面構成

PHP/Laravel、Blade、HTML、CSS、JavaScriptを使用する。画面は当面1ページで完結させ、JavaScriptから同一アプリのAPIを呼び出して取得・更新する。CSSはTailwindの利用も可とする。
業務用のCLI／Artisanコマンドは作成しない。更新ユースケースは`Writes`、参照ユースケースは`Queries`に分ける。詳細はアーキテクチャ設計に従う。

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
