-- ============================================================================
-- ДЕТАЛЬНЫЙ АНАЛИЗ СТРУКТУРЫ И ДАННЫХ MODX REVO + LMS SYSTEM
-- ============================================================================
-- Этот файл содержит подробные SQL запросы для анализа БД структуры,
-- типов данных, связей и примеров использования
-- ============================================================================

-- ============================================================================
-- РАЗДЕЛ 1: ИНФОРМАЦИЯ О РЕСУРСАХ (PAGES) И СНИППЕТАХ
-- ============================================================================

-- Получить все основные ресурсы (страницы) на сайте
SELECT
    r.id as 'Resource ID',
    r.pagetitle as 'Title',
    r.alias as 'URL Alias',
    r.parent as 'Parent ID',
    r.published as 'Published',
    r.publishedon as 'Published Date',
    r.createdon as 'Created Date',
    LENGTH(r.content) as 'Content Length'
FROM `modx_site_content` r
WHERE r.deleted = 0
ORDER BY r.id ASC
LIMIT 20;

-- Получить контент каждого ресурса с примерами сниппетов
SELECT
    id,
    pagetitle,
    alias,
    SUBSTRING(content, 1, 500) as 'Content Preview',
    content as 'Full Content'
FROM `modx_site_content`
WHERE content LIKE '%[[%]]%'
  AND published = 1
  AND deleted = 0
LIMIT 10;

-- Найти и перечислить все сниппеты используемые в контенте
SELECT DISTINCT
    -- Извлечение имени сниппета из [[snippetName]] или [[snippetName?params]]
    REPLACE(
        REPLACE(
            SUBSTRING(
                content,
                POSITION('[[') + 2,
                POSITION(']]') - POSITION('[[') - 2
            ),
            '?',
            ' ?'
        ),
        ' ',
        ''
    ) as snippet_name
FROM `modx_site_content`
WHERE content LIKE '%[[%'
  AND deleted = 0
LIMIT 30;

-- ============================================================================
-- РАЗДЕЛ 2: ИНФОРМАЦИЯ О ПОЛЬЗОВАТЕЛЯХ И РОЛЯХ
-- ============================================================================

-- Таблица пользователей со связями
SHOW CREATE TABLE `modx_users`;

-- Структура таблицы ролей
SHOW CREATE TABLE `modx_member_groups`;
SHOW CREATE TABLE `modx_member_group_names`;

-- Получить информацию о пользователях
SELECT
    u.id,
    u.username,
    u.email,
    u.active,
    u.createdon,
    GROUP_CONCAT(mgn.name SEPARATOR ', ') as 'User Roles'
FROM `modx_users` u
LEFT JOIN `modx_member_group_member` mgm ON u.id = mgm.member
LEFT JOIN `modx_member_group_names` mgn ON mgm.member_group = mgn.id
GROUP BY u.id
LIMIT 15;

-- ============================================================================
-- РАЗДЕЛ 3: ТАБЛИЦЫ LMS СИСТЕМЫ - ПОЛНАЯ СТРУКТУРА
-- ============================================================================

-- 3.1 ТАБЛИЦА: modx_test_categories
SHOW CREATE TABLE `modx_test_categories`;
DESC `modx_test_categories`;
SELECT * FROM `modx_test_categories` LIMIT 10;

-- Примеры: Как категории используются
SELECT
    COUNT(*) as category_count,
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_categories,
    SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_categories
FROM `modx_test_categories`;

-- 3.2 ТАБЛИЦА: modx_test_tests
SHOW CREATE TABLE `modx_test_tests`;
DESC `modx_test_tests`;
SELECT * FROM `modx_test_tests` LIMIT 10;

-- Примеры: Статистика по тестам
SELECT
    t.id,
    t.name as 'Test Name',
    tc.name as 'Category',
    COUNT(DISTINCT q.id) as 'Question Count',
    t.is_active,
    t.created_at
FROM `modx_test_tests` t
LEFT JOIN `modx_test_categories` tc ON t.category_id = tc.id
LEFT JOIN `modx_test_questions` q ON t.id = q.test_id
GROUP BY t.id
LIMIT 15;

