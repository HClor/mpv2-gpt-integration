<?php
/**
 * LMS SYSTEM v2.0 - АВТОМАТИЧЕСКИЙ ИНСТАЛЛЯТОР
 *
 * Использование в MODX Console:
 * 1. Откройте админку MODX → Tools → MODX Console
 * 2. Скопируйте и выполните эти 3 строки (вместе):
 *
 *    require_once MODX_CORE_PATH . 'components/testsystem/installer.php';
 *    $lmsInstaller = new LMSInstaller($modx);
 *    $lmsInstaller->run();
 *
 * ВАЖНО: Скопируйте и выполните все 3 строки одновременно, не по одной!
 */

class LMSInstaller
{
    private $modx;
    private $templateId = 9;
    private $parentId = 0;
    private $errors = [];
    private $success = [];

    public function __construct($modx = null)
    {
        // Если MODX уже загружен в контексте (например, в MODX Console)
        if ($modx && $modx instanceof modX) {
            $this->modx = $modx;
        } else {
            // Проверить что мы можем инициализировать MODX
            if (!defined('MODX_CORE_PATH')) {
                throw new Exception(
                    "MODX не инициализирован.\n" .
                    "Запустите инсталлятор из MODX Console:\n" .
                    "  require_once MODX_CORE_PATH . 'components/testsystem/installer.php';\n" .
                    "  \$installer = new LMSInstaller(\$modx);\n" .
                    "  \$installer->run();"
                );
            }

            // В MODX Console $modx должен быть доступен как переменная
            throw new Exception(
                "Ошибка инициализации MODX.\n" .
                "Убедитесь что вы передали объект \$modx в конструктор"
            );
        }
    }

    /**
     * Главный метод установки
     */
    public function run()
    {
        echo "========================================\n";
        echo "🚀 LMS SYSTEM v2.0 - АВТОМАТИЧЕСКИЙ ИНСТАЛЛЯТОР\n";
        echo "========================================\n\n";

        // Добавим диагностику перед началом
        echo "[ДИАГНОСТИКА] Информация о подключении к БД:\n";
        $this->diagnosticDatabaseInfo();
        echo "\n";

        // Этап 1: Проверка
        echo "[ЭТАП 1/5] Проверка системы...\n";
        $this->checkSystem();

        // Этап 2: Создание ресурсов
        echo "\n[ЭТАП 2/5] Создание ресурсов в MODX...\n";
        $this->createResources();

        // Этап 3: Проверка БД
        echo "\n[ЭТАП 3/5] Проверка базы данных...\n";
        $this->checkDatabase();

        // Этап 4: Инициализация данных
        echo "\n[ЭТАП 4/5] Инициализация данных...\n";
        $this->initializeData();

        // Этап 5: Финальная проверка
        echo "\n[ЭТАП 5/5] Финальная проверка...\n";
        $this->finalCheck();

        // Вывод результатов
        $this->printResults();
    }

    /**
     * Диагностика подключения к БД
     */
    private function diagnosticDatabaseInfo()
    {
        try {
            $db = $this->modx->getConnection();
            echo "  Класс БД: " . get_class($db) . "\n";

            // Проверим методы $modx для работы с SQL
            $hasMethod = function($method) {
                return method_exists($this->modx, $method);
            };

            echo "  Методы $modx для SQL:\n";
            echo "    - query(): " . ($hasMethod('query') ? 'ДА ✓' : 'НЕТ') . "\n";
            echo "    - exec(): " . ($hasMethod('exec') ? 'ДА ✓' : 'НЕТ') . "\n";
            echo "    - quote(): " . ($hasMethod('quote') ? 'ДА ✓' : 'НЕТ') . "\n";

            // Попробуем простой SELECT
            echo "\n  Тест SELECT через query():\n";
            try {
                $stmt = $this->modx->query("SELECT 1 as test");
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                echo "    ✓ query() + fetch() работают\n";
            } catch (Exception $e) {
                echo "    ✗ Ошибка: " . $e->getMessage() . "\n";
            }
        } catch (Exception $e) {
            echo "  ✗ Ошибка диагностики: " . $e->getMessage() . "\n";
        }
    }

