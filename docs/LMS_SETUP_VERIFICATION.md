# Полная проверка и настройка LMS системы

## Описание

Этот документ содержит скрипты для полной проверки и настройки всех компонентов LMS системы в MODX.

---

## 🔍 СКРИПТ 1: Полная диагностика системы

Запусти в **MODX Console**:

```php
echo "═══════════════════════════════════════════════════════════════════\n";
echo "🔍 ПОЛНАЯ ДИАГНОСТИКА LMS СИСТЕМЫ\n";
echo "═══════════════════════════════════════════════════════════════════\n\n";

$prefix = $modx->getOption('table_prefix');

// 1. ПРОВЕРКА ФАЙЛОВ СНИППЕТОВ
echo "[1/5] ПРОВЕРКА ФАЙЛОВ СНИППЕТОВ\n";
echo "────────────────────────────────────────────────────────────────\n";

$requiredSnippets = [
    'categoriesAndTests.php' => 'Список категорий и тестов',
    'testRunner.php' => 'Интерфейс прохождения теста',
    'testResults.php' => 'Результаты теста',
    'testHistory.php' => 'История тестов',
    'getUserStats.php' => 'Статистика пользователя',
    'leaderboard.php' => 'Таблица лидеров',
    'achievements.php' => 'Достижения пользователя',
];

$snippetsDir = $_SERVER['DOCUMENT_ROOT'] . '/core/elements/snippets/';
$missingFiles = [];

foreach ($requiredSnippets as $filename => $description) {
    $filepath = $snippetsDir . $filename;
    $exists = file_exists($filepath) ? '✓' : '✗';
    $status = file_exists($filepath) ? '' : ' [ОТСУТСТВУЕТ!]';

    printf("  %s %-35s | %s\n", $exists, $filename, $description);

    if (!file_exists($filepath)) {
        $missingFiles[] = $filename;
    }
}

if (!empty($missingFiles)) {
    echo "\n  ⚠️  ОТСУТСТВУЮТ ФАЙЛЫ: " . implode(', ', $missingFiles) . "\n";
} else {
    echo "\n  ✓ ВСЕ ФАЙЛЫ СНИППЕТОВ НАЙДЕНЫ\n";
}

// 2. ПРОВЕРКА MODX РЕСУРСОВ (СТРАНИЦ)
echo "\n[2/5] ПРОВЕРКА MODX РЕСУРСОВ\n";
echo "────────────────────────────────────────────────────────────────\n";

$expectedPages = [
    35 => ['alias' => 'tests', 'snippet' => 'categoriesAndTests'],
    155 => ['alias' => 'test-run', 'snippet' => 'testRunner'],
    156 => ['alias' => 'results', 'snippet' => 'testResults'],
    157 => ['alias' => 'history', 'snippet' => 'testHistory'],
    158 => ['alias' => 'stats', 'snippet' => 'getUserStats'],
    159 => ['alias' => 'leaderboard', 'snippet' => 'leaderboard'],
    34 => ['alias' => 'leaderboard', 'snippet' => 'leaderboard'],
];

$pageIssues = [];

foreach ($expectedPages as $id => $expected) {
    $stmt = $modx->query("
        SELECT id, pagetitle, alias, content, deleted, published, template
        FROM modx_site_content
        WHERE id = " . (int)$id
    );

    if ($stmt === false) {
        printf("  ✗ ID %-3d | Ошибка при запросе\n", $id);
        continue;
    }

    $page = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$page) {
        printf("  ✗ ID %-3d | СТРАНИЦА НЕ НАЙДЕНА\n", $id);
        $pageIssues[] = "ID $id не найдена";
        continue;
    }

    $status = '✓';
    $issues = [];

    if ($page['deleted']) {
        $status = '✗';
        $issues[] = 'УДАЛЕНА';
    }

    if (!$page['published']) {
        $status = '✗';
        $issues[] = 'ЧЕРНОВИК';
    }

    if (empty($page['content'])) {
        $status = '✗';
        $issues[] = 'ПУСТО';
    } elseif (strpos($page['content'], $expected['snippet']) === false) {
        $status = '✗';
        $issues[] = 'НЕПРАВИЛЬНЫЙ СНИППЕТ';
    }

    $issueText = empty($issues) ? '' : ' [' . implode(', ', $issues) . ']';

    printf("  %s ID %-3d | %-25s | %s%s\n",
        $status,
        $id,
        "'" . $page['alias'] . "'",
        $page['pagetitle'],
        $issueText
    );

    if (!empty($issues)) {
        $pageIssues[] = "ID $id ({$page['alias']}): " . implode(', ', $issues);
    }
}

if (!empty($pageIssues)) {
    echo "\n  ⚠️  ПРОБЛЕМЫ С СТРАНИЦАМИ:\n";
    foreach ($pageIssues as $issue) {
        echo "     - " . $issue . "\n";
    }
} else {
    echo "\n  ✓ ВСЕ СТРАНИЦЫ НАСТРОЕНЫ ПРАВИЛЬНО\n";
}

// 3. ПРОВЕРКА БД ТАБЛИЦ
echo "\n[3/5] ПРОВЕРКА БД ТАБЛИЦ LMS\n";
echo "────────────────────────────────────────────────────────────────\n";

$requiredTables = [
    'modx_test_categories' => 'Категории тестов',
    'modx_test_tests' => 'Тесты',
    'modx_test_questions' => 'Вопросы',
    'modx_test_answers' => 'Ответы',
    'modx_test_sessions' => 'Сессии тестирования',
    'modx_test_user_answers' => 'Ответы пользователей',
    'modx_test_achievements' => 'Достижения',
    'modx_test_level_config' => 'Конфигурация уровней',
    'modx_test_permissions' => 'Права доступа',
    'modx_test_notifications' => 'Уведомления',
];

$stmt = $modx->query("
    SELECT TABLE_NAME
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
");

$existingTables = [];
if ($stmt !== false) {
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existingTables[] = $row['TABLE_NAME'];
    }
}

$missingTables = [];

foreach ($requiredTables as $tableName => $description) {
    $exists = in_array($tableName, $existingTables) ? '✓' : '✗';
    printf("  %s %-40s | %s\n", $exists, $tableName, $description);

    if (!in_array($tableName, $existingTables)) {
        $missingTables[] = $tableName;
    }
}

if (!empty($missingTables)) {
    echo "\n  ✗ ОТСУТСТВУЮТ ТАБЛИЦЫ: " . implode(', ', $missingTables) . "\n";
    echo "  ⚠️  Нужно выполнить установку БД из FULL_INSTALLATION_FIXED.sql\n";
} else {
    echo "\n  ✓ ВСЕ ТАБЛИЦЫ ПРИСУТСТВУЮТ\n";
}

// 4. ПРОВЕРКА ДАННЫХ В БД
echo "\n[4/5] ПРОВЕРКА ДАННЫХ В БД\n";
echo "────────────────────────────────────────────────────────────────\n";

$dataChecks = [
    'modx_test_categories' => 'Категории',
    'modx_test_tests' => 'Тесты',
    'modx_test_questions' => 'Вопросы',
    'modx_test_sessions' => 'Сессии',
    'modx_users' => 'Пользователи',
];

foreach ($dataChecks as $table => $label) {
    if (!in_array($table, $existingTables)) {
        printf("  ? %-35s | Таблица не найдена\n", $label);
        continue;
    }

    $stmt = $modx->query("SELECT COUNT(*) as cnt FROM " . $table);

    if ($stmt !== false) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $count = $row['cnt'];
        $status = $count > 0 ? '✓' : '○';
        printf("  %s %-35s | %d записей\n", $status, $label, $count);
    }
}

// 5. ПРОВЕРКА ПРАВ ПОЛЬЗОВАТЕЛЯ ID 2
echo "\n[5/5] ПРОВЕРКА ДОСТУПА ПОЛЬЗОВАТЕЛЯ ID 2\n";
echo "────────────────────────────────────────────────────────────────\n";

$stmt = $modx->query("
    SELECT u.username, ua.email
    FROM modx_users u
    LEFT JOIN modx_user_attributes ua ON ua.internalKey = u.id
    WHERE u.id = 2
");

if ($stmt !== false && $row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    printf("  ✓ Пользователь найден: %s (%s)\n", $row['username'], $row['email'] ?? 'нет email');

    // Проверить что он может видеть тесты
    $stmt = $modx->query("
        SELECT COUNT(*) as cnt
        FROM modx_test_tests
        WHERE is_public = 1 OR created_by = 2
    ");

    if ($stmt !== false) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        printf("  ✓ Доступных тестов: %d\n", $row['cnt']);
    }
} else {
    printf("  ✗ Пользователь ID 2 не найден\n");
}

// ИТОГОВЫЙ СТАТУС
echo "\n═══════════════════════════════════════════════════════════════════\n";
echo "✓ ДИАГНОСТИКА ЗАВЕРШЕНА\n";
echo "═══════════════════════════════════════════════════════════════════\n";
```

