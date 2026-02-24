<?php
/**
 * Сниппет: adminDataIntegrity - Проверка целостности данных
 * Вызывается из: MODX ресурсов (админ-панель)
 * Назначение: Проверка и исправление целостности данных системы
 *
 * @package TestSystem
 * @version 1.0
 *
 * ИСПОЛЬЗОВАНИЕ:
 * [[!adminDataIntegrity]]
 *
 * Создайте страницу в MODX, доступную только администраторам,
 * и добавьте этот вызов сниппета
 */

// Подключаем bootstrap для доступа к Config
require_once MODX_CORE_PATH . 'components/testsystem/bootstrap.php';

// Проверка прав администратора
$userId = $modx->user->id;
if ($userId === 0) {
    return '<div class="alert alert-danger">Требуется авторизация</div>';
}

$userGroups = $modx->user->getUserGroups();
$adminGroup = Config::getGroup('admins');
$isAdmin = in_array($adminGroup, $userGroups, true) || $userId === 1;

if (!$isAdmin) {
    return '<div class="alert alert-danger">Доступ запрещен. Требуются права администратора.</div>';
}

// HTML разметка
$output = '
<div class="container-fluid mt-4" id="data-integrity-admin">
    <h2 class="mb-4">🔍 Проверка целостности данных</h2>

    <!-- Кнопки действий -->
    <div class="mb-4">
        <button class="btn btn-primary" id="btn-check-integrity">
            <i class="bi bi-search"></i> Проверить целостность
        </button>
        <button class="btn btn-warning" id="btn-clean-all" disabled>
            <i class="bi bi-brush"></i> Очистить все проблемы
        </button>
        <button class="btn btn-info" id="btn-system-stats">
            <i class="bi bi-bar-chart"></i> Статистика системы
        </button>
        <button class="btn btn-secondary" id="btn-clean-old-sessions">
            <i class="bi bi-trash"></i> Очистить старые сессии (90+ дней)
        </button>
    </div>

    <!-- Лоадер -->
    <div id="loading" class="d-none">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Загрузка...</span>
        </div>
        <span class="ms-2">Обработка...</span>
    </div>

    <!-- Результаты проверки -->
    <div id="integrity-report" class="mt-4"></div>

    <!-- Статистика системы -->
    <div id="system-stats" class="mt-4"></div>
</div>

