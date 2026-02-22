# Инструкция по внедрению системы управления приватными тестами

## 📋 Что добавлено в этом обновлении

1. **Гибридное создание тестов** - импорт вопросов сразу при создании теста
2. **Система управления доступом** - предоставление прав на приватные тесты
3. **Фильтрация тестов** - пользователи видят только доступные им тесты

---

## 🚀 Шаг 1: Бэкап

### Обязательно сделайте бэкап перед обновлением!

```bash
# Бэкап базы данных
mysqldump -u USERNAME -p DATABASE_NAME > backup_$(date +%Y%m%d_%H%M%S).sql

# Бэкап файлов (опционально)
tar -czf backup_files_$(date +%Y%m%d_%H%M%S).tar.gz \
  core/elements/snippets/ \
  core/components/testsystem/ \
  assets/components/testsystem/
```

---

## 📁 Шаг 2: Копирование файлов

### 2.1 Новые файлы (создать)

```bash
# SQL схемы
core/components/testsystem/sql/test_permissions.sql

# PHP хелперы
core/components/testsystem/helpers/QuestionImportHelper.php

# JavaScript
assets/components/testsystem/js/test-permissions.js
```

### 2.2 Измененные файлы (перезаписать)

```bash
# PHP snippets
core/elements/snippets/addTestForm.php

# API
assets/components/testsystem/ajax/testsystem.php

# JavaScript
assets/components/testsystem/js/mytests.js
```

### Команды для копирования (выполнить на сервере):

```bash
# Перейдите в директорию проекта
cd /path/to/your/site/

# Скопируйте новые файлы
mkdir -p core/components/testsystem/helpers/
mkdir -p core/components/testsystem/sql/

# Используйте FTP/SFTP или git pull для загрузки файлов
```

---

## 🗄️ Шаг 3: Обновление базы данных

### 3.1 Запустить SQL скрипт

**Через phpMyAdmin:**
1. Откройте phpMyAdmin
2. Выберите вашу базу данных
3. Перейдите в раздел "SQL"
4. Скопируйте содержимое файла `core/components/testsystem/sql/test_permissions.sql`
5. Нажмите "Вперед"

**Через командную строку:**
```bash
mysql -u USERNAME -p DATABASE_NAME < core/components/testsystem/sql/test_permissions.sql
```

### 3.2 Проверить таблицу

Выполните SQL запрос для проверки:

```sql
-- Проверить структуру таблицы
SHOW COLUMNS FROM modx_test_permissions;

-- Ожидаемый результат:
-- id, test_id, user_id, granted_by, can_view, can_edit, granted_at, expires_at
```

---

## ✅ Шаг 4: Проверка после внедрения

### 4.1 Проверка создания теста

1. Войдите как эксперт/админ
2. Перейдите на страницу создания теста
3. Убедитесь, что:
   - ✅ Есть чекбокс "Приватный тест"
   - ✅ Есть поле загрузки файла
   - ✅ При загрузке CSV/XLSX вопросы импортируются сразу
   - ✅ Показывается страница успеха с количеством вопросов

### 4.2 Проверка управления доступом

1. Создайте приватный тест
2. Перейдите в "Мои тесты"
3. Убедитесь, что:
   - ✅ У теста есть кнопка "👥" (Управление доступом)
   - ✅ При клике открывается модальное окно
   - ✅ Можно найти пользователя через поиск
   - ✅ Можно предоставить права "Просмотр" или "Редактирование"
   - ✅ Можно отозвать права

### 4.3 Проверка фильтрации тестов

1. Войдите под другим пользователем
2. Перейдите в "Мои тесты" → вкладка "Доступны мне"
3. Убедитесь, что:
   - ✅ Видны только тесты с предоставленным доступом
   - ✅ Показывается бейдж "Просмотр" или "Редактирование"
   - ✅ Владелец теста указан

---

## 🔍 Шаг 5: Поиск проблем

### Проблема 1: Таблица уже существует

**Симптом:** Ошибка "Table 'modx_test_permissions' already exists"

**Решение:** Таблица уже создана ранее. Проверьте наличие поля `can_view`:

```sql
-- Добавить поле can_view если его нет
ALTER TABLE `modx_test_permissions`
    ADD COLUMN IF NOT EXISTS `can_view` TINYINT(1) DEFAULT 1
    COMMENT 'Может просматривать тест' AFTER `granted_by`;

-- Добавить поле expires_at если его нет
ALTER TABLE `modx_test_permissions`
    ADD COLUMN IF NOT EXISTS `expires_at` DATETIME DEFAULT NULL
    COMMENT 'Дата истечения доступа' AFTER `granted_at`;
```

