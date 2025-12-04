/**
 * ДИАГНОСТИКА ОБЛАСТЕЙ ЗНАНИЙ - Выполните в консоли браузера
 * 1. Откройте страницу "Мои области знаний"
 * 2. Нажмите F12 (Developer Tools)
 * 3. Скопируйте весь код в консоль браузера
 */

console.log('%c=== ДИАГНОСТИКА ОБЛАСТЕЙ ЗНАНИЙ ===', 'color: blue; font-size: 16px; font-weight: bold');

// 1. ПРОВЕРКА КОНТЕЙНЕРА
console.log('\n%c1. ПРОВЕРКА КОНТЕЙНЕРА', 'color: green; font-weight: bold');
const container = document.getElementById('knowledge-areas-container');
if (container) {
    console.log('✓ Контейнер найден');
    console.log('  - Test Page URL:', container.dataset.testPageUrl);
    console.log('  - Manage Page URL:', container.dataset.managePageUrl);
} else {
    console.error('✗ ОШИБКА: Контейнер knowledge-areas-container не найден!');
}

// 2. ПРОВЕРКА JS
console.log('\n%c2. ПРОВЕРКА JAVASCRIPT', 'color: green; font-weight: bold');
if (typeof window.KAManager !== 'undefined') {
    console.log('✓ window.KAManager инициализирован');
    console.log('  Доступные методы:', Object.keys(window.KAManager));
} else {
    console.error('✗ ОШИБКА: window.KAManager не найден!');
}

// 3. ПРОВЕРКА CSRF ТОКЕНА
console.log('\n%c3. ПРОВЕРКА CSRF ТОКЕНА', 'color: green; font-weight: bold');
const csrfMeta = document.querySelector('meta[name="csrf-token"]');
if (csrfMeta) {
    console.log('✓ CSRF токен найден:', csrfMeta.content.substring(0, 20) + '...');
} else {
    console.error('✗ ОШИБКА: CSRF токен не найден!');
}

// 4. ПРОВЕРКА BOOTSTRAP
console.log('\n%c4. ПРОВЕРКА BOOTSTRAP', 'color: green; font-weight: bold');
if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
    console.log('✓ Bootstrap загружен');
} else {
    console.error('✗ ОШИБКА: Bootstrap не загружен!');
}

// 5. ТЕСТ API - getKnowledgeAreas
console.log('\n%c5. ТЕСТ API - getKnowledgeAreas', 'color: green; font-weight: bold');

function getCsrfToken() {
    const metaTag = document.querySelector('meta[name="csrf-token"]');
    return metaTag ? metaTag.content : null;
}

async function testAPI() {
    const API_URL = "/assets/components/testsystem/ajax/testsystem.php";

    try {
        const csrfToken = getCsrfToken();
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'getKnowledgeAreas',
                data: { csrf_token: csrfToken }
            })
        });

        const result = await response.json();

        if (result.success) {
            console.log('✓ API работает');
            console.log('  Найдено областей:', result.data.length);
            if (result.data.length > 0) {
                console.log('  Первая область:', result.data[0]);
            }
        } else {
            console.error('✗ API вернул ошибку:', result.message);
        }

        return result;
    } catch (error) {
        console.error('✗ ОШИБКА при вызове API:', error);
        return null;
    }
}

// Запускаем тест API
testAPI().then(result => {
    console.log('\n%c6. РЕЗУЛЬТАТ ТЕСТА API', 'color: green; font-weight: bold');
    console.log(result);

    // 7. ФИНАЛЬНАЯ ОЦЕНКА
    console.log('\n%c=== ИТОГОВАЯ ОЦЕНКА ===', 'color: blue; font-size: 16px; font-weight: bold');

    const checks = [
        container !== null,
        typeof window.KAManager !== 'undefined',
        csrfMeta !== null,
        typeof bootstrap !== 'undefined',
        result && result.success
    ];

    const passedChecks = checks.filter(Boolean).length;
    const totalChecks = checks.length;

    if (passedChecks === totalChecks) {
        console.log('%c✓ ВСЕ ПРОВЕРКИ ПРОЙДЕНЫ (' + passedChecks + '/' + totalChecks + ')', 'color: green; font-size: 14px; font-weight: bold');
        console.log('Функционал областей знаний готов к использованию!');
    } else {
        console.warn('%c⚠ ПРОЙДЕНО ' + passedChecks + '/' + totalChecks + ' ПРОВЕРОК', 'color: orange; font-size: 14px; font-weight: bold');
        console.log('Необходимо исправить ошибки выше.');
    }
});

// 8. ДОПОЛНИТЕЛЬНО - Список кнопок
console.log('\n%c7. ДОСТУПНЫЕ КНОПКИ', 'color: green; font-weight: bold');
const createBtn = document.getElementById('create-area-btn');
if (createBtn) {
    console.log('✓ Кнопка "Создать область" найдена');
} else {
    console.error('✗ Кнопка "Создать область" не найдена');
}

console.log('\n%cДля создания тестовой области используйте кнопку "Создать область" или выполните:', 'color: blue');
console.log('document.getElementById("create-area-btn").click()');