---

## 🔧 СКРИПТ 2: Автоматическое исправление проблем

Запусти в **MODX Console** (исправляет обнаруженные проблемы):

```php
echo "═══════════════════════════════════════════════════════════════════\n";
echo "🔧 АВТОМАТИЧЕСКОЕ ИСПРАВЛЕНИЕ ПРОБЛЕМ LMS\n";
echo "═══════════════════════════════════════════════════════════════════\n\n";

$fixed = 0;
$prefix = $modx->getOption('table_prefix');

// Исправление 1: Удалить дублирующуюся страницу 147
echo "[1] Проверка дублирования страницы 147...\n";
$stmt = $modx->query("SELECT id, deleted FROM modx_site_content WHERE id = 147");

if ($stmt !== false && $page = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if (!$page['deleted']) {
        echo "    Удаление ID 147...\n";
        $modx->exec("UPDATE modx_site_content SET deleted = 1 WHERE id = 147");
        $fixed++;
        echo "    ✓ Готово\n";
    } else {
        echo "    ✓ Уже удалена\n";
    }
}

// Исправление 2: Убедиться что все страницы опубликованы
echo "\n[2] Проверка статуса публикации...\n";
$pageIds = [35, 155, 156, 157, 158, 159, 34];

foreach ($pageIds as $id) {
    $stmt = $modx->query("SELECT published FROM modx_site_content WHERE id = " . (int)$id);

    if ($stmt !== false && $page = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!$page['published']) {
            echo "    Публикация ID $id...\n";
            $modx->exec("UPDATE modx_site_content SET published = 1 WHERE id = " . (int)$id);
            $fixed++;
        }
    }
}
echo "    ✓ Готово\n";

// Исправление 3: Убедиться что все страницы имеют правильные сниппеты
echo "\n[3] Проверка содержимого страниц...\n";

$pageSnippets = [
    35 => '[[!categoriesAndTests]]',
    155 => '[[!testRunner]]',
    156 => '[[!testResults]]',
    157 => '[[!testHistory]]',
    158 => '[[!getUserStats]]',
    159 => '[[!leaderboard?&period=`all_time`&limit=`50`]]',
    34 => '[[!leaderboard]]',
];

foreach ($pageSnippets as $id => $snippet) {
    $stmt = $modx->query("SELECT content FROM modx_site_content WHERE id = " . (int)$id);

    if ($stmt !== false && $page = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (empty($page['content']) || strpos($page['content'], substr($snippet, 3, -2)) === false) {
            echo "    Обновление ID $id контентом: $snippet\n";
            $modx->exec("UPDATE modx_site_content SET content = " . $modx->quote($snippet) . " WHERE id = " . (int)$id);
            $fixed++;
        }
    }
}
echo "    ✓ Готово\n";

// Исправление 4: Очистить кеш
echo "\n[4] Очистка кеша MODX...\n";
$modx->cacheManager->clearCache();
echo "    ✓ Кеш очищен\n";

// Результаты
echo "\n═══════════════════════════════════════════════════════════════════\n";
echo "✓ ИСПРАВЛЕНО ПРОБЛЕМ: $fixed\n";
echo "✓ СИСТЕМА ВОССТАНОВЛЕНА\n";
echo "═══════════════════════════════════════════════════════════════════\n";
```

