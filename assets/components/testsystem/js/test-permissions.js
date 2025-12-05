/**
 * Test Permissions Manager
 * Управление доступом к приватным тестам
 */

class TestPermissionsManager {
    constructor(testId, csrfToken) {
        this.testId = testId;
        this.csrfToken = csrfToken;
        this.apiUrl = '/assets/components/testsystem/ajax/testsystem.php';
    }

    /**
     * Выполнить API запрос
     */
    async apiRequest(action, data) {
        const requestData = {
            action: action,
            csrf_token: this.csrfToken,
            data: data
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

            if (!response.ok) {
                throw new Error(result.message || `HTTP error ${response.status}`);
            }

            if (!result.success) {
                throw new Error(result.message || 'Request failed');
            }

            return result;
        } catch (error) {
            console.error('API Request failed:', error);
            throw error;
        }
    }

    /**
     * Загрузить список пользователей с доступом
     */
    async loadPermissions() {
        const result = await this.apiRequest('getTestPermissions', {
            test_id: parseInt(this.testId)
        });
        return result.data.permissions || [];
    }

    /**
     * Загрузить список доступных пользователей
     */
    async loadAvailableUsers() {
        const result = await this.apiRequest('getAvailableUsersForTest', {
            test_id: parseInt(this.testId)
        });
        return result.data || [];
    }

    /**
     * Предоставить доступ пользователю
     */
    async grantAccess(userId, canView, canEdit, expiresAt = null) {
        return await this.apiRequest('grantTestAccess', {
            test_id: parseInt(this.testId),
            user_id: parseInt(userId),
            can_view: canView,
            can_edit: canEdit,
            expires_at: expiresAt
        });
    }

    /**
     * Отозвать доступ пользователя
     */
    async revokeAccess(userId) {
        return await this.apiRequest('revokeTestAccess', {
            test_id: parseInt(this.testId),
            user_id: parseInt(userId)
        });
    }

    /**
     * Отрисовать список пользователей с доступом
     */
    renderPermissionsList(permissions, containerId) {
        const container = document.getElementById(containerId);
        if (!container) return;

        if (permissions.length === 0) {
            container.innerHTML = '<p class="text-muted">Никому не предоставлен доступ к этому тесту</p>';
            return;
        }

        let html = '<div class="list-group">';

        permissions.forEach(perm => {
            const fullname = perm.fullname || perm.username;
            const email = perm.email || '';
            const canView = parseInt(perm.can_view) === 1;
            const canEdit = parseInt(perm.can_edit) === 1;
            const expiresAt = perm.expires_at;

            let permText = '';
            if (canEdit) {
                permText = '<span class="badge bg-warning text-dark">Редактирование</span>';
            } else if (canView) {
                permText = '<span class="badge bg-info">Просмотр</span>';
            }

            let expiryText = '';
            if (expiresAt) {
                const expiryDate = new Date(expiresAt);
                expiryText = `<small class="text-muted">Истекает: ${expiryDate.toLocaleDateString()}</small>`;
            }

            html += `
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1">${this.escapeHtml(fullname)}</h6>
                        <small class="text-muted">${this.escapeHtml(email)}</small><br>
                        ${permText}
                        ${expiryText}
                    </div>
                    <button class="btn btn-sm btn-danger revoke-access-btn" data-user-id="${perm.user_id}">
                        <i class="bi bi-x-circle"></i> Отозвать
                    </button>
                </div>
            `;
        });

        html += '</div>';
        container.innerHTML = html;

        // Добавляем обработчики для кнопок "Отозвать"
        container.querySelectorAll('.revoke-access-btn').forEach(btn => {
            btn.addEventListener('click', (e) => this.handleRevokeAccess(e));
        });
    }

    /**
     * Отрисовать выпадающий список пользователей
     */
    renderUsersSelect(users, selectId) {
        const select = document.getElementById(selectId);
        if (!select) return;

        select.innerHTML = '<option value="">-- Выберите пользователя --</option>';

        users.forEach(user => {
            const displayName = user.fullname ? `${user.fullname} (${user.username})` : user.username;
            const option = document.createElement('option');
            option.value = user.id;
            option.textContent = displayName;
            select.appendChild(option);
        });
    }