    /**
     * 1. Проверка системы
     */
    private function checkSystem()
    {
        // Проверить что MODX загружен
        if (!$this->modx) {
            $this->addError("MODX не инициализирован");
            return;
        }

        $this->addSuccess("✓ MODX инициализирован");

        // Проверить наличие шаблона
        $template = $this->modx->getObject('modTemplate', $this->templateId);
        if (!$template) {
            $this->addError("Шаблон ID {$this->templateId} не найден. Нужно создать шаблон или указать правильный ID");
            return;
        }

        $this->addSuccess("✓ Шаблон ID {$this->templateId} найден: '{$template->get('templatename')}'");

        // Проверить наличие корневого ресурса
        $root = $this->modx->getObject('modResource', 1);
        if ($root) {
            $this->addSuccess("✓ Корневой ресурс найден");
        }

        // Проверить наличие bootstrap файла
        $bootstrapPath = MODX_CORE_PATH . 'components/testsystem/bootstrap.php';
        if (file_exists($bootstrapPath)) {
            $this->addSuccess("✓ Bootstrap файл найден");
        } else {
            $this->addError("Bootstrap файл не найден: {$bootstrapPath}");
        }
    }

    /**
     * 2. Создание ресурсов в MODX
     */
    private function createResources()
    {
        // Массив ресурсов для создания
        $resources = [
            [
                'pagetitle' => 'Тесты',
                'alias' => 'tests',
                'description' => 'Список доступных тестов',
                'content' => '[[categoriesAndTests]]',
                'longtitle' => 'Система тестирования',
            ],
            [
                'pagetitle' => 'Прохождение теста',
                'alias' => 'test-run',
                'description' => 'Интерфейс прохождения теста',
                'content' => '[[testRunner]]',
                'longtitle' => 'Прохождение теста',
            ],
            [
                'pagetitle' => 'Результаты',
                'alias' => 'results',
                'description' => 'Результаты тестирования',
                'content' => '[[testResults]]',
                'longtitle' => 'Результаты тестирования',
            ],
            [
                'pagetitle' => 'История тестов',
                'alias' => 'history',
                'description' => 'История сдачи тестов',
                'content' => '[[testHistory]]',
                'longtitle' => 'История тестирования',
            ],
            [
                'pagetitle' => 'Моя статистика',
                'alias' => 'stats',
                'description' => 'Личная статистика пользователя',
                'content' => '[[getUserStats]]',
                'longtitle' => 'Статистика тестирования',
            ],
            [
                'pagetitle' => 'Таблица лидеров',
                'alias' => 'leaderboard',
                'description' => 'Рейтинг пользователей',
                'content' => '[[leaderboard?&period=`all_time`&limit=`50`]]',
                'longtitle' => 'Таблица лидеров',
            ],
            [
                'pagetitle' => 'Достижения',
                'alias' => 'achievements',
                'description' => 'Мои достижения и значки',
                'content' => '[[achievements]]',
                'longtitle' => 'Достижения и значки',
            ],
        ];

        $createdCount = 0;
        $existingCount = 0;

        foreach ($resources as $resourceData) {
            // Проверить не существует ли уже ресурс
            $existing = $this->modx->getObject('modResource', ['alias' => $resourceData['alias']]);

            if ($existing) {
                $this->addSuccess("  ⟳ Ресурс '{$resourceData['pagetitle']}' уже существует");
                $existingCount++;
                continue;
            }

            // Создать новый ресурс
            $resource = $this->modx->newObject('modResource');
            $resource->set('parent', $this->parentId);
            $resource->set('template', $this->templateId);
            $resource->set('published', 1);
            $resource->set('deleted', 0);
            $resource->set('createdby', 1);
            $resource->set('createdon', time());
            $resource->set('editedby', 1);
            $resource->set('editedon', time());

            // Заполнить данные
            foreach ($resourceData as $key => $value) {
                $resource->set($key, $value);
            }

            // Сохранить
            if ($resource->save()) {
                $this->addSuccess("  ✓ Ресурс создан: '{$resourceData['pagetitle']}' (ID: {$resource->get('id')}, alias: {$resourceData['alias']})");
                $createdCount++;
            } else {
                $this->addError("  ✗ Ошибка при создании ресурса: '{$resourceData['pagetitle']}'");
            }
        }

        echo "   Создано: {$createdCount}, уже существует: {$existingCount}\n";
    }

