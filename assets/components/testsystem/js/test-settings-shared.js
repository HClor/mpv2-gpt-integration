(function () {
    'use strict';

    function toInt(value, fallback) {
        var parsed = parseInt(value, 10);
        return Number.isFinite(parsed) ? parsed : fallback;
    }

    function escapeHtml(value) {
        var text = value == null ? '' : String(value);
        if (window.TestSystemEscapeHtml && typeof window.TestSystemEscapeHtml === 'function') {
            return window.TestSystemEscapeHtml(text);
        }
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function buildSettingsFields(settings) {
        var safe = settings || {};

        var mode = safe.mode === 'exam' ? 'exam' : 'training';
        var title = typeof safe.title === 'string' ? safe.title : '';
        var description = typeof safe.description === 'string' ? safe.description : '';
        var passScore = toInt(safe.pass_score, 70);
        var timeLimit = toInt(safe.time_limit, 0);
        var questionsPerSession = toInt(safe.questions_per_session, 20);
        var randomizeQuestions = toInt(safe.randomize_questions, 1) === 1;
        var randomizeAnswers = toInt(safe.randomize_answers, 1) === 1;
        var allowGuestPass = toInt(safe.allow_guest_pass, 0) === 1;

        var html = '';
        html += '<div class="row">';
        html += '<div class="col-md-6 mb-3"><label class="form-label">Название теста</label>';
        html += '<input type="text" name="title" class="form-control" value="' + escapeHtml(title) + '" required></div>';
        html += '<div class="col-md-6 mb-3"><label class="form-label">Режим теста</label>';
        html += '<select name="mode" class="form-select">';
        html += '<option value="training"' + (mode === 'training' ? ' selected' : '') + '>Тренировка</option>';
        html += '<option value="exam"' + (mode === 'exam' ? ' selected' : '') + '>Экзамен</option>';
        html += '</select></div></div>';

        html += '<div class="mb-3"><label class="form-label">Описание</label>';
        html += '<textarea name="description" class="form-control" rows="3">' + escapeHtml(description) + '</textarea></div>';

        html += '<div class="row">';
        html += '<div class="col-md-4 mb-3"><label class="form-label">Проходной балл (%)</label>';
        html += '<input type="number" name="pass_score" class="form-control" value="' + passScore + '" min="0" max="100"></div>';
        html += '<div class="col-md-4 mb-3"><label class="form-label">Время (минут, 0 = без ограничений)</label>';
        html += '<input type="number" name="time_limit" class="form-control" value="' + timeLimit + '" min="0"></div>';
        html += '<div class="col-md-4 mb-3"><label class="form-label">Вопросов за попытку</label>';
        html += '<input type="number" name="questions_per_session" class="form-control" value="' + questionsPerSession + '" min="1"></div>';
        html += '</div>';

        html += '<div class="row">';
        html += '<div class="col-md-6 mb-3"><div class="form-check form-switch">';
        html += '<input class="form-check-input" type="checkbox" name="randomize_questions" id="ts-randomize-q"' + (randomizeQuestions ? ' checked' : '') + '>';
        html += '<label class="form-check-label" for="ts-randomize-q">Перемешивать вопросы</label></div></div>';
        html += '<div class="col-md-6 mb-3"><div class="form-check form-switch">';
        html += '<input class="form-check-input" type="checkbox" name="randomize_answers" id="ts-randomize-a"' + (randomizeAnswers ? ' checked' : '') + '>';
        html += '<label class="form-check-label" for="ts-randomize-a">Перемешивать ответы</label></div></div>';
        html += '</div>';

        html += '<div class="row">';
        html += '<div class="col-md-12 mb-3"><div class="form-check form-switch">';
        html += '<input class="form-check-input" type="checkbox" name="allow_guest_pass" id="ts-allow-guest-pass"' + (allowGuestPass ? ' checked' : '') + '>';
        html += '<label class="form-check-label" for="ts-allow-guest-pass"><i class="bi bi-person-check me-1"></i>Разрешить гостевое прохождение</label>';
        html += '<div class="form-text">Неавторизованные пользователи смогут запускать тест с фиксированными 10 вопросами.</div>';
        html += '</div></div>';
        html += '</div>';

        return html;
    }

    function parseSettingsForm(formData) {
        return {
            title: formData.get('title') || '',
            description: formData.get('description') || '',
            mode: formData.get('mode') || 'training',
            pass_score: toInt(formData.get('pass_score'), 70),
            time_limit: toInt(formData.get('time_limit'), 0),
            questions_per_session: toInt(formData.get('questions_per_session'), 20),
            randomize_questions: formData.get('randomize_questions') ? 1 : 0,
            randomize_answers: formData.get('randomize_answers') ? 1 : 0,
            allow_guest_pass: formData.get('allow_guest_pass') ? 1 : 0
        };
    }

    window.TestSystemTestSettings = {
        buildSettingsFields: buildSettingsFields,
        parseSettingsForm: parseSettingsForm
    };
})();
