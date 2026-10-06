<!DOCTYPE html>
<html lang="ja">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>オプション管理</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body>
        <main
            class="management-page"
            data-prompt-option-management
            data-prompt-options-url="{{ $promptOptionsUrl }}"
            data-prompt-option-groups-url="{{ $promptOptionGroupsUrl }}"
            data-prompt-option-categories-url="{{ $promptOptionCategoriesUrl }}"
        >
            <header class="management-page__header">
                <div>
                    <p class="eyebrow">PROMPT OPTION MANAGEMENT</p>
                    <h1>オプションを管理</h1>
                    <p>カテゴリは画面上の整理に使います。プロンプトの出力順はブロック順で決まります。</p>
                </div>
                <a class="secondary-button" href="{{ $promptPreparationUrl }}">選択画面に戻る</a>
            </header>

            <section class="management-panel" aria-labelledby="category-management-heading">
                <h2 id="category-management-heading">カテゴリ</h2>
                <form class="option-category-form" data-option-category-form>
                    <input type="hidden" data-option-category-id>
                    <label class="field-label" for="option-category-name">カテゴリ名</label>
                    <div class="inline-form-row">
                        <input id="option-category-name" class="text-input" data-option-category-name>
                        <button class="primary-button" type="submit">保存</button>
                        <button class="secondary-button" type="button" data-option-category-cancel>クリア</button>
                    </div>
                    <p class="editor-status" data-option-category-status role="status" aria-live="polite"></p>
                </form>
                <div data-option-category-management-list></div>
            </section>

            <section class="management-panel prompt-categories" aria-labelledby="option-management-heading" data-prompt-categories>
                <div class="management-page__section-heading">
                    <div>
                        <h2 id="option-management-heading">ブロックと項目</h2>
                        <p>カテゴリ所属、出力順、項目の内容と移動先を編集できます。</p>
                    </div>
                    <button class="small-button" type="button" data-management-group-add>ブロックを追加</button>
                </div>
                <div class="option-load-state">
                    <p data-category-load-status role="status" aria-live="polite"></p>
                    <button class="secondary-button" type="button" data-category-retry hidden>再読み込み</button>
                </div>
                <div data-option-groups hidden></div>
                <button type="button" data-option-group-add hidden disabled>ブロックを追加</button>
                <div data-option-management-groups></div>
            </section>

            <dialog class="option-dialog" data-category-dialog>
                <form data-category-form>
                    <input type="hidden" data-category-id>
                    <h2 data-category-dialog-title></h2>
                    <p class="category-editing-id" data-category-editing-id hidden></p>
                    <label class="field-label" for="managed-option-name">登録名</label>
                    <input id="managed-option-name" class="text-input" data-category-name>
                    <label class="field-label" for="managed-option-content">文面</label>
                    <textarea id="managed-option-content" rows="6" data-category-content></textarea>
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
                    <label class="field-label" for="managed-option-group-name">ブロック名</label>
                    <input id="managed-option-group-name" class="text-input" data-option-group-name>
                    <label class="field-label" for="managed-option-group-mode">選択方式</label>
                    <select id="managed-option-group-mode" class="text-input" data-option-group-mode>
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
        </main>
    </body>
</html>
