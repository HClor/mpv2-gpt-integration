<?php
/**
 * Сниппет: csvImportForm - Импорт вопросов из CSV/Excel
 * Вызывается из: MODX ресурсов (страница импорта)
 * Назначение: Импорт вопросов теста из CSV или Excel файлов
 *
 * @package TestSystem
 * @version 5.2
 */

// Подключаем bootstrap для CSRF защиты
require_once MODX_CORE_PATH . 'components/testsystem/bootstrap.php';

if (!$modx->user->hasSessionContext("web")) {
    $authUrl = $modx->makeUrl($modx->getOption("lms.auth_page", null, 0));
    return "<div class=\"alert alert-warning\"><a href=\"{$authUrl}\">Войдите</a> для импорта вопросов</div>";
}


$currentUserId = (int)$modx->user->get('id');
$prefix = $modx->getOption('table_prefix');
$testId = (int)($_GET['test_id'] ?? $_POST['test_id'] ?? 0);

if ($testId <= 0) {
    return "<div class=\"alert alert-danger\">Не указан ID теста</div>";
}

// Получаем информацию о тесте с resource_id
$stmt = $modx->prepare("SELECT id, title, created_by, publication_status, resource_id FROM `{$prefix}test_tests` WHERE id = ?");
if (!$stmt) {
    return "<div class=\"alert alert-danger\">Ошибка подключения к БД</div>";
}

$stmt->execute([$testId]);
$test = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$test) {
    return "<div class=\"alert alert-danger\">Тест с ID {$testId} не найден</div>";
}

// ИСПРАВЛЕНО: Генерация URL теста через /test-run?testId=X
// resource_id теперь хранит category_id, а не ID страницы MODX
$testRunPageId = Config::getPageId('test_run', 155);
$testUrl = $modx->makeUrl($testRunPageId, '', ['testId' => $testId], 'full');

// Fallback: если страница test-run не найдена
if (empty($testUrl)) {
    $siteUrl = rtrim($modx->getOption('site_url'), '/');
    $testUrl = $siteUrl . '/test-run?testId=' . $testId;
}

// Проверка прав доступа
$canImport = false;
$isOwner = ((int)$test['created_by'] === $currentUserId);
$isSuperAdmin = ($currentUserId === 1);

// Проверка роли (админ/эксперт)
$isExpertOrAdmin = false;
$sql = "SELECT mgn.`name`
        FROM `{$prefix}member_groups` AS mg
        JOIN `{$prefix}membergroup_names` AS mgn ON mgn.`id` = mg.`user_group`
        WHERE mg.`member` = :uid
        AND mgn.`name` IN ('" . Config::getGroup('admins') . "', '" . Config::getGroup('experts') . "')";

$stmt = $modx->prepare($sql);
if ($stmt && $stmt->execute([':uid' => $currentUserId])) {
    $groups = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $isExpertOrAdmin = (count($groups) > 0);
}

