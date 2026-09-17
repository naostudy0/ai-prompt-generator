<!DOCTYPE html>
<html lang="ja">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>AI画像プロンプト作成</title>

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body>
        <main
            class="page-shell"
            data-prompt-preparation
            data-default-prompts-url="{{ $defaultPromptsUrl }}"
        >
            <header class="page-header">
                <p class="eyebrow">AI IMAGE PROMPT BUILDER</p>
                <h1>プロンプトを組み立てる</h1>
            </header>

            <section class="input-section" aria-labelledby="input-heading">
                <div class="section-heading">
                    <span class="step-number" aria-hidden="true">01</span>
                    <div>
                        <h2 id="input-heading">入力：使う項目を選ぶ</h2>
                    </div>
                </div>

                <div class="load-state">
                    <p data-load-status role="status" aria-live="polite">デフォルト文面を読み込んでいます。</p>
                    <button class="secondary-button" type="button" data-retry hidden>再読み込み</button>
                </div>

                <div class="prompt-settings">
                    @foreach ([
                        ['polarity' => 'positive', 'label' => 'positive', 'updateUrl' => $positiveUpdateUrl],
                        ['polarity' => 'negative', 'label' => 'negative', 'updateUrl' => $negativeUpdateUrl],
                    ] as $prompt)
                        <article
                            class="prompt-setting prompt-setting--{{ $prompt['polarity'] }}"
                            data-default-prompt="{{ $prompt['polarity'] }}"
                            data-update-url="{{ $prompt['updateUrl'] }}"
                        >
                            <div class="prompt-setting__header">
                                <div>
                                    <p class="category-label">{{ $prompt['label'] }}</p>
                                </div>
                                <div class="setting-actions">
                                    <button
                                        class="select-button"
                                        type="button"
                                        aria-pressed="true"
                                        aria-label="{{ $prompt['polarity'] }}のデフォルトを選択"
                                        data-select
                                        disabled
                                    >
                                        <span data-selection-marker aria-hidden="true">✓</span>
                                        デフォルト
                                    </button>
                                    <button
                                        class="edit-button"
                                        type="button"
                                        aria-label="{{ $prompt['polarity'] }}のデフォルトを編集"
                                        data-edit
                                        disabled
                                    >
                                        <svg aria-hidden="true" viewBox="0 0 24 24">
                                            <path d="M4 20h4l11-11-4-4L4 16v4Zm12.5-16.5 4 4" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <div class="prompt-editor" data-editor hidden>
                                <label for="{{ $prompt['polarity'] }}-default-content">
                                    {{ $prompt['label'] }}のデフォルトを編集
                                </label>
                                <textarea
                                    id="{{ $prompt['polarity'] }}-default-content"
                                    rows="7"
                                    data-editor-content
                                ></textarea>
                                <p class="editor-help">
                                    保存時に区切りと空白を整え、重複を取り除きます。空欄で保存すると内容を削除します。
                                </p>
                                <p class="editor-status" data-editor-status role="status" aria-live="polite"></p>
                                <div class="editor-actions">
                                    <button class="primary-button" type="button" data-save>保存</button>
                                    <button class="secondary-button" type="button" data-cancel>キャンセル</button>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                <button class="display-button" type="button" data-display disabled>
                    プロンプトを表示
                </button>
            </section>

            <section class="output-section" aria-labelledby="output-heading" data-output-section>
                <div class="section-heading">
                    <span class="step-number" aria-hidden="true">02</span>
                    <div>
                        <h2 id="output-heading" class="output-heading">
                            <span>出力：</span>
                            <span>画像生成ツールへコピー</span>
                        </h2>
                    </div>
                </div>

                <div class="output-cards">
                    @foreach (['positive', 'negative'] as $polarity)
                        <article class="output-card output-card--{{ $polarity }}" data-output="{{ $polarity }}">
                            <div class="output-card__header">
                                <h3>{{ $polarity }}</h3>
                                <button
                                    class="copy-button"
                                    type="button"
                                    aria-label="{{ $polarity }}の出力をコピー"
                                    data-copy
                                    disabled
                                >
                                    <svg aria-hidden="true" viewBox="0 0 24 24">
                                        <rect x="8" y="8" width="11" height="11" rx="2" />
                                        <path d="M16 8V7a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h1" />
                                    </svg>
                                </button>
                            </div>
                            <textarea
                                aria-label="{{ $polarity }}の出力"
                                rows="8"
                                data-output-content
                            ></textarea>
                            <p class="copy-status" data-copy-status role="status" aria-live="polite"></p>
                        </article>
                    @endforeach
                </div>
            </section>

            <div class="toast" data-toast role="status" aria-live="polite" hidden></div>
        </main>
    </body>
</html>
