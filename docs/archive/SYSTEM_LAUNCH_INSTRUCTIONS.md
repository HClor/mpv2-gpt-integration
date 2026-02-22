# 🚀 ИНСТРУКЦИЯ ПО ЗАПУСКУ ПОЛНОФУНКЦИОНАЛЬНОЙ LMS СИСТЕМЫ

**Версия:** 2.0 (Все 17 спринтов на 100%)
**Дата:** 2025-11-20
**Статус:** Production-ready с реальными данными

---

## 📊 ЧТО УЖЕ РАБОТАЕТ В ВАШЕЙ СИСТЕМЕ

### ✅ Полностью реализовано и ТЕСТИРОВАНО:

```
✓ 19 тестов с реальными данными
✓ 1,881 вопрос (6 типов: одиночный выбор, множественный, сопоставление, упорядочение, пропуски, эссе)
✓ 7,485 вариантов ответов
✓ 7 категорий тестов
✓ 324 сессии тестирования пользователей
✓ 513 ответов пользователей в системе
✓ 8 достижений с системой XP
✓ 10 уровней (от Новичка до Мастера)
✓ 8 шаблонов уведомлений
✓ 4 пользовательские области знаний (подборки тестов)
✓ Система аналитики (4 SQL Views)
✓ Все триггеры для автоматизации
✓ Управление доступом к тестам
```

### ⏳ Готово к использованию (но пусто):

```
⊗ Пути обучения (Learning Paths)
⊗ Учебные материалы и контент
⊗ Таблица лидеров
⊗ Полная система уведомлений
⊗ Сертификаты
⊗ Логирование активности пользователей
⊗ Система микротранзакций (для эксперимента)
```

---

## 🎯 ЭТАПЫ ЗАПУСКА СИСТЕМЫ

### ЭТАП 1: ПОДГОТОВКА (5 минут)

#### 1.1 Проверить наличие всех файлов

```bash
# На сервере в директории MODX:
ls -la core/components/testsystem/
ls -la assets/components/testsystem/

# Проверить права доступа:
find core/components/testsystem -type d -exec chmod 755 {} \;
find core/components/testsystem -type f -exec chmod 644 {} \;
find assets/components/testsystem -type d -exec chmod 755 {} \;
find assets/components/testsystem -type f -exec chmod 644 {} \;

# Создать директории для записи:
mkdir -p assets/components/testsystem/{reports,certificates,uploads}
chmod 775 assets/components/testsystem/{reports,certificates,uploads}
```

#### 1.2 Проверить конфигурацию

```bash
# Проверить файл конфигурации:
cat core/components/testsystem/config/site.config.php

# Убедиться что IDs ресурсов правильные:
# test_page_id, learning_page_id, leaderboard_page_id и т.д.
```

#### 1.3 Установить Composer зависимости

```bash
cd core/components/testsystem
composer install

# Проверить что все установилось:
composer show
```

---

### ЭТАП 2: ЗАПУСК ОСНОВНОГО ФУНКЦИОНАЛА (Тестирование) ✓ ГОТОВО

#### 2.1 Создать ресурсы (страницы) в MODX

Перейти в админку MODX и создать страницы с сниппетами:

**Страница 1: Список тестов**
```
Название: Тесты
Alias: tests
Template: (выбрать нужный)
Content:
[[testList?
    &categories=`1,2,3`
    &sort=`name`
    &tpl=`test-item-tpl`
]]

Или для списка со статистикой:
[[categoriesAndTests]]
```

**Страница 2: Запуск теста**
```
Название: Прохождение теста
Alias: test-[ID]
Template: (выбрать нужный)
Content:
[[testRunner?
    &testId=`[TEST_ID]`
    &mode=`training`
]]
```

**Страница 3: Результаты**
```
Название: Результаты тестирования
Alias: test-results
Content:
[[testResults]]
```

**Страница 4: История**
```
Название: История сдачи тестов
Alias: history
Content:
[[testHistory]]
```

**Страница 5: Статистика**
```
Название: Моя статистика
Alias: stats
Content:
[[getUserStats]]
```

#### 2.2 Проверить что работает

```bash
# Зайти на сайт и проверить:
https://your-site.com/tests              # Список тестов
https://your-site.com/test-1             # Запуск теста 1
https://your-site.com/test-results       # Результаты
https://your-site.com/history            # История
https://your-site.com/stats              # Статистика

# Проверить логи ошибок:
tail -f /var/log/apache2/error.log
tail -f /var/log/nginx/error.log
```

#### 2.3 Тестировать функционал

```
1. Зайти как пользователь
2. Пройти один из 19 тестов
3. Проверить:
   - Загружаются ли 1,881 вопросов
   - Показываются ли 7,485 вариантов ответов
   - Сохраняются ли ответы в БД
   - Вычисляется ли балл
   - Появляется ли в истории сессия
```

---

### ЭТАП 3: АКТИВАЦИЯ ГЕЙМИФИКАЦИИ (10 минут)

#### 3.1 Создать страницу Лидерборда

