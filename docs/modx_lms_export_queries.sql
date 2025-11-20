-- ============================================================================
-- SQL ЗАПРОСЫ ДЛЯ ЭКСПОРТА СТРУКТУРЫ И ДАННЫХ МОДX REVO LMS
-- ============================================================================
-- Используйте эти запросы в phpMyAdmin или консоли MySQL
-- для получения полной информации о базе данных

-- ============================================================================
-- ЧАСТЬ 1: СТРУКТУРА ТАБЛИЦ MODX (СТАНДАРТНЫЕ)
-- ============================================================================

-- Экспортировать структуру таблицы modx_site_content (ресурсы)
SHOW CREATE TABLE `modx_site_content`;

-- Экспортировать структуру таблицы modx_users
SHOW CREATE TABLE `modx_users`;

-- Экспортировать структуру таблицы modx_member_groups (роли)
SHOW CREATE TABLE `modx_member_groups`;

-- Экспортировать структуру таблицы modx_member_group_names
SHOW CREATE TABLE `modx_member_group_names`;

-- Получить информацию о пользователях (первые 10)
SELECT * FROM `modx_users` LIMIT 10;

-- Получить информацию о ролях
SELECT * FROM `modx_member_group_names`;

-- ============================================================================
-- ЧАСТЬ 2: СТРУКТУРА ТАБЛИЦ LMS СИСТЕМА (ОСНОВНЫЕ)
-- ============================================================================

-- Получить информацию о ВСЕХ таблицах в БД
SELECT TABLE_NAME, TABLE_TYPE, ENGINE, TABLE_ROWS
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
ORDER BY TABLE_NAME;

-- Получить подробную информацию о структуре каждой LMS таблицы
-- Категории тестов
SHOW CREATE TABLE `modx_test_categories`;
SELECT * FROM `modx_test_categories` LIMIT 10;

-- Тесты
SHOW CREATE TABLE `modx_test_tests`;
SELECT * FROM `modx_test_tests` LIMIT 10;

-- Вопросы
SHOW CREATE TABLE `modx_test_questions`;
SELECT * FROM `modx_test_questions` LIMIT 10;

-- Ответы
SHOW CREATE TABLE `modx_test_answers`;
SELECT * FROM `modx_test_answers` LIMIT 10;

-- Сессии тестирования
SHOW CREATE TABLE `modx_test_sessions`;
SELECT * FROM `modx_test_sessions` LIMIT 10;

-- Ответы пользователей
SHOW CREATE TABLE `modx_test_user_answers`;
SELECT * FROM `modx_test_user_answers` LIMIT 10;

-- Учебные материалы
SHOW CREATE TABLE `modx_test_learning_materials`;
SELECT * FROM `modx_test_learning_materials` LIMIT 10;

-- Пути обучения
SHOW CREATE TABLE `modx_test_learning_paths`;
SELECT * FROM `modx_test_learning_paths` LIMIT 10;

-- Таблица лидеров
SHOW CREATE TABLE `modx_test_leaderboard`;
SELECT * FROM `modx_test_leaderboard` LIMIT 10;

-- Достижения
SHOW CREATE TABLE `modx_test_achievements`;
SELECT * FROM `modx_test_achievements` LIMIT 10;

-- Сертификаты
SHOW CREATE TABLE `modx_test_certificates`;
SELECT * FROM `modx_test_certificates` LIMIT 10;

-- ============================================================================
-- ЧАСТЬ 3: ЭКСПОРТ ПОЛНЫХ ДАННЫХ ДЛЯ ДОКУМЕНТИРОВАНИЯ
-- ============================================================================

-- СКРИПТ ДЛЯ ЭКСПОРТА СТРУКТУРЫ ВСЕХ ТАБЛИЦ В ОДИН ФАЙЛ:
-- Выполните эту команду в консоли (ВНЕ phpMyAdmin):
--
-- mysqldump -u root -p --no-data your_database_name > /tmp/database_structure.sql
--
-- Для экспорта структуры + первые 100 записей каждой таблицы:
--
-- mysqldump -u root -p your_database_name | head -5000 > /tmp/database_partial.sql

