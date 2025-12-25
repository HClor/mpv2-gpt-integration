-- Скопируйте эти SQL запросы и выполните их на сервере через phpMyAdmin или mysql клиент

-- 1. Общее количество тестов
SELECT COUNT(*) AS 'Всего тестов' FROM modx_test_tests;

-- 2. Активные тесты
SELECT COUNT(*) AS 'Активных тестов' FROM modx_test_tests WHERE is_active = 1;

-- 3. Список всех тестов с деталями
SELECT
    t.id,
    t.title,
    t.created_by,
    t.publication_status,
    t.is_active,
    t.created_at,
    (SELECT COUNT(*) FROM modx_test_questions WHERE test_id = t.id) as questions_count
FROM modx_test_tests t
ORDER BY t.created_at DESC
LIMIT 20;

-- 4. Проверка тестов по пользователям
SELECT
    created_by,
    COUNT(*) as tests_count,
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_tests
FROM modx_test_tests
GROUP BY created_by;

-- 5. Проверка прав доступа к тестам
SELECT
    p.test_id,
    t.title,
    p.user_id,
    u.username,
    p.can_edit,
    p.granted_at
FROM modx_test_permissions p
LEFT JOIN modx_test_tests t ON t.id = p.test_id
LEFT JOIN modx_users u ON u.id = p.user_id
LIMIT 20;

-- 6. Проверка структуры таблицы modx_test_tests
DESCRIBE modx_test_tests;
