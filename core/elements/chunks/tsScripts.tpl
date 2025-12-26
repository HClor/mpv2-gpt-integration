<!-- jQuery (если нужен) -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Test System JS -->
<!-- ПРИМЕЧАНИЕ: tsrunner.js подключается в сниппете testRunner.php, не подключать здесь! -->
<script src="/assets/components/testsystem/js/mytests.js"></script>
<script src="/assets/components/testsystem/js/knowledge-areas.js"></script>

<!-- Sprint 9: Учебные материалы -->
<!-- learning-materials.js подключается в learningMaterialsTemplate.php, не подключать здесь! -->

<!-- Sprint 10: Права доступа -->
<script src="/assets/components/testsystem/js/category-permissions.js"></script>

<!-- Sprint 11: Траектории обучения -->
<script src="/assets/components/testsystem/js/learning-paths.js"></script>

<!-- Sprint 12: Расширенные типы вопросов -->
<script src="/assets/components/testsystem/js/special-question-types.js"></script>

<!-- Sprint 13: Геймификация -->
<script src="/assets/components/testsystem/js/gamification.js"></script>

<!-- Sprint 14: Уведомления -->
<script src="/assets/components/testsystem/js/notifications.js"></script>

<!-- Sprint 15: Аналитика -->
<script src="/assets/components/testsystem/js/analytics.js"></script>

<!-- Sprint 16: Сертификаты -->
<script src="/assets/components/testsystem/js/certificates.js"></script>



<!-- Дополнительный JS из TV поля -->
{$_modx->resource.jsTV}

<script>
// Инициализация Test System
document.addEventListener('DOMContentLoaded', function() {
    // API endpoint для AJAX запросов
    window.TS_API_URL = '/assets/components/testsystem/ajax/testsystem.php';

    // ID текущего пользователя
    window.TS_USER_ID = {$_modx->user.id ?: 0};

    // Автообновление уведомлений каждые 30 секунд
    {if $_modx->user.id > 0}
    setInterval(function() {
        fetch(window.TS_API_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'getUnreadNotifications',
                data: {
                    user_id: window.TS_USER_ID,
                    limit: 5
                }
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data.unread_count > 0) {
                // Показать badge с количеством непрочитанных
                updateNotificationBadge(data.data.unread_count);
            }
        })
        .catch(error => console.error('Error fetching notifications:', error));
    }, 30000);
    {/if}

    // Функция обновления badge уведомлений
    function updateNotificationBadge(count) {
        let badge = document.querySelector('.notification-badge');
        if (!badge && count > 0) {
            const userMenu = document.getElementById('userMenu');
            if (userMenu) {
                badge = document.createElement('span');
                badge.className = 'badge bg-danger ms-1 notification-badge';
                userMenu.appendChild(badge);
            }
        }
        if (badge) {
            badge.textContent = count;
            if (count === 0) {
                badge.remove();
            }
        }
    }

    // Tooltips Bootstrap
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Popovers Bootstrap
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });

    // Smooth scroll для якорей
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                e.preventDefault();
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Анимация карточек при скролле
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    document.querySelectorAll('.card').forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = 'opacity 0.5s, transform 0.5s';
        observer.observe(card);
    });

    console.log('Test System initialized successfully');

    // ========== LEARNING PATH STEP COMPLETION ==========
    // Показываем панель завершения шага если есть параметры path и step в URL
    initLearningPathStepPanel();
});

