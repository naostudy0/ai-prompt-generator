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
            class="page-layout"
            data-prompt-preparation
            data-default-prompts-url="{{ $defaultPromptsUrl }}"
            data-model-families-url="{{ $modelFamiliesUrl }}"
            data-lora-options-url="{{ $loraPromptOptionsUrl }}"
            data-clothing-lora-options-url="{{ $clothingLoraOptionsUrl }}"
            data-clothing-loras-url="{{ $clothingLorasUrl }}"
            data-clothing-lora-triggers-url="{{ $clothingLoraTriggersUrl }}"
            data-loras-url="{{ $lorasUrl }}"
            data-lora-triggers-url="{{ $loraTriggersUrl }}"
            data-outfits-url="{{ $outfitsUrl }}"
            data-prompt-options-url="{{ $promptOptionsUrl }}"
            data-prompt-option-groups-url="{{ $promptOptionGroupsUrl }}"
            data-prompt-option-categories-url="{{ $promptOptionCategoriesUrl }}"
            data-favorite-prompts-url="{{ $favoritePromptsUrl }}"
            data-comfy-ui-prompts-url="{{ $comfyUiPromptsUrl }}"
            data-comfy-ui-prompt-batch-url="{{ $comfyUiPromptBatchUrl }}"
            data-generation-setting-options-url="{{ $generationSettingOptionsUrl }}"
            data-illustration-prompt-generation-url="{{ $illustrationPromptGenerationUrl }}"
            data-illustration-prompt-tag-groups-url="{{ $illustrationPromptTagGroupsUrl }}"
            data-illustration-scenario-plan-url="{{ $illustrationScenarioPlanUrl }}"
            data-illustration-scenario-prompts-url="{{ $illustrationScenarioPromptsUrl }}"
            data-illustration-scenario-scene-prompt-url="{{ $illustrationScenarioScenePromptUrl }}"
        >
            <nav class="prompt-sidebar section-navigation" aria-labelledby="section-navigation-heading">
                <h2 id="section-navigation-heading">セクション</h2>
                <div data-section-navigation-list></div>
            </nav>

            <div class="page-shell">
            <header class="page-header">
                <p class="eyebrow">AI IMAGE PROMPT BUILDER</p>
                <h1>プロンプトを組み立てる</h1>
                <button class="secondary-button" type="button" data-illustration-prompt-open>AIでプロンプトを作成</button>
                <button class="secondary-button" type="button" data-scenario-open>シナリオを作成</button>
            </header>

            <dialog class="option-dialog illustration-scenario-dialog" data-scenario-dialog aria-labelledby="illustration-scenario-heading">
                <div class="illustration-scenario-dialog__content">
                    <div class="illustration-scenario-dialog__header">
                        <h2 id="illustration-scenario-heading">シナリオを作成</h2>
                        <button type="button" class="small-button" data-scenario-close>閉じる</button>
                    </div>
                    <label class="field-label" for="scenario-description">シナリオの元になる内容</label>
                    <textarea id="scenario-description" class="text-input illustration-scenario-dialog__description" maxlength="2000" data-scenario-description></textarea>
                    <div class="illustration-scenario-dialog__settings">
                        <label class="field-label" for="scenario-count">場面数</label>
                        <input id="scenario-count" class="text-input" type="number" min="3" max="10" value="5" data-scenario-count>
                        <label class="field-label" for="scenario-style">プロンプト形式</label>
                        <select id="scenario-style" class="text-input" data-scenario-style>
                            <option value="danbooru_tags">Danbooruタグ</option>
                            <option value="natural_language">自然文</option>
                        </select>
                    </div>
                    <button type="button" class="primary-button" data-scenario-plan>シナリオ案を作成</button>
                    <p role="status" aria-live="polite" data-scenario-status></p>

                    <section class="illustration-scenario-dialog__edit" data-scenario-edit hidden>
                        <h3>1. シナリオ案（日本語）</h3>
                        <p class="muted-text">これは場面構成の確認・編集用です。完成した画像生成プロンプトは次の操作で生成します。</p>
                        <label class="field-label" for="scenario-continuity">全場面で固定する要素</label>
                        <textarea id="scenario-continuity" class="text-input" maxlength="2000" data-scenario-continuity></textarea>
                        <div class="illustration-scenario-dialog__scenes" data-scenario-scenes></div>
                        <button type="button" class="secondary-button" data-scenario-add>場面を追加</button>
                        <button type="button" class="primary-button" data-scenario-generate>全場面の画像生成プロンプトを生成</button>
                    </section>

                    <section class="illustration-scenario-dialog__results" data-scenario-results hidden>
                        <h3>2. 画像生成プロンプト（英語）</h3>
                        <p class="muted-text">場面ごとに確認・再生成し、既存の出力へ一つずつ反映できます。</p>
                        <div class="illustration-scenario-dialog__result-list" data-scenario-result-list></div>
                    </section>
                </div>
            </dialog>

            <dialog class="option-dialog illustration-prompt-dialog" data-illustration-prompt-dialog aria-labelledby="illustration-prompt-heading">
                <div class="illustration-prompt-dialog__content">
                    <div class="illustration-prompt-dialog__header">
                        <h2 id="illustration-prompt-heading">AIでプロンプトを作成</h2>
                        <button type="button" class="small-button" data-illustration-prompt-close>閉じる</button>
                    </div>
                    <label class="field-label" for="illustration-prompt-input-mode">入力方法</label>
                    <select id="illustration-prompt-input-mode" class="text-input" data-illustration-prompt-input-mode>
                        <option value="free">自由入力</option>
                        <option value="template">テンプレート入力</option>
                    </select>
                    <section class="illustration-prompt-dialog__input" data-illustration-prompt-free-input>
                        <label class="field-label" for="illustration-prompt-description">イラストの指示</label>
                        <textarea id="illustration-prompt-description" class="text-input illustration-prompt-dialog__description" maxlength="2000" data-illustration-prompt-description></textarea>
                    </section>
                    <section class="illustration-prompt-dialog__template" data-illustration-prompt-template-input hidden>
                        <label class="field-label" for="illustration-prompt-character">人物</label>
                        <textarea id="illustration-prompt-character" class="text-input" maxlength="2000" data-illustration-prompt-template="character"></textarea>
                        <label class="field-label" for="illustration-prompt-action">動作・表情</label>
                        <textarea id="illustration-prompt-action" class="text-input" maxlength="2000" data-illustration-prompt-template="action"></textarea>
                        <label class="field-label" for="illustration-prompt-background">場所・背景</label>
                        <textarea id="illustration-prompt-background" class="text-input" maxlength="2000" data-illustration-prompt-template="background"></textarea>
                        <label class="field-label" for="illustration-prompt-composition">構図</label>
                        <textarea id="illustration-prompt-composition" class="text-input" maxlength="2000" data-illustration-prompt-template="composition"></textarea>
                        <label class="field-label" for="illustration-prompt-atmosphere">雰囲気</label>
                        <textarea id="illustration-prompt-atmosphere" class="text-input" maxlength="2000" data-illustration-prompt-template="atmosphere"></textarea>
                        <label class="field-label" for="illustration-prompt-constraints">必須条件</label>
                        <textarea id="illustration-prompt-constraints" class="text-input" maxlength="2000" data-illustration-prompt-template="constraints"></textarea>
                    </section>
                    <label class="field-label" for="illustration-prompt-style">プロンプト形式</label>
                    <select id="illustration-prompt-style" class="text-input" data-illustration-prompt-style>
                        <option value="danbooru_tags">Danbooruタグ</option>
                        <option value="natural_language">自然文</option>
                    </select>
                    <label class="illustration-prompt-dialog__default-option">
                        <input type="checkbox" data-illustration-prompt-include-defaults checked>
                        現在のデフォルトプロンプトを含める
                    </label>
                    <button type="button" class="primary-button" data-illustration-prompt-generate>生成する</button>
                    <p role="status" aria-live="polite" data-illustration-prompt-status></p>
                    <section class="illustration-prompt-dialog__preview" data-illustration-prompt-preview hidden>
                        <label class="field-label" for="illustration-prompt-positive">positive</label>
                        <textarea id="illustration-prompt-positive" class="text-input" data-illustration-prompt-positive></textarea>
                        <label class="field-label" for="illustration-prompt-negative">negative</label>
                        <textarea id="illustration-prompt-negative" class="text-input" data-illustration-prompt-negative></textarea>
                        <section class="illustration-prompt-dialog__defaults" data-illustration-prompt-defaults hidden>
                            <h3>反映時に追加されるデフォルト</h3>
                            <label class="field-label" for="illustration-prompt-default-positive">positive デフォルト</label>
                            <textarea id="illustration-prompt-default-positive" class="text-input" data-illustration-prompt-default-positive readonly></textarea>
                            <label class="field-label" for="illustration-prompt-default-negative">negative デフォルト</label>
                            <textarea id="illustration-prompt-default-negative" class="text-input" data-illustration-prompt-default-negative readonly></textarea>
                        </section>
                        <p class="muted-text" data-illustration-prompt-metadata></p>
                        <section class="illustration-prompt-dialog__tag-groups" data-illustration-prompt-tag-groups hidden>
                            <h3>再利用するタグのまとまり</h3>
                            <p class="muted-text">保存したい候補を選ぶと、「AIプロンプト」ブロックから再利用できます。</p>
                            <div class="illustration-prompt-dialog__tag-group-list" data-illustration-prompt-tag-group-list></div>
                            <button type="button" class="secondary-button" data-illustration-prompt-tag-group-save disabled>選択した候補を保存</button>
                            <p role="status" aria-live="polite" data-illustration-prompt-tag-group-status></p>
                        </section>
                        <button type="button" class="primary-button" data-illustration-prompt-apply>既存の出力へ反映</button>
                    </section>
                </div>
            </dialog>

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

                <section class="prompt-settings" aria-label="デフォルト" data-default-prompts-section tabindex="-1">
                    <div class="model-family-controls">
                        <p>出力に使う系統：<strong data-active-family-name>Illustrious</strong></p>
                        <label class="field-label" for="default-edit-family">編集する系統</label>
                        <select id="default-edit-family" class="text-input" data-edit-family>
                            <option value="1">Illustrious</option>
                        </select>
                        <button class="small-button" type="button" data-family-manage>系統を管理</button>
                    </div>
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
                </section>

                <section class="lora-options" aria-labelledby="lora-options-heading" data-lora-options tabindex="-1">
                    <div class="subsection-heading">
                        <div>
                            <p class="category-label">positive</p>
                            <h3 id="lora-options-heading">LoRA</h3>
                        </div>
                        <div class="list-actions">
                            <button class="small-button" type="button" data-lora-visibility-manage>非表示を管理</button>
                            <button class="small-button" type="button" aria-label="LoRAを追加" data-option-add="lora">追加</button>
                        </div>
                    </div>

                    <div class="option-load-state">
                        <p data-option-load-status role="status" aria-live="polite"></p>
                        <button class="secondary-button" type="button" data-option-retry hidden>再読み込み</button>
                    </div>

                    <label class="field-label" for="lora-search">LoRAを検索</label>
                    <input id="lora-search" class="text-input" type="search" data-lora-search disabled>
                    <label class="field-label" for="lora-family-filter">系統で絞り込む</label>
                    <select id="lora-family-filter" class="text-input" data-lora-family-filter disabled>
                        <option value="">すべての系統</option>
                    </select>
                    <div class="option-list-scroll" tabindex="0" aria-label="LoRA選択肢を横にスクロール">
                        <select class="option-list" size="5" aria-label="LoRAを選択" data-lora-list disabled></select>
                    </div>
                    <nav class="list-pagination" aria-label="人物LoRAのページ切り替え">
                        <button class="small-button" type="button" data-lora-page-previous disabled>前へ</button>
                        <span data-lora-page-status>0件</span>
                        <button class="small-button" type="button" data-lora-page-next disabled>次へ</button>
                    </nav>
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

                    <div class="linked-option" data-selection-section="trigger">
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

                    <div class="linked-option" data-selection-section="outfit">
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
                <dialog class="option-dialog" data-lora-visibility-dialog>
                    <h2>人物LoRAの表示を管理</h2>
                    <label class="field-label" for="lora-visibility-filter">表示状態</label>
                    <select id="lora-visibility-filter" class="text-input" data-lora-visibility-filter>
                        <option value="all">すべて</option><option value="visible">表示中</option><option value="hidden">非表示</option>
                    </select>
                    <label class="field-label" for="lora-visibility-search">人物LoRAを検索</label>
                    <input id="lora-visibility-search" class="text-input" type="search" data-lora-visibility-search>
                    <p data-lora-visibility-status role="status" aria-live="polite"></p>
                    <div class="character-variant-list" data-lora-visibility-list></div>
                    <button class="secondary-button" type="button" data-lora-visibility-close>閉じる</button>
                </dialog>

                <section class="lora-options" aria-labelledby="clothing-lora-heading" data-clothing-lora-options tabindex="-1">
                    <div class="subsection-heading">
                        <div>
                            <p class="category-label">positive</p>
                            <h3 id="clothing-lora-heading">衣装LoRA</h3>
                        </div>
                        <button class="small-button" type="button" data-clothing-lora-add disabled>追加</button>
                    </div>
                    <div class="option-load-state">
                        <p data-clothing-lora-status role="status" aria-live="polite"></p>
                        <button class="secondary-button" type="button" data-clothing-lora-retry hidden>再読み込み</button>
                    </div>
                    <label class="field-label" for="clothing-lora-search">衣装LoRAを検索</label>
                    <input id="clothing-lora-search" class="text-input" type="search" data-clothing-lora-search disabled>
                    <div class="clothing-lora-list" data-clothing-lora-list></div>
                </section>

                <section class="prompt-categories" aria-labelledby="prompt-categories-heading" data-prompt-categories>
                    <div class="subsection-heading">
                        <div>
                            <p class="category-label">positive</p>
                            <h3 id="prompt-categories-heading">描写</h3>
                        </div>
                        <a class="small-button" href="{{ $promptOptionManagementUrl }}">オプションを管理</a>
                    </div>
                    <div class="option-load-state">
                        <p data-category-load-status role="status" aria-live="polite"></p>
                        <button class="secondary-button" type="button" data-category-retry hidden>再読み込み</button>
                    </div>

                    <div data-option-groups></div>
                    <button class="small-button" type="button" data-option-group-add hidden disabled>ブロックを追加</button>
                </section>

                <div class="editor-actions">
                    <button class="display-button" type="button" data-display disabled>プロンプトを表示</button>
                    <button class="secondary-button" type="button" data-reset disabled>リセット</button>
                </div>
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
                            <p class="output-shortcut-hint">範囲選択して⌘↑／⌘↓で強度を0.05ずつ調整</p>
                            <p class="copy-status" data-copy-status role="status" aria-live="polite"></p>
                        </article>
                    @endforeach
                </div>
                <div class="editor-actions favorite-actions">
                    <button class="primary-button" type="button" data-comfyui-send>ComfyUIへ送信</button>
                    <button class="primary-button" type="button" data-favorite-save disabled>お気に入りへ保存</button>
                    <button class="secondary-button" type="button" data-favorite-open disabled>お気に入りを開く</button>
                    <button class="secondary-button" type="button" data-character-variant-open disabled>人物LoRAを差し替えてコピー</button>
                </div>
                <p class="editor-status" data-comfyui-send-status role="status" aria-live="polite"></p>
            </section>

            <div class="toast" data-toast role="status" aria-live="polite" hidden></div>

            <dialog class="option-dialog character-variant-dialog" data-character-variant-dialog>
                <div class="subsection-heading">
                    <h2>人物LoRAを差し替えてコピー</h2>
                    <button class="secondary-button" type="button" data-character-variant-close>閉じる</button>
                </div>
                <fieldset class="character-variant-groups">
                    <legend>グループ</legend>
                    <div class="character-variant-group-row">
                        <label class="field-label" for="character-variant-group-select">保存済みグループ</label>
                        <select id="character-variant-group-select" class="text-input" data-character-variant-group-select>
                            <option value="">保存済みグループなし</option>
                        </select>
                        <button class="secondary-button" type="button" data-character-variant-group-load disabled>呼び出す</button>
                        <button class="small-button small-button--danger" type="button" data-character-variant-group-delete disabled>削除</button>
                    </div>
                    <div class="character-variant-group-row">
                        <label class="field-label" for="character-variant-group-name">新しいグループ名</label>
                        <input id="character-variant-group-name" class="text-input" maxlength="100" data-character-variant-group-name>
                        <button class="secondary-button" type="button" data-character-variant-group-save>現在のチェックで保存</button>
                    </div>
                </fieldset>
                <label class="field-label" for="character-variant-search">人物LoRAを検索</label>
                <input id="character-variant-search" class="text-input" type="search" data-character-variant-search>
                <label class="field-label" for="character-variant-family">系統で絞り込む</label>
                <select id="character-variant-family" class="text-input" data-character-variant-family>
                    <option value="">すべての系統</option>
                </select>
                <div class="editor-actions">
                    <button class="secondary-button" type="button" data-character-variant-select-all>すべて選択</button>
                    <button class="secondary-button" type="button" data-character-variant-clear-all>すべて解除</button>
                    <span data-character-variant-count>0件選択</span>
                </div>
                <p class="editor-status" data-character-variant-status role="status" aria-live="polite"></p>
                <div class="character-variant-list" data-character-variant-list></div>
                <nav class="list-pagination" aria-label="差し替え候補のページ切り替え">
                    <button class="small-button" type="button" data-character-variant-previous disabled>前へ</button>
                    <span data-character-variant-page-status>0件</span>
                    <button class="small-button" type="button" data-character-variant-next disabled>次へ</button>
                </nav>
                <button class="primary-button" type="button" data-character-variant-send disabled>選択した候補をComfyUIへ送信</button>
            </dialog>

            <dialog class="option-dialog favorite-dialog" data-favorite-form-dialog>
                <form data-favorite-form enctype="multipart/form-data">
                    <h2 data-favorite-form-title>お気に入りへ保存</h2>
                    <label class="field-label" for="favorite-name">名前（任意）</label>
                    <input id="favorite-name" class="text-input" maxlength="255" data-favorite-name>
                    <label class="field-label" for="favorite-image">参考画像（JPEG・PNG・WebP、20MBまで）</label>
                    <input id="favorite-image" class="text-input" type="file" accept="image/jpeg,image/png,image/webp" data-favorite-image>
                    <label class="favorite-remove-image" data-favorite-remove-field hidden>
                        <input type="checkbox" data-favorite-remove-image> 現在の画像を削除
                    </label>
                    <div class="favorite-selection-preview" data-favorite-selection-preview></div>
                    <p class="editor-status" data-favorite-form-status role="status" aria-live="polite"></p>
                    <div class="editor-actions">
                        <button class="primary-button" type="submit" data-favorite-submit>保存</button>
                        <button class="secondary-button" type="button" data-favorite-save-copy hidden>別のお気に入りとして保存</button>
                        <button class="secondary-button" type="button" data-favorite-form-cancel>キャンセル</button>
                    </div>
                </form>
            </dialog>

            <dialog class="option-dialog favorite-list-dialog" data-favorite-list-dialog>
                <div class="subsection-heading">
                    <h2>お気に入り</h2>
                    <button class="secondary-button" type="button" data-favorite-list-close>閉じる</button>
                </div>
                <p class="editor-status" data-favorite-list-status role="status" aria-live="polite"></p>
                <button class="secondary-button" type="button" data-favorite-list-retry hidden>再読み込み</button>
                <div class="favorite-grid" data-favorite-list></div>
            </dialog>

            <dialog class="option-dialog" data-family-dialog>
                <h2>系統を管理</h2>
                <label class="field-label" for="family-manage-list">登録済みの系統</label>
                <select id="family-manage-list" class="text-input" data-family-list></select>
                <label class="field-label" for="family-manage-name">系統名</label>
                <input id="family-manage-name" class="text-input" data-family-name>
                <p class="editor-status" data-family-status role="status" aria-live="polite"></p>
                <fieldset class="workflow-settings">
                    <legend>人物LoRAの生成設定</legend>
                    <p class="editor-help">人物LoRAを登録する前に、この系統で使用する設定値を登録します。</p>
                    <label class="field-label" for="generation-setting-list">登録済み設定</label>
                    <select id="generation-setting-list" class="text-input" size="5" data-generation-setting-list></select>
                    <label class="field-label" for="generation-setting-kind">種類</label>
                    <select id="generation-setting-kind" class="text-input" data-generation-setting-kind>
                        <option value="checkpoint">Checkpoint</option>
                        <option value="sampler">サンプラー</option>
                        <option value="scheduler">スケジューラ</option>
                    </select>
                    <label class="field-label" for="generation-setting-value">ComfyUIへ渡す値</label>
                    <input id="generation-setting-value" class="text-input" data-generation-setting-value>
                    <p class="editor-status" data-generation-setting-status role="status" aria-live="polite"></p>
                    <div class="editor-actions">
                        <button class="secondary-button" type="button" data-generation-setting-add>新規登録</button>
                        <button class="secondary-button" type="button" data-generation-setting-update disabled>更新</button>
                        <button class="danger-button" type="button" data-generation-setting-delete disabled>削除</button>
                    </div>
                </fieldset>
                <fieldset class="workflow-settings">
                    <legend>ComfyUIワークフロー</legend>
                    <p class="editor-help">
                        1. 上の「登録済みの系統」を選ぶ<br>
                        2. その系統用のAPI形式JSONを選ぶ<br>
                        3. JSONに合わせて各項目のノードIDと入力名を入力する<br>
                        4. checkpoint・sampler・schedulerは各一行以上設定する<br>
                        5. 「ワークフローを保存」を押し、「設定済み」と表示されることを確認する
                    </p>
                    <p class="editor-help">
                        ノードIDと入力名はワークフローごとに異なります。ComfyUIから出力したAPI形式JSONの内容を確認して指定してください。
                    </p>
                    <label class="field-label" for="comfyui-workflow-file">API形式JSON（5 MiBまで）</label>
                    <input id="comfyui-workflow-file" class="text-input" type="file" accept="application/json,.json" data-comfyui-workflow-file>
                    @foreach (['positive', 'negative', 'seed'] as $role)
                        <div class="workflow-mapping-row">
                            <strong>{{ $role }}</strong>
                            <label>ノードID <input class="text-input" data-comfyui-{{ $role }}-node></label>
                            <label>入力名 <input class="text-input" data-comfyui-{{ $role }}-input></label>
                        </div>
                    @endforeach
                    @foreach ([
                        ['role' => 'checkpoint', 'label' => 'checkpoint', 'optional' => false],
                        ['role' => 'sampler', 'label' => 'sampler', 'optional' => false],
                        ['role' => 'scheduler', 'label' => 'scheduler', 'optional' => false],
                        ['role' => 'outputFilenamePrefix', 'label' => '保存先プレフィックス（任意）', 'optional' => true],
                    ] as $mapping)
                        <div class="workflow-mapping-list" data-comfyui-mapping-list="{{ $mapping['role'] }}" data-optional="{{ $mapping['optional'] ? 'true' : 'false' }}">
                            <div class="subsection-heading subsection-heading--compact">
                                <strong>{{ $mapping['label'] }}</strong>
                                <button class="small-button" type="button" data-comfyui-mapping-add="{{ $mapping['role'] }}">入力先を追加</button>
                            </div>
                            <div data-comfyui-mapping-rows></div>
                        </div>
                    @endforeach
                    <p class="editor-status" data-comfyui-workflow-status role="status" aria-live="polite"></p>
                    <div class="editor-actions">
                        <button class="secondary-button" type="button" data-comfyui-workflow-save>ワークフローを保存</button>
                        <button class="danger-button" type="button" data-comfyui-workflow-delete disabled>ワークフローを削除</button>
                    </div>
                </fieldset>
                <div class="editor-actions">
                    <button class="primary-button" type="button" data-family-add>追加</button>
                    <button class="secondary-button" type="button" data-family-rename>名前を変更</button>
                    <button class="danger-button" type="button" data-family-delete>削除</button>
                    <button class="secondary-button" type="button" data-family-close>閉じる</button>
                </div>
            </dialog>

            <dialog class="option-dialog" data-option-dialog>
                <form data-option-form>
                    <input type="hidden" data-option-id>
                    <h2 data-option-dialog-title></h2>
                    <div data-lora-field hidden>
                        <label class="field-label" for="option-file-name">ファイル名</label>
                        <input id="option-file-name" class="text-input" data-option-file-name>
                    </div>
                    <div data-lora-family-field hidden>
                        <label class="field-label" for="option-family">系統</label>
                        <select id="option-family" class="text-input" data-option-family></select>
                    </div>
                    <div data-lora-generation-settings hidden>
                        <p class="editor-help">系統に登録済みの生成設定を選択してください。</p>
                        @foreach ([
                            ['kind' => 'checkpoint', 'label' => 'Checkpoint'],
                            ['kind' => 'sampler', 'label' => 'サンプラー'],
                            ['kind' => 'scheduler', 'label' => 'スケジューラ'],
                        ] as $setting)
                            <label class="field-label" for="option-{{ $setting['kind'] }}">{{ $setting['label'] }}</label>
                            <select id="option-{{ $setting['kind'] }}" class="text-input" data-option-generation-setting="{{ $setting['kind'] }}" required></select>
                        @endforeach
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

            <dialog class="option-dialog" data-clothing-option-dialog>
                <form data-clothing-option-form>
                    <h2 data-clothing-option-title></h2>
                    <div data-clothing-lora-fields>
                        <label class="field-label" for="clothing-option-file-name">ファイル名</label>
                        <input id="clothing-option-file-name" class="text-input" data-clothing-option-file-name>
                        <label class="field-label" for="clothing-lora-name">登録名</label>
                        <input id="clothing-lora-name" class="text-input" data-clothing-lora-name>
                        <label class="field-label" for="clothing-option-strength">推奨強度</label>
                        <select id="clothing-option-strength" class="text-input" data-clothing-option-strength>
                            @foreach (range(0, 10) as $strengthStep)
                                @php($strength = $strengthStep === 10 ? '1' : number_format($strengthStep / 10, 1))
                                <option value="{{ $strength }}" @selected($strengthStep === 10)>{{ $strength }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div data-clothing-trigger-fields hidden>
                        <label class="field-label" for="clothing-trigger-name">登録名</label>
                        <input id="clothing-trigger-name" class="text-input" data-clothing-trigger-name>
                        <label class="field-label" for="clothing-option-content">文面</label>
                        <textarea id="clothing-option-content" rows="6" data-clothing-option-content></textarea>
                    </div>
                    <p class="editor-status" data-clothing-option-status role="status" aria-live="polite"></p>
                    <div class="editor-actions">
                        <button class="primary-button" type="submit">保存</button>
                        <button class="secondary-button" type="button" data-clothing-option-cancel>キャンセル</button>
                    </div>
                </form>
            </dialog>

            <dialog class="option-dialog" data-clothing-delete-dialog>
                <form data-clothing-delete-form>
                    <h2>登録を削除</h2>
                    <p data-clothing-delete-message></p>
                    <p class="editor-status" data-clothing-delete-status role="status" aria-live="polite"></p>
                    <div class="editor-actions">
                        <button class="danger-button" type="submit">削除</button>
                        <button class="secondary-button" type="button" data-clothing-delete-cancel>キャンセル</button>
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

            <dialog class="option-dialog option-manage-dialog" data-category-manage-dialog>
                <h2 data-category-manage-title></h2>
                <div class="option-manage-actions">
                    <button class="secondary-button option-manage-edit" type="button" data-category-manage-edit>編集</button>
                    <button class="danger-button" type="button" data-category-manage-delete>削除</button>
                    <button class="secondary-button" type="button" data-category-manage-cancel>キャンセル</button>
                </div>
            </dialog>

            <dialog class="option-dialog" data-option-group-dialog>
                <form data-option-group-form>
                    <h2 data-option-group-dialog-title></h2>
                    <label class="field-label" for="option-group-name">ブロック名</label>
                    <input id="option-group-name" class="text-input" data-option-group-name>
                    <label class="field-label" for="option-group-mode">選択方式</label>
                    <select id="option-group-mode" class="text-input" data-option-group-mode>
                        <option value="multiple">複数選択</option>
                        <option value="single">1つ選択</option>
                    </select>
                    <p class="editor-status" data-option-group-status role="status" aria-live="polite"></p>
                    <div class="editor-actions">
                        <button class="primary-button" type="submit">保存</button>
                        <button class="secondary-button" type="button" data-option-group-cancel>キャンセル</button>
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
            </div>

            <aside class="prompt-sidebar selection-summary" aria-labelledby="selection-summary-heading">
                <h2 id="selection-summary-heading">選択中</h2>
                <div data-selection-summary-list></div>
                <div class="selection-summary__action">
                    <button class="display-button selection-summary__display-button" type="button" data-sidebar-display disabled>
                        プロンプトを作成
                    </button>
                </div>
                <section class="prompt-clips" aria-labelledby="prompt-clips-heading" data-prompt-clips>
                    <h2 id="prompt-clips-heading">クリップ</h2>
                    <form class="prompt-clips__form" data-prompt-clip-form>
                        <label for="prompt-clip-name">名前</label>
                        <div class="prompt-clips__save-row">
                            <input
                                id="prompt-clip-name"
                                type="text"
                                maxlength="100"
                                data-prompt-clip-name
                            >
                            <button class="small-button" type="submit">保存</button>
                        </div>
                    </form>
                    <p class="prompt-clips__empty" data-prompt-clip-empty>クリップはありません。</p>
                    <ul class="prompt-clips__list" data-prompt-clip-list></ul>
                    <p class="prompt-clips__status" data-prompt-clip-status role="status" aria-live="polite"></p>
                </section>
            </aside>
        </main>
    </body>
</html>
