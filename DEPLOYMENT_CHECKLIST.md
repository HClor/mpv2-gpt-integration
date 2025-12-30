# Чек-лист развёртывания исправлений

## Что исправлено

### 1. Автоматическое завершение тестов
- **Проблема**: Тесты в training режиме не завершались автоматически
- **Решение**: Добавлен авто-финиш через 2 секунды после последнего вопроса
- **Файлы**: `assets/components/testsystem/js/tsrunner.js`

### 2. Блокировка UPDATE сессий триггером БД
- **Проблема**: Триггер `trg_session_complete_award_xp` блокировал обновление статуса теста
- **Решение**: Логика XP и streak перенесена в код приложения, триггер отключён
- **Файлы**: `assets/components/testsystem/controllers/SessionController.php`

### 3. Автоматическая выдача сертификатов
- **Проблема**: Сертификаты не создавались при завершении теста
- **Решение**: Добавлен метод `issueCertificateForTest()` в SessionController
- **Файлы**: `assets/components/testsystem/controllers/SessionController.php`

### 4. Отображение сертификатов во фронтенде
- **Проблема**: Сниппет myCertificates использовал неправильные поля БД
- **Решение**: Исправлен SQL запрос и логика определения статуса
- **Файлы**: Миграция `core/components/testsystem/migrations/update_myCertificates_snippet.php`

---

## Шаги развёртывания на сервере

### Шаг 1: Обновление кода
```bash
cd /path/to/your/site
git fetch origin
git checkout claude/fix-test-display-bug-7PAWR
git pull
```

### Шаг 2: Отключение триггера БД
Выполните в MySQL или через MODX console:
```sql
DROP TRIGGER IF EXISTS trg_session_complete_award_xp;
```

**Важно**: Триггер больше не нужен, вся логика теперь в коде!

### Шаг 3: Обновление сниппета myCertificates
```bash
php core/components/testsystem/migrations/update_myCertificates_snippet.php
```

Или выполните в MODX console:
```php
require_once MODX_CORE_PATH . 'components/testsystem/migrations/update_myCertificates_snippet.php';
```

### Шаг 4: Очистка кешей
1. В MODX админке: **Управление → Очистить кеш**
2. В браузере: **Ctrl+Shift+R** (жёсткая перезагрузка)

### Шаг 5: Тестирование
1. Зайдите под тестовым пользователем
2. Пройдите тест в режиме training
3. Убедитесь что:
   - ✅ Тест автоматически завершается после последнего вопроса
   - ✅ Результат сохраняется (status='completed', score=X%)
   - ✅ Тест отображается в `[[!testHistory]]`
   - ✅ Статистика обновляется в `[[!myAchievements]]`
   - ✅ Сертификат создаётся и отображается в `[[!myCertificates]]`
   - ✅ XP начисляется в `[[!userProfile]]`

---

## Проверка после развёртывания

### Проверка 1: Сессия завершается корректно
```php
// Последняя сессия пользователя
$prefix = $modx->getOption('table_prefix') . 'test_';
$stmt = $modx->query("
    SELECT id, status, score, passed, finished_at
    FROM {$prefix}sessions
    WHERE user_id = 2
    ORDER BY id DESC
    LIMIT 1
");
$s = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Status: {$s['status']} (должно быть 'completed')\n";
echo "Score: {$s['score']}% (должен быть реальный балл)\n";
echo "Finished: {$s['finished_at']} (должна быть дата)\n";
```

### Проверка 2: XP начислен
```php
$stmt = $modx->query("
    SELECT user_id, xp_amount, reason, created_at
    FROM {$prefix}xp_history
    WHERE user_id = 2
    ORDER BY created_at DESC
    LIMIT 1
");
$xp = $stmt->fetch(PDO::FETCH_ASSOC);
echo "XP: {$xp['xp_amount']} за {$xp['reason']}\n";
```

### Проверка 3: Сертификат выдан
```php
$stmt = $modx->query("
    SELECT certificate_number, score, issued_at
    FROM {$prefix}certificates
    WHERE user_id = 2 AND entity_type = 'test'
    ORDER BY issued_at DESC
    LIMIT 1
");
$cert = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Сертификат: {$cert['certificate_number']}, балл: {$cert['score']}%\n";
```

---

## Откат (если что-то пошло не так)

### Откат кода
```bash
git checkout main
```

### Восстановление триггера
Если нужно вернуть триггер (НЕ РЕКОМЕНДУЕТСЯ, т.к. он блокирует UPDATE):
```sql
-- Создайте триггер заново из бэкапа
-- (см. файл docs/database/triggers/trg_session_complete_award_xp.sql)
```

---

## Известные ограничения

1. **Старые "зависшие" сессии** - не будут исправлены автоматически. Если есть старые сессии со status='active', но все вопросы отвечены, их нужно обновить вручную или через скрипт миграции.

2. **Сертификаты для старых тестов** - не создаются автоматически. Только новые пройденные тесты получат сертификаты.

3. **Триггер удалён навсегда** - вся логика теперь в коде. Если откатить код, нужно будет вернуть триггер.

---

## Контакты для вопросов

Если возникли проблемы при развёртывании:
1. Проверьте логи PHP: `/var/log/php-fpm/error.log` или `/var/log/apache2/error.log`
2. Ищите строки с `[CertificateService]` и `finishTest called`
3. Проверьте консоль браузера при прохождении теста
