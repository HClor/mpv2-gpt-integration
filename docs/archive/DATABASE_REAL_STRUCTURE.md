# 📊 РЕАЛЬНАЯ СТРУКТУРА БАЗЫ ДАННЫХ - MODX LMS v2.0

**Дата анализа:** 2025-11-20
**База данных:** lmixru_mpv2
**Статус:** Production с реальными данными
**Всего таблиц:** 157 (95 MODX + 62 LMS)

---

## 📈 СТАТИСТИКА РЕАЛЬНЫХ ДАННЫХ

```
📊 MODX CORE:
├─ Пользователи: 11 записей
├─ Ресурсы (страницы): 49 записей
├─ Сниппеты: 63 записей
├─ Шаблоны: 8 записей
└─ Роли: 4 записей

📚 LMS СИСТЕМА (ЗАПОЛНЕННЫЕ ТАБЛИЦЫ):
├─ Категории тестов: 7 записей
├─ Тесты: 19 записей
├─ Вопросы: 1,881 записей ✓ ПОЛНЫЕ ДАННЫЕ
├─ Ответы: 7,485 записей ✓ ПОЛНЫЕ ДАННЫЕ
├─ Сессии тестирования: 324 записей ✓ РЕАЛЬНЫЕ ПОПЫТКИ
├─ Ответы пользователей: 513 записей
├─ Достижения: 8 записей
├─ Области знаний: 4 записи
├─ Конфигурация уровней: 10 записей
├─ Шаблоны уведомлений: 8 записей
├─ Отчеты: 5 записей
└─ Статистика пользователей: 4 записи

🔒 ПУСТЫЕ ТАБЛИЦЫ (ГОТОВЫ К ИСПОЛЬЗОВАНИЮ):
├─ Пути обучения (learning_paths)
├─ Учебные материалы (learning_materials)
├─ Таблица лидеров (leaderboard)
├─ Сертификаты (certificates)
├─ Уведомления (notifications)
└─ 43+ других таблиц
```

---

## 🎯 ТАБЛИЦЫ С РЕАЛЬНЫМИ ДАННЫМИ

### 1. **modx_test_categories** (7 категорий)
```
Структура:
- id (int) PRIMARY KEY
- name (varchar 255)
- description (text)
- sort_order (int)
- is_active (tinyint)
- created_at, updated_at

Примеры категорий:
1. Математика
2. История
3. Литература
4. Физика
5. Химия
6. Биология
7. География
```

### 2. **modx_test_tests** (19 тестов)
```
Структура:
- id (int) PRIMARY KEY AUTO_INCREMENT
- resource_id (int) - ссылка на MODX ресурс
- title (varchar 255) - название теста
- description (text)
- mode (enum) - 'training' или 'exam'
- time_limit (int) - минуты
- pass_score (int) - % для зачета (default 70)
- questions_per_session (int)
- randomize_questions (tinyint)
- randomize_answers (tinyint)
- is_active (tinyint)
- is_learning_material (tinyint)
- created_at, created_by
- publication_status (enum: draft, private, unlisted, public)
- public_url_slug (varchar 255 UNIQUE)

Индексы:
- idx_cat_active
- idx_mode
- idx_resource_id
- idx_learning_material
- idx_created_by
```

### 3. **modx_test_questions** (1,881 вопрос)
```
Структура:
- id (int) PRIMARY KEY AUTO_INCREMENT
- test_id (int) FOREIGN KEY
- category_id (int) - может отличаться от test_category
- question_text (text)
- question_image (varchar 255)
- question_type (enum: single, multiple, matching, ordering, fill_blank, essay)
- explanation (text) - объяснение правильного ответа
- explanation_image (varchar 255)
- published (tinyint) - опубликован ли
- is_learning (tinyint) - обучающий вопрос
- sort_order (int)
- created_at (datetime)

Индексы:
- test_id
- category_id
- idx_test_sort

Типы вопросов реализованные в системе:
✓ single - выбор одного ответа
✓ multiple - выбор нескольких ответов
✓ matching - сопоставление пар
✓ ordering - упорядочение элементов
✓ fill_blank - заполнение пропусков
✓ essay - эссе с ручной проверкой
```

