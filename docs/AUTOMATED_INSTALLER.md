# 🤖 АВТОМАТИЧЕСКИЙ ИНСТАЛЛЯТОР LMS SYSTEM v2.0

**Статус:** Ready to use
**Дата:** 2025-11-20

---

## 📋 ЧТО ДЕЛАЕТ ИНСТАЛЛЯТОР

Автоматически выполнит всё из SYSTEM_LAUNCH_INSTRUCTIONS:

✅ **ЭТАП 1: Проверка системы**
- Проверить MODX загружен
- Проверить наличие шаблона ID 9
- Проверить наличие bootstrap файла

✅ **ЭТАП 2: Создание ресурсов**
- Создать 7 страниц с правильными сниппетами:
  - /tests (Список тестов)
  - /test-run (Прохождение теста)
  - /results (Результаты)
  - /history (История)
  - /stats (Статистика)
  - /leaderboard (Таблица лидеров)
  - /achievements (Достижения)

✅ **ЭТАП 3: Проверка БД**
- Проверить наличие всех 12+ таблиц LMS

✅ **ЭТАП 4: Инициализация данных**
- Создать 10 уровней опыта (если нет)
- Создать 8 шаблонов уведомлений (если нет)
- Проверить достижения

✅ **ЭТАП 5: Финальная проверка**
- Вывести статистику
- Показать результаты

---

## 🚀 КАК ИСПОЛЬЗОВАТЬ

### Способ 1: MODX Console (САМЫЙ ПРОСТОЙ) ⭐

```php
// 1. Откройте админку MODX
// 2. Перейдите в Tools → MODX Console (Инструменты → Консоль)
// 3. Скопируйте и выполните эту команду:

require_once MODX_CORE_PATH . 'components/testsystem/installer.php';
$installer = new LMSInstaller($modx);
$installer->run();
```

**Ожидаемый результат:**
```
========================================
🚀 LMS SYSTEM v2.0 - АВТОМАТИЧЕСКИЙ ИНСТАЛЛЯТОР
========================================

[ЭТАП 1/5] Проверка системы...
   ✓ MODX инициализирован
   ✓ Шаблон ID 9 найден: 'LMS Template'
   ✓ Bootstrap файл найден

[ЭТАП 2/5] Создание ресурсов в MODX...
   ✓ Ресурс создан: 'Тесты' (ID: 50, alias: tests)
   ✓ Ресурс создан: 'Прохождение теста' (ID: 51, alias: test-run)
   ...

[ЭТАП 3/5] Проверка базы данных...
   ✓ Таблица 'modx_test_categories' существует
   ✓ Таблица 'modx_test_tests' существует
   ...

[ЭТАП 4/5] Инициализация данных...
   ✓ Уровни уже созданы (10 записей)
   ✓ Шаблоны уведомлений уже созданы (8 записей)

[ЭТАП 5/5] Финальная проверка...
   ✓ Ресурсов создано/найдено
   ✓ Найдено 19 тестов в БД
   ✓ Найдено 10 уровней в БД

========================================
📊 РЕЗУЛЬТАТЫ УСТАНОВКИ
========================================

✅ УСПЕШНО:
   ✓ MODX инициализирован
   ✓ Шаблон ID 9 найден
   ... (остальные сообщения)

========================================
✨ УСТАНОВКА ЗАВЕРШЕНА
========================================

🚀 СЛЕДУЮЩИЕ ШАГИ:
1. Откройте админку MODX
2. Перейдите на сайт и проверьте страницы:
   - /tests (Список тестов)
   - /leaderboard (Таблица лидеров)
   - /achievements (Достижения)
   - /stats (Статистика)
3. Пройдите один из 19 тестов
4. Проверьте что XP начисляется

🎉 ВСЕ ГОТОВО К ИСПОЛЬЗОВАНИЮ!
```

---

### Способ 2: Через Web URL

```
http://your-site.com/?installer=run
```