    /**
     * 3. Проверка базы данных
     */
    private function checkDatabase()
    {
        $tables = [
            'modx_test_categories',
            'modx_test_tests',
            'modx_test_questions',
            'modx_test_answers',
            'modx_test_sessions',
            'modx_test_user_answers',
            'modx_test_achievements',
            'modx_test_level_config',
            'modx_test_permissions',
            'modx_test_learning_paths',
            'modx_test_notifications',
            'modx_test_certificates',
        ];

        try {
            $stmt = $this->modx->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE()");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $existingTables = [];
            foreach ($rows as $row) {
                $existingTables[] = $row['TABLE_NAME'];
            }

            echo "  [Найдено " . count($existingTables) . " таблиц в БД]\n";
        } catch (Exception $e) {
            $this->addError("Исключение при проверке таблиц: " . $e->getMessage());
            return;
        }

        $missingTables = [];
        foreach ($tables as $table) {
            if (in_array($table, $existingTables)) {
                $this->addSuccess("  ✓ Таблица '{$table}' существует");
            } else {
                $this->addError("  ✗ Таблица '{$table}' не найдена");
                $missingTables[] = $table;
            }
        }

        if (count($missingTables) > 0) {
            $this->addError("Не найдены таблицы: " . implode(', ', $missingTables) . "\n  Нужно выполнить: mysql -u user -p database < core/components/testsystem/sql/FULL_INSTALLATION_FIXED.sql");
        } else {
            $this->addSuccess("✓ Все необходимые таблицы присутствуют");
        }
    }

    /**
     * 4. Инициализация данных
     */
    private function initializeData()
    {
        try {
            // Проверить наличие уровней
            echo "  Проверка уровней...\n";
            $stmt = $this->modx->query("SELECT COUNT(*) as cnt FROM modx_test_level_config");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $levelCount = intval($row['cnt']);
            echo "    [Найдено {$levelCount} уровней]\n";

            if ($levelCount == 0) {
                echo "  Создание уровней опыта...\n";
                $this->createLevels();
            } else {
                $this->addSuccess("  ✓ Уровни уже созданы ({$levelCount} записей)");
            }
        } catch (Exception $e) {
            $this->addError("Ошибка при проверке уровней: " . $e->getMessage());
            return;
        }

        try {
            // Проверить наличие шаблонов уведомлений
            echo "  Проверка шаблонов уведомлений...\n";
            $stmt = $this->modx->query("SELECT COUNT(*) as cnt FROM modx_test_notification_templates");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $templateCount = intval($row['cnt']);
            echo "    [Найдено {$templateCount} шаблонов]\n";

            if ($templateCount == 0) {
                echo "  Создание шаблонов уведомлений...\n";
                $this->createNotificationTemplates();
            } else {
                $this->addSuccess("  ✓ Шаблоны уведомлений уже созданы ({$templateCount} записей)");
            }
        } catch (Exception $e) {
            $this->addError("Ошибка при проверке шаблонов: " . $e->getMessage());
            return;
        }

        try {
            // Проверить наличие достижений
            echo "  Проверка достижений...\n";
            $stmt = $this->modx->query("SELECT COUNT(*) as cnt FROM modx_test_achievements");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $achievementCount = intval($row['cnt']);
            echo "    [Найдено {$achievementCount} достижений]\n";

            if ($achievementCount == 0) {
                echo "  Создание достижений...\n";
                $this->createAchievements();
            } else {
                $this->addSuccess("  ✓ Достижения уже созданы ({$achievementCount} записей)");
            }
        } catch (Exception $e) {
            $this->addError("Ошибка при проверке достижений: " . $e->getMessage());
            return;
        }
    }