---

## ✅ Чек-лист после восстановления

После запуска скриптов проверьте:

- [ ] Все файлы сниппетов существуют в `/core/elements/snippets/`
- [ ] Все LMS страницы (35, 155-159, 34) опубликованы
- [ ] Все LMS страницы содержат правильные сниппеты
- [ ] Страница ID 147 помечена как удаленная
- [ ] Кеш MODX очищен
- [ ] Пользователь ID 2 видит тесты на странице `/tests`
- [ ] Можно пройти тест и увидеть результаты
- [ ] История и статистика отображаются правильно
- [ ] Таблица лидеров работает
- [ ] Достижения отображаются

---

## 🧪 Тестирование функциональности

Войдите в систему с пользователем ID 2 и проверьте:

1. **Страница /tests** - видны доступные тесты
2. **Прохождение теста** - интерфейс работает корректно
3. **Результаты** - отображаются правильно
4. **История** - список всех пройденных тестов
5. **Статистика** - информация о результатах
6. **Достижения** - значки обновляются корректно
7. **Таблица лидеров** - рейтинг пользователей

---

## 📞 Если что-то не работает

1. Проверьте ошибки в `/core/cache/logs/`
2. Запустите скрипт 1 (диагностика) и пришлите результаты
3. Проверьте права доступа пользователя ID 2
4. Убедитесь что все таблицы БД созданы из `FULL_INSTALLATION_FIXED.sql`