    /**
     * Обработчик предоставления доступа
     */
    async handleGrantAccess(event, testId) {
        event.preventDefault();

        const form = event.target;
        const formData = new FormData(form);

        const finalTestId = parseInt(formData.get('test_id') || testId);
        const userId = parseInt(formData.get('user_id'));
        const canView = formData.get('can_view') === '1';
        const canEdit = formData.get('can_edit') === '1';
        const expiresAt = formData.get('expires_at') || null;

        if (!userId) {
            alert('Выберите пользователя');
            return;
        }

        try {
            const result = await this.grantAccess(userId, canView, canEdit, expiresAt);

            // Перезагружаем списки
            const permissions = await this.loadPermissions();
            this.renderPermissionsList(permissions, `permissionsList${finalTestId}`);

            const users = await this.loadAvailableUsers();
            this.renderUsersSelect(users, `userSelect${finalTestId}`);

            // Очищаем форму
            form.reset();

            // Показываем сообщение об успехе
            this.showMessage(`permissionsMessages${finalTestId}`, 'success', 'Доступ предоставлен успешно');
        } catch (error) {
            console.error('Grant access error:', error);
            this.showMessage(`permissionsMessages${finalTestId}`, 'danger', 'Ошибка: ' + error.message);
        }
    }

    /**
     * Обработчик отзыва доступа
     */
    async handleRevokeAccess(event) {
        const btn = event.currentTarget;
        const userId = parseInt(btn.getAttribute('data-user-id'));

        if (!confirm('Вы уверены, что хотите отозвать доступ у этого пользователя?')) {
            return;
        }

        try {
            await this.revokeAccess(userId);

            // Перезагружаем списки
            const permissions = await this.loadPermissions();
            this.renderPermissionsList(permissions, `permissionsList${this.testId}`);

            const users = await this.loadAvailableUsers();
            this.renderUsersSelect(users, `userSelect${this.testId}`);

            this.showMessage(`permissionsMessages${this.testId}`, 'success', 'Доступ отозван успешно');
        } catch (error) {
            console.error('Revoke access error:', error);
            this.showMessage(`permissionsMessages${this.testId}`, 'danger', 'Ошибка: ' + error.message);
        }
    }

    /**
     * Показать сообщение
     */
    showMessage(containerId, type, message) {
        const container = document.getElementById(containerId);
        if (!container) return;

        const alertClass = `alert alert-${type} alert-dismissible fade show`;
        container.innerHTML = `
            <div class="${alertClass}" role="alert">
                ${this.escapeHtml(message)}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;

        // Автоматически скрыть через 5 секунд
        setTimeout(() => {
            container.innerHTML = '';
        }, 5000);
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
document.addEventListener('DOMContentLoaded', function() {
    // Находим все модальные окна для управления доступом
    document.querySelectorAll('[id^="permissionsModal"]').forEach(modal => {
        const testId = parseInt(modal.id.replace('permissionsModal', ''));
        const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';

        const manager = new TestPermissionsManager(testId, csrfToken);

        // Загружаем данные при открытии модального окна
        modal.addEventListener('shown.bs.modal', async function() {
            try {
                // Загружаем текущие права доступа
                const permissions = await manager.loadPermissions();
                manager.renderPermissionsList(permissions, `permissionsList${testId}`);

                // Загружаем доступных пользователей
                const users = await manager.loadAvailableUsers();
                manager.renderUsersSelect(users, `userSelect${testId}`);
            } catch (error) {
                console.error('Failed to load permissions:', error);
                manager.showMessage(`permissionsMessages${testId}`, 'danger', 'Ошибка загрузки данных: ' + error.message);
            }
        });

        // Обработчик формы предоставления доступа
        const form = modal.querySelector(`#grantAccessForm${testId}`);
        if (form) {
            form.addEventListener('submit', (e) => manager.handleGrantAccess(e, testId));
        }

        // Обработчик изменения чекбокса "Редактирование"
        const canEditCheckbox = modal.querySelector(`#canEdit${testId}`);
        const canViewCheckbox = modal.querySelector(`#canView${testId}`);

        if (canEditCheckbox && canViewCheckbox) {
            canEditCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    canViewCheckbox.checked = true;
                    canViewCheckbox.disabled = true;
                } else {
                    canViewCheckbox.disabled = false;
                }
            });
        }
    });
});