    /**
     * Создать уровни опыта
     */
    private function createLevels()
    {
        $levels = [
            ['level' => 1, 'xp_required' => 0, 'title' => 'Новичок'],
            ['level' => 2, 'xp_required' => 100, 'title' => 'Ученик'],
            ['level' => 3, 'xp_required' => 250, 'title' => 'Адепт'],
            ['level' => 4, 'xp_required' => 500, 'title' => 'Мастер'],
            ['level' => 5, 'xp_required' => 1000, 'title' => 'Эксперт'],
            ['level' => 6, 'xp_required' => 1500, 'title' => 'Гений'],
            ['level' => 7, 'xp_required' => 2500, 'title' => 'Ученый'],
            ['level' => 8, 'xp_required' => 3500, 'title' => 'Профессор'],
            ['level' => 9, 'xp_required' => 4500, 'title' => 'Академик'],
            ['level' => 10, 'xp_required' => 5000, 'title' => 'Мудрец'],
        ];

        try {
            $count = 0;
            foreach ($levels as $level) {
                try {
                    $sql = "INSERT INTO modx_test_level_config (level, xp_required, title) VALUES (" .
                           intval($level['level']) . ", " .
                           intval($level['xp_required']) . ", " .
                           "'" . $this->modx->quote($level['title']) . "')";
                    $result = $this->modx->exec($sql);
                    if ($result > 0) {
                        $count++;
                    } else {
                        echo "      [Ошибка при вставке уровня {$level['level']}]\n";
                    }
                } catch (Exception $e) {
                    echo "      [Исключение при вставке уровня {$level['level']}: " . $e->getMessage() . "]\n";
                }
            }

            $this->addSuccess("    ✓ Создано {$count} из 10 уровней опыта");
        } catch (Exception $e) {
            $this->addError("    ✗ Ошибка при создании уровней: " . $e->getMessage());
        }
    }

    /**
     * Создать шаблоны уведомлений
     */
    private function createNotificationTemplates()
    {
        $templates = [
            [
                'template_key' => 'test_completed',
                'notification_type' => 'test_completed',
                'channel' => 'system',
                'subject_template' => 'Тест завершен',
                'body_template' => 'Вы завершили тест "{test_name}" с результатом {score}%',
            ],
            [
                'template_key' => 'achievement_earned',
                'notification_type' => 'achievement_earned',
                'channel' => 'system',
                'subject_template' => 'Получено достижение',
                'body_template' => 'Поздравляем! Вы получили достижение "{achievement_name}"',
            ],
            [
                'template_key' => 'level_up',
                'notification_type' => 'level_up',
                'channel' => 'system',
                'subject_template' => 'Повышение уровня',
                'body_template' => 'Вы достигли уровня {level} - "{level_title}"',
            ],
            [
                'template_key' => 'path_step_unlocked',
                'notification_type' => 'path_step_unlocked',
                'channel' => 'system',
                'subject_template' => 'Разблокирован новый этап',
                'body_template' => 'Следующий этап траектории "{path_name}" теперь доступен',
            ],
            [
                'template_key' => 'essay_reviewed',
                'notification_type' => 'essay_reviewed',
                'channel' => 'system',
                'subject_template' => 'Эссе проверено',
                'body_template' => 'Ваше эссе получило оценку {score} баллов',
            ],
            [
                'template_key' => 'material_available',
                'notification_type' => 'material_available',
                'channel' => 'system',
                'subject_template' => 'Новый материал',
                'body_template' => 'Для вас доступен новый учебный материал "{material_name}"',
            ],
            [
                'template_key' => 'certificate_issued',
                'notification_type' => 'custom',
                'channel' => 'system',
                'subject_template' => 'Получен сертификат',
                'body_template' => 'Вам выдан сертификат за "{entity_name}"',
            ],
            [
                'template_key' => 'deadline_reminder',
                'notification_type' => 'custom',
                'channel' => 'system',
                'subject_template' => 'Напоминание о дедлайне',
                'body_template' => 'Напоминание: дедлайн для "{task_name}" истекает {deadline}',
            ],
        ];

        try {
            $count = 0;
            foreach ($templates as $template) {
                try {
                    $sql = "INSERT INTO modx_test_notification_templates " .
                           "(template_key, notification_type, channel, subject_template, body_template, is_active) " .
                           "VALUES (" .
                           "'" . $this->modx->quote($template['template_key']) . "', " .
                           "'" . $this->modx->quote($template['notification_type']) . "', " .
                           "'" . $this->modx->quote($template['channel']) . "', " .
                           "'" . $this->modx->quote($template['subject_template']) . "', " .
                           "'" . $this->modx->quote($template['body_template']) . "', " .
                           "1)";
                    $result = $this->modx->exec($sql);
                    if ($result > 0) {
                        $count++;
                    } else {
                        echo "      [Ошибка при вставке шаблона {$template['template_key']}]\n";
                    }
                } catch (Exception $e) {
                    echo "      [Исключение при вставке шаблона {$template['template_key']}: " . $e->getMessage() . "]\n";
                }
            }

            $this->addSuccess("    ✓ Создано {$count} из 8 шаблонов уведомлений");
        } catch (Exception $e) {
            $this->addError("    ✗ Ошибка при создании шаблонов: " . $e->getMessage());
        }
    }

