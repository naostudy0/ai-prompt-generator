# 0004 ComfyUI連携の境界

## 状態

採用。系統別ワークフローの入力マッピング詳細はF13で管理する。

## 背景

本アプリで作ったpositive／negativeをComfyUIへ送り、コピーと貼り付けなしで実行待ちへ追加したい。ComfyUIは文字列だけでなく、API形式のワークフローグラフを`POST /prompt`の`prompt`へ渡す。ブラウザーから別オリジンのComfyUIへ直接送る場合はCORS設定が必要で、接続先とワークフローもブラウザーへ公開される。

## 判断案

ブラウザーは同一オリジンのLaravel APIだけを呼び、LaravelのInfrastructureアダプターが設定済みのComfyUIへ送信する。

- ComfyUIのベースURLはサーバー設定とし、リクエストで任意URLを受け取らない。
- 接続先の既定値は`http://host.docker.internal:8188`とし、環境変数と`config`から変更できるようにする。ホストOSから直接実行する場合は`http://127.0.0.1:8188`を使える。
- API形式のワークフローは系統に関連付けてアップロードし、positive／negative／seedの入力位置はノードIDと入力名で保存する。
- JSONの行番号は整形やノード追加で変わるため、書換位置の識別には使わない。
- Applicationに「一つの画像生成プロンプトをキューへ追加する」ポートを置き、ComfyUI固有のJSONとHTTPを漏らさない。
- 通信はDBトランザクション外で同期実行する。受理された`prompt_id`を返すが、画像生成完了までは待たない。
- 人物LoRAの一括送信は候補ごとに一回ずつ`POST /prompt`する。
- 一回の一括送信上限は100件とする。

## 理由

既存の同一オリジン・CSRF保護を維持でき、ComfyUI側のCORS設定に依存しない。固定接続先に限定してSSRF経路を作らず、ワークフロー形式の差をInfrastructure内へ閉じ込められる。

## 影響

- Docker運用では`app`コンテナからComfyUIへ到達できるホスト名・ポートが必要になる。
- 一括送信は途中成功し得るため、原子的な全件成功とはせず候補ごとの成否を返す。
- 認証なしのアプリを公開すると第三者がGPUジョブを投入できる。個人利用の前提を外す場合は認証・認可・レート制限を再設計する。
- 生成完了の追跡、画像取得、キュー取消は初期範囲外とする。

## 参照

- [ComfyUI公式API例](https://github.com/Comfy-Org/ComfyUI/blob/master/script_examples/websockets_api_example.py)
- [ComfyUIサーバー実装](https://github.com/Comfy-Org/ComfyUI/blob/master/server.py)