-- ============================================================================
-- ЧАСТЬ 4: ИНФОРМАЦИОННЫЕ ЗАПРОСЫ О ТАБЛИЦАХ
-- ============================================================================

-- Получить список всех таблиц с количеством записей
SELECT
    TABLE_NAME,
    TABLE_ROWS,
    ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) AS 'Size_MB',
    TABLE_COLLATION,
    ENGINE
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
ORDER BY TABLE_ROWS DESC;

-- Получить структуру всех колонок всех таблиц
SELECT
    TABLE_NAME,
    COLUMN_NAME,
    ORDINAL_POSITION,
    COLUMN_DEFAULT,
    IS_NULLABLE,
    COLUMN_TYPE,
    COLUMN_KEY,
    EXTRA
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
ORDER BY TABLE_NAME, ORDINAL_POSITION;

-- Получить информацию об индексах
SELECT
    TABLE_NAME,
    INDEX_NAME,
    COLUMN_NAME,
    SEQ_IN_INDEX
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
ORDER BY TABLE_NAME, INDEX_NAME;

-- Получить информацию об ограничениях (FOREIGN KEYS)
SELECT
    CONSTRAINT_NAME,
    TABLE_NAME,
    COLUMN_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE()
AND REFERENCED_TABLE_NAME IS NOT NULL;

-- ============================================================================
-- ЧАСТЬ 5: ПРИМЕРЫ СНИППЕТОВ/СКРИПТОВ КОТОРЫЕ ВЫЗЫВАЮТСЯ
-- ============================================================================

-- Получить список ресурсов (страниц) на сайте и какие сниппеты в них используются
SELECT
    r.id,
    r.pagetitle,
    r.alias,
    r.content
FROM `modx_site_content` r
WHERE r.published = 1
LIMIT 20;

-- Поиск сниппетов в контенте (названия в формате [[snippetName]])
SELECT
    id,
    pagetitle,
    SUBSTRING_INDEX(SUBSTRING(content, POSITION('[[' IN content) + 2, 100), ']]', 1) as snippet_name
FROM `modx_site_content`
WHERE content LIKE '%[[%'
LIMIT 20;

-- ============================================================================
-- ЧАСТЬ 6: ПРОВЕРКА ЦЕЛОСТНОСТИ ДАННЫХ
-- ============================================================================

-- Проверить наличие пользователей в системе
SELECT COUNT(*) as total_users,
       COUNT(DISTINCT id) as unique_users
FROM `modx_users`;

-- Проверить количество тестов в системе
SELECT COUNT(*) as total_tests FROM `modx_test_tests`;

-- Проверить количество вопросов в системе
SELECT COUNT(*) as total_questions FROM `modx_test_questions`;

-- Проверить количество учебных материалов
SELECT COUNT(*) as total_materials FROM `modx_test_learning_materials`;

-- Получить статистику по категориям
SELECT
    tc.id,
    tc.name,
    COUNT(DISTINCT tt.id) as test_count,
    COUNT(DISTINCT tq.id) as question_count
FROM `modx_test_categories` tc
LEFT JOIN `modx_test_tests` tt ON tc.id = tt.category_id
LEFT JOIN `modx_test_questions` tq ON tt.id = tq.test_id
GROUP BY tc.id, tc.name;

-- ============================================================================
-- ЧАСТЬ 7: ПОЛНЫЙ ДАМП БД С ОГРАНИЧЕНИЯМИ
-- ============================================================================

-- Команда для создания полного дампа базы данных:
-- mysqldump -u username -p database_name > /path/to/database_backup.sql

-- Команда для дампа только структуры (без данных):
-- mysqldump -u username -p --no-data database_name > /path/to/database_structure.sql

-- Команда для дампа структуры + примеры данных:
-- mysqldump -u username -p --complete-insert database_name > /path/to/database_with_data.sql

-- Для восстановления из дампа:
-- mysql -u username -p database_name < /path/to/database_backup.sql
