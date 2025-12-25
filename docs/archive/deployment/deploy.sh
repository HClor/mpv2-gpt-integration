#!/bin/bash

##############################################################################
# MODX Test System - Автоматизированный скрипт развертывания
# Версия: 2.0
# Дата: 2025-11-17
##############################################################################

set -e  # Остановиться при ошибке

# Цвета для вывода
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Функции для вывода
print_header() {
    echo -e "\n${BLUE}========================================${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}========================================${NC}\n"
}

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

print_info() {
    echo -e "${BLUE}ℹ $1${NC}"
}

##############################################################################
# 1. ПРОВЕРКА ОКРУЖЕНИЯ
##############################################################################

print_header "Шаг 1: Проверка окружения"

# Проверка PHP
if ! command -v php &> /dev/null; then
    print_error "PHP не установлен!"
    exit 1
fi

PHP_VERSION=$(php -r "echo PHP_VERSION;")
print_success "PHP версия: $PHP_VERSION"

# Проверка MySQL
if ! command -v mysql &> /dev/null; then
    print_warning "MySQL клиент не установлен. Установите mysql-client для автоматической установки БД."
    MYSQL_AVAILABLE=0
else
    MYSQL_VERSION=$(mysql --version | awk '{print $5}' | sed 's/,//')
    print_success "MySQL версия: $MYSQL_VERSION"
    MYSQL_AVAILABLE=1
fi

# Проверка необходимых расширений PHP
print_info "Проверка PHP расширений..."
REQUIRED_EXTENSIONS=("pdo" "pdo_mysql" "mysqli" "json" "mbstring")
for ext in "${REQUIRED_EXTENSIONS[@]}"; do
    if php -m | grep -q "^$ext$"; then
        print_success "$ext установлен"
    else
        print_error "$ext не установлен!"
        exit 1
    fi
done

##############################################################################
# 2. ПОЛУЧЕНИЕ ПАРАМЕТРОВ
##############################################################################

print_header "Шаг 2: Настройка параметров"

# MODX путь
read -p "Путь к директории MODX [/var/www/html]: " MODX_PATH
MODX_PATH=${MODX_PATH:-/var/www/html}

if [ ! -d "$MODX_PATH" ]; then
    print_error "Директория MODX не существует: $MODX_PATH"
    exit 1
fi

print_success "Путь к MODX: $MODX_PATH"

# База данных
read -p "Установить/обновить базу данных? (y/n) [y]: " INSTALL_DB
INSTALL_DB=${INSTALL_DB:-y}

if [ "$INSTALL_DB" = "y" ] && [ $MYSQL_AVAILABLE -eq 1 ]; then
    read -p "Имя базы данных: " DB_NAME
    read -p "Пользователь базы данных: " DB_USER
    read -sp "Пароль базы данных: " DB_PASS
    echo
    read -p "Хост базы данных [localhost]: " DB_HOST
    DB_HOST=${DB_HOST:-localhost}

    read -p "Использовать полную установку (FULL) или инкрементальную (INCREMENTAL)? [FULL]: " SQL_TYPE
    SQL_TYPE=${SQL_TYPE:-FULL}
fi

# Владелец файлов
read -p "Владелец файлов (пользователь веб-сервера) [www-data]: " WEB_USER
WEB_USER=${WEB_USER:-www-data}

##############################################################################
# 3. КОПИРОВАНИЕ ФАЙЛОВ
##############################################################################

print_header "Шаг 3: Копирование файлов"

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# Создать резервную копию если уже установлено
if [ -d "$MODX_PATH/core/components/testsystem" ]; then
    BACKUP_DIR="$MODX_PATH/backups/testsystem_$(date +%Y%m%d_%H%M%S)"
    print_warning "Обнаружена существующая установка. Создаю резервную копию..."
    mkdir -p "$BACKUP_DIR"
    cp -r "$MODX_PATH/core/components/testsystem" "$BACKUP_DIR/"
    cp -r "$MODX_PATH/assets/components/testsystem" "$BACKUP_DIR/"
    print_success "Резервная копия создана: $BACKUP_DIR"
