# Test System - Инструкция по развертыванию

Полное руководство по развертыванию системы на рабочем сервере с нуля.

**Версия:** 2.0
**Дата:** 2025-11-15

---

## Требования к серверу

### Минимальные требования:
- **PHP:** 7.4 или выше (рекомендуется PHP 8.0+)
- **MySQL:** 5.7 или выше (рекомендуется MySQL 8.0+)
- **MODX Revolution:** 2.8.0 или выше
- **Место на диске:** 100 MB для кода + достаточно для БД
- **RAM:** Минимум 2 GB (рекомендуется 4 GB+)

### Необходимые PHP расширения:
```bash
php -m | grep -E "pdo|mysqli|json|mbstring|openssl|zip"
```

Должны быть установлены:
- PDO
- pdo_mysql
- mysqli
- json
- mbstring
- openssl
- zip

---

## Шаг 1: Подготовка сервера

### 1.1. Проверка версий

```bash
# Проверить версию PHP
php -v

# Проверить версию MySQL
mysql --version

# Проверить расширения PHP
php -m
```

### 1.2. Создание директорий

```bash
# Перейти в директорию MODX
cd /path/to/your/modx/installation

# Убедиться что директории существуют
ls -la core/components/
ls -la assets/components/
```

---

## Шаг 2: Получение кода из Git

### 2.1. Клонирование репозитория (если репозиторий доступен)

```bash
# Клонировать репозиторий
git clone https://github.com/your-repo/mpv2-gpt-integration.git /tmp/testsystem

# Или скачать конкретную ветку
git clone -b claude/refactor-modx-learning-system-0112dzZP6buDp8vepGyaAqeD https://github.com/your-repo/mpv2-gpt-integration.git /tmp/testsystem
```

### 2.2. Копирование файлов на сервер

```bash
# Перейти в директорию MODX
cd /path/to/your/modx

# Скопировать core компоненты
cp -r /tmp/testsystem/core/components/testsystem ./core/components/

# Скопировать assets компоненты
cp -r /tmp/testsystem/assets/components/testsystem ./assets/components/

# Проверить что файлы скопированы
ls -la core/components/testsystem/
ls -la assets/components/testsystem/
```

### 2.3. Альтернативный метод: прямая загрузка через Git на сервере

```bash
# Если на сервере установлен git
cd /path/to/your/modx

# Инициализировать git (если еще не инициализирован)
git init

# Добавить remote репозиторий
git remote add origin https://github.com/your-repo/mpv2-gpt-integration.git

# Получить конкретную ветку
git fetch origin claude/refactor-modx-learning-system-0112dzZP6buDp8vepGyaAqeD

# Checkout нужных файлов
git checkout origin/claude/refactor-modx-learning-system-0112dzZP6buDp8vepGyaAqeD -- core/components/testsystem
git checkout origin/claude/refactor-modx-learning-system-0112dzZP6buDp8vepGyaAqeD -- assets/components/testsystem
```

### 2.4. Альтернативный метод: загрузка архива

Если Git недоступен, можно скачать архив:

```bash
# Скачать архив ветки (замените URL на ваш)
wget https://github.com/your-repo/mpv2-gpt-integration/archive/refs/heads/claude/refactor-modx-learning-system-0112dzZP6buDp8vepGyaAqeD.zip -O testsystem.zip

# Распаковать
unzip testsystem.zip

# Скопировать файлы
cd mpv2-gpt-integration-claude-refactor-modx-learning-system-0112dzZP6buDp8vepGyaAqeD
cp -r core/components/testsystem /path/to/modx/core/components/
cp -r assets/components/testsystem /path/to/modx/assets/components/

# Удалить временные файлы
cd ..
rm -rf mpv2-gpt-integration-* testsystem.zip
```

---

## Шаг 3: Установка прав доступа

### 3.1. Установить права на файлы