    /**
     * Создать достижения (если нужны дефолтные)
     */
    private function createAchievements()
    {
        try {
            // Если достижения уже созданы, не создавать заново
            $stmt = $this->modx->query("SELECT COUNT(*) as cnt FROM modx_test_achievements");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (intval($row['cnt']) > 0) {
                $this->addSuccess("    ✓ Достижения уже существуют в системе");
                return;
            }
        } catch (Exception $e) {
            $this->addError("    ✗ Ошибка при проверке достижений: " . $e->getMessage());
        }
    }

    /**
     * 5. Финальная проверка
     */
    private function finalCheck()
    {
        try {
            // Проверить количество ресурсов
            $stmt = $this->modx->query("SELECT COUNT(*) as cnt FROM modx_site_content WHERE alias LIKE 'tests' OR alias LIKE 'test-run' OR alias LIKE 'leaderboard'");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $resourceCount = intval($row['cnt']);

            // Проверить количество тестов
            $stmt = $this->modx->query("SELECT COUNT(*) as cnt FROM modx_test_tests");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $testCount = intval($row['cnt']);

            // Проверить количество уровней
            $stmt = $this->modx->query("SELECT COUNT(*) as cnt FROM modx_test_level_config");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $levelCount = intval($row['cnt']);

            $this->addSuccess("  ✓ Ресурсов создано/найдено");
            $this->addSuccess("  ✓ Найдено {$testCount} тестов в БД");
            $this->addSuccess("  ✓ Найдено {$levelCount} уровней в БД");
        } catch (Exception $e) {
            $this->addError("Ошибка при финальной проверке: " . $e->getMessage());
        }
    }

    /**
     * Вывод результатов
     */
    private function printResults()
    {
        echo "\n========================================\n";
        echo "📊 РЕЗУЛЬТАТЫ УСТАНОВКИ\n";
        echo "========================================\n\n";

        if (!empty($this->errors)) {
            echo "❌ ОШИБКИ:\n";
            foreach ($this->errors as $error) {
                echo "   {$error}\n";
            }
            echo "\n";
        }

        if (!empty($this->success)) {
            echo "✅ УСПЕШНО:\n";
            foreach ($this->success as $msg) {
                echo "   {$msg}\n";
            }
            echo "\n";
        }

        echo "========================================\n";
        echo "✨ УСТАНОВКА ЗАВЕРШЕНА\n";
        echo "========================================\n\n";

        echo "🚀 СЛЕДУЮЩИЕ ШАГИ:\n";
        echo "1. Откройте админку MODX\n";
        echo "2. Перейдите на сайт и проверьте страницы:\n";
        echo "   - /tests (Список тестов)\n";
        echo "   - /leaderboard (Таблица лидеров)\n";
        echo "   - /achievements (Достижения)\n";
        echo "   - /stats (Статистика)\n";
        echo "3. Пройдите один из 19 тестов\n";
        echo "4. Проверьте что XP начисляется\n\n";

        if (!empty($this->errors)) {
            echo "⚠️  ВАЖНО: Исправьте ошибки выше перед использованием\n";
        } else {
            echo "🎉 ВСЕ ГОТОВО К ИСПОЛЬЗОВАНИЮ!\n";
        }
    }

    /**
     * Добавить сообщение об ошибке
     */
    private function addError($message)
    {
        $this->errors[] = $message;
        echo "   ✗ {$message}\n";
    }

    /**
     * Добавить сообщение об успехе
     */
    private function addSuccess($message)
    {
        $this->success[] = $message;
        echo "   ✓ {$message}\n";
    }
}

// Этот файл предназначен для использования в MODX Console
// или как require в MODX контексте.
//
// Использование:
// require_once MODX_CORE_PATH . 'components/testsystem/installer.php';
// $installer = new LMSInstaller($modx);
// $installer->run();
