# 🚀 ИНСТРУКЦИЯ ПО РАЗВЕРТЫВАНИЮ НА РАБОЧИЙ СЕРВЕР

**Время:** 15-30 минут
**Требуется:** SSH доступ к серверу

---

## 📋 ПОДГОТОВКА

Перед началом убедитесь, что у вас есть:
- ✅ SSH доступ к серверу
- ✅ Путь к директории MODX на сервере (например: `/var/www/html`)
- ✅ Доступ к MySQL (имя БД, пользователь, пароль)
- ✅ Права sudo на сервере (для установки владельца файлов)

---

## 🎯 ВАРИАНТ 1: Автоматическая установка через Git (САМЫЙ ПРОСТОЙ)

### Шаг 1: Подключитесь к серверу

```bash
ssh ваш_пользователь@ваш_сервер.com
```

### Шаг 2: Клонируйте репозиторий

```bash
# Перейти во временную директорию
cd /tmp

# Клонировать репозиторий
git clone https://github.com/HClor/mpv2-gpt-integration.git

# Перейти в директорию проекта
cd mpv2-gpt-integration

# Переключиться на нужную ветку
git checkout claude/setup-learning-testing-01Dpr6NrQULLGhHXt2ns4p8L
```

### Шаг 3: Запустите автоматическую установку

```bash
# Сделать скрипт исполняемым (если нужно)
chmod +x deploy.sh

# Запустить установку
sudo ./deploy.sh
```

**Скрипт запросит у вас:**

1. **Путь к MODX** - введите например: `/var/www/html` или `/home/username/public_html`
2. **Установить БД?** - нажмите `y` (да)
3. **Имя базы данных** - введите имя вашей БД (например: `modx_db`)
4. **Пользователь БД** - введите пользователя MySQL (например: `modx_user`)
5. **Пароль БД** - введите пароль (не будет отображаться при вводе)
6. **Хост БД** - нажмите Enter (будет `localhost`)
7. **Тип установки** - нажмите Enter (будет `FULL`)
8. **Владелец файлов** - нажмите Enter (будет `www-data`) или введите `nginx` если используете nginx

### Шаг 4: Проверьте установку

```bash
# Проверить что API работает
curl http://ваш-домен.com/assets/components/testsystem/ajax/testsystem.php

# Должен вернуть: {"success":false,"message":"Invalid request"}
# Это нормально - значит endpoint работает!
```

### ✅ ГОТОВО! Система установлена!

---

## 🎯 ВАРИАНТ 2: Если Git не установлен на сервере

### Шаг 1: На локальном компьютере - создайте архив

```bash
# На вашем компьютере (не на сервере)
cd /home/user/mpv2-gpt-integration

# Создать архив для загрузки
tar -czf testsystem-deploy.tar.gz \
  core/ \
  assets/ \
  deploy.sh \
  QUICKSTART.md \
  PRODUCTION_READY_REPORT.md

# Архив создан: testsystem-deploy.tar.gz
```

### Шаг 2: Загрузите архив на сервер

```bash
# На вашем компьютере
scp testsystem-deploy.tar.gz ваш_пользователь@ваш_сервер.com:/tmp/
```

### Шаг 3: На сервере - распакуйте и установите

```bash
# Подключиться к серверу
ssh ваш_пользователь@ваш_сервер.com

# Распаковать архив
cd /tmp
tar -xzf testsystem-deploy.tar.gz
cd /tmp

# Запустить установку
sudo ./deploy.sh
```

Далее следуйте инструкциям из Варианта 1, Шаг 3.

---

## 🎯 ВАРИАНТ 3: Ручная установка (если deploy.sh не работает)

### Шаг 1: Скопировать файлы

```bash
# На сервере
cd /путь/к/вашему/modx

# Скопировать core компоненты
cp -r /tmp/mpv2-gpt-integration/core/components/testsystem ./core/components/

# Скопировать assets компоненты
cp -r /tmp/mpv2-gpt-integration/assets/components/testsystem ./assets/components/
```

### Шаг 2: Установить права

```bash
# Права на файлы
find core/components/testsystem -type f -exec chmod 644 {} \;
find core/components/testsystem -type d -exec chmod 755 {} \;
find assets/components/testsystem -type f -exec chmod 644 {} \;
find assets/components/testsystem -type d -exec chmod 755 {} \;

# Создать директории для записи
mkdir -p assets/components/testsystem/reports
mkdir -p assets/components/testsystem/certificates
chmod 775 assets/components/testsystem/reports
chmod 775 assets/components/testsystem/certificates

# Установить владельца (ВАЖНО!)
sudo chown -R www-data:www-data core/components/testsystem
sudo chown -R www-data:www-data assets/components/testsystem

# Если используете nginx, замените www-data на nginx:
# sudo chown -R nginx:nginx core/components/testsystem
```

### Шаг 3: Установить БД

```bash
# Войти в MySQL
mysql -u ваш_пользователь -p ваша_база_данных

# Или выполнить из командной строки
mysql -u ваш_пользователь -p ваша_база_данных < core/components/testsystem/sql/FULL_INSTALLATION.sql
```

