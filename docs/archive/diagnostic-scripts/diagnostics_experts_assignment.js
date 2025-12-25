/**
 * Диагностика назначения экспертов
 * Вставьте этот код в консоль браузера (F12) на странице Управление категориями
 */

// 1. Проверка загрузки CategoryExpertsManager
console.log('=== ДИАГНОСТИКА НАЗНАЧЕНИЯ ЭКСПЕРТОВ ===');
console.log('1. Проверка загрузки скрипта:');
console.log('categoryExpertsManager:', typeof categoryExpertsManager !== 'undefined' ? '✅ Загружен' : '❌ НЕ загружен');

if (typeof categoryExpertsManager !== 'undefined') {
    console.log('API URL:', categoryExpertsManager.apiUrl);
    console.log('CSRF Token:', categoryExpertsManager.csrfToken ? '✅ Есть' : '❌ НЕТ');
}

// 2. Проверка наличия модальных окон
console.log('\n2. Проверка модальных окон:');
const modals = document.querySelectorAll('[id^="expertsModal"]');
console.log('Найдено модальных окон экспертов:', modals.length);
modals.forEach((modal, index) => {
    const categoryId = modal.id.replace('expertsModal', '');
    console.log(`  Модальное окно #${index + 1}: categoryId = ${categoryId}`);

    // Проверка формы
    const form = document.getElementById(`addExpertForm${categoryId}`);
    console.log(`    Форма: ${form ? '✅ Найдена' : '❌ НЕ найдена'}`);

    if (form) {
        const categoryInput = form.querySelector('input[name="category_id"]');
        console.log(`    Input category_id: ${categoryInput ? '✅ Найден' : '❌ НЕ найден'}`);
        if (categoryInput) {
            console.log(`    Значение category_id: "${categoryInput.value}"`);
        }

        const expertSelect = form.querySelector('select[name="expert_user_id"]');
        console.log(`    Select expert_user_id: ${expertSelect ? '✅ Найден' : '❌ НЕ найден'}`);
    }
});

// 3. Тестовая функция для проверки API запроса
console.log('\n3. Функция для тестирования API запроса:');
console.log('Используйте: testExpertAssignment(categoryId, expertUserId)');

window.testExpertAssignment = async function(categoryId, expertUserId) {
    console.log('\n=== ТЕСТ НАЗНАЧЕНИЯ ЭКСПЕРТА ===');
    console.log('Category ID:', categoryId);
    console.log('Expert User ID:', expertUserId);

    const apiUrl = '/assets/components/testsystem/ajax/testsystem.php';

    // Получаем CSRF токен
    let csrfToken = '';
    const metaToken = document.querySelector('meta[name="csrf-token"]');
    if (metaToken) {
        csrfToken = metaToken.getAttribute('content');
    } else {
        const inputToken = document.querySelector('input[name="csrf_token"]');
        if (inputToken) {
            csrfToken = inputToken.value;
        }
    }

    console.log('CSRF Token:', csrfToken ? '✅ Найден' : '❌ НЕ найден');

    const requestData = {
        action: 'assignCategoryExpert',
        csrf_token: csrfToken,
        data: {
            category_id: categoryId,
            expert_user_id: expertUserId,
            can_manage_tests: true,
            can_manage_questions: true,
            can_approve: false
        }
    };

    console.log('Отправляемые данные:', requestData);

    try {
        const response = await fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(requestData)
        });

        console.log('HTTP Status:', response.status);

        const result = await response.json();
        console.log('Ответ сервера:', result);

        if (result.success) {
            console.log('✅ УСПЕХ:', result.message);
        } else {
            console.log('❌ ОШИБКА:', result.message);
        }

        return result;
    } catch (error) {
        console.error('❌ Ошибка запроса:', error);
        return { success: false, message: error.message };
    }
};

// 4. Функция для мониторинга отправки формы
console.log('\n4. Установка перехватчика отправки форм:');

window.debugFormSubmission = function(categoryId) {
    const form = document.getElementById(`addExpertForm${categoryId}`);

    if (!form) {
        console.error(`❌ Форма addExpertForm${categoryId} не найдена`);
        return;
    }

    console.log(`✅ Перехватчик установлен для формы категории ${categoryId}`);

    // Убираем старый обработчик и добавляем новый с логированием
    const newForm = form.cloneNode(true);
    form.parentNode.replaceChild(newForm, form);

    newForm.addEventListener('submit', function(e) {
        e.preventDefault();
        console.log('\n=== ПЕРЕХВАТ ОТПРАВКИ ФОРМЫ ===');
        console.log('Event:', e);
        console.log('Form:', newForm);

        const formData = new FormData(newForm);
        console.log('\nДанные формы:');
        for (let [key, value] of formData.entries()) {
            console.log(`  ${key}: "${value}"`);
        }

        const categoryIdValue = formData.get('category_id');
        const expertIdValue = formData.get('expert_user_id');

        console.log('\nАнализ:');
        console.log('category_id:', categoryIdValue || '❌ ПУСТО');
        console.log('expert_user_id:', expertIdValue || '❌ ПУСТО');
        console.log('can_manage_tests:', formData.get('can_manage_tests'));
        console.log('can_manage_questions:', formData.get('can_manage_questions'));
        console.log('can_approve:', formData.get('can_approve'));

        // Вызываем оригинальную функцию
        if (typeof categoryExpertsManager !== 'undefined') {
            console.log('\nВызов categoryExpertsManager.handleAssignExpert...');
            categoryExpertsManager.handleAssignExpert(e, categoryId);
        } else {
            console.error('❌ categoryExpertsManager не определен');
        }
    });
};

// 5. Получение списка доступных экспертов
console.log('\n5. Функция для получения списка экспертов:');

window.getAvailableExperts = async function() {
    console.log('\n=== ПОЛУЧЕНИЕ СПИСКА ЭКСПЕРТОВ ===');

    if (typeof categoryExpertsManager === 'undefined') {
        console.error('❌ categoryExpertsManager не загружен');
        return;
    }

    try {
        const experts = await categoryExpertsManager.loadAvailableExperts();
        console.log('Доступные эксперты:', experts);
        return experts;
    } catch (error) {
        console.error('❌ Ошибка загрузки экспертов:', error);
    }
};

// 6. Проверка текущих экспертов категории
window.getCategoryExperts = async function(categoryId) {
    console.log(`\n=== ЭКСПЕРТЫ КАТЕГОРИИ ${categoryId} ===`);

    if (typeof categoryExpertsManager === 'undefined') {
        console.error('❌ categoryExpertsManager не загружен');
        return;
    }

    try {
        const experts = await categoryExpertsManager.loadCategoryExperts(categoryId);
        console.log('Текущие эксперты:', experts);
        return experts;
    } catch (error) {
        console.error('❌ Ошибка загрузки экспертов:', error);
    }
};

console.log('\n=== КОМАНДЫ ДЛЯ ИСПОЛЬЗОВАНИЯ ===');
console.log('testExpertAssignment(categoryId, expertUserId) - Тестовая отправка API запроса');
console.log('debugFormSubmission(categoryId) - Перехватить отправку формы для отладки');
console.log('getAvailableExperts() - Получить список доступных экспертов');
console.log('getCategoryExperts(categoryId) - Получить экспертов категории');
console.log('\nПример использования:');
console.log('  debugFormSubmission(1)  // Установить отладку для категории 1');
console.log('  testExpertAssignment(1, 5)  // Назначить эксперта 5 на категорию 1');