### 4. **modx_test_answers** (7,485 вариантов ответов)
```
Структура:
- id (int) PRIMARY KEY AUTO_INCREMENT
- question_id (int) FOREIGN KEY
- answer_text (text)
- is_correct (tinyint) - правильный ответ
- sort_order (int)

ВАЖНО: Несколько правильных ответов возможны!
```

### 5. **modx_test_sessions** (324 сессии тестирования)
```
Структура:
- id (int) PRIMARY KEY AUTO_INCREMENT
- test_id (int) FOREIGN KEY
- user_id (int) FOREIGN KEY
- mode (enum: training, exam)
- question_order (text) - JSON массив порядка вопросов
- status (enum: active, completed, cancelled)
- score (int) - полученный балл
- passed (tinyint) - пройден ли тест
- max_score (int)
- started_at (datetime) AUTO_CURRENT_TIMESTAMP
- finished_at (datetime)

Индексы:
- test_id
- idx_user
- idx_status
- idx_mode
- idx_started_at
- idx_sessions_analytics (комплексный для аналитики)
```

### 6. **modx_test_user_answers** (513 ответов пользователей)
```
Структура:
- id (int) PRIMARY KEY AUTO_INCREMENT
- session_id (int) FOREIGN KEY
- question_id (int) FOREIGN KEY
- answer_id (int) FOREIGN KEY nullable
- answer_text (text) - для текстовых ответов (эссе)
- is_correct (tinyint) - автоматически проверено
- answered_at (datetime) AUTO_CURRENT_TIMESTAMP
- answer_data (json) - структурированные данные для новых типов вопросов

Индексы:
- session_id
- question_id
- answer_id
- idx_session_question
- idx_answers_analytics
```

### 7. **modx_test_achievements** (8 достижений)
```
Структура:
- id (int) PRIMARY KEY AUTO_INCREMENT
- name (varchar 255)
- description (text)
- badge_icon (varchar 255)
- condition_type (enum: score_threshold, num_tests_passed, streak, etc.)
- condition_value (int)
- xp_reward (int) - опыт за достижение
- created_at, updated_at

Примеры:
1. "Первый тест" - пройти первый тест
2. "Отличник" - 100% правильных ответов
3. "Усердный ученик" - пройти 10 тестов
4. "Неудержимый" - серия из 7 дней активности
5. И еще 3...
```

### 8. **modx_test_knowledge_areas** (4 области знаний)
```
Структура:
- id (int) PRIMARY KEY AUTO_INCREMENT
- user_id (int) - владелец области
- name (varchar 255)
- description (text)
- test_ids (text) - JSON массив [1, 5, 12, ...]
- questions_per_session (int) default 20
- question_distribution_mode (enum: proportional, equal)
- is_active (tinyint)
- created_at, updated_at

Назначение: Пользовательские подборки тестов
```

### 9. **modx_test_level_config** (10 уровней)
```
Структура:
- id (int) PRIMARY KEY AUTO_INCREMENT
- level (int) UNIQUE - номер уровня (1-10)
- xp_required (int) - опыт для достижения уровня
- title (varchar 255) - звание (Новичок, Ученик, Эксперт и т.д.)
- perks (json) - преимущества уровня

Примеры:
Уровень 1: 0 XP - "Новичок"
Уровень 2: 100 XP - "Ученик"
Уровень 3: 250 XP - "Адепт"
...
Уровень 10: 5000 XP - "Мастер"
```

### 10. **modx_test_permissions** (3 записи)
```
Структура:
- id (int) PRIMARY KEY AUTO_INCREMENT
- test_id (int) - какой тест
- user_id (int) - какому пользователю
- granted_by (int) - кто дал доступ
- can_edit (tinyint) - может редактировать
- granted_at (datetime)

Назначение: Управление доступом к редактированию тестов
```

### 11. **modx_test_notification_templates** (8 шаблонов)
```
Структура:
- id (int) PRIMARY KEY AUTO_INCREMENT
- template_key (varchar 100) UNIQUE
- notification_type (varchar 50)
- channel (enum: system, email, push)
- subject_template (varchar 255)
- body_template (text) - поддерживает плейсхолдеры
- html_template (text)
- is_active (tinyint)
- created_at, updated_at

Шаблоны уведомлений:
1. test_completed - Тест завершен
2. achievement_earned - Получено достижение
3. level_up - Повышение уровня
4. path_step_unlocked - Разблокирован шаг пути
5. essay_reviewed - Эссе проверено
6. deadline_reminder - Напоминание о дедлайне
7. material_available - Материал доступен
8. И еще 1...
```