⚠️ **Внимание:** Нужно будет добавить обработчик на главной странице:

```php
// В index.php добавьте:
if (isset($_GET['installer']) && $_GET['installer'] == 'run') {
    require_once MODX_CORE_PATH . 'components/testsystem/installer.php';
    $installer = new LMSInstaller($modx);
    $installer->run();
    exit;
}
```

---

### Способ 3: Через PHP CLI (Командная строка)

```bash
# На сервере в директории MODX:
cd /path/to/modx
php -d display_errors=1 -r "
    define('MODX_CORE_PATH', dirname(__FILE__) . '/core/');
    define('MODX_BASE_URL', '/');
    require_once MODX_CORE_PATH . 'components/testsystem/installer.php';
    \$installer = new LMSInstaller();
    \$installer->run();
"
```

---

## ⚠️ ПРЕДВАРИТЕЛЬНЫЕ ТРЕБОВАНИЯ

Перед запуском инсталлятора убедитесь:

### 1. Таблицы БД созданы ✓

```bash
# Проверить что таблицы существуют:
mysql -u lmixru_mpv2 -pR04nCgeh*xIZ lmixru_mpv2 -e "
SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA='lmixru_mpv2'
AND TABLE_NAME LIKE 'modx_test_%'
LIMIT 5;"
```

Если таблиц нет, выполните:
```bash
mysql -u lmixru_mpv2 -pR04nCgeh*xIZ lmixru_mpv2 < core/components/testsystem/sql/FULL_INSTALLATION_FIXED.sql
```

### 2. Шаблон ID 9 существует

```bash
# Проверить:
mysql -u lmixru_mpv2 -pR04nCgeh*xIZ lmixru_mpv2 -e "
SELECT id, templatename FROM modx_site_templates WHERE id=9;"
```

**Если шаблона нет:**

```bash
# Создать простой шаблон (только контент):
mysql -u lmixru_mpv2 -pR04nCgeh*xIZ lmixru_mpv2 << 'EOF'
INSERT INTO modx_site_templates (id, templatename, description, content)
VALUES (9, 'LMS Template', 'Шаблон для LMS системы', '[*content*]');
EOF
```

### 3. Bootstrap файл есть

```bash
# Проверить:
ls -la core/components/testsystem/bootstrap.php
```

Если файла нет - файлы не скопированы корректно.

### 4. Права доступа установлены

```bash
# Установить права:
chmod 755 core/components/testsystem
chmod 644 core/components/testsystem/*.php
chmod 755 assets/components/testsystem
chmod 644 assets/components/testsystem/ajax/*.php
```

---

## 🔧 ПАРАМЕТРЫ ИНСТАЛЛЯТОРА

### Изменить шаблон по умолчанию

```php
// Если хотите использовать другой шаблон:
$installer = new LMSInstaller($modx);
$installer->templateId = 5;  // ← Измените ID
$installer->run();
```

### Изменить родительский ресурс

```php
// По умолчанию создает в корне (parent=0)
// Если нужно создать в подпапке:
$installer = new LMSInstaller($modx);
$installer->parentId = 10;  // ← ID родительского ресурса
$installer->run();
```

---

## 📊 ПРОВЕРКА ПОСЛЕ УСТАНОВКИ

### 1. Проверить ресурсы

```bash
# Должны быть созданы эти alias:
mysql -u lmixru_mpv2 -pR04nCgeh*xIZ lmixru_mpv2 -e "
SELECT id, pagetitle, alias FROM modx_site_content
WHERE alias IN ('tests', 'test-run', 'leaderboard', 'achievements', 'stats');"
```

### 2. Проверить снипеты в контенте

```bash
# Должны быть вызовы сниппетов:
mysql -u lmixru_mpv2 -pR04nCgeh*xIZ lmixru_mpv2 -e "
SELECT id, pagetitle, alias,
SUBSTRING(content, 1, 50) as content_preview
FROM modx_site_content
WHERE alias IN ('tests', 'leaderboard', 'achievements');"
```

