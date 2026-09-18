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
            data-lora-options-url="{{ $loraPromptOptionsUrl }}"
            data-loras-url="{{ $lorasUrl }}"
            data-lora-triggers-url="{{ $loraTriggersUrl }}"
            data-outfits-url="{{ $outfitsUrl }}"
            data-character-directions-url="{{ $characterDirectionsUrl }}"
            data-scene-directions-url="{{ $sceneDirectionsUrl }}"
            data-expressions-url="{{ $expressionsUrl }}"
            data-gazes-url="{{ $gazesUrl }}"
            data-locations-url="{{ $locationsUrl }}"
            data-compositions-url="{{ $compositionsUrl }}"
            data-actions-url="{{ $actionsUrl }}"
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

                <section class="lora-options" aria-labelledby="lora-options-heading" data-lora-options>
                    <div class="subsection-heading">
                        <div>
                            <p class="category-label">positive</p>
                            <h3 id="lora-options-heading">LoRA</h3>
                        </div>
                        <button class="small-button" type="button" aria-label="LoRAを追加" data-option-add="lora">追加</button>
                    </div>

                    <div class="option-load-state">
                        <p data-option-load-status role="status" aria-live="polite"></p>
                        <button class="secondary-button" type="button" data-option-retry hidden>再読み込み</button>
                    </div>

                    <label class="field-label" for="lora-search">LoRAを検索</label>
                    <input id="lora-search" class="text-input" type="search" data-lora-search disabled>
                    <div class="option-list-scroll" tabindex="0" aria-label="LoRA選択肢を横にスクロール">
                        <select class="option-list" size="5" aria-label="LoRAを選択" data-lora-list disabled></select>
                    </div>
                    <div class="list-actions">
                        <button class="small-button" type="button" aria-label="LoRAの選択を解除" data-option-clear="lora" disabled>選択解除</button>
                        <button class="small-button" type="button" aria-label="LoRAを編集" data-option-edit="lora" disabled>編集</button>
                        <button class="small-button small-button--danger" type="button" aria-label="LoRAを削除" data-option-delete="lora" disabled>削除</button>
                    </div>

                    <label class="field-label" for="lora-strength">強度</label>
                    <select id="lora-strength" class="text-input" data-lora-strength disabled>
                        @foreach (range(0, 10) as $strengthStep)
                            @php($strength = $strengthStep === 10 ? '1' : number_format($strengthStep / 10, 1))
                            <option value="{{ $strength }}" @selected($strengthStep === 10)>{{ $strength }}</option>
                        @endforeach
                    </select>

                    <div class="linked-option">
                        <div class="subsection-heading subsection-heading--compact">
                            <h3>トリガー</h3>
                            <button class="small-button" type="button" aria-label="トリガーを追加" data-option-add="trigger" disabled>追加</button>
                        </div>
                        <div class="option-list-scroll" tabindex="0" aria-label="トリガー選択肢を横にスクロール">
                            <select class="option-list" size="4" aria-label="トリガーを選択" data-trigger-list disabled></select>
                        </div>
                        <div class="list-actions">
                            <button class="small-button" type="button" aria-label="トリガーの選択を解除" data-option-clear="trigger" disabled>選択解除</button>
                            <button class="small-button" type="button" aria-label="トリガーを編集" data-option-edit="trigger" disabled>編集</button>
                            <button class="small-button small-button--danger" type="button" aria-label="トリガーを削除" data-option-delete="trigger" disabled>削除</button>
                        </div>
                    </div>

                    <div class="linked-option">
                        <div class="subsection-heading subsection-heading--compact">
                            <h3>服装</h3>
                            <button class="small-button" type="button" aria-label="服装を追加" data-option-add="outfit" disabled>追加</button>
                        </div>
                        <label class="field-label" for="outfit-search">服装を検索</label>
                        <input id="outfit-search" class="text-input" type="search" data-outfit-search disabled>
                        <div class="option-list-scroll" tabindex="0" aria-label="服装選択肢を横にスクロール">
                            <select class="option-list" size="5" aria-label="服装を選択" data-outfit-list disabled></select>
                        </div>
                        <div class="list-actions">
                            <button class="small-button" type="button" aria-label="服装の選択を解除" data-option-clear="outfit" disabled>選択解除</button>
                            <button class="small-button" type="button" aria-label="服装を編集" data-option-edit="outfit" disabled>編集</button>
                            <button class="small-button small-button--danger" type="button" aria-label="服装を削除" data-option-delete="outfit" disabled>削除</button>
                        </div>
                    </div>
                </section>

                <section class="prompt-categories" aria-labelledby="prompt-categories-heading" data-prompt-categories>
                    <div class="subsection-heading">
                        <div>
                            <p class="category-label">positive</p>
                            <h3 id="prompt-categories-heading">描写</h3>
                        </div>
                    </div>
                    <div class="option-load-state">
                        <p data-category-load-status role="status" aria-live="polite"></p>
                        <button class="secondary-button" type="button" data-category-retry hidden>再読み込み</button>
                    </div>

                    @foreach ([
                        ['type' => 'expression', 'label' => '表情', 'multiple' => false, 'search' => true],
                        ['type' => 'gaze', 'label' => '視線', 'multiple' => false, 'search' => false],
                        ['type' => 'action', 'label' => '動作', 'multiple' => true, 'search' => false],
                        ['type' => 'location', 'label' => '場所', 'multiple' => true, 'search' => false],
                        ['type' => 'composition', 'label' => '構図', 'multiple' => false, 'search' => false],
                    ] as $category)
                        <div class="linked-option" data-prompt-category="{{ $category['type'] }}" data-multiple="{{ $category['multiple'] ? 'true' : 'false' }}">
                            <div class="subsection-heading subsection-heading--compact">
                                <h3>{{ $category['label'] }}</h3>
                                <button class="small-button" type="button" aria-label="{{ $category['label'] }}を追加" data-category-add disabled>追加</button>
                            </div>
                            @if ($category['search'])
                                <label class="field-label" for="{{ $category['type'] }}-search">{{ $category['label'] }}を検索</label>
                                <input id="{{ $category['type'] }}-search" class="text-input" type="search" data-category-search disabled>
                            @endif
                            @if ($category['multiple'])
                                <div class="badge-options" aria-label="{{ $category['label'] }}を選択" data-category-badges></div>
                            @else
                                <div class="option-list-scroll" tabindex="0" aria-label="{{ $category['label'] }}選択肢を横にスクロール">
                                    <select class="option-list" size="4" aria-label="{{ $category['label'] }}を選択" data-category-list disabled></select>
                                </div>
                                <div class="list-actions">
                                    <button class="small-button" type="button" aria-label="{{ $category['label'] }}の選択を解除" data-category-clear disabled>選択解除</button>
                                    <button class="small-button" type="button" aria-label="{{ $category['label'] }}を編集" data-category-edit disabled>編集</button>
                                    <button class="small-button small-button--danger" type="button" aria-label="{{ $category['label'] }}を削除" data-category-delete disabled>削除</button>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </section>

                <button class="display-button" type="button" data-display disabled>
                    プロンプトを表示
                </button>
            </section>

            <section class="output-section" aria-labelledby="output-heading" data-output-section>
                <div class="section-heading">
                    <span class="step-number" aria-hidden="true">02</span>
                    <div>
                        <h2 id="output-heading">出力：画像生成ツールへコピー</h2>
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

            <dialog class="option-dialog" data-option-dialog>
                <form data-option-form>
                    <input type="hidden" data-option-id>
                    <h2 data-option-dialog-title></h2>
                    <div data-lora-field hidden>
                        <label class="field-label" for="option-file-name">ファイル名</label>
                        <input id="option-file-name" class="text-input" data-option-file-name>
                    </div>
                    <label class="field-label" for="option-name">登録名</label>
                    <input id="option-name" class="text-input" data-option-name>
                    <div data-strength-field hidden>
                        <label class="field-label" for="option-strength">推奨強度</label>
                        <select id="option-strength" class="text-input" data-option-strength>
                            @foreach (range(0, 10) as $strengthStep)
                                @php($strength = $strengthStep === 10 ? '1' : number_format($strengthStep / 10, 1))
                                <option value="{{ $strength }}" @selected($strengthStep === 10)>{{ $strength }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div data-association-field hidden>
                        <label class="field-label" for="option-lora">紐付けるLoRA</label>
                        <select id="option-lora" class="text-input" data-option-lora></select>
                    </div>
                    <div data-content-field hidden>
                        <label class="field-label" for="option-content">文面</label>
                        <textarea id="option-content" rows="6" data-option-content></textarea>
                    </div>
                    <p class="editor-status" data-option-form-status role="status" aria-live="polite"></p>
                    <div class="editor-actions">
                        <button class="primary-button" type="submit" data-option-save>保存</button>
                        <button class="secondary-button" type="button" data-option-cancel>キャンセル</button>
                    </div>
                </form>
            </dialog>

            <dialog class="option-dialog" data-delete-dialog>
                <form data-delete-form>
                    <h2>登録を削除</h2>
                    <p data-delete-message></p>
                    <p class="editor-status" data-delete-status role="status" aria-live="polite"></p>
                    <div class="editor-actions">
                        <button class="danger-button" type="submit" data-delete-confirm>削除</button>
                        <button class="secondary-button" type="button" data-delete-cancel>キャンセル</button>
                    </div>
                </form>
            </dialog>

            <dialog class="option-dialog" data-category-dialog>
                <form data-category-form>
                    <input type="hidden" data-category-id>
                    <h2 data-category-dialog-title></h2>
                    <p class="category-editing-id" data-category-editing-id hidden></p>
                    <label class="field-label" for="category-name">登録名</label>
                    <input id="category-name" class="text-input" data-category-name>
                    <label class="field-label" for="category-content">文面</label>
                    <textarea id="category-content" rows="6" data-category-content></textarea>
                    <p class="editor-status" data-category-form-status role="status" aria-live="polite"></p>
                    <div class="editor-actions">
                        <button class="primary-button" type="submit" data-category-save>保存</button>
                        <button class="secondary-button" type="button" data-category-cancel>キャンセル</button>
                    </div>
                </form>
            </dialog>

            <dialog class="option-dialog" data-category-delete-dialog>
                <form data-category-delete-form>
                    <h2>登録を削除</h2>
                    <p data-category-delete-message></p>
                    <p class="editor-status" data-category-delete-status role="status" aria-live="polite"></p>
                    <div class="editor-actions">
                        <button class="danger-button" type="submit" data-category-delete-confirm>削除</button>
                        <button class="secondary-button" type="button" data-category-delete-cancel>キャンセル</button>
                    </div>
                </form>
            </dialog>
        </main>
    </body>
</html>
