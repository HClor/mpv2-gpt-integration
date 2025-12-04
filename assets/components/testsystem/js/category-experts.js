/**
 * Category Experts Management JavaScript
 * Управление экспертами категорий
 */

class CategoryExpertsManager {
    constructor(apiUrl) {
        this.apiUrl = apiUrl;
        this.csrfToken = this.getCSRFToken();
    }

    /**
     * Получить CSRF токен из мета-тега или формы
     */
    getCSRFToken() {
        const metaToken = document.querySelector('meta[name="csrf-token"]');
        if (metaToken) {
            return metaToken.getAttribute('content');
        }

        const inputToken = document.querySelector('input[name="csrf_token"]');
        if (inputToken) {
            return inputToken.value;
        }

        return '';
    }

    /**
     * Выполнить API запрос
     */
    async apiRequest(action, data) {
        const requestData = {
            action: action,
            csrf_token: this.csrfToken,
            ...data
        };

        try {
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(requestData)
            });

            const result = await response.json();

            if (!result.success) {
                throw new Error(result.message || 'Unknown error');
            }

            return result;
        } catch (error) {
            console.error('API request error:', error);
            throw error;
        }
    }

    /**
     * Загрузить список экспертов категории
     */
    async loadCategoryExperts(categoryId) {
        try {
            const result = await this.apiRequest('getCategoryExperts', {
                category_id: parseInt(categoryId)
            });

            return result.data || [];
        } catch (error) {
            console.error('Error loading category experts:', error);
            return [];
        }
    }

    /**
     * Загрузить список доступных экспертов
     */
    async loadAvailableExperts() {
        try {
            const result = await this.apiRequest('getAvailableExperts', {});
            return result.data || [];
        } catch (error) {
            console.error('Error loading available experts:', error);
            return [];
        }
    }

    /**
     * Назначить эксперта
     */
    async assignExpert(categoryId, expertUserId, permissions) {
        return await this.apiRequest('assignCategoryExpert', {
            category_id: parseInt(categoryId),
            expert_user_id: parseInt(expertUserId),
            can_manage_tests: permissions.can_manage_tests,
            can_manage_questions: permissions.can_manage_questions,
            can_approve: permissions.can_approve
        });
    }

    /**
     * Удалить эксперта
     */
    async removeExpert(categoryId, expertUserId) {
        return await this.apiRequest('removeCategoryExpert', {
            category_id: parseInt(categoryId),
            expert_user_id: parseInt(expertUserId)
        });
    }

    /**
     * Отобразить список экспертов
     */
    renderExpertsList(categoryId, experts) {
        const container = document.getElementById(`expertsList${categoryId}`);
        if (!container) return;

        if (experts.length === 0) {
            container.innerHTML = '<div class="text-center text-muted py-3">Нет назначенных экспертов</div>';
            return;
        }

        let html = '<div class="list-group">';
        experts.forEach(expert => {
            const permissions = [];
            if (expert.can_manage_tests) permissions.push('Тесты');
            if (expert.can_manage_questions) permissions.push('Вопросы');
            if (expert.can_approve) permissions.push('Подтверждение');

            html += `
                <div class="list-group-item d-flex justify-content-between align-items-start">
                    <div class="ms-2 me-auto">
                        <div class="fw-bold">${this.escapeHtml(expert.username)}</div>
                        <small class="text-muted">
                            Email: ${this.escapeHtml(expert.email || 'N/A')}
                        </small><br>
                        <small class="text-muted">
                            Права: ${permissions.join(', ') || 'Нет'}
                        </small><br>
                        <small class="text-muted">
                            Назначен: ${new Date(expert.assigned_at).toLocaleString('ru-RU')}
                        </small>
                    </div>
                    <button
                        type="button"
                        class="btn btn-sm btn-danger"
                        onclick="categoryExpertsManager.handleRemoveExpert(${categoryId}, ${expert.user_id}, '${this.escapeHtml(expert.username)}')"
                    >
                        Удалить
                    </button>
                </div>
            `;
        });
        html += '</div>';

        container.innerHTML = html;
    }

    /**
     * Заполнить селект доступными экспертами
     */
    renderExpertsSelect(categoryId, allExperts, assignedExperts) {
        const select = document.getElementById(`expertSelect${categoryId}`);
        if (!select) return;

        // Получаем IDs уже назначенных экспертов
        const assignedIds = assignedExperts.map(e => e.user_id);

        // Фильтруем только не назначенных
        const availableExperts = allExperts.filter(e => !assignedIds.includes(e.id));

        if (availableExperts.length === 0) {
            select.innerHTML = '<option value="">Нет доступных экспертов</option>';
            select.disabled = true;
            return;
        }

        let html = '<option value="">Выберите эксперта...</option>';
        availableExperts.forEach(expert => {
            html += `<option value="${expert.id}">${this.escapeHtml(expert.username)} (${this.escapeHtml(expert.email || 'N/A')})</option>`;
        });

        select.innerHTML = html;
        select.disabled = false;
    }

    /**
     * Обработчик открытия модального окна
     */
    async handleModalOpen(categoryId) {
        console.log('Opening experts modal for category:', categoryId);

        // Загружаем текущих экспертов
        const experts = await this.loadCategoryExperts(categoryId);
        this.renderExpertsList(categoryId, experts);

        // Загружаем доступных экспертов
        const availableExperts = await this.loadAvailableExperts();
        this.renderExpertsSelect(categoryId, availableExperts, experts);
    }

    /**
     * Обработчик назначения эксперта
     */
    async handleAssignExpert(event, categoryId) {
        event.preventDefault();

        const form = event.target;
        const formData = new FormData(form);

        // Получаем categoryId из скрытого поля формы или параметра
        const formCategoryId = formData.get('category_id');
        const finalCategoryId = parseInt(formCategoryId || categoryId);

        if (!finalCategoryId) {
            alert('Ошибка: не указан ID категории');
            console.error('Category ID missing. FormData:', formCategoryId, 'Parameter:', categoryId);
            return;
        }

        const expertUserId = parseInt(formData.get('expert_user_id'));
        if (!expertUserId) {
            alert('Выберите эксперта');
            return;
        }

        const permissions = {
            can_manage_tests: formData.get('can_manage_tests') === 'on',
            can_manage_questions: formData.get('can_manage_questions') === 'on',
            can_approve: formData.get('can_approve') === 'on'
        };

        try {
            await this.assignExpert(finalCategoryId, expertUserId, permissions);

            // Перезагружаем список экспертов
            await this.handleModalOpen(categoryId);

            // Очищаем форму
            form.reset();

            alert('Эксперт успешно назначен!');
        } catch (error) {
            alert('Ошибка при назначении эксперта: ' + error.message);
        }
    }

    /**
     * Обработчик удаления эксперта
     */
    async handleRemoveExpert(categoryId, expertUserId, expertName) {
        if (!confirm(`Удалить эксперта ${expertName}?`)) {
            return;
        }

        const finalCategoryId = parseInt(categoryId);
        const finalExpertUserId = parseInt(expertUserId);

        if (!finalCategoryId || !finalExpertUserId) {
            alert('Ошибка: некорректные ID');
            console.error('Invalid IDs:', { categoryId, expertUserId });
            return;
        }

        try {
            await this.removeExpert(finalCategoryId, finalExpertUserId);

            // Перезагружаем список экспертов
            await this.handleModalOpen(finalCategoryId);

            alert('Эксперт успешно удален!');
        } catch (error) {
            alert('Ошибка при удалении эксперта: ' + error.message);
        }
    }

    /**
     * Экранирование HTML
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Инициализация при загрузке страницы
let categoryExpertsManager = null;

document.addEventListener('DOMContentLoaded', function() {
    // Получаем URL API из конфигурации
    const apiUrl = '/assets/components/testsystem/ajax/testsystem.php';

    categoryExpertsManager = new CategoryExpertsManager(apiUrl);

    // Привязываем обработчики к модальным окнам экспертов
    document.querySelectorAll('[id^="expertsModal"]').forEach(modal => {
        const categoryId = parseInt(modal.id.replace('expertsModal', ''));

        if (!categoryId) {
            console.error('Invalid category ID from modal:', modal.id);
            return;
        }

        // При открытии модального окна загружаем данные
        modal.addEventListener('show.bs.modal', function() {
            categoryExpertsManager.handleModalOpen(categoryId);
        });

        // Привязываем обработчик формы добавления
        const form = document.getElementById(`addExpertForm${categoryId}`);
        if (form) {
            form.addEventListener('submit', function(e) {
                categoryExpertsManager.handleAssignExpert(e, categoryId);
            });
        }
    });
});
