# Схема базы данных LMS системы

## Описание

Этот документ содержит **реальную** схему базы данных LMS системы, полученную из production БД.

**ВАЖНО:** Всегда используйте названия полей из этого документа при написании SQL-запросов!

---

## 📋 Основные таблицы

### 1. `modx_test_tests` - Тесты

Основная таблица с тестами.

| Поле | Тип | Null | Описание |
|------|-----|------|----------|
| `id` | int(11) | NO | ID теста |
| `resource_id` | int(11) | YES | **ID категории** (не ресурса MODX!) |
| `title` | varchar(255) | NO | Название теста |
| `description` | text | YES | Описание теста |
| `mode` | enum('training','exam') | YES | Режим: тренировка или экзамен |
| `time_limit` | int(11) | YES | Ограничение времени (минуты) |
| `pass_score` | int(11) | YES | Проходной балл (%) |
| `questions_per_session` | int(11) | YES | Количество вопросов за сессию |
| `randomize_questions` | tinyint(1) | YES | Рандомизировать вопросы |
| `randomize_answers` | tinyint(1) | YES | Рандомизировать ответы |
| `is_active` | tinyint(1) | YES | **Активен ли тест** (важно!) |
| `is_learning_material` | tinyint(1) | NO | Обучающий материал |
| `created_at` | datetime | YES | Дата создания |
| `created_by` | int(11) | YES | ID создателя |
| `publication_status` | enum('draft','private','unlisted','public') | YES | **Статус публикации** |
| `published_at` | datetime | YES | Дата публикации |
| `public_url_slug` | varchar(255) | YES | URL slug |

#### Важные заметки:
- **НЕТ поля `is_public`** - используется `publication_status`
- **`publication_status`** может быть:
  - `draft` - черновик
  - `private` - приватный
  - `unlisted` - скрытый (по ссылке)
  - `public` - **публичный** (видно всем)
- **`resource_id`** - это ID категории из `modx_test_categories`, а не ID ресурса MODX!
- **`is_active`** - тест активен (0 или 1)

#### SQL-примеры:
```sql
-- Получить все публичные активные тесты
SELECT * FROM modx_test_tests
WHERE publication_status = 'public' AND is_active = 1;

-- Получить тесты по категории
SELECT * FROM modx_test_tests
WHERE resource_id = 1 AND publication_status = 'public';
```

---

### 2. `modx_test_categories` - Категории тестов

Таблица категорий тестов.

| Поле | Тип | Null | Описание |
|------|-----|------|----------|
| `id` | int(11) | NO | ID категории |
| `parent_id` | int(11) | YES | ID родительской категории |
| `name` | varchar(255) | NO | **Название** (не `title`!) |
| `icon` | varchar(50) | YES | Иконка категории |
| `description` | text | YES | Описание категории |
| `sort_order` | int(11) | YES | Порядок сортировки |
| `created_at` | datetime | YES | Дата создания |

#### Важные заметки:
- **НЕТ поля `title`** - используется `name`
- Поддерживается вложенность через `parent_id`

#### SQL-примеры:
```sql
-- Получить все категории
SELECT id, name, description FROM modx_test_categories;

-- Получить категорию с тестами
SELECT
    c.id,
    c.name,
    COUNT(t.id) as test_count
FROM modx_test_categories c
LEFT JOIN modx_test_tests t ON t.resource_id = c.id
GROUP BY c.id, c.name;
```

---

### 3. `modx_test_questions` - Вопросы

Таблица вопросов тестов.

| Поле | Тип | Null | Описание |
|------|-----|------|----------|
| `id` | int(11) | NO | ID вопроса |
| `test_id` | int(11) | NO | ID теста |
| `category_id` | int(11) | YES | ID категории вопроса |
| `question_text` | text | NO | Текст вопроса |
| `question_image` | varchar(255) | YES | Изображение вопроса |
| `question_type` | enum | YES | Тип вопроса |
| `explanation` | text | YES | Пояснение к ответу |
| `explanation_image` | varchar(255) | YES | Изображение пояснения |
| `published` | tinyint(1) | NO | Опубликован |
| `is_learning` | tinyint(1) | NO | Обучающий вопрос |
| `sort_order` | int(11) | YES | Порядок сортировки |
| `created_at` | datetime | YES | Дата создания |

#### Типы вопросов (`question_type`):
- `single` - один правильный ответ
- `multiple` - несколько правильных ответов
- `matching` - сопоставление
- `ordering` - упорядочивание
- `fill_blank` - заполнение пропусков
- `essay` - эссе (текстовый ответ)

---

### 4. `modx_test_answers` - Ответы

Таблица вариантов ответов на вопросы.

| Поле | Тип | Null | Описание |
|------|-----|------|----------|
| `id` | int(11) | NO | ID ответа |
| `question_id` | int(11) | NO | ID вопроса |
| `answer_text` | text | NO | Текст ответа |
| `answer_image` | varchar(255) | YES | Изображение ответа |
| `is_correct` | tinyint(1) | NO | Правильный ответ |
| `sort_order` | int(11) | YES | Порядок сортировки |

---

### 5. `modx_test_sessions` - Сессии тестирования

Таблица активных и завершенных сессий прохождения тестов.

