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
});
</script>