-- 3.3 ТАБЛИЦА: modx_test_questions
SHOW CREATE TABLE `modx_test_questions`;
DESC `modx_test_questions`;
SELECT * FROM `modx_test_questions` LIMIT 10;

-- Примеры: Типы вопросов и их распределение
SELECT
    question_type,
    COUNT(*) as 'Count',
    AVG(difficulty_level) as 'Avg Difficulty'
FROM `modx_test_questions`
GROUP BY question_type;

-- 3.4 ТАБЛИЦА: modx_test_answers
SHOW CREATE TABLE `modx_test_answers`;
DESC `modx_test_answers`;
SELECT * FROM `modx_test_answers` LIMIT 10;

-- Примеры: Как ответы привязаны к вопросам
SELECT
    q.id,
    q.question_text,
    COUNT(a.id) as 'Answer Count',
    SUM(CASE WHEN a.is_correct = 1 THEN 1 ELSE 0 END) as 'Correct Answers'
FROM `modx_test_questions` q
LEFT JOIN `modx_test_answers` a ON q.id = a.question_id
GROUP BY q.id
LIMIT 10;

-- 3.5 ТАБЛИЦА: modx_test_sessions
SHOW CREATE TABLE `modx_test_sessions`;
DESC `modx_test_sessions`;
SELECT * FROM `modx_test_sessions` LIMIT 10;

-- Примеры: Активные сессии тестирования
SELECT
    id,
    user_id,
    test_id,
    session_status,
    start_time,
    end_time,
    score,
    TIMESTAMPDIFF(MINUTE, start_time, COALESCE(end_time, NOW())) as 'Duration Minutes'
FROM `modx_test_sessions`
WHERE session_status IN ('ACTIVE', 'COMPLETED')
ORDER BY start_time DESC
LIMIT 15;

-- 3.6 ТАБЛИЦА: modx_test_user_answers
SHOW CREATE TABLE `modx_test_user_answers`;
DESC `modx_test_user_answers`;
SELECT * FROM `modx_test_user_answers` LIMIT 10;

-- 3.7 ТАБЛИЦА: modx_test_learning_materials
SHOW CREATE TABLE `modx_test_learning_materials`;
DESC `modx_test_learning_materials`;
SELECT * FROM `modx_test_learning_materials` LIMIT 10;

-- Примеры: Материалы и их привязка к тестам
SELECT
    m.id,
    m.title,
    m.type,
    COUNT(DISTINCT mtl.test_id) as 'Linked Tests',
    m.created_at
FROM `modx_test_learning_materials` m
LEFT JOIN `modx_test_material_test_links` mtl ON m.id = mtl.material_id
GROUP BY m.id
LIMIT 10;

-- 3.8 ТАБЛИЦА: modx_test_learning_paths
SHOW CREATE TABLE `modx_test_learning_paths`;
DESC `modx_test_learning_paths`;
SELECT * FROM `modx_test_learning_paths` LIMIT 10;

-- 3.9 ТАБЛИЦА: modx_test_leaderboard
SHOW CREATE TABLE `modx_test_leaderboard`;
DESC `modx_test_leaderboard`;
SELECT * FROM `modx_test_leaderboard` LIMIT 10;

-- 3.10 ТАБЛИЦА: modx_test_achievements
SHOW CREATE TABLE `modx_test_achievements`;
DESC `modx_test_achievements`;
SELECT * FROM `modx_test_achievements` LIMIT 10;

-- 3.11 ТАБЛИЦА: modx_test_certificates
SHOW CREATE TABLE `modx_test_certificates`;
DESC `modx_test_certificates`;
SELECT * FROM `modx_test_certificates` LIMIT 10;

-- ============================================================================
-- РАЗДЕЛ 4: СВЯЗИ МЕЖДУ ТАБЛИЦАМИ (RELATIONSHIPS)
-- ============================================================================

-- Получить все FOREIGN KEY (внешние ключи)
SELECT
    CONSTRAINT_NAME,
    TABLE_NAME,
    COLUMN_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE()
  AND REFERENCED_TABLE_NAME IS NOT NULL