| Поле | Тип | Null | Описание |
|------|-----|------|----------|
| `id` | int(11) | NO | ID сессии |
| `user_id` | int(11) | NO | ID пользователя |
| `test_id` | int(11) | NO | ID теста |
| `start_time` | datetime | YES | Время начала |
| `end_time` | datetime | YES | Время окончания |
| `score` | decimal(5,2) | YES | Балл (%) |
| `passed` | tinyint(1) | YES | Тест пройден |
| `status` | enum | YES | Статус сессии |
| `current_question` | int(11) | YES | Текущий вопрос |

#### Статусы сессии (`status`):
- `in_progress` - в процессе
- `completed` - завершена
- `abandoned` - оставлена

---

### 6. `modx_test_user_answers` - Ответы пользователей

Таблица ответов пользователей на вопросы.

| Поле | Тип | Null | Описание |
|------|-----|------|----------|
| `id` | int(11) | NO | ID записи |
| `session_id` | int(11) | NO | ID сессии |
| `question_id` | int(11) | NO | ID вопроса |
| `answer_id` | int(11) | YES | ID выбранного ответа |
| `user_answer_text` | text | YES | Текстовый ответ |
| `is_correct` | tinyint(1) | YES | Правильно |
| `answered_at` | datetime | YES | Время ответа |

---

### 7. `modx_test_achievements` - Достижения

Таблица достижений пользователей.

| Поле | Тип | Null | Описание |
|------|-----|------|----------|
| `id` | int(11) | NO | ID достижения |
| `user_id` | int(11) | NO | ID пользователя |
| `achievement_type` | varchar(50) | NO | Тип достижения |
| `achievement_data` | text | YES | Данные достижения |
| `earned_at` | datetime | YES | Дата получения |

---

### 8. `modx_test_level_config` - Конфигурация уровней

Таблица конфигурации уровней пользователей.

| Поле | Тип | Null | Описание |
|------|-----|------|----------|
| `id` | int(11) | NO | ID уровня |
| `level_number` | int(11) | NO | Номер уровня |
| `level_name` | varchar(100) | NO | Название уровня |
| `points_required` | int(11) | NO | Требуется очков |
| `icon` | varchar(255) | YES | Иконка уровня |

---

### 9. `modx_test_permissions` - Права доступа

Таблица прав доступа к тестам.

| Поле | Тип | Null | Описание |
|------|-----|------|----------|
| `id` | int(11) | NO | ID права |
| `test_id` | int(11) | YES | ID теста |
| `user_id` | int(11) | YES | ID пользователя |
| `user_group_id` | int(11) | YES | ID группы |
| `permission_type` | enum | YES | Тип права |

---

### 10. `modx_test_notifications` - Уведомления

Таблица уведомлений пользователей.

| Поле | Тип | Null | Описание |
|------|-----|------|----------|
| `id` | int(11) | NO | ID уведомления |
| `user_id` | int(11) | NO | ID пользователя |
| `notification_type` | varchar(50) | NO | Тип уведомления |
| `notification_data` | text | YES | Данные уведомления |
| `is_read` | tinyint(1) | YES | Прочитано |
| `created_at` | datetime | YES | Дата создания |

---

## 🔗 Связи между таблицами

```
modx_test_categories (id)
    ↓ (resource_id)
modx_test_tests (id)
    ↓ (test_id)
modx_test_questions (id)
    ↓ (question_id)
modx_test_answers (id)

modx_users (id)
    ↓ (user_id)
modx_test_sessions (id)
    ↓ (session_id)
modx_test_user_answers
```

---

## 📊 Таблицы групп пользователей MODX

### `modx_member_groups` - Группы пользователей

| Поле | Тип | Описание |
|------|-----|----------|
| `id` | int(11) | ID группы |
| `name` | varchar(255) | Название группы |
| `description` | text | Описание |

**ВАЖНО:** Таблица называется `modx_member_groups`, а не `modx_membergroup`!

---

## ✅ Чек-лист для разработки

При написании SQL-запросов:

- [ ] Используй `publication_status = 'public'` вместо `is_public = 1`
- [ ] Используй `modx_test_categories.name` вместо `.title`
- [ ] Используй `modx_test_tests.resource_id` для связи с категорией
- [ ] Проверяй `is_active = 1` для активных тестов
- [ ] Используй `modx_member_groups` вместо `modx_membergroup`

---

## 🔧 Примеры запросов

### Получить все публичные тесты пользователя
```sql
SELECT
    t.id,
    t.title,
    t.description,
    c.name as category_name
FROM modx_test_tests t
LEFT JOIN modx_test_categories c ON c.id = t.resource_id
WHERE t.publication_status = 'public'
  AND t.is_active = 1
ORDER BY c.name, t.title;
```

### Получить категории с количеством публичных тестов
```sql
SELECT
    c.id,
    c.name,
    c.description,
    COUNT(t.id) as test_count
FROM modx_test_categories c
LEFT JOIN modx_test_tests t ON t.resource_id = c.id
    AND t.publication_status = 'public'
    AND t.is_active = 1
GROUP BY c.id, c.name, c.description
HAVING test_count > 0
ORDER BY c.sort_order, c.name;
```

### Получить результаты пользователя
```sql
SELECT
    s.id,
    s.start_time,
    s.end_time,
    s.score,
    s.passed,
    t.title as test_title,
    c.name as category_name
FROM modx_test_sessions s
INNER JOIN modx_test_tests t ON t.id = s.test_id
LEFT JOIN modx_test_categories c ON c.id = t.resource_id
WHERE s.user_id = 2
  AND s.status = 'completed'
ORDER BY s.end_time DESC;
```

---

**Последнее обновление:** 2025-11-20

**Источник:** Production БД, команда `DESCRIBE`