### 3. Проверить уровни

```bash
# Должно быть 10 уровней:
mysql -u lmixru_mpv2 -pR04nCgeh*xIZ lmixru_mpv2 -e "
SELECT COUNT(*) as level_count FROM modx_test_level_config;"
```

### 4. Проверить шаблоны уведомлений

```bash
# Должно быть 8 шаблонов:
mysql -u lmixru_mpv2 -pR04nCgeh*xIZ lmixru_mpv2 -e "
SELECT COUNT(*) as template_count FROM modx_test_notification_templates;"
```

### 5. Протестировать на фронте

```
1. Откройте: http://your-site.com/tests
2. Должен загрузиться список из 19 тестов
3. Нажмите на тест
4. Должна загрузиться страница с вопросами
5. Ответьте на вопросы
6. Должен вычислиться балл и XP
```

---

## 🐛 РЕШЕНИЕ ПРОБЛЕМ

### Проблема: "MODX не инициализирован"

**Решение:** Запустить через MODX Console, а не напрямую

```php
// ✗ Неправильно:
require_once 'installer.php';

// ✓ Правильно (в консоли MODX):
require_once MODX_CORE_PATH . 'components/testsystem/installer.php';
$installer = new LMSInstaller($modx);
$installer->run();
```

---

### Проблема: "Шаблон ID 9 не найден"

**Решение:** Создать шаблон или указать правильный ID

```bash
# Опция 1: Создать шаблон ID 9
mysql -u lmixru_mpv2 -pR04nCgeh*xIZ lmixru_mpv2 << 'EOF'
INSERT INTO modx_site_templates (id, templatename, content)
VALUES (9, 'LMS Template', '[*content*]');
EOF

# Опция 2: Использовать существующий шаблон
SELECT id, templatename FROM modx_site_templates LIMIT 10;
# Потом скорректировать в коде $installer->templateId = 3;
```

---

### Проблема: "Таблицы не найдены"

**Решение:** Создать таблицы

```bash
# Выполнить SQL установку:
mysql -u lmixru_mpv2 -pR04nCgeh*xIZ lmixru_mpv2 < core/components/testsystem/sql/FULL_INSTALLATION_FIXED.sql

# Проверить:
mysql -u lmixru_mpv2 -pR04nCgeh*xIZ lmixru_mpv2 -e "SELECT COUNT(*) FROM modx_test_tests;"
```

---

### Проблема: "Ошибка при создании ресурса"

**Решение:** Проверить права

```bash
# Установить права на файлы:
chmod 755 core/components/testsystem
chmod 644 core/components/testsystem/*.php

# Проверить конфиг:
cat core/components/testsystem/config/site.config.php
```

---

## ✅ ФИНАЛЬНЫЙ ЧЕКЛИСТ

```
Перед запуском инсталлятора:
□ Таблицы БД созданы (FULL_INSTALLATION_FIXED.sql)
□ Шаблон ID 9 существует или создан
□ Bootstrap файл есть в core/components/testsystem/
□ Права доступа установлены (755 для директорий)
□ Installer файл скопирован

Запуск:
□ Открыть MODX Console (Tools → MODX Console)
□ Скопировать и выполнить код из Способ 1 выше
□ Ждать завершения (30 секунд)
□ Смотреть результаты

После установки:
□ Открыть http://your-site.com/tests
□ Проверить что загружается список тестов
□ Пройти один тест
□ Проверить результаты и XP
□ Проверить /leaderboard и /achievements

✅ ГОТОВО!
```

---

## 📞 ЕСЛИ ЧТО-ТО НЕ РАБОТАЕТ

1. Проверить логи: `/var/log/apache2/error.log`
2. Включить отладку: `error_reporting(E_ALL);`
3. Запустить SQL скрипт вручную
4. Проверить права доступа на файлы
5. Убедиться что шаблон существует

**Инсталлятор полностью готов к использованию!** 🚀