// Логика проверки прав в зависимости от статуса теста
if ($isSuperAdmin || $isExpertOrAdmin) {
    // Админы и эксперты могут импортировать везде
    $canImport = true;
} elseif ($test['publication_status'] === 'private') {
    // Для приватных тестов: владелец ИЛИ пользователь с can_edit=1
    if ($isOwner) {
        $canImport = true;
    } else {
        $stmt = $modx->prepare("
            SELECT can_edit 
            FROM `{$prefix}test_permissions` 
            WHERE test_id = ? AND user_id = ? AND can_edit = 1
        ");
        if ($stmt && $stmt->execute([$testId, $currentUserId])) {
            $canImport = (bool)$stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
} elseif (in_array($test['publication_status'], ['public', 'unlisted', 'draft'])) {
    // Для публичных/unlisted/draft: только владелец
    $canImport = $isOwner;
}

if (!$canImport) {
    return "<div class=\"alert alert-danger\">
        <h4>Доступ запрещён</h4>
        <p>Импорт вопросов доступен только:</p>
        <ul>
            <li>Владельцу теста</li>
            <li>Пользователям с правами редактирования (для приватных тестов)</li>
            <li>Экспертам и администраторам (для всех тестов)</li>
        </ul>
        <p class=\"mb-0\"><small>Владелец: ID {$test['created_by']} | Ваш ID: {$currentUserId} | Статус: {$test['publication_status']}</small></p>
    </div>";
}

// Подключаем QuestionImportHelper для проверки PhpSpreadsheet
require_once MODX_CORE_PATH . 'components/testsystem/helpers/QuestionImportHelper.php';
use MPV2\TestSystem\Helpers\QuestionImportHelper;

// Проверяем наличие PhpSpreadsheet
$hasPhpSpreadsheet = QuestionImportHelper::hasPhpSpreadsheet();

$errors = [];
$success = [];
$importedCount = 0;

// Автозагрузка файла из параметра ?file=
$preloadedFile = $_GET['file'] ?? null;
$autoLoadFile = null;

if ($preloadedFile) {
    $uploadDir = MODX_ASSETS_PATH . 'uploads/test_imports/';
    $filePath = $uploadDir . basename($preloadedFile);
    
    if (file_exists($filePath)) {
        $autoLoadFile = $filePath;
    } else {
        $errors[] = "Файл не найден: " . basename($preloadedFile);
    }
}

// Обработка загрузки через POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    // CSRF Protection
    if (!CsrfProtection::validateRequest($_POST)) {
        $errors[] = "Ошибка безопасности. Обновите страницу и попробуйте снова.";
    } else {
    $file = $_FILES['csv_file'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Ошибка загрузки файла (код: {$file['error']})";
    } else {
        $filePath = $file['tmp_name'];
        $fileName = $file['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        // Проверка размера файла (максимум 10MB)
        $maxFileSize = 10 * 1024 * 1024; // 10MB
        if ($file['size'] > $maxFileSize) {
            $errors[] = "Файл слишком большой. Максимальный размер: 10MB";
        }

        // Проверка расширения
        $allowedExtensions = ['csv', 'xlsx', 'xls'];
        if (!in_array($fileExtension, $allowedExtensions)) {
            $errors[] = "Недопустимый формат файла. Разрешены: CSV, XLSX, XLS";
        }

        // Проверка MIME-типа
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);

        $allowedMimeTypes = [
            'text/csv',
            'text/plain',
            'application/csv',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];

        if (!in_array($mimeType, $allowedMimeTypes)) {
            $errors[] = "Недопустимый тип файла. Обнаружен: {$mimeType}";
        }
        
        if (empty($errors)) {
            $result = processImportFile($filePath, $fileExtension, $testId, $modx, $prefix, $hasPhpSpreadsheet);
            $errors = array_merge($errors, $result['errors']);
            $success = array_merge($success, $result['success']);
            $importedCount = $result['imported'];
        }
    }
    } // Закрываем else блок CSRF проверки
}

// Обработка автозагрузки
if ($autoLoadFile && empty($errors)) {
    $fileExtension = strtolower(pathinfo($autoLoadFile, PATHINFO_EXTENSION));
    $result = processImportFile($autoLoadFile, $fileExtension, $testId, $modx, $prefix, $hasPhpSpreadsheet);
    $errors = array_merge($errors, $result['errors']);
    $success = array_merge($success, $result['success']);
    $importedCount = $result['imported'];
    
    // Удаляем временный файл
    @unlink($autoLoadFile);
}

// ФУНКЦИЯ ОБРАБОТКИ ФАЙЛА
function processImportFile($filePath, $fileExtension, $testId, $modx, $prefix, $hasPhpSpreadsheet) {
    $errors = [];
    $success = [];
    $imported = 0;
    
    try {
        $rows = [];
        
        // CSV обработка
        if ($fileExtension === 'csv') {
            if (($handle = fopen($filePath, 'r')) !== false) {
                $firstLine = fgets($handle);
                rewind($handle);
                
                $encoding = mb_detect_encoding($firstLine, ['UTF-8', 'Windows-1251', 'ISO-8859-1'], true);
                
                $lineNumber = 0;
                while (($data = fgetcsv($handle, 10000, ',')) !== false) {
                    $lineNumber++;
                    if ($lineNumber === 1) continue;
                    
                    if ($encoding !== 'UTF-8') {
                        $data = array_map(function($item) use ($encoding) {
                            return mb_convert_encoding($item, 'UTF-8', $encoding);
                        }, $data);
                    }
                    
                    $rows[] = $data;
                }
                fclose($handle);
            } else {
                $errors[] = "Не удалось открыть CSV файл";
                return ['errors' => $errors, 'success' => $success, 'imported' => 0];
            }
        }
        // Excel обработка
        elseif (in_array($fileExtension, ['xlsx', 'xls'])) {
            if (!$hasPhpSpreadsheet) {
                $errors[] = "PhpSpreadsheet не установлен. Используйте CSV или установите библиотеку.";
                return ['errors' => $errors, 'success' => $success, 'imported' => 0];
            }
            
            try {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
                $worksheet = $spreadsheet->getActiveSheet();
                $highestRow = $worksheet->getHighestRow();
                
                for ($row = 2; $row <= $highestRow; $row++) {
                    $rowData = [];
                    for ($col = 'A'; $col <= 'H'; $col++) {
                        $cellValue = $worksheet->getCell($col . $row)->getValue();
                        $rowData[] = $cellValue !== null ? (string)$cellValue : '';
                    }
                    
                    if (!empty(trim($rowData[0]))) {
                        $rows[] = $rowData;
                    }
                }
            } catch (Exception $e) {
                $errors[] = "Ошибка чтения Excel: " . $e->getMessage();
                $modx->log(modX::LOG_LEVEL_ERROR, "[importCSV] Excel error: " . $e->getMessage());
                return ['errors' => $errors, 'success' => $success, 'imported' => 0];
            }
        }
        
        if (empty($rows)) {
            $errors[] = "Файл пуст или не содержит данных";
            return ['errors' => $errors, 'success' => $success, 'imported' => 0];
        }
        
        // Обработка строк
        foreach ($rows as $index => $row) {
            $lineNumber = $index + 2;
            
            if (count($row) < 7) {
                $errors[] = "Строка {$lineNumber}: недостаточно столбцов (минимум 7)";
                continue;
            }
            
            $questionText = trim($row[0] ?? '');
            $questionType = strtolower(trim($row[1] ?? 'single'));
            $answer1 = trim($row[2] ?? '');
            $answer2 = trim($row[3] ?? '');
            $answer3 = trim($row[4] ?? '');
            $answer4 = trim($row[5] ?? '');
            $correctAnswers = trim($row[6] ?? '');
            $explanation = trim($row[7] ?? '');
            
            // Валидация
            if (empty($questionText)) {
                $errors[] = "Строка {$lineNumber}: пустой текст вопроса";
                continue;
            }
            
            if (!in_array($questionType, ['single', 'multiple'])) {
                $errors[] = "Строка {$lineNumber}: некорректный тип вопроса '{$questionType}'";
                continue;
            }
            
            $answers = array_filter([$answer1, $answer2, $answer3, $answer4], function($a) {
                return !empty(trim($a));
            });
            
            if (count($answers) < 2) {
                $errors[] = "Строка {$lineNumber}: минимум 2 варианта ответа";
                continue;
            }
            
            if (empty($correctAnswers)) {
                $errors[] = "Строка {$lineNumber}: не указаны правильные ответы";
                continue;
            }
            
            $correctIndexes = array_map('trim', explode(',', $correctAnswers));
            $correctIndexes = array_filter($correctIndexes, 'is_numeric');
            
            if (empty($correctIndexes)) {
                $errors[] = "Строка {$lineNumber}: некорректный формат правильных ответов";
                continue;
            }
            
            // Вставка вопроса
            $stmt = $modx->prepare("
                INSERT INTO `{$prefix}test_questions` 
                (test_id, question_text, question_type, explanation, published, created_at)
                VALUES (?, ?, ?, ?, 1, NOW())
            ");
            
            if (!$stmt->execute([$testId, $questionText, $questionType, $explanation])) {
                $errors[] = "Строка {$lineNumber}: ошибка вставки вопроса в БД";
                continue;
            }
            
            $questionId = (int)$modx->lastInsertId();
            
            // Вставка ответов
            $answersArray = array_values($answers);
            $allAnswersInserted = true;
            
            foreach ($answersArray as $idx => $answerText) {
                $answerNumber = $idx + 1;
                $isCorrect = in_array((string)$answerNumber, $correctIndexes) ? 1 : 0;
                
                $stmt = $modx->prepare("
                    INSERT INTO `{$prefix}test_answers` 
                    (question_id, answer_text, is_correct, sort_order)
                    VALUES (?, ?, ?, ?)
                ");
                
                if (!$stmt->execute([$questionId, $answerText, $isCorrect, $answerNumber])) {
                    $allAnswersInserted = false;
                    break;
                }
            }
            
            if (!$allAnswersInserted) {
                $errors[] = "Строка {$lineNumber}: ошибка вставки ответов";
                // ИСПРАВЛЕНО: используем prepared statement
                $stmt = $modx->prepare("DELETE FROM `{$prefix}test_questions` WHERE id = ?");
                $stmt->execute([$questionId]);
                continue;
            }
            
            $success[] = "Строка {$lineNumber}: вопрос добавлен успешно";
            $imported++;
        }
        
    } catch (Exception $e) {
        $errors[] = "Критическая ошибка: " . $e->getMessage();
        $modx->log(modX::LOG_LEVEL_ERROR, "[importCSV] Exception: " . $e->getMessage());
    }
    
    return ['errors' => $errors, 'success' => $success, 'imported' => $imported];
}

// ФОРМИРОВАНИЕ HTML
$output = '<div class="container my-4">';
$output .= '<div class="row">';
$output .= '<div class="col-xl-10 offset-xl-1">';

$backLink = $testUrl !== '#' ? $testUrl : 'javascript:history.back()';
$output .= '<div class="d-flex justify-content-between align-items-center mb-4">';
$output .= '<a href="' . htmlspecialchars($backLink, ENT_QUOTES, 'UTF-8') . '" class="ts-btn ts-btn-secondary"><i class="bi bi-arrow-left"></i> Вернуться к тесту</a>';
$output .= '</div>';

$output .= '<section class="ts-import-page-header">';
$output .= '<p class="ts-import-page-header-kicker">Импорт вопросов</p>';
$output .= '<h1 class="ts-import-page-header-title">Загрузка вопросов из CSV/Excel</h1>';
$output .= '<p class="ts-import-page-header-subtitle">Добавьте вопросы в тест структурированным файлом. Интерфейс показывает только важные действия и проверку результата.</p>';
$output .= '</section>';

$output .= '<div class="ts-card ts-import-test-meta mb-4">';
$output .= '<div class="ts-card-body d-flex flex-wrap justify-content-between align-items-center gap-2">';
$output .= '<div>';
$output .= '<h2 class="ts-card-title">' . htmlspecialchars($test['title'], ENT_QUOTES, 'UTF-8') . '</h2>';
$output .= '<p class="mb-0 text-muted">ID теста: ' . $testId . '</p>';
$output .= '</div>';
$output .= '<span class="ts-badge ts-badge-primary">Шаг 2: Импорт вопросов</span>';
$output .= '</div>';
$output .= '</div>';

if (!empty($errors)) {
    $output .= '<div class="ts-alert ts-alert-danger">';
    $output .= '<h5><i class="bi bi-exclamation-triangle"></i> Ошибки импорта</h5>';
    $output .= '<ul class="mb-0">';
    foreach ($errors as $error) {
        $output .= '<li>' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</li>';
    }
    $output .= '</ul>';
    $output .= '</div>';
}

if (!empty($success)) {
    $output .= '<div class="ts-alert ts-alert-success">';
    $output .= '<h5><i class="bi bi-check-circle"></i> Успешно импортировано: ' . $importedCount . ' вопросов</h5>';
    if (count($success) <= 10) {
        $output .= '<ul class="mb-0">';
        foreach ($success as $msg) {
            $output .= '<li>' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</li>';
        }
        $output .= '</ul>';
    } else {
        $output .= '<p class="mb-2">Показаны первые 10 сообщений:</p>';
        $output .= '<ul>';
        foreach (array_slice($success, 0, 10) as $msg) {
            $output .= '<li>' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</li>';
        }
        $output .= '</ul>';
        $output .= '<p class="text-muted mb-0">...и ещё ' . (count($success) - 10) . ' вопросов</p>';
    }

    $output .= '<hr>';
    $output .= '<a href="' . htmlspecialchars($testUrl, ENT_QUOTES, 'UTF-8') . '" class="ts-btn ts-btn-primary ts-btn-lg"><i class="bi bi-play-circle"></i> Перейти к тесту</a>';
    $output .= '</div>';
}

$output .= '<div class="ts-card ts-import-upload-main">';
$output .= '<div class="ts-card-header">';
$output .= '<h2 class="ts-card-title">Загрузить файл с вопросами</h2>';
$output .= '<p class="ts-card-description">Поддерживаются CSV, XLSX и XLS. После загрузки система проверит структуру и добавит валидные строки.</p>';
$output .= '</div>';
$output .= '<div class="ts-card-body">';

$output .= '<form method="POST" enctype="multipart/form-data">';
$output .= CsrfProtection::getTokenField(); // CSRF Protection
$output .= '<input type="hidden" name="test_id" value="' . $testId . '">';

$output .= '<div class="row g-4">';
$output .= '<div class="col-lg-6">';
$output .= '<label class="form-label fw-semibold">Выберите файл (CSV или Excel)</label>';
$output .= '<input type="file" name="csv_file" class="form-control" accept=".csv,.xlsx,.xls" required>';
$output .= '<small class="form-text text-muted">Максимальный размер файла: 10MB.</small>';

if (!$hasPhpSpreadsheet) {
    $output .= '<div class="ts-alert ts-alert-warning mt-3 mb-0">';
    $output .= '<strong>Внимание:</strong> PhpSpreadsheet не установлен. Excel файлы не поддерживаются, используйте CSV формат.';
    $output .= '</div>';
}

$output .= '<button type="submit" class="ts-btn ts-btn-primary ts-btn-lg w-100 mt-3">';
$output .= '<i class="bi bi-upload"></i> Загрузить и импортировать';
$output .= '</button>';
$output .= '</div>';

$output .= '<div class="col-lg-6">';
$output .= '<div class="ts-import-format-panel">';
$output .= '<h6 class="ts-import-format-panel-title"><i class="bi bi-table"></i> Структура файла</h6>';
$output .= '<ul class="ts-import-format-list">';
$output .= '<li><span>A</span> Текст вопроса</li>';
$output .= '<li><span>B</span> Тип: <code>single</code> или <code>multiple</code></li>';
$output .= '<li><span>C–F</span> Варианты ответов</li>';
$output .= '<li><span>G</span> Правильные ответы: <code>2</code> или <code>1,3</code></li>';
$output .= '<li><span>H</span> Объяснение (необязательно)</li>';
$output .= '</ul>';
$output .= '<p class="ts-import-format-panel-note mb-0">Рекомендуется использовать шаблон с заголовком в первой строке.</p>';
$output .= '</div>';
$output .= '</div>';
$output .= '</div>';

$output .= '</form>';
$output .= '</div>';
$output .= '</div>';

$output .= '</div>';
$output .= '</div>';
$output .= '</div>';

return $output;
