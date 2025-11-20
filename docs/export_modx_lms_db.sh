#!/bin/bash
# ============================================================================
# СКРИПТ ДЛЯ ЭКСПОРТА СТРУКТУРЫ И ДАННЫХ MODX REVO LMS БЕЗ СОЕДИНЕНИЯ
# ============================================================================
# Использование:
#   ./export_modx_lms_db.sh <DB_NAME> <DB_USER> <DB_PASSWORD> [DB_HOST]
#
# Примеры:
#   ./export_modx_lms_db.sh modx root password localhost
#   ./export_modx_lms_db.sh modx root password 192.168.1.1
#
# Результаты будут сохранены в текущую директорию:
#   - database_structure.sql        (только структура)
#   - database_with_data.sql        (структура + данные)
#   - table_descriptions.txt        (описание таблиц)
#   - sample_data.txt               (примеры данных)
# ============================================================================

# Проверка параметров
if [ $# -lt 3 ]; then
    echo "Использование: $0 <DB_NAME> <DB_USER> <DB_PASSWORD> [DB_HOST]"
    echo ""
    echo "Примеры:"
    echo "  $0 modx root password localhost"
    echo "  $0 modx root password 192.168.1.1"
    exit 1
fi

DB_NAME="$1"
DB_USER="$2"
DB_PASSWORD="$3"
DB_HOST="${4:-localhost}"

echo "=========================================="
echo "ЭКСПОРТ MODX REVO LMS DATABASE"
echo "=========================================="
echo "Database: $DB_NAME"
echo "User: $DB_USER"
echo "Host: $DB_HOST"
echo "=========================================="

# Дамп только структуры
echo ""
echo "[1/4] Экспортирование структуры БД (без данных)..."
mysqldump -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" --no-data "$DB_NAME" > database_structure.sql 2>/dev/null
if [ $? -eq 0 ]; then
    echo "✓ Сохранено: database_structure.sql"
else
    echo "✗ Ошибка при экспорте структуры"
fi

# Дамп структуры + данные
echo ""
echo "[2/4] Экспортирование структуры + данные..."
mysqldump -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" > database_with_data.sql 2>/dev/null
if [ $? -eq 0 ]; then
    echo "✓ Сохранено: database_with_data.sql"
else
    echo "✗ Ошибка при экспорте с данными"
fi

# Получить описание таблиц
echo ""
echo "[3/4] Получение описания таблиц и их размеров..."
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" << 'EOF' > table_descriptions.txt 2>/dev/null
SELECT
    TABLE_NAME,
    TABLE_ROWS,
    ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) AS 'Size_MB',
    ENGINE,
    TABLE_COLLATION
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
ORDER BY TABLE_ROWS DESC;

-- Структура каждой таблицы
EOF

# Получить примеры данных из каждой таблицы
echo ""
echo "[4/4] Получение примеров данных из таблиц..."
cat > sample_data_queries.sql << 'EOF'
-- ПРИМЕРЫ ДАННЫХ ИЗ ОСНОВНЫХ ТАБЛИЦ

-- Категории
SELECT '=== CATEGORIES ===' as 'TABLE_NAME';
SELECT * FROM `modx_test_categories` LIMIT 5;

-- Тесты
SELECT '=== TESTS ===' as 'TABLE_NAME';
SELECT * FROM `modx_test_tests` LIMIT 5;

-- Вопросы
SELECT '=== QUESTIONS ===' as 'TABLE_NAME';
SELECT * FROM `modx_test_questions` LIMIT 5;

-- Ответы
SELECT '=== ANSWERS ===' as 'TABLE_NAME';
SELECT * FROM `modx_test_answers` LIMIT 5;

-- Сессии тестирования
SELECT '=== SESSIONS ===' as 'TABLE_NAME';
SELECT * FROM `modx_test_sessions` LIMIT 5;

-- Учебные материалы
SELECT '=== LEARNING MATERIALS ===' as 'TABLE_NAME';
SELECT * FROM `modx_test_learning_materials` LIMIT 5;

-- Пути обучения
SELECT '=== LEARNING PATHS ===' as 'TABLE_NAME';
SELECT * FROM `modx_test_learning_paths` LIMIT 5;

-- Пользователи MODX
SELECT '=== MODX USERS ===' as 'TABLE_NAME';
SELECT id, username, email FROM `modx_users` LIMIT 5;

-- Роли
SELECT '=== USER ROLES ===' as 'TABLE_NAME';
SELECT * FROM `modx_member_group_names` LIMIT 5;
EOF

mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" < sample_data_queries.sql > sample_data.txt 2>/dev/null

if [ -f sample_data.txt ]; then
    echo "✓ Сохранено: sample_data.txt"
fi

echo ""
echo "=========================================="
echo "ЭКСПОРТ ЗАВЕРШЕН"
echo "=========================================="
echo ""
echo "Созданные файлы:"
echo "  • database_structure.sql    - структура всех таблиц"
echo "  • database_with_data.sql    - структура + полные данные"
echo "  • table_descriptions.txt    - описание и размеры таблиц"
echo "  • sample_data.txt           - примеры данных из таблиц"
echo ""
echo "Использование дампов:"
echo "  mysql -u $DB_USER -p $DB_NAME < database_with_data.sql"
echo ""
