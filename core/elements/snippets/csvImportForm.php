<?php
/* TS CSV/EXCEL IMPORT v5.2 - REFACTORED */

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

// Генерация URL теста
$testUrl = '#';
$resourceId = (int)($test['resource_id'] ?? 0);

if ($resourceId > 0) {
    $resource = $modx->getObject('modResource', $resourceId);
    
    if ($resource) {
        // Сразу строим URL вручную (надёжнее чем makeUrl для свежих ресурсов)
        $siteUrl = rtrim($modx->getOption('site_url'), '/');
        $alias = $resource->get('alias');
        $parentId = (int)$resource->get('parent');
        
        if ($parentId > 0) {
            $parent = $modx->getObject('modResource', $parentId);
            if ($parent) {
                $parentUri = trim($parent->get('uri'), '/');
                $testUrl = $siteUrl . '/' . $parentUri . '/' . $alias;
            } else {
                $testUrl = $siteUrl . '/' . $alias;
            }
        } else {
            // Ресурс в корне
            $testUrl = $siteUrl . '/' . $alias;
        }
    }
}

// Fallback 1: страница "Мои тесты"
if (empty($testUrl) || $testUrl === '#') {
    $testsPageId = (int)$modx->getOption('lms.user_tests_folder', null, 0);
    if ($testsPageId > 0) {
        $testUrl = $modx->makeUrl($testsPageId, '', '', 'full');
    }
}

// Fallback 2: текущая страница с якорем
if (empty($testUrl) || $testUrl === '#') {
    $testUrl = $modx->makeUrl($modx->resource->get('id'), '', '', 'full') . '#test-' . $testId;
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
        AND mgn.`name` IN ('LMS Admins', 'LMS Experts')";

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

// Подключаем PhpSpreadsheet
$vendorPaths = [
    MODX_BASE_PATH . 'vendor/autoload.php',
    '/home/l/lmixru/mpv2.lmix.ru/public_html/vendor/autoload.php',
    MODX_CORE_PATH . '../vendor/autoload.php'
];

$hasPhpSpreadsheet = false;
foreach ($vendorPaths as $vendorPath) {
    if (file_exists($vendorPath)) {
        require_once $vendorPath;
        if (class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
            $hasPhpSpreadsheet = true;
            break;
        }
    }
}

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
$output .= '<div class="col-lg-10 offset-lg-1">';

$output .= '<div class="d-flex justify-content-between align-items-center mb-4">';
$output .= '<h2><i class="bi bi-file-earmark-spreadsheet"></i> Импорт вопросов</h2>';
$backLink = $testUrl !== '#' ? $testUrl : 'javascript:history.back()';
$output .= '<a href="' . htmlspecialchars($backLink, ENT_QUOTES, 'UTF-8') . '" class="btn btn-secondary">← Вернуться к тесту</a>';
$output .= '</div>';

$output .= '<div class="card mb-4">';
$output .= '<div class="card-body">';
$output .= '<h5 class="card-title">' . htmlspecialchars($test['title'], ENT_QUOTES, 'UTF-8') . '</h5>';
$output .= '<p class="text-muted mb-0">ID теста: ' . $testId . '</p>';
$output .= '</div>';
$output .= '</div>';

if (!empty($errors)) {
    $output .= '<div class="alert alert-danger">';
    $output .= '<h5><i class="bi bi-exclamation-triangle"></i> Ошибки импорта:</h5>';
    $output .= '<ul class="mb-0">';
    foreach ($errors as $error) {
        $output .= '<li>' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</li>';
    }
    $output .= '</ul>';
    $output .= '</div>';
}

if (!empty($success)) {
    $output .= '<div class="alert alert-success">';
    $output .= '<h5><i class="bi bi-check-circle"></i> Успешно импортировано: ' . $importedCount . ' вопросов</h5>';
    if (count($success) <= 10) {
        $output .= '<ul class="mb-0">';
        foreach ($success as $msg) {
            $output .= '<li>' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</li>';
        }
        $output .= '</ul>';
    } else {
        $output .= '<p class="mb-0">Показаны первые 10 сообщений:</p>';
        $output .= '<ul>';
        foreach (array_slice($success, 0, 10) as $msg) {
            $output .= '<li>' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</li>';
        }
        $output .= '</ul>';
        $output .= '<p class="text-muted mb-0">...и ещё ' . (count($success) - 10) . ' вопросов</p>';
    }
    
    $output .= '<hr>';
    $output .= '<a href="' . htmlspecialchars($testUrl, ENT_QUOTES, 'UTF-8') . '" class="btn btn-primary btn-lg">Перейти к тесту →</a>';
    $output .= '</div>';
}

$output .= '<div class="card">';
$output .= '<div class="card-header bg-primary text-white">';
$output .= '<h5 class="mb-0">Загрузить файл с вопросами</h5>';
$output .= '</div>';
$output .= '<div class="card-body">';

$output .= '<form method="POST" enctype="multipart/form-data">';
$output .= CsrfProtection::getTokenField(); // CSRF Protection
$output .= '<input type="hidden" name="test_id" value="' . $testId . '">';

$output .= '<div class="mb-3">';
$output .= '<label class="form-label fw-bold">Выберите файл (CSV или Excel)</label>';
$output .= '<input type="file" name="csv_file" class="form-control" accept=".csv,.xlsx,.xls" required>';
$output .= '<small class="form-text text-muted">Поддерживаемые форматы: CSV, XLSX, XLS</small>';
$output .= '</div>';

$output .= '<div class="alert alert-info">';
$output .= '<h6><i class="bi bi-info-circle"></i> Формат файла:</h6>';
$output .= '<table class="table table-sm table-bordered mb-0 bg-white">';
$output .= '<thead><tr>';
$output .= '<th>Столбец</th><th>Описание</th><th>Пример</th>';
$output .= '</tr></thead>';
$output .= '<tbody>';
$output .= '<tr><td><strong>A</strong></td><td>Текст вопроса</td><td>Что такое SQL?</td></tr>';
$output .= '<tr><td><strong>B</strong></td><td>Тип вопроса</td><td>single или multiple</td></tr>';
$output .= '<tr><td><strong>C</strong></td><td>Вариант ответа 1</td><td>Язык программирования</td></tr>';
$output .= '<tr><td><strong>D</strong></td><td>Вариант ответа 2</td><td>Язык запросов</td></tr>';
$output .= '<tr><td><strong>E</strong></td><td>Вариант ответа 3</td><td>База данных</td></tr>';
$output .= '<tr><td><strong>F</strong></td><td>Вариант ответа 4</td><td>Сервер</td></tr>';
$output .= '<tr><td><strong>G</strong></td><td>Правильные ответы</td><td>2 (или 1,3 для multiple)</td></tr>';
$output .= '<tr><td><strong>H</strong></td><td>Объяснение (необязательно)</td><td>SQL - это язык структурированных запросов</td></tr>';
$output .= '</tbody>';
$output .= '</table>';
$output .= '</div>';

if (!$hasPhpSpreadsheet) {
    $output .= '<div class="alert alert-warning">';
    $output .= '<strong>Внимание:</strong> PhpSpreadsheet не установлен. Excel файлы не поддерживаются.<br>';
    $output .= 'Используйте CSV формат.';
    $output .= '</div>';
}

$output .= '<button type="submit" class="btn btn-primary btn-lg w-100">';
$output .= '<i class="bi bi-upload"></i> Загрузить и импортировать';
$output .= '</button>';

$output .= '</form>';
$output .= '</div>';
$output .= '</div>';

$output .= '</div>';
$output .= '</div>';
$output .= '</div>';

return $output;