---

## ⚙️ ПОСЛЕ УСТАНОВКИ - НАСТРОЙКА CRON

```bash
# Открыть редактор crontab
crontab -e

# Добавить следующие строки (замените ваш-домен.com на реальный домен):

# Очистка старых сессий (ежедневно в 3:00)
0 3 * * * curl -X POST http://ваш-домен.com/assets/components/testsystem/ajax/testsystem.php -H "Content-Type: application/json" -d '{"action":"cleanOldSessions","data":{"days":30}}'

# Обновление рейтингов (еженедельно в воскресенье в 2:00)
0 2 * * 0 curl -X POST http://ваш-домен.com/assets/components/testsystem/ajax/testsystem.php -H "Content-Type: application/json" -d '{"action":"updateLeaderboard","data":{"period":"weekly"}}'

# Сохранить и выйти (Ctrl+X, затем Y, затем Enter)
```

---

## 🔒 БЕЗОПАСНОСТЬ (ВАЖНО!)

### Для Apache:

```bash
# Защитить директорию отчетов
cat > /путь/к/modx/assets/components/testsystem/reports/.htaccess <<'EOF'
Order Deny,Allow
Deny from all
EOF

# Защитить директорию сертификатов
cat > /путь/к/modx/assets/components/testsystem/certificates/.htaccess <<'EOF'
Order Deny,Allow
Deny from all
EOF
```

### Для Nginx:

```bash
# Добавить в конфигурацию nginx (/etc/nginx/sites-available/ваш-сайт)
# В блок server { ... }:

location ~ ^/assets/components/testsystem/(reports|certificates)/ {
    deny all;
    return 403;
}

# Перезапустить nginx
sudo systemctl reload nginx
```

---

## ✅ ПРОВЕРКА РАБОТОСПОСОБНОСТИ

### 1. Проверить API endpoint

```bash
curl http://ваш-домен.com/assets/components/testsystem/ajax/testsystem.php
```

**Ожидаемый результат:**
```json
{"success":false,"message":"Invalid request"}
```

Это нормально! Endpoint работает.

### 2. Проверить таблицы БД

```bash
mysql -u ваш_пользователь -p ваша_база -e "SHOW TABLES LIKE 'modx_test%';" | wc -l
```

**Ожидаемый результат:** должно быть больше 50 таблиц

### 3. Проверить файлы

```bash
ls -la /путь/к/modx/core/components/testsystem/services/ | wc -l
ls -la /путь/к/modx/assets/components/testsystem/controllers/ | wc -l
```

**Ожидаемый результат:**
- ~14 файлов в services/
- ~15 файлов в controllers/

---

## 🐛 РЕШЕНИЕ ПРОБЛЕМ

### Проблема: "Permission denied" при выполнении deploy.sh

**Решение:**
```bash
chmod +x deploy.sh
sudo ./deploy.sh
```

### Проблема: "Git command not found"

**Решение:** Используйте Вариант 2 (загрузка архива через SCP)

### Проблема: "MySQL command not found"

**Решение:**
```bash
# Для Ubuntu/Debian
sudo apt-get install mysql-client

# Для CentOS/RHEL
sudo yum install mysql
```

### Проблема: 500 Internal Server Error при проверке API

**Решение:**
```bash
# Проверить логи
tail -f /var/log/apache2/error.log
# или
tail -f /var/log/nginx/error.log

# Проверить права
ls -la assets/components/testsystem/ajax/testsystem.php

# Проверить синтаксис PHP
php -l assets/components/testsystem/ajax/testsystem.php
```

### Проблема: Не могу подключиться к MySQL

**Решение:**
```bash
# Проверить подключение
mysql -u ваш_пользователь -p

# Проверить права пользователя
mysql -u root -p
SHOW GRANTS FOR 'ваш_пользователь'@'localhost';
```

---

## 📞 НУЖНА ПОМОЩЬ?

Если что-то пошло не так:

1. Проверьте логи:
   - Apache: `/var/log/apache2/error.log`
   - Nginx: `/var/log/nginx/error.log`
   - PHP: `/var/log/php-fpm/error.log`
   - MODX: `/путь/к/modx/core/cache/logs/error.log`

2. Убедитесь что:
   - PHP 7.4+ установлен
   - MySQL 5.7+ установлен
   - Все необходимые расширения PHP установлены
   - Права доступа установлены корректно

3. Напишите мне - помогу разобраться!

---

## 🎉 ГОТОВО!

После успешной установки:

1. ✅ Войдите в MODX Manager
2. ✅ Создайте тестовую категорию
3. ✅ Создайте первый тест
4. ✅ Настройте права доступа
5. ✅ Наслаждайтесь новой LMS системой!

**Система включает:**
- 120 API endpoints
- 14 сервисов
- 15 контроллеров
- Геймификацию
- Аналитику
- Сертификаты
- Траектории обучения
- И многое другое!

---

**Версия:** 2.0
**Дата:** 2025-11-17
