/**
 * Question List Shared Renderer
 * Общие утилиты и рендер-функции для интерфейса "Список вопросов теста"
 *
 * PR-1: выделение общего рендера без изменения текущей бизнес-логики.
 */
(function () {
    'use strict';

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }

    function stripHtml(html) {
        const tmp = document.createElement('div');
        tmp.innerHTML = html || '';
        return tmp.textContent || tmp.innerText || '';
    }

    function buildHeaderHtml(options) {
        const opts = options || {};
        const title = opts.title || 'Список вопросов теста';
        const controlsHtml = opts.controlsHtml || '';
        const titleClass = opts.titleClass || 'mb-3';
        const controlsClass = opts.controlsClass || 'd-flex flex-column flex-sm-row gap-2 w-100 align-items-stretch align-items-sm-center mb-3';

        return '' +
            '<h3 class="' + escapeHtml(titleClass) + '"><i class="bi bi-list-ul"></i> ' + escapeHtml(title) + '</h3>' +
            '<div class="' + escapeHtml(controlsClass) + '">' + controlsHtml + '</div>';
    }

    function getFilterButtonClass(name, isActive) {
        const byName = {
            all: ['btn-primary', 'btn-outline-primary'],
            published: ['btn-success', 'btn-outline-success'],
            unpublished: ['btn-secondary', 'btn-outline-secondary'],
            learning: ['btn-info', 'btn-outline-info']
        };
        const pair = byName[name] || ['btn-primary', 'btn-outline-primary'];
        return isActive ? pair[0] : pair[1];
    }

    function buildFiltersHtml(stats, activeFilter, options) {
        const s = stats || { total: 0, published: 0, unpublished: 0, learning: 0 };
        const f = activeFilter || 'all';
        const opts = options || {};
        const resolve = typeof opts === 'function'
            ? opts
            : (typeof opts.buttonClassResolver === 'function' ? opts.buttonClassResolver : getFilterButtonClass);
        const onclickMap = typeof opts === 'object' && opts.onclickMap ? opts.onclickMap : {};

        function buttonAttr(name) {
            if (onclickMap[name]) {
                return ' onclick="' + escapeHtml(onclickMap[name]) + '"';
            }
            return ' data-filter="' + escapeHtml(name) + '"';
        }

        return '' +
            '<div class="questions-filters-container">' +
            '<div class="row g-2">' +
            '<div class="col-6 col-md-3">' +
            '<button type="button" class="btn ' + resolve('all', f === 'all') + ' w-100"' + buttonAttr('all') + '>' +
            'Все <span class="badge bg-light text-dark ms-1">' + Number(s.total || 0) + '</span></button></div>' +
            '<div class="col-6 col-md-3">' +
            '<button type="button" class="btn ' + resolve('published', f === 'published') + ' w-100"' + buttonAttr('published') + '>' +
            'Опубликовано <span class="badge bg-light text-dark ms-1">' + Number(s.published || 0) + '</span></button></div>' +
            '<div class="col-6 col-md-3">' +
            '<button type="button" class="btn ' + resolve('unpublished', f === 'unpublished') + ' w-100"' + buttonAttr('unpublished') + '>' +
            'Не опубликовано <span class="badge bg-light text-dark ms-1">' + Number(s.unpublished || 0) + '</span></button></div>' +
            '<div class="col-6 col-md-3">' +
            '<button type="button" class="btn ' + resolve('learning', f === 'learning') + ' w-100"' + buttonAttr('learning') + '>' +
            'В обучении <span class="badge bg-light text-dark ms-1">' + Number(s.learning || 0) + '</span></button></div>' +
            '</div></div>';
    }

    function toggleSelectAllCheckboxes(selector) {
        const checkboxes = document.querySelectorAll(selector || '.question-select-checkbox');
        if (checkboxes.length === 0) {
            return [];
        }

        const allChecked = Array.from(checkboxes).every(function (cb) { return cb.checked; });
        checkboxes.forEach(function (cb) {
            cb.checked = !allChecked;
        });
        return Array.from(checkboxes);
    }

    function createQuestionListActions(options) {
        const opts = options || {};

        function getSelected() {
            const getter = opts.getSelectedIds;
            if (typeof getter !== 'function') {
                throw new Error('getSelectedIds is not configured');
            }
            return getter() || [];
        }

        async function runWithSelection(handlerName, emptyMessage) {
            const selectedIds = getSelected();
            if (!selectedIds.length) {
                throw new Error(emptyMessage || 'Выберите хотя бы один вопрос');
            }

            const handler = opts[handlerName];
            if (typeof handler !== 'function') {
                throw new Error(handlerName + ' is not configured');
            }

            return handler(selectedIds);
        }

        return {
            selectAll: function () {
                const handler = opts.onSelectAll;
                if (typeof handler !== 'function') {
                    throw new Error('onSelectAll is not configured');
                }
                return handler();
            },
            clearSelection: function () {
                const handler = opts.onClearSelection;
                if (typeof handler !== 'function') {
                    throw new Error('onClearSelection is not configured');
                }
                return handler();
            },
            publishSelected: function () {
                return runWithSelection('onPublish', opts.noSelectionMessage);
            },
            unpublishSelected: function () {
                return runWithSelection('onUnpublish', opts.noSelectionMessage);
            },
            deleteSelected: function () {
                return runWithSelection('onDelete', opts.noSelectionMessage);
            }
        };
    }

    function requireSharedApi(requiredMethods) {
        const methods = Array.isArray(requiredMethods) ? requiredMethods : [];
        const api = window.QuestionListShared;
        if (!api) {
            throw new Error('QuestionListShared is not loaded');
        }

        const missing = methods.filter(function (name) {
            return typeof api[name] !== 'function';
        });

        if (missing.length > 0) {
            throw new Error('QuestionListShared missing methods: ' + missing.join(', '));
        }

        return api;
    }

    function buildBulkPanelHtml(options) {
        const opts = options || {};
        const selectedCount = Number(opts.selectedCount || 0);
        const visibleClass = selectedCount > 0 ? '' : ' d-none';
        const actionsHtml = opts.actionsHtml || '';

        return '' +
            '<div id="bulk-actions-panel" class="alert alert-light border mt-2 mb-0' + visibleClass + '">' +
            '<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">' +
            '<div class="d-flex align-items-center gap-2 flex-wrap">' +
            '<strong>Выбрано: <span id="selected-questions-count">' + selectedCount + '</span></strong>' +
            '</div>' +
            '<div class="d-flex flex-wrap gap-2">' + actionsHtml + '</div>' +
            '</div></div>';
    }

    function buildQuestionPreview(question) {
        const q = question || {};
        const rawText = stripHtml(q.question_text || q.text || '');
        const shortText = rawText.substring(0, 120);

        return {
            shortText: shortText,
            isTrimmed: rawText.length >= 120,
            hasExplanation: !!(q.explanation),
            questionTypeLabel: q.question_type === 'single' ? '⭕ Один ответ' : '☑️ Несколько ответов'
        };
    }

    window.QuestionListShared = {
        escapeHtml: escapeHtml,
        stripHtml: stripHtml,
        getFilterButtonClass: getFilterButtonClass,
        buildHeaderHtml: buildHeaderHtml,
        buildFiltersHtml: buildFiltersHtml,
        buildBulkPanelHtml: buildBulkPanelHtml,
        buildQuestionPreview: buildQuestionPreview,
        toggleSelectAllCheckboxes: toggleSelectAllCheckboxes,
        createQuestionListActions: createQuestionListActions,
        requireSharedApi: requireSharedApi
    };
})();
