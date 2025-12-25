-- ДИАГНОСТИКА КАТЕГОРИЙ ТЕСТОВ
-- Выполните в phpMyAdmin

-- 1. Проверяем структуру связки тестов и ресурсов
SELECT
    t.id AS 'Test ID',
    t.title AS 'Тест',
    t.resource_id AS 'Resource ID',
    r.pagetitle AS 'Страница теста',
    r.parent AS 'Parent ID',
    parent_r.pagetitle AS 'Родитель (Категория)',
    t.publication_status
FROM modx_test_tests t
LEFT JOIN modx_site_content r ON r.id = t.resource_id
LEFT JOIN modx_site_content parent_r ON parent_r.id = r.parent
WHERE t.is_active = 1
ORDER BY r.parent, t.title
LIMIT 20;

-- 2. Какие ресурсы являются родителями (категориями)?
SELECT DISTINCT
    parent_r.id AS 'Категория ID',
    parent_r.pagetitle AS 'Название категории',
    parent_r.alias AS 'Alias',
    COUNT(r.id) AS 'Количество тестов'
FROM modx_site_content r
JOIN modx_test_tests t ON t.resource_id = r.id
LEFT JOIN modx_site_content parent_r ON parent_r.id = r.parent
WHERE t.is_active = 1 AND r.parent IS NOT NULL
GROUP BY parent_r.id
ORDER BY parent_r.pagetitle;

-- 3. Проверяем дочерние ресурсы родителя ID 35 (Тесты)
SELECT
    id,
    pagetitle AS 'Название',
    alias,
    published,
    deleted
FROM modx_site_content
WHERE parent = 35 AND deleted = 0
ORDER BY menuindex;

-- 4. Тесты БЕЗ категории (resource_id = NULL или parent = NULL)
SELECT
    t.id AS 'Test ID',
    t.title AS 'Тест',
    t.resource_id,
    r.parent,
    t.publication_status
FROM modx_test_tests t
LEFT JOIN modx_site_content r ON r.id = t.resource_id
WHERE t.is_active = 1
    AND (t.resource_id IS NULL OR r.parent IS NULL OR r.parent = 0)
ORDER BY t.title;
