/**
 * NOTIFICATIONS SYSTEM - Sprint 14
 * Система уведомлений: system, email, push
 * 10 типов уведомлений, шаблоны, настройки подписок
 */

(function() {
    'use strict';

    const API_URL = '/assets/components/testsystem/ajax/testsystem.php';
    let notifications = [];
    let unreadCount = 0;

    function getCsrfToken() {
        const metaTag = document.querySelector('meta[name="csrf-token"]');
        return metaTag ? metaTag.content : null;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }

    const NOTIFICATION_ICONS = {
        'test_completed': 'bi-clipboard-check text-success',
        'test_assigned': 'bi-bell text-primary',
        'achievement_earned': 'bi-trophy text-warning',
        'level_up': 'bi-arrow-up-circle text-info',
        'path_step_unlocked': 'bi-unlock text-success',
        'essay_reviewed': 'bi-chat-square-text text-primary',
        'deadline_reminder': 'bi-alarm text-danger',
        'material_available': 'bi-book text-info',
        'permission_granted': 'bi-shield-check text-success',
        'custom': 'bi-info-circle text-secondary'
    };

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

            const contentType = response.headers.get('content-type') || '';
            if (!contentType.includes('application/json')) {
                throw new Error('Invalid response format (expected JSON)');
            }

            return await response.json();
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    }

    // ==================== INITIALIZATION ====================

    document.addEventListener('DOMContentLoaded', function() {
        // Bell icon в хедере
        const bellIcon = document.getElementById('notifications-bell');
        if (bellIcon) {
            initNotificationsBell();
        }

        // Страница настроек уведомлений
        const settingsContainer = document.getElementById('notification-settings-container');
        if (settingsContainer) {
            loadNotificationSettings();
        }

        // Страница всех уведомлений
        const allNotificationsContainer = document.getElementById('all-notifications-container');
        if (allNotificationsContainer) {
            loadAllNotifications();
        }
    });

    // ==================== NOTIFICATIONS BELL ====================

    async function initNotificationsBell() {
        await loadUnreadCount();

        // Периодическое обновление
        setInterval(loadUnreadCount, 60000); // Каждую минуту

        // Event listener для открытия dropdown
        document.getElementById('notifications-bell')?.addEventListener('click', function(e) {
            e.preventDefault();
            loadRecentNotifications();
        });
    }

    async function loadUnreadCount() {
        try {
            const result = await apiCall('getUnreadNotificationsCount', {});

            if (result.success) {
                unreadCount = result.data?.unread_count ?? result.data?.count ?? result.count ?? 0;
                updateBellIcon();
            }
        } catch (error) {
            console.error('Load unread count error:', error);
        }
    }

    function updateBellIcon() {
        const badge = document.getElementById('notifications-badge');
        if (badge) {
            if (unreadCount > 0) {
                badge.textContent = unreadCount > 99 ? '99+' : unreadCount;
                badge.style.display = '';
            } else {
                badge.style.display = 'none';
            }
        }
    }

    async function loadRecentNotifications() {
        const dropdown = document.getElementById('notifications-dropdown');
        if (!dropdown) return;

        dropdown.innerHTML = '<div class="text-center p-3"><div class="spinner-border spinner-border-sm"></div></div>';

        try {
            const result = await apiCall('getRecentNotifications', { limit: 10 });

            if (result.success) {
                notifications = result.data?.notifications || result.data || [];
                renderNotificationsDropdown();
            }
        } catch (error) {
            console.error('Load notifications error:', error);
            dropdown.innerHTML = '<div class="text-center p-3 text-danger">Ошибка загрузки</div>';
        }
    }

    function renderNotificationsDropdown() {
        const dropdown = document.getElementById('notifications-dropdown');

        if (notifications.length === 0) {
            dropdown.innerHTML = `
                <div class="text-center p-4 text-muted">
                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                    <p class="mb-0">Нет уведомлений</p>
                </div>
            `;
            return;
        }

        let html = '<div class="list-group list-group-flush">';

        notifications.forEach(notification => {
            const iconClass = NOTIFICATION_ICONS[notification.type] || 'bi-info-circle';
            const unreadClass = notification.is_read ? '' : 'bg-light';
            const timeAgo = getTimeAgo(notification.created_at);

            html += `
                <a href="#"
                   class="list-group-item list-group-item-action ${unreadClass}"
                   data-notification-id="${notification.id}"
                   onclick="Notifications.markAsRead(${notification.id}); return false;">
                    <div class="d-flex align-items-start">
                        <div class="flex-shrink-0 me-3">
                            <i class="bi ${iconClass} fs-4"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="fw-bold">${escapeHtml(notification.title)}</div>
                            <div class="small text-muted">${escapeHtml(notification.message)}</div>
                            <div class="small text-muted mt-1">${timeAgo}</div>
                        </div>
                        ${!notification.is_read ? '<span class="badge bg-primary">Новое</span>' : ''}
                    </div>
                </a>
            `;
        });

        html += '</div>';
        html += `
            <div class="text-center p-2 border-top">
                <a href="/my-notifications" class="btn btn-sm btn-link">Все уведомления</a>
            </div>
        `;

        dropdown.innerHTML = html;
    }

    async function markAsRead(notificationId) {
        try {
            const result = await apiCall('markNotificationAsRead', {
                notification_id: notificationId
            });

            if (result.success) {
                // Обновляем UI
                const notifElement = document.querySelector(`[data-notification-id="${notificationId}"]`);
                if (notifElement) {
                    notifElement.classList.remove('bg-light');
                    const badge = notifElement.querySelector('.badge');
                    if (badge) badge.remove();
                }

                unreadCount = Math.max(0, unreadCount - 1);
                updateBellIcon();
            }
        } catch (error) {
            console.error('Mark as read error:', error);
        }
    }

    // ==================== ALL NOTIFICATIONS PAGE ====================

    async function loadAllNotifications() {
        const container = document.getElementById('all-notifications-container');

        try {
            const result = await apiCall('getAllNotifications', {});

            if (result.success) {
                renderAllNotifications(result.data || []);
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            console.error('Load all notifications error:', error);
            container.innerHTML = `
                <div class="alert alert-danger">
                    Ошибка загрузки уведомлений: ${escapeHtml(error.message)}
                </div>
            `;
        }
    }

    function renderAllNotifications(notificationsList) {
        const container = document.getElementById('all-notifications-container');

        if (notificationsList.length === 0) {
            container.innerHTML = `
                <div class="text-center p-5">
                    <i class="bi bi-inbox fs-1 text-muted d-block mb-3"></i>
                    <h5>Нет уведомлений</h5>
                </div>
            `;
            return;
        }

        let html = '<div class="notifications-list">';

        notificationsList.forEach(notification => {
            const iconClass = NOTIFICATION_ICONS[notification.type] || 'bi-info-circle';
            const unreadClass = notification.is_read ? '' : 'bg-light';
            const timeAgo = getTimeAgo(notification.created_at);

            html += `
                <div class="card mb-2 ${unreadClass}">
                    <div class="card-body">
                        <div class="d-flex align-items-start">
                            <div class="flex-shrink-0 me-3">
                                <i class="bi ${iconClass} fs-3"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="card-title mb-1">${escapeHtml(notification.title)}</h6>
                                <p class="card-text">${escapeHtml(notification.message)}</p>
                                <p class="small text-muted mb-0">${timeAgo}</p>
                            </div>
                            <div>
                                ${!notification.is_read ? `
                                    <button class="btn btn-sm btn-outline-primary"
                                            onclick="Notifications.markAsRead(${notification.id})">
                                        Отметить прочитанным
                                    </button>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });

        html += '</div>';
        container.innerHTML = html;
    }

    // ==================== NOTIFICATION SETTINGS ====================

    async function loadNotificationSettings() {
        try {
            const result = await apiCall('getNotificationSettings', {});

            if (result.success) {
                renderNotificationSettings(result.data || {});
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            console.error('Load settings error:', error);
            document.getElementById('notification-settings-container').innerHTML = `
                <div class="alert alert-danger">
                    Ошибка загрузки настроек: ${escapeHtml(error.message)}
                </div>
            `;
        }
    }

    function renderNotificationSettings(settings) {
        const container = document.getElementById('notification-settings-container');

        const notificationTypes = [
            { key: 'test_completed', label: 'Завершение теста', description: 'Уведомления при завершении прохождения теста' },
            { key: 'test_assigned', label: 'Назначен тест', description: 'Когда вам назначают новый тест' },
            { key: 'achievement_earned', label: 'Получено достижение', description: 'При разблокировке нового достижения' },
            { key: 'level_up', label: 'Повышение уровня', description: 'Когда вы достигаете нового уровня' },
            { key: 'path_step_unlocked', label: 'Разблокирован шаг траектории', description: 'В траекториях обучения' },
            { key: 'essay_reviewed', label: 'Проверено эссе', description: 'Когда эксперт проверил ваше эссе' },
            { key: 'deadline_reminder', label: 'Напоминание о дедлайне', description: 'За день до окончания срока сдачи' },
            { key: 'material_available', label: 'Доступен новый материал', description: 'Новые учебные материалы' },
            { key: 'permission_granted', label: 'Предоставлены права', description: 'Изменение прав доступа' },
            { key: 'custom', label: 'Прочие уведомления', description: 'Кастомные уведомления от администраторов' }
        ];

        let html = `
            <form id="notification-settings-form">
                <h5 class="mb-4">Настройки уведомлений</h5>

                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Тип уведомления</th>
                                <th class="text-center">Системные</th>
                                <th class="text-center">Email</th>
                                <th class="text-center">Push</th>
                            </tr>
                        </thead>
                        <tbody>
        `;

        notificationTypes.forEach(type => {
            const typeSetting = settings[type.key] || {};

            html += `
                <tr>
                    <td>
                        <strong>${type.label}</strong><br>
                        <small class="text-muted">${type.description}</small>
                    </td>
                    <td class="text-center">
                        <input type="checkbox"
                               class="form-check-input"
                               name="${type.key}_system"
                               ${typeSetting.system !== false ? 'checked' : ''}>
                    </td>
                    <td class="text-center">
                        <input type="checkbox"
                               class="form-check-input"
                               name="${type.key}_email"
                               ${typeSetting.email ? 'checked' : ''}>
                    </td>
                    <td class="text-center">
                        <input type="checkbox"
                               class="form-check-input"
                               name="${type.key}_push"
                               ${typeSetting.push ? 'checked' : ''}>
                    </td>
                </tr>
            `;
        });

        html += `
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    <button type="button" class="btn btn-primary" onclick="Notifications.saveSettings()">
                        <i class="bi bi-check-circle"></i> Сохранить настройки
                    </button>
                </div>
            </form>
        `;

        container.innerHTML = html;
    }

    async function saveSettings() {
        const form = document.getElementById('notification-settings-form');
        const formData = new FormData(form);
        const settings = {};

        // Собираем настройки из формы
        const notificationTypes = ['test_completed', 'test_assigned', 'achievement_earned', 'level_up',
                                   'path_step_unlocked', 'essay_reviewed', 'deadline_reminder',
                                   'material_available', 'permission_granted', 'custom'];

        notificationTypes.forEach(type => {
            settings[type] = {
                system: form.querySelector(`input[name="${type}_system"]`)?.checked || false,
                email: form.querySelector(`input[name="${type}_email"]`)?.checked || false,
                push: form.querySelector(`input[name="${type}_push"]`)?.checked || false
            };
        });

        try {
            const result = await apiCall('saveNotificationSettings', { settings: settings });

            if (result.success) {
                showNotification('Настройки сохранены', 'success');
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            console.error('Save settings error:', error);
            showNotification('Ошибка сохранения настроек: ' + error.message, 'danger');
        }
    }

    // ==================== UTILITY FUNCTIONS ====================

    function getTimeAgo(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const seconds = Math.floor((now - date) / 1000);

        if (seconds < 60) return 'только что';
        if (seconds < 3600) return Math.floor(seconds / 60) + ' мин. назад';
        if (seconds < 86400) return Math.floor(seconds / 3600) + ' ч. назад';
        if (seconds < 604800) return Math.floor(seconds / 86400) + ' дн. назад';

        return date.toLocaleDateString('ru-RU');
    }

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

    window.Notifications = {
        loadUnreadCount,
        markAsRead,
        loadAllNotifications,
        loadNotificationSettings,
        saveSettings
    };

})();
