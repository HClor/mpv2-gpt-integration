-- Проверка структуры таблицы test_tests для приватных тестов
SHOW COLUMNS FROM modx_test_tests;

-- Проверка существующих приватных тестов
SELECT
    id,
    title,
    created_by,
    publication_status,
    is_active,
    resource_id as category_id
FROM modx_test_tests
WHERE publication_status = 'private'
LIMIT 10;

-- Проверка таблицы доступа к приватным тестам
SHOW TABLES LIKE '%test%access%';

-- Если есть таблица доступа
SELECT * FROM modx_test_permissions LIMIT 5;
