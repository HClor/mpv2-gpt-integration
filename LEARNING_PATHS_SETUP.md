# Инструкция по запуску траекторий обучения

## 📋 Что было сделано

### ✅ Backend (PHP)
- Исправлены все названия API действий в контроллере
- Добавлены методы для работы со студентами:
  - `getAvailableStudents` - список всех студентов
  - `getEnrolledStudents` - студенты, записанные на траекторию
  - `bulkEnrollOnPath` - массовое назначение траекторий

### ✅ Frontend (JavaScript)
- Исправлены все вызовы API в `learning-paths.js`
- Приведены названия полей к соответствию с БД

### ✅ Диагностика и тестовые данные
- Создан файл `diagnostic_learning_paths.php` для проверки
- Создан файл `test_data_learning_paths.sql` с тестовыми траекториями

## 🚀 Шаги для запуска

### Шаг 1: Проверка структуры БД

Открой в браузере:
```
https://lmixru.beget.tech/diagnostic_learning_paths.php
```

Эта страница покажет:
- Наличие всех таблиц ✓
- Структуру таблиц ✓
- Триггеры ✓
- Текущие данные
- Примеры API вызовов

### Шаг 2: Создание тестовых данных

1. Открой phpMyAdmin
2. Выбери базу `lmixru_mpv2`
3. Перейди на вкладку SQL
4. Скопируй содержимое файла `test_data_learning_paths.sql`

⚠️ **ВАЖНО**: Перед выполнением SQL:
- Замени `item_id` в шагах на **реальные ID** материалов и тестов из твоей БД
- Проверь, что у тебя есть материалы и тесты для траекторий

Чтобы узнать ID материалов и тестов:
```sql
-- Список доступных материалов (MODX resources с template=learning-material)
SELECT id, pagetitle, alias FROM modx_site_content
WHERE template = (SELECT id FROM modx_site_templates WHERE templatename LIKE '%material%')
LIMIT 10;

-- Список доступных тестов
SELECT id, title FROM modx_test_tests
WHERE is_active = 1
ORDER BY created_at DESC
LIMIT 10;
```

Замени в SQL:
```sql
-- Было:
(@path1_id, 1, 'material', 1, ...

-- Стало (например):
(@path1_id, 1, 'material', 15, ...  -- ID реального материала
```

5. Выполни SQL

### Шаг 3: Назначение траектории студенту

#### Вариант A: Через console MODX

```php
<?php
// 1. Получи ID траектории
$pathId = 1; // ID созданной траектории

// 2. Получи ID студента
$studentId = 5; // ID студента

// 3. Назначь траекторию
$enrollmentId = LearningPathService::enrollUser($modx, $pathId, $studentId, $modx->user->id);

if ($enrollmentId) {
    echo "Студент записан на траекторию! Enrollment ID: $enrollmentId\n";

    // Проверяем прогресс
    $progress = LearningPathService::getUserProgress($modx, $pathId, $studentId);
    echo "Создан прогресс: " . print_r($progress, true);
} else {
    echo "Ошибка записи!\n";
}
```

#### Вариант B: Через API (POST запрос)

**Endpoint**: `https://lmixru.beget.tech/assets/components/testsystem/ajax/testsystem.php`

**Данные**:
```json
{
  "action": "bulkEnrollOnPath",
  "data": {
    "path_id": 1,
    "user_ids": [5, 10, 15],
    "csrf_token": "ТВОЙ_CSRF_TOKEN"
  }
}
```

**Получить список студентов**:
```json
{
  "action": "getAvailableStudents",
  "data": {
    "path_id": 1
  }
}
```

**Получить студентов на траектории**:
```json
{
  "action": "getEnrolledStudents",
  "data": {
    "path_id": 1
  }
}
```

### Шаг 4: Проверка работы триггеров

После назначения траектории, в БД должны автоматически создаться:

1. **Запись прогресса** в `modx_test_learning_path_progress`
2. **Записи для всех шагов** в `modx_test_learning_path_step_completion`