// Панель завершения шага траектории
function initLearningPathStepPanel() {
    const urlParams = new URLSearchParams(window.location.search);
    const pathId = urlParams.get('path');
    const stepId = urlParams.get('step');

    // Если нет параметров траектории - выходим
    if (!pathId || !stepId) return;

    // Не показываем панель на странице самой траектории
    if (window.location.pathname.includes('learning-paths')) return;

    // Создаём плавающую панель
    const panel = document.createElement('div');
    panel.id = 'learning-path-step-panel';
    panel.innerHTML = `
        <div class="lp-step-panel shadow-lg">
            <div class="lp-step-header">
                <i class="bi bi-signpost-2 me-2"></i>
                <span>Шаг траектории</span>
                <button type="button" class="btn-close btn-close-white btn-sm ms-auto" id="lp-panel-close"></button>
            </div>
            <div class="lp-step-body">
                <p class="mb-3">Вы изучаете этот материал в рамках траектории обучения</p>
                <div class="d-grid gap-2">
                    <button class="btn btn-success" id="lp-complete-step">
                        <i class="bi bi-check-circle me-1"></i> Завершить и продолжить
                    </button>
                    <button class="btn btn-outline-light btn-sm" id="lp-back-to-path">
                        <i class="bi bi-arrow-left me-1"></i> Вернуться к траектории
                    </button>
                </div>
            </div>
        </div>
    `;

    // Стили для панели
    const styles = document.createElement('style');
    styles.textContent = `
        #learning-path-step-panel {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 320px;
            animation: slideInRight 0.3s ease-out;
        }
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        .lp-step-panel {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            color: white;
            overflow: hidden;
        }
        .lp-step-header {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            background: rgba(0,0,0,0.1);
            font-weight: 600;
        }
        .lp-step-body {
            padding: 16px;
        }
        .lp-step-body p {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        #learning-path-step-panel .btn-success {
            background: #28a745;
            border: none;
            padding: 10px 16px;
        }
        #learning-path-step-panel .btn-success:hover {
            background: #218838;
        }
        #learning-path-step-panel .btn-outline-light {
            border-color: rgba(255,255,255,0.5);
        }
        #learning-path-step-panel .btn-outline-light:hover {
            background: rgba(255,255,255,0.1);
        }
        #learning-path-step-panel.minimized .lp-step-body {
            display: none;
        }
        #learning-path-step-panel.minimized .lp-step-header {
            cursor: pointer;
        }
    `;
    document.head.appendChild(styles);
    document.body.appendChild(panel);

    // Обработчики
    document.getElementById('lp-panel-close').addEventListener('click', function() {
        panel.classList.toggle('minimized');
        if (panel.classList.contains('minimized')) {
            this.innerHTML = '<i class="bi bi-chevron-up"></i>';
            document.querySelector('.lp-step-header').addEventListener('click', function() {
                panel.classList.remove('minimized');
                document.getElementById('lp-panel-close').innerHTML = '';
            }, { once: true });
        }
    });

    document.getElementById('lp-complete-step').addEventListener('click', async function() {
        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Сохранение...';

        try {
            const response = await fetch(window.TS_API_URL || '/assets/components/testsystem/ajax/testsystem.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'completePathStep',
                    data: {
                        path_id: parseInt(pathId),
                        step_id: parseInt(stepId)
                    }
                })
            });

            const result = await response.json();

            if (result.success) {
                btn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Готово!';
                btn.classList.remove('btn-success');
                btn.classList.add('btn-light', 'text-success');

                // Показываем уведомление
                showLpNotification('Шаг успешно завершён!', 'success');

                // Переходим к следующему шагу или обратно к траектории
                setTimeout(function() {
                    window.location.href = '/learning-paths?mode=view&id=' + pathId;
                }, 1000);
            } else {
                throw new Error(result.message || 'Ошибка завершения шага');
            }
        } catch (error) {
            console.error('Complete step error:', error);
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Завершить и продолжить';
            showLpNotification('Ошибка: ' + error.message, 'danger');
        }
    });

    document.getElementById('lp-back-to-path').addEventListener('click', function() {
        window.location.href = '/learning-paths?mode=view&id=' + pathId;
    });
}

function showLpNotification(message, type) {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} position-fixed top-0 start-50 translate-middle-x mt-3 shadow`;
    notification.style.zIndex = '10000';
    notification.innerHTML = message;
    document.body.appendChild(notification);
    setTimeout(() => notification.remove(), 3000);
}
</script>