---

## 🔗 ПУСТЫЕ ТАБЛИЦЫ (ГОТОВЫ К ВНЕДРЕНИЮ)

### Пути обучения
```
modx_test_learning_paths (0 записей)
├─ modx_test_learning_path_steps (0)
├─ modx_test_learning_path_enrollments (0)
├─ modx_test_learning_path_progress (0)
├─ modx_test_learning_path_step_completion (0)
├─ modx_test_learning_path_achievements (0)
└─ modx_test_learning_path_user_achievements (0)
```

### Учебные материалы
```
modx_test_learning_materials (0 записей)
├─ modx_test_learning_content (0)
├─ modx_test_learning_attachments (0)
├─ modx_test_material_progress (0)
├─ modx_test_material_test_links (0)
└─ modx_test_material_tags (0)
```

### Геймификация
```
modx_test_leaderboard (0)
modx_test_user_achievements (0)
modx_test_user_experience (0)
modx_test_user_streaks (0)
modx_test_xp_history (0)
```

### Уведомления и управление
```
modx_test_notifications (0)
modx_test_notification_queue (0)
modx_test_notification_delivery (0)
modx_test_notification_preferences (0)
```

### Сертификаты
```
modx_test_certificates (0)
modx_test_certificate_templates (3 шаблона)
modx_test_certificate_signers (2 подписанта)
modx_test_certificate_signatures (0)
└─ modx_test_certificate_verifications (0)
```

### Расширенные типы вопросов
```
modx_test_question_essays (0)
modx_test_question_fill_blanks (0)
├─ modx_test_question_fill_blank_answers (0)
modx_test_question_matching_pairs (0)
└─ modx_test_question_ordering_items (0)
```

---

## 📊 SQL VIEWS (Представления для аналитики)

### 1. modx_test_category_statistics
```sql
Поля:
- category_id
- category_name
- total_tests - количество тестов в категории
- total_users - количество пользователей, тестировавшихся
- total_attempts - всего попыток
- completed_attempts - завершенных
- average_score - средний балл
- passed_count - пройдено
- pass_rate - процент прохождения
```

### 2. modx_test_question_statistics
```sql
Поля:
- question_id
- test_id
- category_id
- question_text
- question_type
- total_answers - всего ответов дано
- correct_answers - правильно ответили
- incorrect_answers - неправильно
- correct_percentage - процент правильных
- difficulty_score - сложность вопроса
```

### 3. modx_test_test_statistics
```sql
Поля:
- test_id
- test_name
- total_attempts - всего попыток
- completed_attempts - завершено
- passed_attempts - пройдено
- average_score - средний балл
- highest_score - лучший балл
- lowest_score - худший балл
- average_time_seconds - среднее время
- unique_users - уникальных пользователей
- last_attempt_date - последняя попытка
```

### 4. modx_test_user_statistics
```sql
Поля:
- user_id
- username
- total_tests_taken - всего пройдено
- tests_completed - завершено
- avg_score - средний балл
- max_score - лучший результат
- min_score - худший результат
- tests_passed - пройдено
- tests_failed - не пройдено
- perfect_scores - 100% результаты
- avg_time_spent - среднее время
- last_test_date - последний тест
- total_xp - общий опыт
- current_level - уровень
- achievements_count - количество достижений
```

---

## 🔧 ТРИГГЕРЫ (АВТОМАТИЗАЦИЯ)

### Триггеры достижений и опыта:

1. **trg_session_complete_award_xp**
   - При завершении теста автоматически начисляется опыт:
     - 90%+: 50 XP
     - 70-89%: 30 XP
     - 50-69%: 20 XP
     - <50%: 10 XP
   - Бонус за 100%: +25 XP

2. **trg_achievement_notify**
   - При получении достижения создается уведомление

3. **trg_xp_update_level** и **trg_level_up_notify**
   - Автоматическое обновление уровня при накоплении опыта
   - Создание уведомления о повышении уровня

4. **trg_enrollment_create_progress**
   - При записи на путь обучения создается запись прогресса