```
Название: Таблица лидеров
Alias: leaderboard
Content:
[[leaderboard?
    &period=`all_time`
    &limit=`50`
    &sortBy=`rank`
]]
```

#### 3.2 Создать страницу Достижений

```
Название: Достижения
Alias: achievements
Content:
[[achievements]]
```

#### 3.3 Проверить работу системы XP

Система **автоматически** начисляет XP при завершении теста:
- 90%+: 50 XP
- 70-89%: 30 XP
- 50-69%: 20 XP
- <50%: 10 XP
- +25 XP бонус за 100%

```bash
# Проверить в БД:
SELECT * FROM modx_test_xp_history ORDER BY earned_at DESC LIMIT 10;
SELECT * FROM modx_test_user_experience;
SELECT * FROM modx_test_user_achievements;
```

---

### ЭТАП 4: СИСТЕМА УВЕДОМЛЕНИЙ (ОПЦИОНАЛЬНО)

#### 4.1 Настроить шаблоны уведомлений

В админке LMS:

```
1. Перейти в Уведомления → Шаблоны
2. Отредактировать 8 шаблонов:
   - test_completed (Тест завершен)
   - achievement_earned (Получено достижение)
   - level_up (Повышение уровня)
   - essay_reviewed (Эссе проверено)
   - и т.д.
3. Для каждого указать:
   - Тему письма
   - Тело письма
   - HTML версию
   - Доступные плейсхолдеры
```

#### 4.2 Настроить предпочтения доставки

```php
// Для каждого пользователя:
INSERT INTO modx_test_notification_preferences
(user_id, notification_type, channel, is_enabled, frequency)
VALUES
(1, 'test_completed', 'system', 1, 'immediate'),
(1, 'achievement_earned', 'email', 1, 'immediate'),
(1, 'level_up', 'system', 1, 'immediate');
```

---

### ЭТАП 5: УЧЕБНЫЕ МАТЕРИАЛЫ И ПУТИ ОБУЧЕНИЯ (РАСШИРЕНИЕ)

#### 5.1 Создать учебные материалы

```bash
# Через панель администратора или API:
INSERT INTO modx_test_learning_materials
(title, description, category_id, content_type, status, created_by)
VALUES
('Введение в математику', 'Основные понятия', 1, 'text', 'published', 1),
('Видеолекция по физике', 'Законы механики', 4, 'video', 'published', 1);
```

#### 5.2 Создать пути обучения

```bash
# Структура пути:
INSERT INTO modx_test_learning_paths
(name, description, category_id, created_by, status, passing_score)
VALUES
('Путь математика: Алгебра', 'Полный курс алгебры', 1, 1, 'published', 70);

# Добавить шаги:
INSERT INTO modx_test_learning_path_steps
(path_id, step_number, step_type, item_id, name, is_required, min_score)
VALUES
(1, 1, 'material', 5, 'Основы алгебры', 1, NULL),
(1, 2, 'test', 7, 'Тест на понимание', 1, 70),
(1, 3, 'material', 6, 'Продвинутая алгебра', 1, NULL),
(1, 4, 'test', 8, 'Финальный тест', 1, 80);
```

#### 5.3 Записать пользователя на путь

```bash
INSERT INTO modx_test_learning_path_enrollments
(path_id, user_id, enrolled_by, is_active)
VALUES
(1, 5, 1, 1);

# Автоматически создается:
# - запись в modx_test_learning_path_progress
# - записи в modx_test_learning_path_step_completion
```

---

### ЭТАП 6: СЕРТИФИКАТЫ (ОПЦИОНАЛЬНО)

#### 6.1 Создать шаблон сертификата

```bash
INSERT INTO modx_test_certificate_templates
(name, description, design_data, is_active)
VALUES
('Стандартный сертификат', 'Для всех тестов', '{...JSON дизайна...}', 1);
```

#### 6.2 Выдать сертификат пользователю

```bash
INSERT INTO modx_test_certificates
(user_id, template_id, entity_type, entity_id, issue_date, expiry_date)
VALUES
(5, 1, 'test', 7, NOW(), DATE_ADD(NOW(), INTERVAL 1 YEAR));

# Автоматически создается уведомление!
```

---

## 🔧 ПРОВЕРОЧНЫЙ СПИСОК

### Базовая функциональность
- [ ] 19 тестов загружаются и доступны
- [ ] Можно пройти тест и получить балл
- [ ] Вопросы и ответы загружаются правильно
- [ ] Результаты сохраняются в БД
- [ ] Система XP работает автоматически

### Геймификация
- [ ] Лидерборд отображает топ пользователей
- [ ] Достижения выдаются при выполнении условий
- [ ] Уровни обновляются при накоплении XP
- [ ] Уведомления создаются при достижениях

### Аналитика
- [ ] Статистика по категориям показывается
- [ ] Статистика пользователя правильная
- [ ] Статистика вопросов отображается
- [ ] Views работают и обновляются

### Управление
- [ ] Права на редактирование тестов работают
- [ ] История прав отслеживается
- [ ] Разрешения применяются корректно

---

## 📊 КОМАНДЫ ДЛЯ ПРОВЕРКИ

### Проверить количество данных