Проверь в phpMyAdmin:
```sql
-- Проверка прогресса
SELECT * FROM modx_test_learning_path_progress
WHERE user_id = 5;  -- ID студента

-- Проверка шагов
SELECT lpsc.*, lps.name
FROM modx_test_learning_path_step_completion lpsc
JOIN modx_test_learning_path_steps lps ON lps.id = lpsc.step_id
WHERE lpsc.user_id = 5
ORDER BY lps.step_number;
```

Должно быть:
- 1 запись прогресса со статусом `not_started`
- N записей шагов (где N = количество шагов в траектории)
- Первый шаг со статусом `available`, остальные `locked`

## 📊 Структура данных

### Основные таблицы

1. **modx_test_learning_paths** - траектории
2. **modx_test_learning_path_steps** - шаги траектории
3. **modx_test_learning_path_enrollments** - записи студентов
4. **modx_test_learning_path_progress** - общий прогресс
5. **modx_test_learning_path_step_completion** - прогресс по шагам
6. **modx_test_learning_path_achievements** - достижения
7. **modx_test_learning_path_user_achievements** - полученные достижения

### Триггеры

1. **trg_enrollment_create_progress** - создает прогресс при записи
2. **trg_step_completion_update_progress** - обновляет % завершения

## 🧪 Тестирование API

### Создание траектории

```bash
curl -X POST https://lmixru.beget.tech/assets/components/testsystem/ajax/testsystem.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "createPath",
    "data": {
      "name": "Тестовая траектория",
      "description": "Описание",
      "difficulty_level": "beginner",
      "estimated_hours": 10,
      "status": "published"
    }
  }'
```

### Получение списка траекторий

```bash
curl -X POST https://lmixru.beget.tech/assets/components/testsystem/ajax/testsystem.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "getPathsList",
    "data": {}
  }'
```

### Добавление шага

```bash
curl -X POST https://lmixru.beget.tech/assets/components/testsystem/ajax/testsystem.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "addStep",
    "data": {
      "path_id": 1,
      "step_type": "material",
      "item_id": 15,
      "name": "Вводный материал",
      "description": "Первый шаг",
      "is_required": 1
    }
  }'
```

## ❗ Частые проблемы

### 1. "CSRF token validation failed"
**Решение**: Добавь `csrf_token` в запрос или используй console MODX

### 2. "Test ID not found" при добавлении шага
**Решение**: Проверь, что `item_id` указывает на существующий тест/материал

### 3. Триггеры не срабатывают
**Решение**: Проверь наличие триггеров через диагностику

### 4. "Permission denied"
**Решение**: Убедись, что пользователь - админ или эксперт

## 📝 Примеры кода для console MODX

### Получить все траектории
```php
$paths = LearningPathService::getPathsList($modx);
print_r($paths);
```

### Получить траекторию с шагами
```php
$path = LearningPathService::getPath($modx, 1, true);
print_r($path);
```

### Получить прогресс студента
```php
$progress = LearningPathService::getUserProgress($modx, 1, 5);
print_r($progress);
```

### Завершить шаг
```php
$success = LearningPathService::completeStep(
    $modx,
    $progressId,
    $stepId,
    ['score' => 85, 'session_id' => 123]
);
```

### Получить статистику
```php
$stats = LearningPathService::getPathStatistics($modx, 1);
print_r($stats);
```

## 🎯 Следующие шаги

1. ✅ Выполни диагностику
2. ✅ Создай тестовые данные с реальными ID
3. ✅ Назначь траекторию тестовому студенту
4. ✅ Проверь работу триггеров
5. ⏳ Создай страницы MODX для UI (отдельная задача)
6. ⏳ Протестируй полный цикл обучения

## 📞 Что делать дальше?

После выполнения всех шагов проверки, сообщи мне:
1. Результаты диагностики
2. Успешно ли созданы тестовые данные
3. Сработали ли триггеры
4. Какие ошибки возникли (если были)

Тогда я создам страницы MODX для полноценного UI управления траекториями!
