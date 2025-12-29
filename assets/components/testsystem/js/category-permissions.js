/**
 * CATEGORY PERMISSIONS MANAGER - Sprint 10
 * Управление правами доступа к категориям
 * Роли: admin, expert, viewer
 */

(function() {
    'use strict';

    const API_URL = '/assets/components/testsystem/ajax/testsystem.php';

    // CSRF Protection
    function getCsrfToken() {
        const metaTag = document.querySelector('meta[name="csrf-token"]');
        return metaTag ? metaTag.content : null;
    }

    // XSS Protection
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }

    // State
    let categories = [];
    let permissions = [];
    let auditLog = [];

    // Role Labels
    const ROLE_LABELS = {
        'admin': '👑 Администратор',
        'expert': '🎓 Эксперт',
        'viewer': '👀 Просмотр'
    };

    const ROLE_DESCRIPTIONS = {
        'admin': 'Полный доступ: создание, редактирование, удаление контента',
        'expert': 'Создание и редактирование контента, проверка эссе',
        'viewer': 'Только просмотр контента категории'
    };

    const ROLE_BADGES = {
        'admin': 'bg-danger',
        'expert': 'bg-warning text-dark',
        'viewer': 'bg-info'
    };

    // API Call Helper
    async function apiCall(action, data = {}) {
        try {
            const csrfToken = getCsrfToken();
            if (csrfToken) {
                data.csrf_token = csrfToken;
            }

            const response = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action, data })
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            return await response.json();
        } catch (error) {
            console.error('API Error:', error);
            showNotification('Ошибка соединения с сервером', 'danger');
            throw error;
        }
    }

    // ==================== INITIALIZATION ====================

    document.addEventListener('DOMContentLoaded', function() {
        const permissionsContainer = document.getElementById('category-permissions-container');
        if (!permissionsContainer) return;

        initCategoryPermissions();
    });

    async function initCategoryPermissions() {
        await loadCategories();
        await loadPermissions();

        // Event listeners
        document.getElementById('add-permission-btn')?.addEventListener('click', showAddPermissionModal);
        document.getElementById('save-permission-btn')?.addEventListener('click', savePermission);
        document.getElementById('view-audit-log-btn')?.addEventListener('click', showAuditLog);
        document.getElementById('category-filter')?.addEventListener('change', filterByCategory);
        document.getElementById('role-filter')?.addEventListener('change', filterByRole);
        document.getElementById('user-search')?.addEventListener('input', filterByUser);
    }

    // ==================== LOAD DATA ====================

    async function loadCategories() {
        try {
            const result = await apiCall('getCategories', {});

            if (result.success) {
                categories = result.data || [];
                populateCategorySelects();
            } else {
                throw new Error(result.message || 'Ошибка загрузки категорий');
            }
        } catch (error) {
            console.error('Load categories error:', error);
            showNotification('Ошибка загрузки категорий: ' + error.message, 'danger');
        }
    }

    async function loadPermissions() {
        try {
            const result = await apiCall('getCategoryPermissions', {});

            if (result.success) {
                permissions = result.data || [];
                renderPermissions();
            } else {
                throw new Error(result.message || 'Ошибка загрузки прав доступа');
            }
        } catch (error) {
            console.error('Load permissions error:', error);
            document.getElementById('permissions-list-container').innerHTML = `
                <div class="alert alert-danger">
                    Ошибка загрузки прав доступа: ${escapeHtml(error.message)}
                </div>
            `;
        }
    }

    function populateCategorySelects() {
        const selects = document.querySelectorAll('.category-select');

        selects.forEach(select => {
            let html = '<option value="">Все категории</option>';

            categories.forEach(cat => {
                const indent = '—'.repeat(cat.level || 0);
                html += `<option value="${cat.id}">${indent} ${escapeHtml(cat.title)}</option>`;
            });

            select.innerHTML = html;
        });
    }

    // ==================== RENDER PERMISSIONS ====================

    function renderPermissions() {
        const container = document.getElementById('permissions-list-container');

        if (!permissions || permissions.length === 0) {
            container.innerHTML = `
                <div class="alert alert-info text-center py-5">
                    <i class="bi bi-shield-lock fs-1 d-block mb-3"></i>
                    <h5>Права доступа не настроены</h5>
                    <p class="mb-0">Добавьте права для пользователей или групп</p>
                </div>
            `;
            return;
        }

        // Группируем по категориям
        const grouped = {};

        permissions.forEach(perm => {
            const catId = perm.category_id;
            if (!grouped[catId]) {
                grouped[catId] = {
                    category: perm.category_name,
                    permissions: []
                };
            }
            grouped[catId].permissions.push(perm);
        });

        let html = '';

        Object.keys(grouped).forEach(catId => {
            const group = grouped[catId];

            html += `
                <div class="card mb-3 category-permissions-group"
                     data-category-id="${catId}">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">
                            <i class="bi bi-folder-fill text-warning"></i>
                            ${escapeHtml(group.category)}
                            <span class="badge bg-secondary ms-2">${group.permissions.length}</span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Пользователь</th>
                                        <th>Роль</th>
                                        <th>Предоставлено</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
        `;

            group.permissions.forEach(perm => {
                const userDisplay = perm.user_fullname || perm.username;
                const roleBadge = ROLE_BADGES[perm.role] || 'bg-secondary';

                html += `
                    <tr class="permission-row" data-permission-id="${perm.id}" data-role="${perm.role}">
                        <td>
                            <strong>${escapeHtml(userDisplay)}</strong>
                            ${perm.email ? `<br><small class="text-muted">${escapeHtml(perm.email)}</small>` : ''}
                        </td>
                        <td>
                            <span class="badge ${roleBadge}">${ROLE_LABELS[perm.role]}</span>
                            <br>
                            <small class="text-muted">${ROLE_DESCRIPTIONS[perm.role]}</small>
                        </td>
                        <td>
                            <small class="text-muted">
                                ${new Date(perm.granted_at).toLocaleDateString('ru-RU')}
                                ${perm.granted_by_name ? `<br>Кем: ${escapeHtml(perm.granted_by_name)}` : ''}
                            </small>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary"
                                        onclick="CategoryPermissions.changeRole(${perm.id}, '${perm.role}')"
                                        title="Изменить роль">
                                    <i class="bi bi-arrow-left-right"></i>
                                </button>
                                <button class="btn btn-outline-danger"
                                        onclick="CategoryPermissions.revokePermission(${perm.id}, '${escapeHtml(userDisplay).replace(/'/g, "\\'")}')"
                                        title="Отозвать доступ">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });

            html += `
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;
    }

    // ==================== FILTERING ====================

    function filterByCategory() {
        const categoryId = document.getElementById('category-filter')?.value;

        document.querySelectorAll('.category-permissions-group').forEach(group => {
            if (!categoryId || group.dataset.categoryId === categoryId) {
                group.style.display = '';
            } else {
                group.style.display = 'none';
            }
        });
    }

    function filterByRole() {
        const role = document.getElementById('role-filter')?.value;

        document.querySelectorAll('.permission-row').forEach(row => {
            if (!role || row.dataset.role === role) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    function filterByUser() {
        const searchText = document.getElementById('user-search')?.value.toLowerCase() || '';

        document.querySelectorAll('.permission-row').forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchText) ? '' : 'none';
        });
    }

    // ==================== ADD PERMISSION ====================

    function showAddPermissionModal() {
        const categoryEl = document.getElementById('permission-category');
        const userSearchEl = document.getElementById('permission-user-search');
        const roleEl = document.getElementById('permission-role');
        const resultsEl = document.getElementById('user-search-results');

        if (categoryEl) categoryEl.value = '';
        if (userSearchEl) userSearchEl.value = '';
        if (roleEl) roleEl.value = 'viewer';
        if (resultsEl) resultsEl.innerHTML = '';

        const modal = new bootstrap.Modal(document.getElementById('addPermissionModal'));
        modal.show();

        // Event listener для поиска пользователей
        document.getElementById('search-users-btn')?.addEventListener('click', searchUsers);
        document.getElementById('permission-user-search')?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchUsers();
            }
        });
    }

    async function searchUsers() {
        const query = document.getElementById('permission-user-search')?.value.trim();

        if (!query || query.length < 2) {
            showNotification('Введите минимум 2 символа для поиска', 'warning');
            return;
        }

        const resultsContainer = document.getElementById('user-search-results');
        resultsContainer.innerHTML = '<div class="text-center"><span class="spinner-border spinner-border-sm"></span> Поиск...</div>';

        try {
            const result = await apiCall('searchUsers', { query: query });

            if (result.success) {
                renderUserSearchResults(result.data || []);
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            console.error('Search users error:', error);
            resultsContainer.innerHTML = `<div class="alert alert-danger">Ошибка поиска: ${escapeHtml(error.message)}</div>`;
        }
    }

    function renderUserSearchResults(users) {
        const container = document.getElementById('user-search-results');

        if (users.length === 0) {
            container.innerHTML = '<div class="alert alert-warning">Пользователи не найдены</div>';
            return;
        }

        let html = '<div class="list-group">';

        users.forEach(user => {
            const userDisplay = user.fullname || user.username;
            const email = user.email ? `(${escapeHtml(user.email)})` : '';

            html += `
                <div class="list-group-item list-group-item-action user-search-result"
                     data-user-id="${user.id}"
                     style="cursor: pointer;"
                     onclick="CategoryPermissions.selectUser(${user.id}, '${escapeHtml(userDisplay).replace(/'/g, "\\'")}')">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong>${escapeHtml(userDisplay)}</strong> ${email}
                        </div>
                        <i class="bi bi-arrow-right"></i>
                    </div>
                </div>
            `;
        });

        html += '</div>';
        container.innerHTML = html;
    }

    let selectedUserId = null;

    function selectUser(userId, userName) {
        selectedUserId = userId;

        // Highlight selected
        document.querySelectorAll('.user-search-result').forEach(el => {
            el.classList.remove('active');
        });

        event.target.closest('.user-search-result').classList.add('active');

        showNotification(`Выбран пользователь: ${userName}`, 'info');
    }

    async function savePermission() {
        const categoryId = document.getElementById('permission-category')?.value;
        const role = document.getElementById('permission-role')?.value;

        if (!categoryId) {
            showNotification('Выберите категорию', 'warning');
            return;
        }

        if (!selectedUserId) {
            showNotification('Выберите пользователя из результатов поиска', 'warning');
            return;
        }

        if (!role) {
            showNotification('Выберите роль', 'warning');
            return;
        }

        const saveBtn = document.getElementById('save-permission-btn');
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Сохранение...';

        try {
            const result = await apiCall('grantCategoryPermission', {
                category_id: parseInt(categoryId),
                user_id: selectedUserId,
                role: role
            });

            if (result.success) {
                showNotification('Права доступа предоставлены', 'success');

                const modal = bootstrap.Modal.getInstance(document.getElementById('addPermissionModal'));
                modal.hide();

                await loadPermissions();
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            console.error('Save permission error:', error);
            showNotification('Ошибка предоставления прав: ' + error.message, 'danger');
        } finally {
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="bi bi-check-circle"></i> Добавить';
        }
    }

    // ==================== CHANGE ROLE ====================

    async function changeRole(permissionId, currentRole) {
        const roles = ['viewer', 'expert', 'admin'];
        let html = '<div class="list-group">';

        roles.forEach(role => {
            const badge = ROLE_BADGES[role] || 'bg-secondary';
            const disabled = role === currentRole ? 'disabled' : '';

            html += `
                <button class="list-group-item list-group-item-action ${disabled}"
                        onclick="CategoryPermissions.confirmRoleChange(${permissionId}, '${role}')"
                        ${disabled}>
                    <span class="badge ${badge}">${ROLE_LABELS[role]}</span>
                    <br>
                    <small class="text-muted">${ROLE_DESCRIPTIONS[role]}</small>
                </button>
            `;
        });

        html += '</div>';

        // Создаем модальное окно
        const modalHtml = `
            <div class="modal fade" id="changeRoleModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Изменить роль</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p>Выберите новую роль:</p>
                            ${html}
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Удаляем старое модальное окно если есть
        const oldModal = document.getElementById('changeRoleModal');
        if (oldModal) {
            oldModal.remove();
        }

        document.body.insertAdjacentHTML('beforeend', modalHtml);

        const modal = new bootstrap.Modal(document.getElementById('changeRoleModal'));
        modal.show();
    }

    async function confirmRoleChange(permissionId, newRole) {
        try {
            const result = await apiCall('updateCategoryPermission', {
                permission_id: permissionId,
                role: newRole
            });

            if (result.success) {
                showNotification('Роль изменена', 'success');

                const modal = bootstrap.Modal.getInstance(document.getElementById('changeRoleModal'));
                modal.hide();

                await loadPermissions();
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            console.error('Change role error:', error);
            showNotification('Ошибка изменения роли: ' + error.message, 'danger');
        }
    }

    // ==================== REVOKE PERMISSION ====================

    async function revokePermission(permissionId, userName) {
        if (!confirm(`Вы уверены, что хотите отозвать права доступа у пользователя "${userName}"?`)) {
            return;
        }

        try {
            const result = await apiCall('revokeCategoryPermission', {
                permission_id: permissionId
            });

            if (result.success) {
                showNotification('Права доступа отозваны', 'success');
                await loadPermissions();
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            console.error('Revoke permission error:', error);
            showNotification('Ошибка отзыва прав: ' + error.message, 'danger');
        }
    }

    // ==================== AUDIT LOG ====================

    async function showAuditLog() {
        try {
            const result = await apiCall('getCategoryPermissionAuditLog', {});

            if (result.success) {
                auditLog = result.data || [];
                renderAuditLog();
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            console.error('Load audit log error:', error);
            showNotification('Ошибка загрузки журнала: ' + error.message, 'danger');
        }
    }

    function renderAuditLog() {
        let html = `
            <div class="modal fade" id="auditLogModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="bi bi-clock-history"></i> Журнал изменений прав доступа
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body" style="max-height: 500px; overflow-y: auto;">
        `;

        if (auditLog.length === 0) {
            html += '<div class="alert alert-info">Записей в журнале нет</div>';
        } else {
            html += '<div class="table-responsive">';
            html += '<table class="table table-sm table-hover">';
            html += `
                <thead>
                    <tr>
                        <th>Дата</th>
                        <th>Действие</th>
                        <th>Пользователь</th>
                        <th>Категория</th>
                        <th>Роль</th>
                        <th>Кем выполнено</th>
                    </tr>
                </thead>
                <tbody>
            `;

            auditLog.forEach(log => {
                const actionBadge = log.action === 'granted' ? 'bg-success' :
                                   log.action === 'revoked' ? 'bg-danger' :
                                   log.action === 'updated' ? 'bg-warning' : 'bg-secondary';

                const actionText = log.action === 'granted' ? 'Предоставлено' :
                                  log.action === 'revoked' ? 'Отозвано' :
                                  log.action === 'updated' ? 'Изменено' : log.action;

                html += `
                    <tr>
                        <td><small>${new Date(log.created_at).toLocaleString('ru-RU')}</small></td>
                        <td><span class="badge ${actionBadge}">${actionText}</span></td>
                        <td><strong>${escapeHtml(log.user_name)}</strong></td>
                        <td>${escapeHtml(log.category_name)}</td>
                        <td><span class="badge ${ROLE_BADGES[log.role]}">${ROLE_LABELS[log.role]}</span></td>
                        <td><small>${escapeHtml(log.granted_by_name)}</small></td>
                    </tr>
                `;
            });

            html += '</tbody></table></div>';
        }

        html += `
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Удаляем старое модальное окно если есть
        const oldModal = document.getElementById('auditLogModal');
        if (oldModal) {
            oldModal.remove();
        }

        document.body.insertAdjacentHTML('beforeend', html);

        const modal = new bootstrap.Modal(document.getElementById('auditLogModal'));
        modal.show();
    }

    // ==================== UTILITY FUNCTIONS ====================

    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3`;
        notification.style.zIndex = '9999';
        notification.style.minWidth = '300px';
        notification.innerHTML = `
            ${escapeHtml(message)}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(notification);

        setTimeout(() => notification.remove(), 4000);
    }

    // ==================== PUBLIC API ====================

    window.CategoryPermissions = {
        loadPermissions,
        selectUser,
        changeRole,
        confirmRoleChange,
        revokePermission,
        showAuditLog
    };

})();