```bash
cd /path/to/your/modx

# Права на core компоненты (только чтение и выполнение)
find core/components/testsystem -type f -exec chmod 644 {} \;
find core/components/testsystem -type d -exec chmod 755 {} \;

# Права на assets компоненты
find assets/components/testsystem -type f -exec chmod 644 {} \;
find assets/components/testsystem -type d -exec chmod 755 {} \;

# Специальные права на PHP файлы контроллеров
chmod 755 assets/components/testsystem/ajax/testsystem.php
chmod 755 assets/components/testsystem/controllers/*.php

# Создать директорию для отчетов и установить права записи
mkdir -p assets/components/testsystem/reports
chmod 775 assets/components/testsystem/reports

# Создать директорию для сертификатов и установить права записи
mkdir -p assets/components/testsystem/certificates
chmod 775 assets/components/testsystem/certificates

# Установить владельца (замените www-data на вашего веб-пользователя)
chown -R www-data:www-data core/components/testsystem
chown -R www-data:www-data assets/components/testsystem
```

### 3.2. Проверка SELinux (для CentOS/RHEL)

```bash
# Если используется SELinux
setenforce 0  # Временно отключить для теста

# Или настроить контекст
chcon -R -t httpd_sys_rw_content_t assets/components/testsystem/reports
chcon -R -t httpd_sys_rw_content_t assets/components/testsystem/certificates
```

---

## Шаг 4: Создание базы данных (если нужно)

### 4.1. Вход в MySQL

```bash
# Войти в MySQL как root
mysql -u root -p
```

### 4.2. Создание БД и пользователя (опционально)

```sql
-- Создать БД (если еще нет)
CREATE DATABASE IF NOT EXISTS modx_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Создать пользователя (если нужен отдельный)
CREATE USER 'modx_user'@'localhost' IDENTIFIED BY 'strong_password_here';

-- Выдать права
GRANT ALL PRIVILEGES ON modx_db.* TO 'modx_user'@'localhost';

-- Применить изменения
FLUSH PRIVILEGES;

-- Выйти
EXIT;
```

---

## Шаг 5: Выполнение SQL миграций

### 5.1. Подготовка

```bash
# Перейти в директорию с SQL файлами
cd /path/to/modx/core/components/testsystem/sql

# Проверить наличие всех файлов
ls -la

# Должны быть следующие файлы (если нет - создать из FULL_INSTALLATION.sql):
# - learning_materials.sql
# - category_permissions.sql
# - learning_paths.sql
# - advanced_question_types.sql
# - gamification.sql
# - notifications.sql
# - analytics.sql
# - certificates.sql
```

### 5.2. Выполнение SQL миграций

**ВАЖНО:** Выполнять строго в указанном порядке!

```bash
# Войти в MySQL
mysql -u modx_user -p modx_db

# Или выполнить из командной строки:

# 1. Базовые таблицы (если есть отдельный файл base_schema.sql)
# mysql -u modx_user -p modx_db < base_schema.sql

# ИЛИ выполнить единый файл со всеми миграциями:
mysql -u modx_user -p modx_db < FULL_INSTALLATION.sql
```

### 5.3. Альтернативный метод: выполнение по одному файлу

Если выполняете файлы по отдельности:

```bash
cd /path/to/modx/core/components/testsystem/sql

# Выполнить каждый файл по порядку
mysql -u modx_user -p modx_db < learning_materials.sql
mysql -u modx_user -p modx_db < category_permissions.sql
mysql -u modx_user -p modx_db < learning_paths.sql
mysql -u modx_user -p modx_db < advanced_question_types.sql
mysql -u modx_user -p modx_db < gamification.sql
mysql -u modx_user -p modx_db < notifications.sql
mysql -u modx_user -p modx_db < analytics.sql
mysql -u modx_user -p modx_db < certificates.sql
```

### 5.4. Проверка успешной установки

```sql
-- Войти в MySQL
mysql -u modx_user -p modx_db

-- Проверить созданные таблицы
SHOW TABLES LIKE 'modx_test%';

-- Должно быть около 50+ таблиц

-- Проверить триггеры
SHOW TRIGGERS LIKE 'modx_test%';

-- Проверить stored procedures
SHOW PROCEDURE STATUS WHERE Db = 'modx_db' AND Name LIKE '%test%';

-- Проверить views
SHOW FULL TABLES WHERE Table_type = 'VIEW';

-- Выйти
EXIT;
```

---

## Шаг 6: Проверка установки

### 6.1. Проверка файловой структуры

