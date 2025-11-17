/**
 * GAMIFICATION SYSTEM - Sprint 13
 * XP, уровни, достижения, серии активности, рейтинги
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
    let userData = {};
    let achievements = [];
    let leaderboardData = [];

    // Achievement Type Icons
    const ACHIEVEMENT_ICONS = {
        'tests_completed': '✅',
        'perfect_score': '💯',
        'streak': '🔥',
        'speed': '⚡',
        'consistency': '🎯',
        'explorer': '🗺️',
        'master': '👑'
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
        // Страница профиля пользователя
        const profileContainer = document.getElementById('gamification-profile-container');
        if (profileContainer) {
            loadUserProfile();
        }

        // Виджет в хедере
        const headerWidget = document.getElementById('gamification-header-widget');
        if (headerWidget) {
            loadHeaderWidget();
        }

        // Страница достижений
        const achievementsContainer = document.getElementById('achievements-container');
        if (achievementsContainer) {
            loadAchievements();
        }

        // Страница рейтингов
        const leaderboardContainer = document.getElementById('leaderboard-container');
        if (leaderboardContainer) {
            initLeaderboard();
        }
    });

    // ==================== USER PROFILE ====================

    async function loadUserProfile() {
        try {
            const result = await apiCall('getUserGamificationProfile', {});

            if (result.success) {
                userData = result.data;
                renderUserProfile();
            } else {
                throw new Error(result.message || 'Ошибка загрузки профиля');
            }
        } catch (error) {
            console.error('Load profile error:', error);
            document.getElementById('gamification-profile-container').innerHTML = `
                <div class="alert alert-danger">
                    Ошибка загрузки профиля: ${escapeHtml(error.message)}
                </div>
            `;
        }
    }

    function renderUserProfile() {
        const container = document.getElementById('gamification-profile-container');

        const currentLevel = userData.level || 1;
        const currentXP = userData.xp || 0;
        const nextLevelXP = userData.next_level_xp || 1000;
        const progressPercent = Math.min(100, Math.round((currentXP / nextLevelXP) * 100));

        let html = `
            <div class="row g-4">
                <!-- XP и уровень -->
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="bi bi-trophy-fill text-warning"></i>
                                Ваш уровень и опыт
                            </h5>

                            <div class="text-center my-4">
                                <div class="level-badge mb-3">
                                    <div class="display-1 fw-bold text-primary">${currentLevel}</div>
                                    <div class="text-muted">Уровень</div>
                                </div>

                                <div class="xp-progress mb-3">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span><strong>${currentXP.toLocaleString()}</strong> XP</span>
                                        <span class="text-muted">Следующий уровень: <strong>${nextLevelXP.toLocaleString()}</strong> XP</span>
                                    </div>
                                    <div class="progress" style="height: 25px;">
                                        <div class="progress-bar bg-success progress-bar-striped"
                                             role="progressbar"
                                             style="width: ${progressPercent}%">
                                            ${progressPercent}%
                                        </div>
                                    </div>
                                    <p class="text-muted small mt-2">
                                        Осталось <strong>${(nextLevelXP - currentXP).toLocaleString()}</strong> XP до следующего уровня
                                    </p>
                                </div>
                            </div>

                            <div class="level-perks alert alert-info">
                                <h6>Преимущества вашего уровня:</h6>
                                <ul class="mb-0">
                                    ${getLevelPerks(currentLevel).map(perk => `<li>${perk}</li>`).join('')}
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Статистика активности -->
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="bi bi-graph-up text-success"></i>
                                Статистика
                            </h5>

                            <div class="row g-3 mt-2">
                                <div class="col-6">
                                    <div class="stat-box text-center p-3 bg-light rounded">
                                        <div class="fs-2 fw-bold text-primary">${userData.tests_completed || 0}</div>
                                        <div class="small text-muted">Тестов завершено</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="stat-box text-center p-3 bg-light rounded">
                                        <div class="fs-2 fw-bold text-success">${userData.avg_score || 0}%</div>
                                        <div class="small text-muted">Средний балл</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="stat-box text-center p-3 bg-light rounded">
                                        <div class="fs-2 fw-bold text-warning">${userData.current_streak || 0} 🔥</div>
                                        <div class="small text-muted">Дней подряд</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="stat-box text-center p-3 bg-light rounded">
                                        <div class="fs-2 fw-bold text-danger">${userData.achievements_count || 0}</div>
                                        <div class="small text-muted">Достижений</div>
                                    </div>
                                </div>
                            </div>

                            ${userData.current_streak >= 3 ? `
                                <div class="alert alert-warning mt-3 mb-0">
                                    <i class="bi bi-fire"></i>
                                    <strong>Отличная серия!</strong><br>
                                    Вы проходите тесты ${userData.current_streak} дней подряд! Продолжайте!
                                </div>
                            ` : ''}
                        </div>
                    </div>
                </div>

                <!-- Последние достижения -->
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-award-fill text-primary"></i>
                                    Последние достижения
                                </h5>
                                <a href="/my-achievements" class="btn btn-sm btn-outline-primary">
                                    Все достижения
                                </a>
                            </div>

                            ${renderRecentAchievements(userData.recent_achievements || [])}
                        </div>
                    </div>
                </div>
            </div>
        `;

        container.innerHTML = html;
    }

    function renderRecentAchievements(achievements) {
        if (!achievements || achievements.length === 0) {
            return '<p class="text-muted">Пока нет достижений. Начните проходить тесты!</p>';
        }

        let html = '<div class="row g-3">';

        achievements.slice(0, 6).forEach(achievement => {
            const icon = ACHIEVEMENT_ICONS[achievement.type] || '🏆';

            html += `
                <div class="col-md-4">
                    <div class="achievement-card card h-100 ${achievement.rarity === 'legendary' ? 'border-warning' : ''}">
                        <div class="card-body text-center">
                            <div class="achievement-icon fs-1 mb-2">${icon}</div>
                            <h6 class="card-title">${escapeHtml(achievement.title)}</h6>
                            <p class="card-text small text-muted">${escapeHtml(achievement.description)}</p>
                            <div class="badge bg-success">+${achievement.xp_reward} XP</div>
                            ${achievement.rarity === 'legendary' ? '<div class="badge bg-warning text-dark ms-1">Легендарное</div>' : ''}
                            <div class="small text-muted mt-2">
                                ${new Date(achievement.unlocked_at).toLocaleDateString('ru-RU')}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });

        html += '</div>';
        return html;
    }

    function getLevelPerks(level) {
        const perks = [
            'Доступ ко всем базовым тестам'
        ];

        if (level >= 3) perks.push('Доступ к промежуточным тестам');
        if (level >= 5) perks.push('Доступ к продвинутым тестам');
        if (level >= 7) perks.push('Возможность создавать траектории обучения');
        if (level >= 10) perks.push('Статус эксперта, доступ к проверке эссе');

        return perks;
    }

    // ==================== HEADER WIDGET ====================

    async function loadHeaderWidget() {
        try {
            const result = await apiCall('getUserGamificationSummary', {});

            if (result.success) {
                renderHeaderWidget(result.data);
            }
        } catch (error) {
            console.error('Load header widget error:', error);
        }
    }

    function renderHeaderWidget(data) {
        const container = document.getElementById('gamification-header-widget');

        const progressPercent = Math.min(100, Math.round((data.xp / data.next_level_xp) * 100));

        let html = `
            <div class="gamification-widget">
                <a href="/my-profile" class="text-decoration-none text-dark">
                    <div class="d-flex align-items-center">
                        <div class="level-badge-small bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2"
                             style="width: 40px; height: 40px;">
                            <strong>${data.level}</strong>
                        </div>
                        <div class="flex-grow-1">
                            <div class="fw-bold">${data.xp.toLocaleString()} XP</div>
                            <div class="progress" style="height: 5px; width: 100px;">
                                <div class="progress-bar bg-success"
                                     style="width: ${progressPercent}%"></div>
                            </div>
                        </div>
                        ${data.current_streak > 0 ? `
                            <div class="streak-badge ms-2 text-warning">
                                🔥 ${data.current_streak}
                            </div>
                        ` : ''}
                    </div>
                </a>
            </div>
        `;

        container.innerHTML = html;
    }

    // ==================== ACHIEVEMENTS PAGE ====================

    async function loadAchievements() {
        try {
            const result = await apiCall('getUserAchievements', {});

            if (result.success) {
                achievements = result.data;
                renderAchievements();
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            console.error('Load achievements error:', error);
            document.getElementById('achievements-container').innerHTML = `
                <div class="alert alert-danger">
                    Ошибка загрузки достижений: ${escapeHtml(error.message)}
                </div>
            `;
        }
    }

    function renderAchievements() {
        const container = document.getElementById('achievements-container');

        // Группируем по типам
        const grouped = {};

        achievements.forEach(achievement => {
            const type = achievement.type || 'other';
            if (!grouped[type]) {
                grouped[type] = [];
            }
            grouped[type].push(achievement);
        });

        let html = '';

        Object.keys(grouped).forEach(type => {
            const icon = ACHIEVEMENT_ICONS[type] || '🏆';
            const typeLabel = getAchievementTypeLabel(type);

            html += `
                <div class="achievement-group mb-4">
                    <h4 class="mb-3">
                        <span class="me-2">${icon}</span>
                        ${typeLabel}
                    </h4>
                    <div class="row g-3">
            `;

            grouped[type].forEach(achievement => {
                const isUnlocked = achievement.unlocked;
                const cardClass = isUnlocked ? 'border-success' : 'opacity-50';
                const rarityBadge = getRarityBadge(achievement.rarity);

                html += `
                    <div class="col-md-4 col-lg-3">
                        <div class="card h-100 ${cardClass}">
                            <div class="card-body text-center">
                                <div class="achievement-icon fs-1 mb-2">${icon}</div>
                                <h6 class="card-title">${escapeHtml(achievement.title)}</h6>
                                <p class="card-text small text-muted">${escapeHtml(achievement.description)}</p>

                                ${rarityBadge}
                                <div class="badge bg-primary ms-1">+${achievement.xp_reward} XP</div>

                                ${isUnlocked ? `
                                    <div class="mt-3">
                                        <i class="bi bi-check-circle-fill text-success fs-4"></i>
                                        <div class="small text-muted mt-1">
                                            Получено: ${new Date(achievement.unlocked_at).toLocaleDateString('ru-RU')}
                                        </div>
                                    </div>
                                ` : `
                                    <div class="mt-3">
                                        <i class="bi bi-lock-fill text-muted fs-4"></i>
                                        <div class="small text-muted mt-1">
                                            ${getAchievementProgress(achievement)}
                                        </div>
                                    </div>
                                `}
                            </div>
                        </div>
                    </div>
                `;
            });

            html += `
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;
    }

    function getAchievementTypeLabel(type) {
        const labels = {
            'tests_completed': 'Завершение тестов',
            'perfect_score': 'Идеальный результат',
            'streak': 'Серия активности',
            'speed': 'Скорость',
            'consistency': 'Постоянство',
            'explorer': 'Исследователь',
            'master': 'Мастер'
        };
        return labels[type] || 'Достижения';
    }

    function getRarityBadge(rarity) {
        const badges = {
            'common': '<span class="badge bg-secondary">Обычное</span>',
            'rare': '<span class="badge bg-info">Редкое</span>',
            'epic': '<span class="badge bg-purple">Эпическое</span>',
            'legendary': '<span class="badge bg-warning text-dark">Легендарное</span>'
        };
        return badges[rarity] || '';
    }

    function getAchievementProgress(achievement) {
        if (achievement.progress && achievement.target) {
            return `Прогресс: ${achievement.progress}/${achievement.target}`;
        }
        return 'Заблокировано';
    }

    // ==================== LEADERBOARD ====================

    async function initLeaderboard() {
        // Event listeners
        document.getElementById('leaderboard-period')?.addEventListener('change', loadLeaderboard);

        loadLeaderboard();
    }

    async function loadLeaderboard() {
        const period = document.getElementById('leaderboard-period')?.value || 'all_time';

        try {
            const result = await apiCall('getLeaderboard', { period: period });

            if (result.success) {
                leaderboardData = result.data;
                renderLeaderboard();
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            console.error('Load leaderboard error:', error);
            document.getElementById('leaderboard-container').innerHTML = `
                <div class="alert alert-danger">
                    Ошибка загрузки рейтинга: ${escapeHtml(error.message)}
                </div>
            `;
        }
    }

    function renderLeaderboard() {
        const container = document.getElementById('leaderboard-container');

        if (!leaderboardData || leaderboardData.length === 0) {
            container.innerHTML = '<div class="alert alert-info">Рейтинг пуст</div>';
            return;
        }

        let html = `
            <div class="leaderboard-list">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Место</th>
                                <th>Пользователь</th>
                                <th>Уровень</th>
                                <th>XP</th>
                                <th>Тестов</th>
                                <th>Средний балл</th>
                                <th>Серия</th>
                            </tr>
                        </thead>
                        <tbody>
        `;

        leaderboardData.forEach((user, index) => {
            const rank = index + 1;
            const rankBadge = rank === 1 ? '🥇' :
                             rank === 2 ? '🥈' :
                             rank === 3 ? '🥉' : rank;

            const isCurrentUser = user.is_current_user;
            const rowClass = isCurrentUser ? 'table-primary' : '';

            html += `
                <tr class="${rowClass}">
                    <td><strong>${rankBadge}</strong></td>
                    <td>
                        ${escapeHtml(user.username)}
                        ${isCurrentUser ? '<span class="badge bg-primary ms-2">Вы</span>' : ''}
                    </td>
                    <td><span class="badge bg-info">${user.level}</span></td>
                    <td><strong>${user.xp.toLocaleString()}</strong></td>
                    <td>${user.tests_completed}</td>
                    <td>${user.avg_score}%</td>
                    <td>${user.current_streak > 0 ? `🔥 ${user.current_streak}` : '—'}</td>
                </tr>
            `;
        });

        html += `
                        </tbody>
                    </table>
                </div>
            </div>
        `;

        container.innerHTML = html;
    }

    // ==================== XP NOTIFICATION ====================

    function showXPNotification(xpEarned, reason) {
        const notification = document.createElement('div');
        notification.className = 'xp-notification position-fixed bottom-0 end-0 m-4 p-3 bg-success text-white rounded shadow-lg';
        notification.style.zIndex = '9999';
        notification.innerHTML = `
            <div class="d-flex align-items-center">
                <div class="fs-3 me-3">✨</div>
                <div>
                    <div class="fw-bold">+${xpEarned} XP</div>
                    <div class="small">${escapeHtml(reason)}</div>
                </div>
            </div>
        `;

        document.body.appendChild(notification);

        // Анимация
        setTimeout(() => {
            notification.style.transform = 'translateY(-20px)';
            notification.style.opacity = '0';
            notification.style.transition = 'all 0.5s ease';
        }, 2000);

        setTimeout(() => {
            notification.remove();
        }, 2500);

        // Обновляем виджет в хедере
        loadHeaderWidget();
    }

    // ==================== LEVEL UP NOTIFICATION ====================

    function showLevelUpNotification(newLevel) {
        const modalHtml = `
            <div class="modal fade" id="levelUpModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-body text-center py-5">
                            <div class="level-up-animation mb-4">
                                <i class="bi bi-trophy-fill text-warning" style="font-size: 5rem;"></i>
                            </div>
                            <h2 class="fw-bold mb-3">Поздравляем!</h2>
                            <p class="lead mb-4">Вы достигли уровня <span class="badge bg-primary fs-3">${newLevel}</span></p>
                            <div class="alert alert-info">
                                <h6>Новые возможности:</h6>
                                <ul class="mb-0 text-start">
                                    ${getLevelPerks(newLevel).slice(-1).map(perk => `<li>${perk}</li>`).join('')}
                                </ul>
                            </div>
                            <button type="button" class="btn btn-primary btn-lg" data-bs-dismiss="modal">
                                Отлично!
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHtml);

        const modal = new bootstrap.Modal(document.getElementById('levelUpModal'));
        modal.show();

        // Удаляем модальное окно после закрытия
        document.getElementById('levelUpModal').addEventListener('hidden.bs.modal', function() {
            this.remove();
        });
    }

    // ==================== ACHIEVEMENT UNLOCK NOTIFICATION ====================

    function showAchievementNotification(achievement) {
        const icon = ACHIEVEMENT_ICONS[achievement.type] || '🏆';

        const notification = document.createElement('div');
        notification.className = 'achievement-notification position-fixed top-0 start-50 translate-middle-x mt-4 p-4 bg-white rounded shadow-lg border border-success';
        notification.style.zIndex = '9999';
        notification.style.minWidth = '400px';
        notification.innerHTML = `
            <div class="text-center">
                <div class="achievement-icon fs-1 mb-2">${icon}</div>
                <h5 class="fw-bold text-success mb-2">Достижение разблокировано!</h5>
                <h6 class="mb-2">${escapeHtml(achievement.title)}</h6>
                <p class="text-muted small mb-2">${escapeHtml(achievement.description)}</p>
                <div class="badge bg-success">+${achievement.xp_reward} XP</div>
            </div>
        `;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.style.opacity = '0';
            notification.style.transition = 'opacity 0.5s ease';
        }, 4000);

        setTimeout(() => {
            notification.remove();
        }, 4500);
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

    window.Gamification = {
        loadUserProfile,
        loadHeaderWidget,
        loadAchievements,
        loadLeaderboard,
        showXPNotification,
        showLevelUpNotification,
        showAchievementNotification
    };

})();
