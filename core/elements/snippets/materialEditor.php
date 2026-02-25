<?php
/**
 * Сниппет: materialEditor - Редактор учебных материалов
 * Вызывается из: learning-materials-template.html
 * Назначение: Quill редактор для редактирования учебных материалов
 * Права: LMS Experts, LMS Admins или владелец материала
 *
 * @package TestSystem
 */

// Проверка авторизации
if (!$modx->user->hasSessionContext('web')) {
    return '<div class="ts-alert ts-alert-warning">Требуется авторизация для редактирования</div>';
}

$userId = (int)$modx->user->get('id');
$userGroups = $modx->user->getUserGroupNames();
$resourceId = $modx->resource->get('id');
$createdBy = (int)$modx->resource->get('createdby');

// Проверка прав редактирования
$canEdit = $createdBy === $userId
    || in_array('LMS Admins', $userGroups, true)
    || in_array('LMS Experts', $userGroups, true);

if (!$canEdit) {
    return '<div class="ts-alert ts-alert-danger">Нет прав для редактирования</div>';
}

// Получаем текущий контент
$currentTitle = $modx->resource->get('pagetitle');
$currentIntrotext = $modx->resource->get('introtext');
$currentContent = $modx->resource->get('content');

$output = <<<HTML
<div class="material-editor-container mb-4">
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Редактирование материала</h5>
        </div>
        <div class="card-body">

            <div class="mb-3">
                <label class="form-label fw-bold">Название материала</label>
                <input type="text" class="form-control" id="material-title" value="{$currentTitle}">
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Краткое описание</label>
                <textarea class="form-control" id="material-introtext" rows="3">{$currentIntrotext}</textarea>
                <small class="text-muted">Отображается в списке материалов</small>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Содержимое</label>
                <div id="material-content-editor" style="min-height: 400px; background: white;"></div>
                <textarea id="material-content" style="display:none;">{$currentContent}</textarea>
            </div>

            <div class="mb-3">
                <div class="d-flex gap-2">
                    <button class="ts-btn ts-btn-secondary" id="insert-image-btn">
                        <i class="bi bi-image"></i> Вставить изображение
                    </button>
                    <button class="ts-btn ts-btn-secondary" id="insert-document-btn">
                        <i class="bi bi-file-earmark-pdf"></i> Вставить документ
                    </button>
                </div>
                <small class="text-muted d-block mt-2">
                    <i class="bi bi-info-circle"></i>
                    Изображения: до 5MB (JPG, PNG, GIF, WebP).
                    Документы: до 10MB (PDF, Excel, Word, PowerPoint)
                </small>
            </div>

            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="material-published" checked>
                <label class="form-check-label" for="material-published">
                    Опубликовать материал
                </label>
            </div>

            <div class="d-flex gap-2">
                <button class="ts-btn ts-btn-primary" id="save-material-btn">
                    <i class="bi bi-check-circle"></i> Сохранить
                </button>
                <a href="{$modx->makeUrl($resourceId)}" class="ts-btn ts-btn-secondary">
                    <i class="bi bi-x-circle"></i> Отмена
                </a>
            </div>
        </div>
    </div>
</div>

<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>

