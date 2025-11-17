# Test System v2.0 - Быстрая установка в MODX

Пошаговая инструкция по установке компонента Test System в MODX Revolution.

## ✅ Предварительные требования

- MODX Revolution 2.8.0+ установлен и работает
- MySQL 5.7.21+
- PHP 7.4+
- Права администратора в MODX
- Доступ к phpMyAdmin или MySQL CLI

---

## 📦 Шаг 1: Установка базы данных

### 1.1. Выполнить SQL скрипт через phpMyAdmin

1. Войти в phpMyAdmin
2. Выбрать вашу базу данных MODX
3. Перейти на вкладку "SQL"
4. Открыть файл `core/components/testsystem/sql/FULL_INSTALLATION_FIXED.sql`
5. Скопировать **ВСЁ** содержимое
6. Вставить в окно SQL
7. Нажать "Выполнить"

**ВАЖНО:** Если возникают ошибки с DELIMITER, выполняйте запросы с триггерами и процедурами отдельно:
- Сначала установите DELIMITER в `$` в phpMyAdmin (выпадающее меню)
- Затем выполните блоки с триггерами/процедурами
- Верните DELIMITER в `;`

### 1.2. Проверить установку

Выполните в SQL:
```sql
SHOW TABLES LIKE 'modx_test%';
```

Должно быть **58 таблиц**.

```sql
SHOW TRIGGERS;
```

Должно быть **14 триггеров**.

```sql
SHOW PROCEDURE STATUS WHERE Db = DATABASE();
```

Должно быть **12 процедур**.

**✓ База данных готова!**

---

## 🔧 Шаг 2: Регистрация компонента в MODX

### 2.1. Выполнить SQL скрипт регистрации

1. В phpMyAdmin перейти на вкладку "SQL"
2. Открыть файл `core/components/testsystem/sql/MODX_INSTALL.sql`
3. Скопировать **ВСЁ** содержимое
4. Вставить и выполнить

Этот скрипт создаст:
- Namespace `testsystem`
- 20+ System Settings
- Категорию "Test System"
- 30+ Snippets (заглушки)
- 17 Chunks (заглушки)
- Меню в админке

**✓ Компонент зарегистрирован!**

---

## 📝 Шаг 3: Загрузка Snippets и Chunks

### 3.1. Запустить PHP скрипт установки

**Вариант A: Через командную строку (рекомендуется)**

```bash
cd /path/to/your/modx/core/components/testsystem
php install_elements.php
```

**Вариант B: Через браузер**

1. Открыть в браузере:
```
http://your-domain.com/core/components/testsystem/install_elements.php
```

2. Дождаться сообщения "Installation complete!"

Скрипт загрузит содержимое всех snippets и chunks из файлов.

**✓ Элементы установлены!**

---

## 🧹 Шаг 4: Очистить кеш MODX

1. Войти в MODX Manager (админку)
2. Перейти: **Управление → Очистить кеш**
3. Нажать "Очистить кеш"

**✓ Кеш очищен!**

---

## ✨ Шаг 5: Создать тестовые страницы

### 5.1. Создать контейнер для системы

1. В MODX Manager создать новый ресурс:
   - **Заголовок:** Система тестирования
   - **Псевдоним:** tests
   - **Тип содержимого:** HTML
   - **Шаблон:** Ваш базовый шаблон
   - **Опубликовано:** Да
   - **Контейнер:** Да

### 5.2. Создать страницу списка тестов

1. Создать дочерний ресурс для "tests":
   - **Заголовок:** Список тестов
   - **Псевдоним:** list
   - **Содержимое:**
   ```
   [[!testsList]]
   ```
   - **Опубликовано:** Да

### 5.3. Создать страницу прохождения теста

1. Создать дочерний ресурс для "tests":
   - **Заголовок:** Прохождение теста
   - **Псевдоним:** run
   - **Содержимое:**
   ```
   [[!testRunner]]
   ```
   - **Опубликовано:** Да

### 5.4. Создать страницу "Мои тесты"

1. Создать дочерний ресурс для "tests":
   - **Заголовок:** Мои тесты
   - **Псевдоним:** my-tests
   - **Содержимое:**
   ```
   [[!myTests]]
   ```
   - **Опубликовано:** Да

### 5.5. Создать страницу профиля

1. Создать дочерний ресурс для "tests":
   - **Заголовок:** Профиль
   - **Псевдоним:** profile
   - **Содержимое:**
   ```
   [[!userProfile]]
   ```
   - **Опубликовано:** Да

### 5.6. Создать страницу таблицы лидеров

1. Создать дочерний ресурс для "tests":
   - **Заголовок:** Таблица лидеров
   - **Псевдоним:** leaderboard
   - **Содержимое:**
   ```
   [[!leaderboard]]
   ```
   - **Опубликовано:** Да

**✓ Страницы созданы!**

---

## 🎨 Шаг 6: Подключить стили и скрипты

### 6.1. Добавить в шаблон (перед `</head>`)

```html
<link rel="stylesheet" href="/assets/components/testsystem/css/tsrunner.css">
```

