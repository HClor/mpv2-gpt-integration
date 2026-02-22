# 🚀 Быстрый старт - MODX Test System v2.0

Упрощенная инструкция по развертыванию системы обучения и тестирования на сервере.

**Время установки:** 15-30 минут
**Уровень сложности:** Средний

---

## 📋 Требования

- **Сервер:** Linux (Ubuntu/Debian/CentOS)
- **PHP:** 7.4+ (рекомендуется 8.0+)
- **MySQL:** 5.7+ (рекомендуется 8.0+)
- **MODX Revolution:** 2.8.0+
- **Доступ:** SSH с правами sudo

---

## ⚡ Вариант 1: Автоматическая установка (РЕКОМЕНДУЕТСЯ)

### Шаг 1: Загрузка на сервер

```bash
# Вариант A: Через Git (если Git установлен на сервере)
ssh user@your-server.com
cd /path/to/modx
git clone https://github.com/HClor/mpv2-gpt-integration.git /tmp/testsystem
cd /tmp/testsystem
git checkout main

# Вариант B: Через SCP (загрузка архива с локального компьютера)
scp testsystem-v2.0-deployment.tar.gz user@your-server.com:/tmp/
ssh user@your-server.com
cd /tmp
tar -xzf testsystem-v2.0-deployment.tar.gz
cd mpv2-gpt-integration
```

### Шаг 2: Запуск автоматической установки

```bash
# Запустить скрипт развертывания
sudo ./deploy.sh
```

Скрипт запросит:
- Путь к директории MODX (например: `/var/www/html`)
- Параметры подключения к БД (имя БД, пользователь, пароль)
- Тип установки (FULL или INCREMENTAL)
- Владельца файлов (обычно `www-data` или `nginx`)

**Готово!** Система установлена и готова к использованию.

---

## 🔧 Вариант 2: Ручная установка

### Шаг 1: Копирование файлов

```bash
# Перейти в директорию MODX
cd /path/to/modx

# Скопировать core компоненты
cp -r /tmp/testsystem/core/components/testsystem ./core/components/

# Скопировать assets компоненты
cp -r /tmp/testsystem/assets/components/testsystem ./assets/components/
```

### Шаг 2: Установка прав

```bash
# Права на файлы
find core/components/testsystem -type f -exec chmod 644 {} \;
find core/components/testsystem -type d -exec chmod 755 {} \;
find assets/components/testsystem -type f -exec chmod 644 {} \;
find assets/components/testsystem -type d -exec chmod 755 {} \;

# Создать директории для записи
mkdir -p assets/components/testsystem/{reports,certificates}
chmod 775 assets/components/testsystem/{reports,certificates}

# Установить владельца (замените www-data на вашего пользователя)
sudo chown -R www-data:www-data core/components/testsystem
sudo chown -R www-data:www-data assets/components/testsystem
```

### Шаг 3: Установка базы данных

```bash
# Войти в MySQL
mysql -u your_user -p your_database

# Или выполнить из командной строки
mysql -u your_user -p your_database < core/components/testsystem/sql/FULL_INSTALLATION.sql
```

**Готово!**

---

## ✅ Проверка установки

### 1. Проверить API endpoint

```bash
curl http://your-domain.com/assets/components/testsystem/ajax/testsystem.php
```

**Ожидаемый ответ:**
```json
{"success":false,"message":"Invalid request"}
```

Это нормально! Endpoint работает.

### 2. Проверить таблицы БД

```bash
mysql -u your_user -p your_database -e "SHOW TABLES LIKE 'modx_test%';"
```

Должно быть **50+ таблиц**.

### 3. Проверить файлы

```bash
ls -la core/components/testsystem/services/
ls -la assets/components/testsystem/controllers/
```

Должно быть:
- **14 сервисов** в `services/`
- **15 контроллеров** в `controllers/`

---

## 🔐 Настройка безопасности

### 1. Защита служебных директорий (Apache)

```bash
# Создать .htaccess для защиты отчетов
cat > assets/components/testsystem/reports/.htaccess <<'EOF'
Order Deny,Allow
Deny from all
EOF

# Создать .htaccess для защиты сертификатов
cat > assets/components/testsystem/certificates/.htaccess <<'EOF'
Order Deny,Allow
Deny from all
EOF
```

### 2. Настройка SSL (настоятельно рекомендуется)

```bash
# Установить Let's Encrypt сертификат
sudo apt-get install certbot python3-certbot-apache
sudo certbot --apache -d your-domain.com
```

---

## ⚙️ Настройка Cron задач