fi

# Копирование core компонентов
print_info "Копирование core компонентов..."
mkdir -p "$MODX_PATH/core/components/"
cp -r "$SCRIPT_DIR/core/components/testsystem" "$MODX_PATH/core/components/"
print_success "Core компоненты скопированы"

# Копирование assets компонентов
print_info "Копирование assets компонентов..."
mkdir -p "$MODX_PATH/assets/components/"
cp -r "$SCRIPT_DIR/assets/components/testsystem" "$MODX_PATH/assets/components/"
print_success "Assets компоненты скопированы"

##############################################################################
# 4. УСТАНОВКА ПРАВ ДОСТУПА
##############################################################################

print_header "Шаг 4: Установка прав доступа"

# Права на файлы
print_info "Установка прав на файлы..."
find "$MODX_PATH/core/components/testsystem" -type f -exec chmod 644 {} \; 2>/dev/null || true
find "$MODX_PATH/core/components/testsystem" -type d -exec chmod 755 {} \; 2>/dev/null || true
find "$MODX_PATH/assets/components/testsystem" -type f -exec chmod 644 {} \; 2>/dev/null || true
find "$MODX_PATH/assets/components/testsystem" -type d -exec chmod 755 {} \; 2>/dev/null || true

# Специальные права на PHP файлы
chmod 755 "$MODX_PATH/assets/components/testsystem/ajax/testsystem.php" 2>/dev/null || true

# Создать директории для записи
print_info "Создание директорий для отчетов и сертификатов..."
mkdir -p "$MODX_PATH/assets/components/testsystem/reports"
mkdir -p "$MODX_PATH/assets/components/testsystem/certificates"
chmod 775 "$MODX_PATH/assets/components/testsystem/reports"
chmod 775 "$MODX_PATH/assets/components/testsystem/certificates"

# Установка владельца
print_info "Установка владельца файлов..."
if id "$WEB_USER" &>/dev/null; then
    chown -R "$WEB_USER:$WEB_USER" "$MODX_PATH/core/components/testsystem" 2>/dev/null || print_warning "Не удалось изменить владельца (нужны права root)"
    chown -R "$WEB_USER:$WEB_USER" "$MODX_PATH/assets/components/testsystem" 2>/dev/null || print_warning "Не удалось изменить владельца (нужны права root)"
    print_success "Владелец установлен: $WEB_USER"
else
    print_warning "Пользователь $WEB_USER не найден. Пропускаю установку владельца."
fi

##############################################################################
# 5. УСТАНОВКА БАЗЫ ДАННЫХ
##############################################################################

if [ "$INSTALL_DB" = "y" ] && [ $MYSQL_AVAILABLE -eq 1 ]; then
    print_header "Шаг 5: Установка базы данных"

    # Проверка подключения
    print_info "Проверка подключения к БД..."
    if mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -e "USE $DB_NAME;" 2>/dev/null; then
        print_success "Подключение к БД успешно"
    else
        print_error "Не удалось подключиться к БД. Проверьте параметры."
        exit 1
    fi

    # Выполнение SQL
    if [ "$SQL_TYPE" = "FULL" ]; then
        SQL_FILE="$MODX_PATH/core/components/testsystem/sql/FULL_INSTALLATION.sql"
        print_info "Выполнение полной установки БД..."
    else
        SQL_FILE="$MODX_PATH/core/components/testsystem/sql/INCREMENTAL_INSTALLATION.sql"
        print_info "Выполнение инкрементального обновления БД..."
    fi

    if [ ! -f "$SQL_FILE" ]; then
        print_error "SQL файл не найден: $SQL_FILE"
        exit 1
    fi

    mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$SQL_FILE"

    if [ $? -eq 0 ]; then
        print_success "База данных успешно установлена/обновлена"

        # Проверка созданных таблиц
        TABLE_COUNT=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -N -e "SHOW TABLES LIKE 'modx_test%';" | wc -l)
        print_success "Создано/обновлено таблиц: $TABLE_COUNT"
    else
        print_error "Ошибка при установке БД"
        exit 1
    fi