```bash
# Проверить что все контроллеры на месте
ls -la assets/components/testsystem/controllers/

# Должны быть следующие файлы:
# - BaseController.php
# - SessionController.php
# - QuestionController.php
# - TestController.php
# - AdminController.php
# - FavoriteController.php
# - MaterialController.php
# - CategoryController.php
# - LearningPathController.php
# - SpecialQuestionController.php
# - GamificationController.php
# - NotificationController.php
# - AnalyticsController.php
# - CertificateController.php
# - ControllerFactory.php

# Проверить сервисы
ls -la core/components/testsystem/services/

# Должны быть:
# - DataIntegrityService.php
# - LearningMaterialService.php
# - CategoryPermissionService.php
# - LearningPathService.php
# - QuestionTypeService.php
# - GamificationService.php
# - NotificationService.php
# - AnalyticsService.php
# - ReportService.php
# - CertificateService.php
```

### 6.2. Проверка API endpoint

```bash
# Проверить что endpoint доступен
curl -X POST http://your-domain.com/assets/components/testsystem/ajax/testsystem.php \
  -H "Content-Type: application/json" \
  -d '{"action":"test"}'

# Должен вернуть JSON с ошибкой "Unknown action: test" - это нормально!
# Это означает что роутинг работает
```

### 6.3. Проверка через браузер

Откройте в браузере:
```
http://your-domain.com/assets/components/testsystem/ajax/testsystem.php
```

Должен вернуть:
```json
{
  "success": false,
  "message": "Invalid request"
}
```

Это нормально - endpoint работает!

---

## Шаг 7: Начальная конфигурация

### 7.1. Создание MODX плагина для обслуживания

```bash
# Войти в MODX Manager
# Перейти: Элементы -> Плагины -> Создать новый плагин
```

Настройки плагина:
- **Название:** TestSystemMaintenance
- **Описание:** Автоматическое обслуживание системы тестирования
- **События:** `OnBeforeCacheUpdate`

Код плагина:
```php
<?php
/**
 * Test System Maintenance Plugin
 *
 * События: OnBeforeCacheUpdate
 */

$corePath = $modx->getOption('core_path');
require_once $corePath . 'components/testsystem/services/DataIntegrityService.php';

switch ($modx->event->name) {
    case 'OnBeforeCacheUpdate':
        // Очистка старых сессий при обновлении кеша
        DataIntegrityService::cleanupOldSessions($modx, 30);

        // Опционально: проверка целостности данных
        // DataIntegrityService::checkIntegrity($modx);
        break;
}
```

### 7.2. Создание начального администратора

```sql
-- Войти в MySQL
mysql -u modx_user -p modx_db

-- Обновить роль существующего пользователя (замените 1 на ID вашего админа)
UPDATE modx_users SET role = 'admin' WHERE id = 1;

-- Или создать тестового пользователя
INSERT INTO modx_users (username, password, email, createdon)
VALUES ('testadmin', MD5('test123'), 'admin@example.com', UNIX_TIMESTAMP());

-- Получить ID созданного пользователя
SET @user_id = LAST_INSERT_ID();

-- Инициализировать профиль геймификации
INSERT INTO modx_test_user_experience (user_id, total_xp, current_level)
VALUES (@user_id, 0, 1);

EXIT;
```

### 7.3. Создание тестовых данных (опционально)

```sql
-- Войти в MySQL
mysql -u modx_user -p modx_db

-- Создать тестовую категорию
INSERT INTO modx_categories (category, parent) VALUES ('Test Category', 0);
SET @cat_id = LAST_INSERT_ID();

-- Создать тестовый тест
INSERT INTO modx_test_tests (test_name, category_id, time_limit, pass_score, published)
VALUES ('Тестовый тест', @cat_id, 30, 70, 1);
SET @test_id = LAST_INSERT_ID();

-- Создать тестовый вопрос
INSERT INTO modx_test_questions (test_id, question_text, question_type, points, published)
VALUES (@test_id, 'Сколько будет 2+2?', 'single', 1, 1);
SET @question_id = LAST_INSERT_ID();

-- Создать варианты ответов
INSERT INTO modx_test_answers (question_id, answer_text, is_correct, order_num)
VALUES
(@question_id, '3', 0, 1),
(@question_id, '4', 1, 2),
(@question_id, '5', 0, 3),
(@question_id, '6', 0, 4);

EXIT;
```

---

## Шаг 8: Настройка Cron задач