### Проблема 2: JavaScript не загружается

**Симптом:** Кнопки не работают, консоль показывает ошибки

**Решение:**
```bash
# Проверьте права доступа к файлам
chmod 644 assets/components/testsystem/js/test-permissions.js
chmod 644 assets/components/testsystem/js/mytests.js

# Очистите кэш браузера (Ctrl+F5)
```

### Проблема 3: API возвращает ошибки

**Симптом:** "Unknown action" или "Method not found"

**Решение:**
```bash
# Проверьте что файл testsystem.php обновлен
ls -la assets/components/testsystem/ajax/testsystem.php

# Проверьте логи ошибок
tail -f /path/to/error_log.log
```

### Проблема 4: PhpSpreadsheet не работает

**Симптом:** "PhpSpreadsheet не установлен"

**Решение:**
```bash
# Установите composer зависимости
cd /path/to/your/site/
composer install --no-dev

# Проверьте наличие vendor/autoload.php
ls -la vendor/autoload.php
```

---

## 🧪 Шаг 6: Тестовый сценарий

### Полный цикл тестирования:

1. **Создание приватного теста**
   ```
   - Создать тест с чекбоксом "Приватный тест"
   - Загрузить CSV файл с вопросами
   - Проверить что вопросы импортировались
   ```

2. **Предоставление доступа**
   ```
   - Открыть "Мои тесты"
   - Нажать "Управление доступом"
   - Найти пользователя "testuser"
   - Предоставить права "Просмотр"
   - Проверить что пользователь добавлен в список
   ```

3. **Проверка доступа**
   ```
   - Войти под "testuser"
   - Открыть вкладку "Доступны мне"
   - Убедиться что тест виден
   - Пройти тест
   ```

4. **Отзыв доступа**
   ```
   - Вернуться к владельцу теста
   - Открыть "Управление доступом"
   - Нажать "Отозвать" у пользователя
   - Проверить под "testuser" - тест исчез
   ```

---

## 📊 Шаг 7: Мониторинг

### SQL запросы для проверки

```sql
-- Посмотреть все приватные тесты
SELECT id, title, publication_status, created_by
FROM modx_test_tests
WHERE publication_status = 'private';

-- Посмотреть все предоставленные права
SELECT
    tp.id,
    t.title as test_title,
    u.username,
    tp.can_view,
    tp.can_edit,
    tp.granted_at,
    tp.expires_at
FROM modx_test_permissions tp
JOIN modx_test_tests t ON t.id = tp.test_id
JOIN modx_users u ON u.id = tp.user_id;

-- Найти истекшие права (должны быть автоматически скрыты)
SELECT *
FROM modx_test_permissions
WHERE expires_at IS NOT NULL AND expires_at < NOW();
```

---

## 🔧 Откат изменений (если что-то пошло не так)

### Вернуть старые файлы

```bash
# Восстановить из бэкапа
tar -xzf backup_files_YYYYMMDD_HHMMSS.tar.gz
```

### Откатить базу данных

```sql
-- Удалить добавленные поля (если нужно)
ALTER TABLE `modx_test_permissions` DROP COLUMN `can_view`;
ALTER TABLE `modx_test_permissions` DROP COLUMN `expires_at`;

-- Или удалить всю таблицу
DROP TABLE IF EXISTS `modx_test_permissions`;
```

---

## 📝 Примечания

1. **Composer**: Убедитесь что на сервере установлен Composer и выполнен `composer install`
2. **Права доступа**: Файлы должны быть доступны для чтения веб-сервером (обычно `chmod 644`)
3. **Кэш**: После обновления очистите кэш MODX и браузера
4. **Тестирование**: Сначала протестируйте на копии сайта/staging окружении

---

## 🆘 Поддержка

Если возникли проблемы:

1. Проверьте логи ошибок PHP
2. Откройте консоль браузера (F12) и посмотрите JavaScript ошибки
3. Проверьте что все файлы скопированы корректно
4. Убедитесь что SQL скрипт выполнен успешно

---

## ✨ Новые возможности для пользователей

После внедрения пользователи смогут:

✅ **Создавать приватные тесты** - доступны только избранным пользователям
✅ **Импортировать вопросы сразу** - один клик вместо двух
✅ **Управлять доступом** - предоставлять права просмотра/редактирования
✅ **Видеть только свои тесты** - автоматическая фильтрация по правам
✅ **Временный доступ** - устанавливать срок действия прав (опционально)

---

**Дата создания:** 2024-12-05
**Версия:** 1.0