### 6.2. Добавить в шаблон (перед `</body>`)

```html
<script src="/assets/components/testsystem/js/tsrunner.js"></script>
<script src="/assets/components/testsystem/js/mytests.js"></script>
<script src="/assets/components/testsystem/js/knowledge-areas.js"></script>
```

**✓ Стили подключены!**

---

## 🧪 Шаг 7: Создать тестовые данные

### 7.1. Создать категорию

1. В MODX Manager перейти: **Компоненты → Test System → Категории**
2. Создать новую категорию:
   - **Название:** Тестовая категория
   - **Описание:** Для тестирования системы

Или через SQL:
```sql
INSERT INTO modx_test_categories (name, description, sort_order)
VALUES ('Тестовая категория', 'Для тестирования системы', 0);
```

### 7.2. Создать тестовый тест

Через админку или SQL:
```sql
-- Получить ID категории
SET @cat_id = (SELECT id FROM modx_test_categories WHERE name = 'Тестовая категория' LIMIT 1);

-- Создать тест
INSERT INTO modx_test_tests (title, description, mode, time_limit, pass_score, is_active, created_by)
VALUES ('Тестовый тест', 'Простой тест для проверки работы системы', 'training', 30, 70, 1, 1);

SET @test_id = LAST_INSERT_ID();
```

### 7.3. Создать вопросы

```sql
-- Создать вопрос
INSERT INTO modx_test_questions (test_id, category_id, question_text, question_type, published)
VALUES (@test_id, @cat_id, 'Сколько будет 2 + 2?', 'single', 1);

SET @question_id = LAST_INSERT_ID();

-- Создать варианты ответов
INSERT INTO modx_test_answers (question_id, answer_text, is_correct, sort_order)
VALUES
(@question_id, '3', 0, 1),
(@question_id, '4', 1, 2),
(@question_id, '5', 0, 3),
(@question_id, '6', 0, 4);
```

**✓ Тестовые данные созданы!**

---

## 🎯 Шаг 8: Проверить работу

### 8.1. Открыть список тестов

```
http://your-domain.com/tests/list
```

Должен отобразиться список тестов.

### 8.2. Пройти тестовый тест

1. Кликнуть на тестовый тест
2. Должна открыться страница с вопросом
3. Выбрать ответ "4"
4. Нажать "Завершить тест"
5. Должен показаться результат 100%

### 8.3. Проверить профиль

```
http://your-domain.com/tests/profile
```

Должна отобразиться статистика с пройденным тестом.

### 8.4. Проверить таблицу лидеров

```
http://your-domain.com/tests/leaderboard
```

Должен отобразиться рейтинг пользователей.

**✓ Система работает!**

---

## 🔧 Дополнительные настройки

### Настройка System Settings

1. В MODX Manager: **Система → Системные настройки**
2. Фильтр по namespace: `testsystem`
3. Доступные настройки:
   - `testsystem.default_pass_score` - проходной балл по умолчанию (70)
   - `testsystem.default_time_limit` - лимит времени (30 минут)
   - `testsystem.enable_gamification` - включить геймификацию (1)
   - `testsystem.xp_per_correct_answer` - XP за правильный ответ (10)
   - `testsystem.enable_notifications` - включить уведомления (1)
   - И многие другие...

---

## 🚨 Решение проблем

### Проблема: Snippets не работают

**Решение:**
1. Проверить что файлы snippets существуют в `core/elements/snippets/`
2. Запустить `install_elements.php` заново
3. Очистить кеш MODX

### Проблема: CSS не применяется

**Решение:**
1. Проверить путь к CSS файлу (должен быть `/assets/components/testsystem/css/tsrunner.css`)
2. Проверить права на файлы (должно быть 644)
3. Открыть CSS файл в браузере напрямую - должен открываться

### Проблема: AJAX не работает

**Решение:**
1. Проверить путь к endpoint: `/assets/components/testsystem/ajax/testsystem.php`
2. Открыть endpoint в браузере - должен вернуть JSON
3. Проверить console браузера (F12) на ошибки JavaScript

### Проблема: Триггеры не создались

**Решение:**
1. Установить DELIMITER в `$` в phpMyAdmin
2. Выполнить блоки с триггерами отдельно
3. Проверить: `SHOW TRIGGERS;`

---

## 📚 Дополнительная информация

- **API документация:** `core/components/testsystem/API_ENDPOINTS.md`
- **Примеры использования:** `core/components/testsystem/EXAMPLES.md`
- **Полная документация:** `core/components/testsystem/README.md`

---

## ✅ Чек-лист установки

- [ ] База данных установлена (58 таблиц, 14 триггеров, 12 процедур)
- [ ] Компонент зарегистрирован в MODX (namespace, settings, category)
- [ ] Snippets и chunks загружены из файлов
- [ ] Кеш MODX очищен
- [ ] Тестовые страницы созданы
- [ ] CSS и JS подключены в шаблон
- [ ] Тестовые данные созданы
- [ ] Система протестирована и работает

---

**Поздравляем! Система тестирования установлена! 🎉**

Если возникли вопросы - смотрите раздел "Решение проблем" выше.