### 8.1. Создание cron скриптов

```bash
# Создать директорию для cron скриптов
mkdir -p /path/to/modx/core/components/testsystem/cron

# Создать скрипт для очистки старых сессий
cat > /path/to/modx/core/components/testsystem/cron/cleanup_sessions.sh <<'EOF'
#!/bin/bash
curl -X POST http://your-domain.com/assets/components/testsystem/ajax/testsystem.php \
  -H "Content-Type: application/json" \
  -d '{"action":"cleanOldSessions","data":{"days":30}}'
EOF

# Создать скрипт для обновления рейтингов
cat > /path/to/modx/core/components/testsystem/cron/update_leaderboard.sh <<'EOF'
#!/bin/bash
curl -X POST http://your-domain.com/assets/components/testsystem/ajax/testsystem.php \
  -H "Content-Type: application/json" \
  -d '{"action":"updateLeaderboard","data":{"period":"weekly"}}'
EOF

# Создать скрипт для обработки очереди уведомлений
cat > /path/to/modx/core/components/testsystem/cron/process_notifications.sh <<'EOF'
#!/bin/bash
curl -X POST http://your-domain.com/assets/components/testsystem/ajax/testsystem.php \
  -H "Content-Type: application/json" \
  -d '{"action":"processQueue","data":{"batch_size":100}}'
EOF

# Установить права на выполнение
chmod +x /path/to/modx/core/components/testsystem/cron/*.sh
```

### 8.2. Настройка crontab

```bash
# Открыть crontab для редактирования
crontab -e

# Добавить следующие строки:

# Очистка старых сессий (ежедневно в 3:00)
0 3 * * * /path/to/modx/core/components/testsystem/cron/cleanup_sessions.sh >> /var/log/testsystem_cleanup.log 2>&1

# Обновление рейтингов (еженедельно в воскресенье в 2:00)
0 2 * * 0 /path/to/modx/core/components/testsystem/cron/update_leaderboard.sh >> /var/log/testsystem_leaderboard.log 2>&1

# Обработка очереди уведомлений (каждые 5 минут)
*/5 * * * * /path/to/modx/core/components/testsystem/cron/process_notifications.sh >> /var/log/testsystem_notifications.log 2>&1

# Очистка кеша аналитики (ежедневно в 4:00)
0 4 * * * curl -X POST http://your-domain.com/assets/components/testsystem/ajax/testsystem.php -d '{"action":"cleanupCache"}' >> /var/log/testsystem_cache.log 2>&1
```

---

## Шаг 9: Тестирование установки

### 9.1. Тест API endpoints

```bash
# Тест получения профиля (требуется авторизация в MODX)
curl -X POST http://your-domain.com/assets/components/testsystem/ajax/testsystem.php \
  -H "Content-Type: application/json" \
  -b "PHPSESSID=your_session_id" \
  -d '{"action":"getMyProfile"}'

# Тест получения рейтинга (публичный endpoint)
curl -X POST http://your-domain.com/assets/components/testsystem/ajax/testsystem.php \
  -H "Content-Type: application/json" \
  -d '{"action":"getLeaderboard","data":{"period":"all_time","limit":10}}'

# Тест получения уровней (публичный endpoint)
curl -X POST http://your-domain.com/assets/components/testsystem/ajax/testsystem.php \
  -H "Content-Type: application/json" \
  -d '{"action":"getLevelStats"}'
```

### 9.2. Проверка логов

```bash
# Проверить логи PHP
tail -f /var/log/php-fpm/error.log
# или
tail -f /var/log/apache2/error.log
# или
tail -f /var/log/nginx/error.log

# Проверить логи MySQL
tail -f /var/log/mysql/error.log

# Проверить логи MODX
tail -f /path/to/modx/core/cache/logs/error.log
```

---

## Шаг 10: Настройка производительности

### 10.1. Оптимизация MySQL

```sql
-- Войти в MySQL
mysql -u root -p

-- Настроить InnoDB buffer pool (замените 512M на 25-50% от RAM)
SET GLOBAL innodb_buffer_pool_size = 536870912;

-- Включить медленные запросы
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 2;

-- Оптимизация таблиц
USE modx_db;
OPTIMIZE TABLE modx_test_sessions;
OPTIMIZE TABLE modx_test_user_answers;
OPTIMIZE TABLE modx_test_analytics_cache;

EXIT;
```