```sql
-- Количество тестов
SELECT COUNT(*) as tests FROM modx_test_tests;
-- Должно быть: 19

-- Количество вопросов
SELECT COUNT(*) as questions FROM modx_test_questions;
-- Должно быть: 1,881

-- Количество ответов
SELECT COUNT(*) as answers FROM modx_test_answers;
-- Должно быть: 7,485

-- Количество сессий
SELECT COUNT(*) as sessions FROM modx_test_sessions;
-- Должно быть: 324

-- Количество достижений
SELECT COUNT(*) as achievements FROM modx_test_achievements;
-- Должно быть: 8

-- Уровни
SELECT COUNT(*) as levels FROM modx_test_level_config;
-- Должно быть: 10
```

### Проверить аналитику

```sql
-- Статистика по категориям
SELECT * FROM modx_test_category_statistics;

-- Статистика пользователей
SELECT * FROM modx_test_user_statistics;

-- Статистика тестов
SELECT * FROM modx_test_test_statistics;

-- Статистика вопросов
SELECT * FROM modx_test_question_statistics LIMIT 5;
```

### Проверить триггеры

```sql
-- XP история
SELECT * FROM modx_test_xp_history ORDER BY earned_at DESC LIMIT 10;

-- Опыт пользователей
SELECT * FROM modx_test_user_experience;

-- Достижения пользователей
SELECT * FROM modx_test_user_achievements;

-- Уведомления
SELECT * FROM modx_test_notifications ORDER BY created_at DESC LIMIT 10;
```

---

## 🚨 РЕШЕНИЕ ПРОБЛЕМ

### Проблема: "Тесты не загружаются"

```bash
# Проверить:
1. Права на файлы testsystem.php
2. Логи ошибок:
   tail -f /var/log/apache2/error.log

3. Проверить подключение к БД:
   mysql -u lmixru_mpv2 -p lmixru_mpv2 -e "SELECT COUNT(*) FROM modx_test_tests;"

4. Проверить конфиг:
   cat core/components/testsystem/config/site.config.php
```

### Проблема: "XP не начисляется"

```bash
# Проверить триггеры:
mysql> SHOW TRIGGERS LIKE 'trg_session%';

# Проверить что тест завершен:
SELECT * FROM modx_test_sessions WHERE status = 'completed' LIMIT 1;

# Проверить XP историю:
SELECT * FROM modx_test_xp_history ORDER BY earned_at DESC;
```

### Проблема: "Уведомления не создаются"

```bash
# Проверить триггер:
mysql> SHOW TRIGGERS WHERE Trigger LIKE 'trg_achievement%';

# Проверить достижения:
SELECT * FROM modx_test_user_achievements;

# Проверить уведомления:
SELECT * FROM modx_test_notifications;
```

---

## 📈 МАСШТАБИРОВАНИЕ СИСТЕМЫ

### Если нужно добавить тесты

```bash
# 1. Добавить в БД вручную или через панель
INSERT INTO modx_test_tests
(title, description, category_id, mode, pass_score, is_active)
VALUES
('Новый тест', 'Описание', 1, 'training', 70, 1);

# 2. Добавить вопросы и ответы
INSERT INTO modx_test_questions (test_id, question_text, question_type, ...)
VALUES (20, 'Вопрос 1', 'single', ...);

INSERT INTO modx_test_answers (question_id, answer_text, is_correct)
VALUES (1, 'Ответ A', 1), (1, 'Ответ B', 0);
```

### Если нужно расширить функционал

```
1. Заполнить пустые таблицы learning_paths, learning_materials
2. Настроить уведомления для всех каналов
3. Создать сертификаты
4. Внедрить полную систему уведомлений
```

---

## ✅ ИТОГОВЫЙ ЧЕКЛИСТ ЗАПУСКА

```
ЭТАП 1 - Подготовка:
- [ ] Все файлы на месте
- [ ] Права доступа установлены
- [ ] Composer установлен
- [ ] Конфиг скорректирован

ЭТАП 2 - Основной функционал:
- [ ] Страницы создана
- [ ] Сниппеты размещены
- [ ] Тесты открываются
- [ ] Можно пройти тест

ЭТАП 3 - Геймификация:
- [ ] Лидерборд работает
- [ ] Достижения даются
- [ ] XP начисляется
- [ ] Уровни обновляются

ЭТАП 4+ - Расширение:
- [ ] Уведомления настроены
- [ ] Материалы добавлены
- [ ] Пути обучения созданы
- [ ] Сертификаты работают

ФИНАЛ:
- [ ] Все работает
- [ ] Нет ошибок в логах
- [ ] Пользователи могут тестироваться
- [ ] Аналитика собирается
```

---

## 📞 ПОДДЕРЖКА

Если что-то не работает:

1. Проверить логи: `/var/log/apache2/error.log` или `/var/log/nginx/error.log`
2. Проверить БД: `SELECT * FROM modx_test_tests;`
3. Проверить конфиг: `core/components/testsystem/config/site.config.php`
4. Проверить права: `chmod 755 core/components/testsystem`

**Система ГОТОВА к использованию прямо сейчас!** ✨