5. **trg_step_completion_update_progress**
   - При завершении шага обновляется прогресс пути

6. **trg_cert_issue_notify**
   - При выдаче сертификата создается уведомление

7. **trg_essay_reviewed_notify**
   - При проверке эссе создается уведомление пользователю

---

## 🔑 КЛЮЧЕВЫЕ ОТНОШЕНИЯ (RELATIONSHIPS)

```
MODX CORE:
└─ modx_users (11)
   └─ modx_member_group_member ─→ modx_membergroup_names (4 роли)

LMS СИСТЕМА:
├─ modx_test_categories (7)
│  └─ modx_test_tests (19)
│     ├─ modx_test_questions (1,881)
│     │  ├─ modx_test_answers (7,485)
│     │  └─ modx_test_user_answers (513) ←─────┐
│     │                                         │
│     └─ modx_test_sessions (324) ──┬──────────┘
│        └─ modx_test_users_answers
│
├─ modx_test_learning_paths
│  ├─ modx_test_learning_path_steps
│  ├─ modx_test_learning_path_enrollments
│  └─ modx_test_learning_path_progress
│
└─ modx_users ─────────→ modx_test_achievements
              └────────→ modx_test_leaderboard
              └────────→ modx_test_user_experience
              └────────→ modx_test_notifications
```

---

## 📝 ТИПЫ ДАННЫХ И ОГРАНИЧЕНИЯ

### Базовые типы:
```
- INT(11) для ID таблиц
- VARCHAR(255) для названий
- TEXT для описаний и контента
- TINYINT(1) для флагов (0/1)
- DECIMAL(5,2) для процентов и баллов
- ENUM для фиксированных наборов ('active', 'completed')
- JSON для структурированных данных
- DATETIME для временных меток
- TIMESTAMP AUTO_CURRENT_TIMESTAMP для created_at
```

### Кодировка:
```
MODX Core таблицы: utf8
LMS таблицы: utf8mb4 (поддержка эмодзи и спецсимволов)
```

---

## 🚀 ГОТОВЫЕ К ИСПОЛЬЗОВАНИЮ КОМПОНЕНТЫ

✅ **Полностью реализовано и работает:**
- Система тестирования (вопросы, ответы, сессии)
- Типы вопросов (6 типов)
- Система достижений
- Система уровней (10 уровней)
- Шаблоны уведомлений (8 шаблонов)
- Управление доступом
- Статистика и аналитика (4 VIEW)
- Система опыта (XP/уровни)

⏳ **Готовы к внедрению (структура есть, данных нет):**
- Пути обучения и траектории
- Учебные материалы и контент
- Таблица лидеров
- Система уведомлений в полном объеме
- Сертификаты
- Пользовательские области знаний

---

## 📈 ОБЪЕМЫ ДАННЫХ

| Таблица | Записей | Размер |
|---------|---------|--------|
| modx_test_answers | 7,485 | 1.5 MB |
| modx_test_questions | 1,881 | 2.6 MB |
| modx_test_sessions | 324 | 80 KB |
| modx_test_user_answers | 513 | 49 KB |
| modx_test_categories | 7 | <1 KB |
| modx_test_tests | 19 | <1 KB |
| modx_test_achievements | 8 | <1 KB |
| modx_test_knowledge_areas | 4 | <1 KB |

**Общий размер БД:** ~50 MB

---

## 🔍 ПРИМЕРЫ ЗАПРОСОВ

```sql
-- Получить статистику по категориям
SELECT * FROM modx_test_category_statistics;

-- Получить статистику пользователя
SELECT * FROM modx_test_user_statistics WHERE user_id = 5;

-- Получить детальную статистику вопроса
SELECT * FROM modx_test_question_statistics WHERE test_id = 3;

-- Получить все сессии пользователя
SELECT * FROM modx_test_sessions WHERE user_id = 5 ORDER BY started_at DESC;

-- Получить результаты теста
SELECT
    s.id as session_id,
    s.score,
    s.passed,
    COUNT(ua.id) as total_questions,
    SUM(ua.is_correct) as correct_answers
FROM modx_test_sessions s
LEFT JOIN modx_test_user_answers ua ON s.id = ua.session_id
WHERE s.test_id = 1
GROUP BY s.id;
```

---

**Документация составлена на основе реального дампа БД от 2025-11-20**