### 10.2. Настройка PHP

Отредактировать `/etc/php.ini` или `/etc/php/7.4/fpm/php.ini`:

```ini
memory_limit = 256M
max_execution_time = 300
post_max_size = 50M
upload_max_filesize = 50M
max_input_vars = 5000

# Для production отключить display_errors
display_errors = Off
log_errors = On
error_log = /var/log/php_errors.log
```

Перезапустить PHP:
```bash
# PHP-FPM
sudo systemctl restart php7.4-fpm

# Или Apache
sudo systemctl restart apache2
```

### 10.3. Кеширование MODX

В MODX Manager:
- Система -> Настройки системы
- Установить `cache_db = 1`
- Установить `cache_db_expires = 3600`

---

## Шаг 11: Безопасность

### 11.1. Настройка .htaccess (для Apache)

```bash
# Создать .htaccess в assets/components/testsystem/reports/
cat > /path/to/modx/assets/components/testsystem/reports/.htaccess <<'EOF'
# Запретить прямой доступ к отчетам
Order Deny,Allow
Deny from all
EOF

# То же для certificates
cat > /path/to/modx/assets/components/testsystem/certificates/.htaccess <<'EOF'
# Запретить прямой доступ к сертификатам
Order Deny,Allow
Deny from all
EOF
```

### 11.2. Настройка Nginx (альтернатива)

Добавить в конфигурацию Nginx:

```nginx
# Запретить доступ к служебным директориям
location ~ ^/assets/components/testsystem/(reports|certificates)/ {
    deny all;
    return 403;
}

# Разрешить только .php файлы в ajax/
location ~ ^/assets/components/testsystem/ajax/.*\.php$ {
    fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
}
```

### 11.3. SSL/TLS (настоятельно рекомендуется)

```bash
# Установить Let's Encrypt сертификат
sudo apt-get install certbot python3-certbot-apache

# Получить сертификат
sudo certbot --apache -d your-domain.com

# Автообновление
sudo certbot renew --dry-run
```

---

## Шаг 12: Мониторинг

### 12.1. Создать скрипт для проверки здоровья системы

```bash
cat > /path/to/modx/core/components/testsystem/cron/health_check.sh <<'EOF'
#!/bin/bash

# Проверка доступности endpoint
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://your-domain.com/assets/components/testsystem/ajax/testsystem.php)

if [ $HTTP_CODE -ne 200 ]; then
    echo "ERROR: Endpoint unavailable (HTTP $HTTP_CODE)"
    # Отправить уведомление администратору
    mail -s "Test System Down" admin@example.com <<< "Endpoint returned HTTP $HTTP_CODE"
    exit 1
fi

# Проверка базы данных
mysql -u modx_user -p'password' modx_db -e "SELECT COUNT(*) FROM modx_test_sessions;" > /dev/null 2>&1

if [ $? -ne 0 ]; then
    echo "ERROR: Database connection failed"
    mail -s "Test System DB Error" admin@example.com <<< "Database connection failed"
    exit 1
fi

echo "OK: System healthy"
exit 0
EOF

chmod +x /path/to/modx/core/components/testsystem/cron/health_check.sh
```

### 12.2. Добавить в crontab проверку каждые 5 минут

```bash
crontab -e

# Добавить:
*/5 * * * * /path/to/modx/core/components/testsystem/cron/health_check.sh >> /var/log/testsystem_health.log 2>&1
```

---

## Шаг 13: Бэкап

### 13.1. Создать скрипт бэкапа

```bash
cat > /path/to/modx/core/components/testsystem/cron/backup.sh <<'EOF'
#!/bin/bash

BACKUP_DIR="/backups/testsystem"
DATE=$(date +%Y%m%d_%H%M%S)
DB_NAME="modx_db"
DB_USER="modx_user"
DB_PASS="password"

# Создать директорию
mkdir -p $BACKUP_DIR

# Бэкап базы данных
mysqldump -u $DB_USER -p$DB_PASS $DB_NAME \
  --tables $(mysql -u $DB_USER -p$DB_PASS $DB_NAME -N -e "SHOW TABLES LIKE 'modx_test%'") \
  | gzip > $BACKUP_DIR/testsystem_db_$DATE.sql.gz

# Бэкап файлов
tar -czf $BACKUP_DIR/testsystem_files_$DATE.tar.gz \
  /path/to/modx/core/components/testsystem \
  /path/to/modx/assets/components/testsystem

# Удалить бэкапы старше 30 дней
find $BACKUP_DIR -name "testsystem_*" -mtime +30 -delete

echo "Backup completed: $DATE"
EOF

chmod +x /path/to/modx/core/components/testsystem/cron/backup.sh
```

