/**
 * SPECIAL QUESTION TYPES - Sprint 12
 * Расширенные типы вопросов: matching, ordering, fill_blank, essay
 * Интеграция с основным test runner
 */

(function() {
    'use strict';

    // XSS Protection
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }

    // ==================== MATCHING QUESTION (Сопоставление) ====================

    function renderMatchingQuestion(question, questionData) {
        const pairs = JSON.parse(questionData.pairs || '[]');
        const leftItems = pairs.map(p => p.left);
        const rightItems = pairs.map(p => p.right);

        // Перемешиваем правые элементы
        const shuffledRight = shuffleArray([...rightItems]);

        let html = `
            <div class="matching-question" data-question-id="${question.id}">
                <h5 class="mb-4">${escapeHtml(question.question_text)}</h5>

                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-muted mb-3">Термины:</h6>
                        <div class="list-group mb-3">
        `;

        leftItems.forEach((item, index) => {
            html += `
                <div class="list-group-item matching-left-item"
                     data-index="${index}">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="flex-grow-1">
                            <strong>${escapeHtml(item)}</strong>
                        </div>
                        <div>
                            <select class="form-select form-select-sm matching-select"
                                    data-left-index="${index}"
                                    style="min-width: 50px;">
                                <option value="">—</option>
                                ${shuffledRight.map((rightItem, idx) => `
                                    <option value="${idx}">${String.fromCharCode(65 + idx)}</option>
                                `).join('')}
                            </select>
                        </div>
                    </div>
                </div>
            `;
        });

        html += `
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted mb-3">Определения:</h6>
                        <div class="list-group">
        `;

        shuffledRight.forEach((item, index) => {
            html += `
                <div class="list-group-item matching-right-item"
                     data-value="${index}">
                    <strong>${String.fromCharCode(65 + index)}.</strong>
                    ${escapeHtml(item)}
                </div>
            `;
        });

        html += `
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Сохраняем правильный порядок для проверки
        const correctMapping = {};
        pairs.forEach((pair, idx) => {
            const rightIndex = shuffledRight.indexOf(pair.right);
            correctMapping[idx] = rightIndex;
        });

        // Сохраняем в data-атрибут
        setTimeout(() => {
            document.querySelector(`[data-question-id="${question.id}"]`)
                ?.setAttribute('data-correct-mapping', JSON.stringify(correctMapping));
        }, 100);

        return html;
    }

    function getMatchingAnswer(questionElement) {
        const selects = questionElement.querySelectorAll('.matching-select');
        const answers = {};

        selects.forEach(select => {
            const leftIndex = select.dataset.leftIndex;
            const rightIndex = select.value;
            if (rightIndex) {
                answers[leftIndex] = parseInt(rightIndex);
            }
        });

        return { type: 'matching', matches: answers };
    }

    function validateMatchingAnswer(questionElement, userAnswer) {
        const correctMapping = JSON.parse(questionElement.dataset.correctMapping || '{}');
        const userMatches = userAnswer.matches || {};

        let correct = 0;
        let total = Object.keys(correctMapping).length;

        Object.keys(correctMapping).forEach(leftIdx => {
            if (userMatches[leftIdx] === correctMapping[leftIdx]) {
                correct++;
            }
        });

        return {
            isCorrect: correct === total,
            score: Math.round((correct / total) * 100),
            correctCount: correct,
            totalCount: total
        };
    }

    // ==================== ORDERING QUESTION (Упорядочивание) ====================

    function renderOrderingQuestion(question, questionData) {
        const items = JSON.parse(questionData.items || '[]');
        const shuffledItems = shuffleArray([...items]);

        let html = `
            <div class="ordering-question" data-question-id="${question.id}">
                <h5 class="mb-4">${escapeHtml(question.question_text)}</h5>
                <p class="text-muted mb-3">Расположите элементы в правильном порядке (перетаскивайте мышью):</p>

                <div class="list-group ordering-list" id="ordering-${question.id}">
        `;

        shuffledItems.forEach((item, index) => {
            html += `
                <div class="list-group-item ordering-item"
                     data-original-text="${escapeHtml(item)}"
                     draggable="true"
                     style="cursor: move;">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-grip-vertical me-3 text-muted"></i>
                        <span class="order-number badge bg-secondary me-3">${index + 1}</span>
                        <span class="order-text">${escapeHtml(item)}</span>
                    </div>
                </div>
            `;
        });

        html += `
                </div>
            </div>
        `;

        // Сохраняем правильный порядок
        setTimeout(() => {
            const container = document.querySelector(`[data-question-id="${question.id}"]`);
            if (container) {
                container.setAttribute('data-correct-order', JSON.stringify(items));
                initDragAndDrop(`ordering-${question.id}`);
            }
        }, 100);

        return html;
    }

    function initDragAndDrop(containerId) {
        const container = document.getElementById(containerId);
        if (!container) return;

        let draggedElement = null;

        container.addEventListener('dragstart', function(e) {
            if (e.target.classList.contains('ordering-item')) {
                draggedElement = e.target;
                e.target.classList.add('dragging');
            }
        });

        container.addEventListener('dragend', function(e) {
            if (e.target.classList.contains('ordering-item')) {
                e.target.classList.remove('dragging');
                updateOrderNumbers(container);
            }
        });

        container.addEventListener('dragover', function(e) {
            e.preventDefault();
            const afterElement = getDragAfterElement(container, e.clientY);
            if (afterElement == null) {
                container.appendChild(draggedElement);
            } else {
                container.insertBefore(draggedElement, afterElement);
            }
        });
    }

    function getDragAfterElement(container, y) {
        const draggableElements = [...container.querySelectorAll('.ordering-item:not(.dragging)')];

        return draggableElements.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;

            if (offset < 0 && offset > closest.offset) {
                return { offset: offset, element: child };
            } else {
                return closest;
            }
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    }

    function updateOrderNumbers(container) {
        const items = container.querySelectorAll('.ordering-item');
        items.forEach((item, index) => {
            const badge = item.querySelector('.order-number');
            if (badge) {
                badge.textContent = index + 1;
            }
        });
    }

    function getOrderingAnswer(questionElement) {
        const items = questionElement.querySelectorAll('.ordering-item');
        const order = [];

        items.forEach(item => {
            order.push(item.dataset.originalText);
        });

        return { type: 'ordering', order: order };
    }

    function validateOrderingAnswer(questionElement, userAnswer) {
        const correctOrder = JSON.parse(questionElement.dataset.correctOrder || '[]');
        const userOrder = userAnswer.order || [];

        let correct = 0;
        const total = correctOrder.length;

        correctOrder.forEach((item, index) => {
            if (userOrder[index] === item) {
                correct++;
            }
        });

        return {
            isCorrect: correct === total,
            score: Math.round((correct / total) * 100),
            correctCount: correct,
            totalCount: total
        };
    }

    // ==================== FILL BLANK QUESTION (Заполнение пропусков) ====================

    function renderFillBlankQuestion(question, questionData) {
        const blanks = JSON.parse(questionData.blanks || '[]');
        let questionText = question.question_text;

        // Заменяем [blank] на поля ввода
        let blankIndex = 0;
        questionText = questionText.replace(/\[blank\]/gi, () => {
            const field = `<input type="text"
                                  class="form-control d-inline-block fill-blank-input"
                                  data-blank-index="${blankIndex}"
                                  style="width: 200px; margin: 0 5px;"
                                  placeholder="...">`;
            blankIndex++;
            return field;
        });

        let html = `
            <div class="fill-blank-question" data-question-id="${question.id}">
                <h5 class="mb-4">Заполните пропуски:</h5>
                <div class="question-text-with-blanks fs-5">
                    ${questionText}
                </div>
                ${questionData.case_sensitive ? `
                    <p class="text-muted small mt-3">
                        <i class="bi bi-info-circle"></i>
                        Регистр символов учитывается
                    </p>
                ` : ''}
            </div>
        `;

        // Сохраняем правильные ответы
        setTimeout(() => {
            const container = document.querySelector(`[data-question-id="${question.id}"]`);
            if (container) {
                container.setAttribute('data-correct-blanks', JSON.stringify(blanks));
                container.setAttribute('data-case-sensitive', questionData.case_sensitive ? '1' : '0');
            }
        }, 100);

        return html;
    }

    function getFillBlankAnswer(questionElement) {
        const inputs = questionElement.querySelectorAll('.fill-blank-input');
        const answers = [];

        inputs.forEach(input => {
            answers.push(input.value.trim());
        });

        return { type: 'fill_blank', answers: answers };
    }

    function validateFillBlankAnswer(questionElement, userAnswer) {
        const correctBlanks = JSON.parse(questionElement.dataset.correctBlanks || '[]');
        const caseSensitive = questionElement.dataset.caseSensitive === '1';
        const userAnswers = userAnswer.answers || [];

        let correct = 0;
        const total = correctBlanks.length;

        correctBlanks.forEach((correctAnswer, index) => {
            const userAns = userAnswers[index] || '';
            const possibleAnswers = Array.isArray(correctAnswer) ? correctAnswer : [correctAnswer];

            const matches = possibleAnswers.some(answer => {
                if (caseSensitive) {
                    return userAns === answer;
                } else {
                    return userAns.toLowerCase() === answer.toLowerCase();
                }
            });

            if (matches) {
                correct++;
            }
        });

        return {
            isCorrect: correct === total,
            score: Math.round((correct / total) * 100),
            correctCount: correct,
            totalCount: total
        };
    }

    // ==================== ESSAY QUESTION (Эссе) ====================

    function renderEssayQuestion(question, questionData) {
        const minWords = questionData.min_words || 50;
        const maxWords = questionData.max_words || 1000;

        let html = `
            <div class="essay-question" data-question-id="${question.id}">
                <h5 class="mb-4">${escapeHtml(question.question_text)}</h5>

                ${questionData.instructions ? `
                    <div class="alert alert-info mb-4">
                        <strong>Инструкции:</strong><br>
                        ${escapeHtml(questionData.instructions)}
                    </div>
                ` : ''}

                <div class="mb-3">
                    <textarea class="form-control essay-textarea"
                              rows="15"
                              placeholder="Введите ваш ответ здесь..."
                              data-min-words="${minWords}"
                              data-max-words="${maxWords}"></textarea>
                </div>

                <div class="d-flex justify-content-between text-muted small">
                    <div>
                        Минимум слов: <strong>${minWords}</strong>
                        ${maxWords ? ` | Максимум слов: <strong>${maxWords}</strong>` : ''}
                    </div>
                    <div class="word-counter">
                        Количество слов: <strong class="word-count">0</strong>
                    </div>
                </div>

                <div class="alert alert-warning mt-3">
                    <i class="bi bi-exclamation-triangle"></i>
                    Ваш ответ будет проверен экспертом вручную
                </div>
            </div>
        `;

        // Добавляем счетчик слов
        setTimeout(() => {
            const textarea = document.querySelector(`[data-question-id="${question.id}"] .essay-textarea`);
            if (textarea) {
                textarea.addEventListener('input', function() {
                    updateWordCount(this);
                });
            }
        }, 100);

        return html;
    }

    function updateWordCount(textarea) {
        const text = textarea.value.trim();
        const words = text ? text.split(/\s+/).length : 0;

        const counter = textarea.closest('.essay-question').querySelector('.word-count');
        if (counter) {
            counter.textContent = words;

            const minWords = parseInt(textarea.dataset.minWords);
            const maxWords = parseInt(textarea.dataset.maxWords);

            if (words < minWords) {
                counter.classList.add('text-danger');
                counter.classList.remove('text-success', 'text-warning');
            } else if (maxWords && words > maxWords) {
                counter.classList.add('text-warning');
                counter.classList.remove('text-success', 'text-danger');
            } else {
                counter.classList.add('text-success');
                counter.classList.remove('text-danger', 'text-warning');
            }
        }
    }

    function getEssayAnswer(questionElement) {
        const textarea = questionElement.querySelector('.essay-textarea');
        const text = textarea ? textarea.value.trim() : '';
        const words = text ? text.split(/\s+/).length : 0;

        return { type: 'essay', text: text, word_count: words };
    }

    function validateEssayAnswer(questionElement, userAnswer) {
        const textarea = questionElement.querySelector('.essay-textarea');
        const minWords = parseInt(textarea.dataset.minWords);
        const maxWords = parseInt(textarea.dataset.maxWords);
        const wordCount = userAnswer.word_count || 0;

        const meetsMinimum = wordCount >= minWords;
        const meetsMaximum = !maxWords || wordCount <= maxWords;

        return {
            isValid: meetsMinimum && meetsMaximum,
            needsReview: true, // Требует ручной проверки
            message: !meetsMinimum ? `Требуется минимум ${minWords} слов (у вас ${wordCount})` :
                    !meetsMaximum ? `Превышен максимум ${maxWords} слов (у вас ${wordCount})` :
                    'Ответ отправлен на проверку эксперту'
        };
    }

    // ==================== UTILITY FUNCTIONS ====================

    function shuffleArray(array) {
        const shuffled = [...array];
        for (let i = shuffled.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]];
        }
        return shuffled;
    }

    // ==================== PUBLIC API ====================

    window.SpecialQuestionTypes = {
        // Rendering
        renderMatchingQuestion,
        renderOrderingQuestion,
        renderFillBlankQuestion,
        renderEssayQuestion,

        // Getting answers
        getMatchingAnswer,
        getOrderingAnswer,
        getFillBlankAnswer,
        getEssayAnswer,

        // Validation
        validateMatchingAnswer,
        validateOrderingAnswer,
        validateFillBlankAnswer,
        validateEssayAnswer,

        // Utilities
        updateWordCount
    };

})();