<script>
document.addEventListener(\'DOMContentLoaded\', function() {
    const apiUrl = \'' . $modx->getOption('site_url') . 'assets/components/testsystem/ajax/testsystem.php\';
    const csrfToken = \'' . CsrfProtection::generateToken($modx) . '\';

    const btnCheck = document.getElementById(\'btn-check-integrity\');
    const btnCleanAll = document.getElementById(\'btn-clean-all\');
    const btnStats = document.getElementById(\'btn-system-stats\');
    const btnCleanOld = document.getElementById(\'btn-clean-old-sessions\');
    const loading = document.getElementById(\'loading\');
    const reportDiv = document.getElementById(\'integrity-report\');
    const statsDiv = document.getElementById(\'system-stats\');

    function showLoading(show) {
        loading.classList.toggle(\'d-none\', !show);
    }

    function apiRequest(action, data = {}) {
        showLoading(true);
        data.csrf_token = csrfToken;

        return fetch(apiUrl, {
            method: \'POST\',
            headers: {
                \'Content-Type\': \'application/json\'
            },
            body: JSON.stringify({
                action: action,
                data: data
            })
        })
        .then(response => response.json())
        .finally(() => showLoading(false));
    }

    // Проверка целостности
    btnCheck.addEventListener(\'click\', function() {
        apiRequest(\'checkIntegrity\').then(result => {
            if (result.success) {
                const report = result.data;
                renderIntegrityReport(report);

                if (report.total_issues > 0) {
                    btnCleanAll.disabled = false;
                }
            } else {
                alert(\'Ошибка: \' + result.message);
            }
        });
    });

    // Очистка всех проблем
    btnCleanAll.addEventListener(\'click\', function() {
        if (!confirm(\'Вы уверены, что хотите очистить все найденные проблемы? Это действие необратимо!\')) {
            return;
        }

        apiRequest(\'cleanOrphanedData\', {confirmed: 1}).then(result => {
            if (result.success) {
                alert(\'Очищено записей: \' + result.data.total_deleted);
                btnCheck.click(); // Повторная проверка
            } else {
                alert(\'Ошибка: \' + result.message);
            }
        });
    });

    // Статистика системы
    btnStats.addEventListener(\'click\', function() {
        apiRequest(\'getSystemStats\').then(result => {
            if (result.success) {
                renderSystemStats(result.data);
            } else {
                alert(\'Ошибка: \' + result.message);
            }
        });
    });

    // Очистка старых сессий
    btnCleanOld.addEventListener(\'click\', function() {
        if (!confirm(\'Удалить все завершенные сессии старше 90 дней?\')) {
            return;
        }

        apiRequest(\'cleanOldSessions\', {days_old: 90}).then(result => {
            if (result.success) {
                alert(result.data.message);
            } else {
                alert(\'Ошибка: \' + result.message);
            }
        });
    });

    function renderIntegrityReport(report) {
        let html = \'\';

        if (report.total_issues === 0) {
            html = \'<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i> Проблем не обнаружено! База данных в порядке.</div>\';
        } else {
            html = \'<div class="alert alert-warning"><strong>Обнаружено проблем: \' + report.total_issues + \'</strong></div>\';

            // Осиротевшие тесты
            if (report.orphaned_tests.length > 0) {
                html += renderIssueCard(\'Осиротевшие тесты\', report.orphaned_tests, \'danger\', function(item) {
                    return `Test #${item.id}: ${item.title} (resource_id: ${item.resource_id})`;
                });
            }

            // Осиротевшие вопросы
            if (report.orphaned_questions.length > 0) {
                html += renderIssueCard(\'Осиротевшие вопросы\', report.orphaned_questions, \'warning\', function(item) {
                    return `Question #${item.id}: ${item.question_text.substring(0, 50)}... (test_id: ${item.test_id})`;
                });
            }

            // Осиротевшие ответы
            if (report.orphaned_answers.length > 0) {
                html += renderIssueCard(\'Осиротевшие ответы\', report.orphaned_answers, \'info\', function(item) {
                    return `Answer #${item.id}: ${item.answer_text.substring(0, 50)}... (question_id: ${item.question_id})`;
                });
            }

            // Осиротевшие сессии
            if (report.orphaned_sessions.length > 0) {
                html += renderIssueCard(\'Осиротевшие сессии\', report.orphaned_sessions, \'secondary\', function(item) {
                    return `Session #${item.id} (${item.issue_type})`;
                });
            }

            // Осиротевшие пользовательские ответы
            if (report.orphaned_user_answers.length > 0) {
                html += renderIssueCard(\'Осиротевшие пользовательские ответы\', report.orphaned_user_answers, \'secondary\', function(item) {
                    return `UserAnswer #${item.id} (${item.issue_type})`;
                });
            }

            // Осиротевшие избранные
            if (report.orphaned_favorites.length > 0) {
                html += renderIssueCard(\'Осиротевшие избранные вопросы\', report.orphaned_favorites, \'secondary\', function(item) {
                    return `Favorite #${item.id} (${item.issue_type})`;
                });
            }

            // Неверные ссылки на категории
            if (report.invalid_category_refs.length > 0) {
                html += renderIssueCard(\'Неверные ссылки на категории\', report.invalid_category_refs, \'warning\', function(item) {
                    return `Test #${item.id}: ${item.title} (category_id: ${item.category_id})`;
                });
            }
        }

        reportDiv.innerHTML = html;
    }

    function renderIssueCard(title, items, variant, itemRenderer) {
        let html = `
            <div class="card issue-card border-${variant}">
                <div class="card-header bg-${variant} text-white">
                    <strong>${title}</strong> <span class="badge bg-light text-dark">${items.length}</span>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
        `;

        items.forEach(item => {
            html += `<li class="mb-1"><small>${itemRenderer(item)}</small></li>`;
        });

        html += `
                    </ul>
                </div>
            </div>
        `;

        return html;
    }

    function renderSystemStats(stats) {
        let html = `
            <h3 class="mb-3">Статистика системы</h3>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <div class="card stat-card border-primary">
                        <div class="card-body text-center">
                            <h4 class="text-primary">${stats.total_tests}</h4>
                            <p class="mb-0">Всего тестов</p>
                            <small class="text-muted">${stats.active_tests} активных</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card stat-card border-success">
                        <div class="card-body text-center">
                            <h4 class="text-success">${stats.total_questions}</h4>
                            <p class="mb-0">Всего вопросов</p>
                            <small class="text-muted">${stats.published_questions} опубликовано</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card stat-card border-info">
                        <div class="card-body text-center">
                            <h4 class="text-info">${stats.total_sessions}</h4>
                            <p class="mb-0">Всего сессий</p>
                            <small class="text-muted">${stats.completed_sessions} завершено</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card stat-card border-warning">
                        <div class="card-body text-center">
                            <h4 class="text-warning">${stats.avg_score}%</h4>
                            <p class="mb-0">Средний балл</p>
                            <small class="text-muted">${stats.active_users} активных пользователей</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <div class="card">
                        <div class="card-header">
                            <strong>Популярные тесты</strong>
                        </div>
                        <div class="card-body">
                            <ol class="mb-0">
        `;

        if (stats.popular_tests && stats.popular_tests.length > 0) {
            stats.popular_tests.forEach(test => {
                html += `<li>${test.title} <span class="badge bg-secondary">${test.session_count} сессий</span></li>`;
            });
        } else {
            html += `<li class="text-muted">Нет данных</li>`;
        }

        html += `
                            </ol>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="card">
                        <div class="card-header">
                            <strong>Активность за последние 30 дней</strong>
                        </div>
                        <div class="card-body text-center">
                            <h3>${stats.sessions_last_30_days}</h3>
                            <p class="mb-0">пройденных тестов</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="alert alert-info">
                <strong>Размер БД:</strong> ${stats.database_size_mb} MB
            </div>
        `;

        statsDiv.innerHTML = html;
    }

    // Автоматическая проверка при загрузке
    btnCheck.click();
});
</script>
';

return $output;