### 13.2. Добавить в crontab (еженедельно)

```bash
crontab -e

# Добавить: каждое воскресенье в 1:00
0 1 * * 0 /path/to/modx/core/components/testsystem/cron/backup.sh >> /var/log/testsystem_backup.log 2>&1
```

---

## Troubleshooting

### Проблема: 500 Internal Server Error

**Решение:**
```bash
# Проверить логи
tail -f /var/log/apache2/error.log

# Проверить права
ls -la assets/components/testsystem/ajax/testsystem.php

# Проверить синтаксис PHP
php -l assets/components/testsystem/ajax/testsystem.php
```

### Проблема: Database connection error

**Решение:**
```bash
# Проверить подключение к БД
mysql -u modx_user -p modx_db

# Проверить настройки MODX
cat core/config/config.inc.php | grep database
```

### Проблема: CSRF token error

**Решение:**
- Убедитесь что пользователь авторизован в MODX
- Проверьте что передается правильный CSRF токен в заголовке

### Проблема: Медленная работа

**Решение:**
```sql
-- Проверить медленные запросы
SELECT * FROM mysql.slow_log ORDER BY query_time DESC LIMIT 10;

-- Очистить кеш аналитики
DELETE FROM modx_test_analytics_cache WHERE expires_at < NOW();

-- Оптимизировать таблицы
OPTIMIZE TABLE modx_test_sessions;
OPTIMIZE TABLE modx_test_user_answers;
```

---

## Контрольный чеклист

- [ ] PHP 7.4+ установлен и настроен
- [ ] MySQL 5.7+ установлен и настроен
- [ ] MODX Revolution установлен
- [ ] Файлы скопированы в core/components/testsystem
- [ ] Файлы скопированы в assets/components/testsystem
- [ ] Права доступа установлены корректно
- [ ] Директории reports и certificates созданы и доступны для записи
- [ ] SQL миграции выполнены (все таблицы созданы)
- [ ] API endpoint доступен и отвечает
- [ ] MODX плагин создан и активирован
- [ ] Cron задачи настроены
- [ ] SSL сертификат установлен (для production)
- [ ] Бэкапы настроены
- [ ] Мониторинг настроен
- [ ] Тестовые данные созданы
- [ ] Система протестирована

---

## Полезные команды

```bash
# Очистить все кеши MODX
rm -rf /path/to/modx/core/cache/*

# Пересоздать индексы MySQL
mysql -u modx_user -p modx_db -e "CALL optimize_test_tables();"

# Проверить количество таблиц системы
mysql -u modx_user -p modx_db -e "SHOW TABLES LIKE 'modx_test%';" | wc -l

# Посмотреть размер БД
mysql -u modx_user -p modx_db -e "
  SELECT
    table_name AS 'Table',
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)'
  FROM information_schema.TABLES
  WHERE table_schema = 'modx_db' AND table_name LIKE 'modx_test%'
  ORDER BY (data_length + index_length) DESC;
"

# Экспортировать только схему (без данных)
mysqldump -u modx_user -p modx_db --no-data --tables \
  $(mysql -u modx_user -p modx_db -N -e "SHOW TABLES LIKE 'modx_test%'") \
  > testsystem_schema_only.sql
```

---

## Поддержка

При возникновении проблем:

1. Проверьте логи: `/var/log/apache2/error.log` или `/var/log/nginx/error.log`
2. Проверьте логи PHP: `/var/log/php-fpm/error.log`
3. Проверьте логи MySQL: `/var/log/mysql/error.log`
4. Проверьте логи MODX: `core/cache/logs/error.log`
5. Обратитесь к документации: `README.md`, `API_ENDPOINTS.md`, `EXAMPLES.md`

---

**Дата создания:** 2025-11-15
**Версия:** 2.0