ORDER BY TABLE_NAME;

-- ============================================================================
-- РАЗДЕЛ 5: АНАЛИЗ ИНДЕКСОВ И ПРОИЗВОДИТЕЛЬНОСТИ
-- ============================================================================

-- Получить информацию об индексах для каждой таблицы
SELECT
    TABLE_NAME,
    INDEX_NAME,
    SEQ_IN_INDEX,
    COLUMN_NAME,
    CARDINALITY
FROM INFORMATION_SCHEMA.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME LIKE 'modx_test_%'
ORDER BY TABLE_NAME, INDEX_NAME;

-- ============================================================================
-- РАЗДЕЛ 6: АНАЛИЗ РАЗМЕРА И ИСПОЛЬЗОВАНИЯ БД
-- ============================================================================

-- Получить размер каждой таблицы
SELECT
    TABLE_NAME,
    ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) as 'Size_MB',
    TABLE_ROWS as 'Rows',
    ENGINE,
    ROUND(DATA_LENGTH / 1024 / 1024, 2) as 'Data_MB',
    ROUND(INDEX_LENGTH / 1024 / 1024, 2) as 'Index_MB'
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = DATABASE()
ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC;

-- ============================================================================
-- РАЗДЕЛ 7: ПРИМЕРЫ РЕАЛЬНЫХ ЗАПРОСОВ КОТОРЫЕ ПРИЛОЖЕНИЕ ВЫПОЛНЯЕТ
-- ============================================================================

-- Получить все тесты с полной информацией
SELECT
    t.id,
    t.name,
    tc.name as category,
    COUNT(DISTINCT q.id) as question_count,
    COUNT(DISTINCT s.id) as session_count,
    AVG(s.score) as avg_score
FROM modx_test_tests t
LEFT JOIN modx_test_categories tc ON t.category_id = tc.id
LEFT JOIN modx_test_questions q ON t.id = q.test_id
LEFT JOIN modx_test_sessions s ON t.id = s.test_id
GROUP BY t.id;

-- Получить результаты пользователя по всем тестам
SELECT
    ts.user_id,
    t.name as test_name,
    ts.score,
    ts.session_status,
    ts.start_time,
    ts.end_time,
    TIMESTAMPDIFF(MINUTE, ts.start_time, ts.end_time) as duration_minutes
FROM modx_test_sessions ts
JOIN modx_test_tests t ON ts.test_id = t.id
WHERE ts.user_id = 1  -- Замените на нужный ID пользователя
ORDER BY ts.start_time DESC
LIMIT 20;

-- Получить прогресс пользователя по пути обучения
SELECT
    lpe.user_id,
    lp.name as path_name,
    lpe.enrollment_date,
    lpe.completion_date,
    lpe.progress_percentage,
    COUNT(DISTINCT lpp.step_id) as completed_steps
FROM modx_test_learning_path_enrollments lpe
JOIN modx_test_learning_paths lp ON lpe.path_id = lp.id
LEFT JOIN modx_test_learning_path_progress lpp ON lpe.id = lpp.enrollment_id
GROUP BY lpe.id;

-- ============================================================================
-- РАЗДЕЛ 8: ПОЛНЫЙ СПИСОК ВСЕх ТАБЛИЦ В БД
-- ============================================================================

-- Получить список всех таблиц с количеством записей
SELECT
    TABLE_NAME,
    TABLE_ROWS,
    ENGINE,
    TABLE_COLLATION,
    ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) as 'Size_MB'
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = DATABASE()
ORDER BY TABLE_NAME;

-- ============================================================================
-- РАЗДЕЛ 9: ПАРАМЕТРЫ ЭКСПОРТА
-- ============================================================================

-- Для экспорта структуры всех таблиц выполните:
-- mysqldump -u username -p --no-data database_name > structure.sql

-- Для экспорта полных данных:
-- mysqldump -u username -p database_name > full_backup.sql

-- Для экспорта с усиленной информацией:
-- mysqldump -u username -p --complete-insert --no-tablespaces database_name > backup.sql

-- Для восстановления:
-- mysql -u username -p database_name < backup.sql
