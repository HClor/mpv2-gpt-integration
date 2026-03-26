{* Чанк: tsScripts - JavaScript для системы тестирования
   Вызывается из: TestSystem.tpl
   Содержит: Bootstrap JS, все JS модули системы тестирования
*}
<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/components/testsystem/js/csrf-helper.js"></script>

{set $resourceAlias = $_modx->resource.alias|default:''}

<!-- Test System JS: feature bundle (page/role driven) -->
<!-- ПРИМЕЧАНИЕ: tsrunner.js подключается в сниппете testRunner.php, не подключать здесь! -->
{if $_modx->user.id > 0}
<script src="/assets/components/testsystem/js/mytests.js"></script>
<script src="/assets/components/testsystem/js/knowledge-areas.js"></script>
<script src="/assets/components/testsystem/js/category-permissions.js"></script>
<script src="/assets/components/testsystem/js/special-question-types.js"></script>
<script src="/assets/components/testsystem/js/gamification.js"></script>
<script src="/assets/components/testsystem/js/notifications.js"></script>
<script src="/assets/components/testsystem/js/analytics.js"></script>
<script src="/assets/components/testsystem/js/certificates.js"></script>
{/if}

{if $resourceAlias == 'learning-paths' || $resourceAlias == 'learning-articles' || $resourceAlias == 'tests'}
<script src="/assets/components/testsystem/js/learning-paths.js"></script>
{/if}
<script>
// ========== ГЛОБАЛЬНЫЙ ХЕЛПЕР ДЛЯ API ЗАПРОСОВ С CSRF ==========
window.TS_API_URL = '/assets/components/testsystem/ajax/testsystem.php';

// Получить CSRF токен из meta тега
function getCSRFToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.content : '';
}

// Универсальная функция для API запросов с автоматическим CSRF
// action - Название action для API
// data - Данные для отправки
// Возвращает Promise с результатом запроса
async function tsApiRequest(action, data = {}) {
    const csrfToken = getCSRFToken();

    const response = await fetch(window.TS_API_URL, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({
            action: action,
            data: data
        })
    });

    return await response.json();
}

// Инициализация Test System
document.addEventListener('DOMContentLoaded', function() {
    // ID текущего пользователя
    window.TS_USER_ID = {$_modx->user.id ?: 0};

    // Автообновление уведомлений каждые 30 секунд
    {if $_modx->user.id > 0}
    setInterval(function() {
        fetch(window.TS_API_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCSRFToken()
            },
            body: JSON.stringify({
                action: 'getUnreadNotifications',
                data: {
                    user_id: window.TS_USER_ID,
                    limit: 5
                }
            })
        })
        .then(async response => {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }

            const contentType = response.headers.get('content-type') || '';
            if (!contentType.includes('application/json')) {
                throw new Error('Invalid response format (expected JSON)');
            }

            return response.json();
        })
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
        const badge = document.getElementById('notifications-badge');
        if (!badge) {
            return;
        }

        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.style.display = '';
            return;
        }

        badge.style.display = 'none';
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
            const href = this.getAttribute('href');
            if (!href || href === '#') {
                return;
            }

            const target = document.querySelector(href);
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
});
</script>