else
    print_header "Шаг 5: Установка базы данных - ПРОПУЩЕНО"
    print_warning "Установка БД пропущена. Выполните SQL миграции вручную:"
    print_info "mysql -u user -p database < $MODX_PATH/core/components/testsystem/sql/FULL_INSTALLATION.sql"
fi

##############################################################################
# 6. ПРОВЕРКА УСТАНОВКИ
##############################################################################

print_header "Шаг 6: Проверка установки"

# Проверка файлов
print_info "Проверка файловой структуры..."
REQUIRED_FILES=(
    "$MODX_PATH/core/components/testsystem/bootstrap.php"
    "$MODX_PATH/core/components/testsystem/services/TestService.php"
    "$MODX_PATH/assets/components/testsystem/ajax/testsystem.php"
    "$MODX_PATH/assets/components/testsystem/controllers/BaseController.php"
)

ALL_FILES_OK=1
for file in "${REQUIRED_FILES[@]}"; do
    if [ -f "$file" ]; then
        print_success "$(basename $file) найден"
    else
        print_error "$(basename $file) не найден"
        ALL_FILES_OK=0
    fi
done

if [ $ALL_FILES_OK -eq 1 ]; then
    print_success "Все необходимые файлы на месте"
else
    print_error "Некоторые файлы отсутствуют!"
    exit 1
fi

# Подсчет компонентов
SERVICE_COUNT=$(find "$MODX_PATH/core/components/testsystem/services" -name "*.php" 2>/dev/null | wc -l)
CONTROLLER_COUNT=$(find "$MODX_PATH/assets/components/testsystem/controllers" -name "*.php" 2>/dev/null | wc -l)

print_info "Установлено сервисов: $SERVICE_COUNT"
print_info "Установлено контроллеров: $CONTROLLER_COUNT"

##############################################################################
# 7. ФИНАЛЬНЫЕ ИНСТРУКЦИИ
##############################################################################

print_header "Установка завершена успешно!"

echo -e "\n${GREEN}✓✓✓ Система тестирования успешно установлена! ✓✓✓${NC}\n"

print_info "Следующие шаги:"
echo "1. Проверьте API endpoint:"
echo "   curl http://your-domain.com/assets/components/testsystem/ajax/testsystem.php"
echo ""
echo "2. Настройте cron задачи для обслуживания:"
echo "   crontab -e"
echo "   0 3 * * * curl -X POST http://your-domain.com/assets/components/testsystem/ajax/testsystem.php -d '{\"action\":\"cleanOldSessions\",\"data\":{\"days\":30}}'"
echo ""
echo "3. Документация:"
echo "   - Полная документация: $MODX_PATH/core/components/testsystem/README.md"
echo "   - API документация: $MODX_PATH/core/components/testsystem/API_ENDPOINTS.md"
echo "   - Инструкции: $MODX_PATH/core/components/testsystem/DEPLOYMENT.md"
echo ""

if [ "$INSTALL_DB" != "y" ] || [ $MYSQL_AVAILABLE -eq 0 ]; then
    print_warning "НЕ ЗАБУДЬТЕ выполнить SQL миграции вручную!"
    echo "   mysql -u $DB_USER -p $DB_NAME < $MODX_PATH/core/components/testsystem/sql/FULL_INSTALLATION.sql"
    echo ""
fi

print_success "Логи установки сохранены в: /tmp/testsystem_deploy_$(date +%Y%m%d).log"

echo -e "\n${BLUE}Спасибо за использование MODX Test System!${NC}\n"