<script>
(function() {
    const API_URL = '/assets/components/testsystem/ajax/testsystem.php';
    const MATERIAL_ID = {$resourceId};

    // Инициализация Quill
    const quill = new Quill('#material-content-editor', {
        theme: 'snow',
        modules: {
            toolbar: [
                [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                [{ 'indent': '-1'}, { 'indent': '+1' }],
                [{ 'color': [] }, { 'background': [] }],
                [{ 'align': [] }],
                ['blockquote', 'code-block'],
                ['link'],
                ['clean']
            ]
        },
        placeholder: 'Введите содержимое материала...'
    });

    // Загружаем текущий контент
    const currentContent = document.getElementById('material-content').value;
    if (currentContent) {
        quill.root.innerHTML = currentContent;
    }

    // Обновляем hidden textarea при изменении
    quill.on('text-change', function() {
        document.getElementById('material-content').value = quill.root.innerHTML;
    });

    // CSRF Token
    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.content : null;
    }

    // Вставка изображения
    document.getElementById('insert-image-btn').addEventListener('click', function() {
        const input = document.createElement('input');
        input.setAttribute('type', 'file');
        input.setAttribute('accept', 'image/png, image/gif, image/jpeg, image/jpg, image/webp');
        input.click();

        input.onchange = async () => {
            const file = input.files[0];
            if (!file) return;

            if (file.size > 5 * 1024 * 1024) {
                alert('Файл слишком большой (макс. 5MB)');
                return;
            }

            const formData = new FormData();
            formData.append('image', file);

            const csrfToken = getCsrfToken();
            if (csrfToken) {
                formData.append('csrf_token', csrfToken);
            }

            try {
                const range = quill.getSelection(true);
                quill.insertText(range.index, 'Загрузка...', 'user');

                const response = await fetch('/assets/components/testsystem/ajax/upload-image.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                quill.deleteText(range.index, 11);

                if (result.success) {
                    quill.insertEmbed(range.index, 'image', result.url, 'user');
                    quill.insertText(range.index + 1, '\\n', 'user');
                    quill.setSelection(range.index + 2);
                } else {
                    alert('Ошибка загрузки: ' + result.message);
                }
            } catch (error) {
                alert('Ошибка: ' + error.message);
            }
        };
    });

    // Вставка документа
    document.getElementById('insert-document-btn').addEventListener('click', function() {
        const input = document.createElement('input');
        input.setAttribute('type', 'file');
        input.setAttribute('accept', '.pdf,.xlsx,.xls,.docx,.doc,.pptx,.ppt,.txt,.csv');
        input.click();

        input.onchange = async () => {
            const file = input.files[0];
            if (!file) return;

            if (file.size > 10 * 1024 * 1024) {
                alert('Файл слишком большой (макс. 10MB)');
                return;
            }

            const formData = new FormData();
            formData.append('document', file);

            const csrfToken = getCsrfToken();
            if (csrfToken) {
                formData.append('csrf_token', csrfToken);
            }

            try {
                const range = quill.getSelection(true);
                quill.insertText(range.index, 'Загрузка документа...', 'user');

                const response = await fetch('/assets/components/testsystem/ajax/upload-document.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                quill.deleteText(range.index, 20);

                if (result.success) {
                    // Вставляем ссылку на документ с иконкой
                    const linkHtml = `<p><a href="${result.url}" target="_blank" class="ts-btn ts-btn-ghost ts-btn-sm"><i class="bi ${result.icon} me-2"></i>${result.filename} (${result.sizeFormatted})</a></p>`;
                    quill.clipboard.dangerouslyPasteHTML(range.index, linkHtml);
                    quill.setSelection(range.index + linkHtml.length);
                } else {
                    alert('Ошибка загрузки: ' + result.message);
                }
            } catch (error) {
                alert('Ошибка: ' + error.message);
            }
        };
    });

    // Сохранение материала
    document.getElementById('save-material-btn').addEventListener('click', async function() {
        const title = document.getElementById('material-title').value.trim();
        const introtext = document.getElementById('material-introtext').value.trim();
        const content = quill.root.innerHTML;
        const published = document.getElementById('material-published').checked ? 1 : 0;

        if (!title) {
            alert('Введите название материала');
            return;
        }

        const saveBtn = this;
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Сохранение...';

        try {
            const csrfToken = getCsrfToken();
            const headers = { 'Content-Type': 'application/json' };
            if (csrfToken) {
                headers['X-CSRF-TOKEN'] = csrfToken;
            }

            const response = await fetch(API_URL, {
                method: 'POST',
                headers: headers,
                body: JSON.stringify({
                    action: 'saveMaterial',
                    data: {
                        material_id: MATERIAL_ID,
                        title: title,
                        introtext: introtext,
                        content: content,
                        published: published
                    }
                })
            });

            const result = await response.json();

            if (result.success) {
                alert('Материал сохранен успешно!');
                window.location.reload();
            } else {
                throw new Error(result.message || 'Ошибка сохранения');
            }
        } catch (error) {
            alert('Ошибка: ' + error.message);
        } finally {
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="bi bi-check-circle"></i> Сохранить';
        }
    });
})();
</script>
HTML;

return $output;