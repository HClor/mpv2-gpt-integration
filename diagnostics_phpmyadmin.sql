-- ============================================
-- ДИАГНОСТИКА ОБЛАСТЕЙ ЗНАНИЙ - phpMyAdmin
-- Выполните эти запросы в phpMyAdmin
-- ============================================

-- 1. ПРОВЕРКА СТРУКТУРЫ ТАБЛИЦЫ
DESCRIBE modx_test_knowledge_areas;

-- 2. КОЛИЧЕСТВО ЗАПИСЕЙ
SELECT
    COUNT(*) as 'Всего областей',
    SUM(is_active = 1) as 'Активных',
    SUM(is_active = 0) as 'Неактивных'
FROM modx_test_knowledge_areas;

-- 3. СПИСОК ВСЕХ ОБЛАСТЕЙ ЗНАНИЙ
SELECT
    ka.id,
    ka.name AS 'Название',
    ka.user_id AS 'User ID',
    u.username AS 'Пользователь',
    ka.test_ids AS 'Тесты (JSON)',
    ka.questions_per_session AS 'Вопросов за сессию',
    ka.question_distribution_mode AS 'Режим распределения',
    ka.min_questions_per_test AS 'Мин. из теста',
    ka.is_active AS 'Активна',
    ka.created_at,
    ka.updated_at
FROM modx_test_knowledge_areas ka
LEFT JOIN modx_users u ON u.id = ka.user_id
ORDER BY ka.updated_at DESC;

-- 4. ПРОВЕРКА ТЕСТОВ В ОБЛАСТЯХ
-- Показываем какие тесты входят в каждую область
SELECT
    ka.id AS 'Область ID',
    ka.name AS 'Область',
    ka.test_ids,
    (SELECT COUNT(*)
     FROM modx_test_tests t
     WHERE FIND_IN_SET(t.id, REPLACE(REPLACE(ka.test_ids, '[', ''), ']', ''))
    ) AS 'Тестов найдено'
FROM modx_test_knowledge_areas ka
WHERE ka.is_active = 1;

-- 5. ДЕТАЛЬНАЯ ИНФОРМАЦИЯ ПО ОБЛАСТИ (замените 1 на нужный ID)
SELECT
    ka.*,
    u.username,
    u.email
FROM modx_test_knowledge_areas ka
LEFT JOIN modx_users u ON u.id = ka.user_id
WHERE ka.id = 1; -- ИЗМЕНИТЕ ID ЗДЕСЬ

-- 6. ПРОВЕРКА РЕСУРСОВ MODX
SELECT
    id,
    pagetitle AS 'Название',
    alias AS 'Alias',
    published AS 'Опубл.',
    deleted AS 'Удалён',
    LEFT(content, 100) AS 'Содержимое (первые 100 символов)'
FROM modx_site_content
WHERE id IN (145, 185)
ORDER BY id;

-- 7. ПРОВЕРКА СНИППЕТОВ
SELECT
    id,
    name AS 'Название',
    category AS 'Категория',
    LEFT(description, 200) AS 'Описание'
FROM modx_site_snippets
WHERE name IN ('knowledgeAreasManager', 'testRunner')
ORDER BY name;

-- 8. ДОБАВЛЕНИЕ НЕДОСТАЮЩЕГО ПОЛЯ (если нужно)
-- ВЫПОЛНИТЕ ТОЛЬКО ЕСЛИ В DESCRIBE НЕ ВИДНО ПОЛЯ min_questions_per_test
ALTER TABLE modx_test_knowledge_areas
ADD COLUMN IF NOT EXISTS `min_questions_per_test` INT(11) DEFAULT 3
COMMENT 'Минимальное количество вопросов из каждого теста'
AFTER `question_distribution_mode`;

-- 9. ПРОВЕРКА ПОСЛЕ ДОБАВЛЕНИЯ ПОЛЯ
SHOW COLUMNS FROM modx_test_knowledge_areas LIKE '%question%';