```bash
# Открыть редактор crontab
crontab -e

# Добавить следующие строки:

# Очистка старых сессий (ежедневно в 3:00)
0 3 * * * curl -X POST http://your-domain.com/assets/components/testsystem/ajax/testsystem.php -d '{"action":"cleanOldSessions","data":{"days":30}}'

# Обновление рейтингов (еженедельно по воскресеньям в 2:00)
0 2 * * 0 curl -X POST http://your-domain.com/assets/components/testsystem/ajax/testsystem.php -d '{"action":"updateLeaderboard","data":{"period":"weekly"}}'

# Обработка очереди уведомлений (каждые 5 минут)
*/5 * * * * curl -X POST http://your-domain.com/assets/components/testsystem/ajax/testsystem.php -d '{"action":"processQueue","data":{"batch_size":100}}'
```

---

## 📊 Тестирование основных функций

### 1. Проверить профиль пользователя

```bash
curl -X POST http://your-domain.com/assets/components/testsystem/ajax/testsystem.php \
  -H "Content-Type: application/json" \
  -b "PHPSESSID=your_session_id" \
  -d '{"action":"getMyProfile"}'
```

### 2. Проверить рейтинги

```bash
curl -X POST http://your-domain.com/assets/components/testsystem/ajax/testsystem.php \
  -H "Content-Type: application/json" \
  -d '{"action":"getLeaderboard","data":{"period":"all_time","limit":10}}'
```

### 3. Проверить статистику уровней

```bash
curl -X POST http://your-domain.com/assets/components/testsystem/ajax/testsystem.php \
  -H "Content-Type: application/json" \
  -d '{"action":"getLevelStats"}'
```

---

## 🐛 Решение проблем

### Проблема: 500 Internal Server Error

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

### Проблема: Database connection error

**Решение:**
```bash
# Проверить подключение
mysql -u your_user -p your_database

# Проверить настройки MODX
cat core/config/config.inc.php | grep database
```

### Проблема: CSRF token error

**Решение:**
- Убедитесь, что пользователь авторизован в MODX
- Проверьте передачу CSRF токена в заголовке
- Очистите кеш браузера и MODX

---

## 📚 Дополнительная документация

После установки смотрите:

1. **Полная документация:** `core/components/testsystem/README.md`
2. **API документация:** `core/components/testsystem/API_ENDPOINTS.md`
3. **Детальная инструкция:** `core/components/testsystem/DEPLOYMENT.md`
4. **Отчет о качестве:** `core/components/testsystem/CODE_QUALITY_REPORT.md`

---

## 📞 Что дальше?

После успешной установки:

1. ✅ Войдите в MODX Manager
2. ✅ Создайте категории для тестов
3. ✅ Создайте первый тест
4. ✅ Настройте права доступа для пользователей
5. ✅ Изучите API endpoints для интеграции

---

## ✨ Реализованные возможности

### Спринты 1-7: Базовая функциональность
- ✅ Безопасность (CSRF, SQL Injection защита)
- ✅ Конфигурация и централизация
- ✅ Service Layer архитектура
- ✅ MVC контроллеры
- ✅ Кеширование и оптимизация
- ✅ Система миграций БД

### Спринты 8-17: Расширенная функциональность
- ✅ Очистка данных и целостность БД
- ✅ Учебные материалы (LMS)
- ✅ Гранулярные права доступа
- ✅ Траектории обучения
- ✅ Расширенные типы вопросов
- ✅ Система уведомлений
- ✅ Геймификация (XP, уровни, достижения)
- ✅ Расширенная аналитика
- ✅ Сертификаты и верификация
- ✅ 120 API endpoints

---

## 📈 Статистика проекта

- **Сервисов:** 14 (7,710 строк кода)
- **Контроллеров:** 15 (5,278 строк кода)
- **API Endpoints:** 120
- **Таблиц БД:** 50+
- **SQL миграций:** 9 файлов
- **Документация:** 5 файлов

---

## 🎓 Для студентов

Система готова к использованию в учебных целях:
1. Изучите архитектуру MVC
2. Посмотрите примеры Service Layer
3. Изучите работу с БД через PDO
4. Проанализируйте систему безопасности (CSRF, валидация)
5. Используйте как референс для своих проектов

---

## 💡 Полезные команды

```bash
# Очистить кеш MODX
rm -rf /path/to/modx/core/cache/*

# Проверить количество таблиц
mysql -u user -p database -e "SHOW TABLES LIKE 'modx_test%';" | wc -l

# Посмотреть размер БД
mysql -u user -p database -e "
  SELECT
    table_name AS 'Table',
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)'
  FROM information_schema.TABLES
  WHERE table_schema = 'database' AND table_name LIKE 'modx_test%'
  ORDER BY (data_length + index_length) DESC;
"

# Создать резервную копию БД
mysqldump -u user -p database \
  --tables $(mysql -u user -p database -N -e "SHOW TABLES LIKE 'modx_test%'") \
  | gzip > backup_$(date +%Y%m%d).sql.gz
```

---

**Версия:** 2.0
**Дата:** 2025-11-17
**Автор:** Claude (AI Assistant)

**Готово к production использованию!** 🎉
