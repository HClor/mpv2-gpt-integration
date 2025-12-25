// БЫСТРАЯ ДИАГНОСТИКА - Вставьте в консоль браузера (F12)

// 1. ПЕРВИЧНАЯ ПРОВЕРКА
console.log('CategoryExpertsManager загружен?', typeof categoryExpertsManager !== 'undefined');

// 2. НАЙТИ ВСЕ ФОРМЫ
const forms = document.querySelectorAll('[id^="addExpertForm"]');
console.log(`Найдено форм: ${forms.length}`);

forms.forEach(form => {
    const categoryId = form.id.replace('addExpertForm', '');
    const hiddenInput = form.querySelector('input[name="category_id"]');
    console.log(`Форма для категории ${categoryId}:`);
    console.log('  Hidden input найден?', !!hiddenInput);
    console.log('  Значение category_id:', hiddenInput ? hiddenInput.value : 'НЕТ');
});

// 3. ФУНКЦИЯ ДЛЯ БЫСТРОГО ТЕСТА
// Использование: quickTest(1, 5) где 1 - categoryId, 5 - expertUserId
window.quickTest = async function(categoryId, expertUserId) {
    // Найти CSRF токен
    let csrf = '';
    const csrfInput = document.querySelector('input[name="csrf_token"]');
    if (csrfInput) csrf = csrfInput.value;

    console.log('Отправка запроса...');
    console.log('categoryId:', categoryId, 'type:', typeof categoryId);
    console.log('expertUserId:', expertUserId, 'type:', typeof expertUserId);
    console.log('CSRF:', csrf ? 'OK' : 'MISSING');

    const requestData = {
        action: 'assignCategoryExpert',
        csrf_token: csrf,
        data: {
            category_id: parseInt(categoryId),
            expert_user_id: parseInt(expertUserId),
            can_manage_tests: true,
            can_manage_questions: true,
            can_approve: false
        }
    };

    console.log('Данные:', requestData);

    try {
        const response = await fetch('/assets/components/testsystem/ajax/testsystem.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(requestData)
        });

        const result = await response.json();
        console.log('Результат:', result);

        if (!result.success) {
            console.error('ОШИБКА:', result.message);
        } else {
            console.log('УСПЕХ!', result.message);
        }

        return result;
    } catch (error) {
        console.error('ОШИБКА ЗАПРОСА:', error);
    }
};

console.log('\n=== Используйте команду quickTest(categoryId, expertUserId) ===');
console.log('Пример: quickTest(1, 5)');
