<?php
/* TS ADD TEST FORM v4.9 - FIXED FOR RESOURCE HIERARCHY + EXCEL SUPPORT */

// Подключаем bootstrap для CSRF защиты
require_once MODX_CORE_PATH . 'components/testsystem/bootstrap.php';

// ============================================
// НАСТРОЙКИ (ID ресурсов)
// ============================================
$TESTS_ROOT_ID = 35; // ID корневой папки "Тесты"
$IMPORT_PAGE_ID = 29; // ID страницы импорта

// ============================================
// ПРОВЕРКА АВТОРИЗАЦИИ
// ============================================
if (!$modx->user->hasSessionContext("web")) {
    $authUrl = $modx->makeUrl($modx->getOption("lms.auth_page", null, 0));
    return "<div class=\"alert alert-warning\"><a href=\"" . $authUrl . "\">Войдите</a>, чтобы добавлять тесты</div>";
}


// ИСПРАВЛЕНО: Более гибкая проверка прав
$currentUserId = (int)$modx->user->get('id');
$canCreate = false;

$prefix = $modx->getOption('table_prefix');

// Проверка 1: ID=1 (суперадмин)
if ($currentUserId === 1) {
    $canCreate = true;
}

// Проверка 2: Роли LMS Admins или LMS Experts
if (!$canCreate) {
    $sql = "SELECT mgn.`name` 
            FROM `{$prefix}member_groups` AS mg
            JOIN `{$prefix}membergroup_names` AS mgn ON mgn.`id` = mg.`user_group`
            WHERE mg.`member` = :uid 
            AND mgn.`name` IN ('LMS Admins', 'LMS Experts')";

    $stmt = $modx->prepare($sql);
    if ($stmt && $stmt->execute([':uid' => $currentUserId])) {
        $groups = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $canCreate = count($groups) > 0;
    }
}

if (!$canCreate) {
    return "<div class=\"alert alert-danger\">
        <h4>Доступ запрещён</h4>
        <p>Создание тестов доступно только экспертам и администраторам.</p>
        <p><small>Ваш ID: {$currentUserId}. Обратитесь к администратору для назначения роли LMS Experts.</small></p>
    </div>";
}

$errors = [];

// ============================================
// ОБРАБОТКА СОЗДАНИЯ ТЕСТА
// ============================================
if ($_POST && isset($_POST["add_test"])) {
    // CSRF Protection
    if (!CsrfProtection::validateRequest($_POST)) {
        $errors[] = "Ошибка безопасности. Обновите страницу и попробуйте снова.";
    } else {
    $parentId = (int)($_POST["category_id"] ?? 0);
    $title = trim($_POST["title"] ?? "");
    $description = trim($_POST["description"] ?? "");
    
    // Преобразуем mode для БД (enum принимает только 'training' или 'exam')
    $modeInput = $_POST["mode"] ?? "both";
    $mode = ($modeInput === 'exam') ? 'exam' : 'training';
    
    $timeLimit = (int)($_POST["time_limit"] ?? 0);
    $passScore = (int)($_POST["pass_score"] ?? 70);
    $questionsPerSession = (int)($_POST["questions_per_session"] ?? 20);
    $uploadFile = isset($_POST["upload_file"]) && $_POST["upload_file"] === "1";
    
    if (!$parentId) $errors[] = "Выберите категорию";
    if (empty($title)) $errors[] = "Введите название теста";
    if ($passScore < 0 || $passScore > 100) $errors[] = "Проходной балл: 0-100%";
    
    // Обработка загруженного файла
    $uploadedFilePath = null;
    if ($uploadFile && isset($_FILES['questions_file']) && $_FILES['questions_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['questions_file'];
        $fileName = $file['name'];
        $fileTmpPath = $file['tmp_name'];
        $fileSize = $file['size'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        $allowedExtensions = ['csv', 'xlsx', 'xls'];
        if (!in_array($fileExtension, $allowedExtensions)) {
            $errors[] = "Недопустимый формат файла. Разрешены: CSV, XLSX, XLS";
        }
        
        if ($fileSize > 10 * 1024 * 1024) {
            $errors[] = "Файл слишком большой (макс. 10MB)";
        }
        
        if (empty($errors)) {
            $uploadDir = MODX_ASSETS_PATH . 'uploads/test_imports/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $newFileName = 'test_' . time() . '_' . uniqid() . '.' . $fileExtension;
            $destPath = $uploadDir . $newFileName;
            
            if (move_uploaded_file($fileTmpPath, $destPath)) {
                $uploadedFilePath = $newFileName;
                $modx->log(modX::LOG_LEVEL_INFO, "[addTestForm] File uploaded: {$destPath}");
            } else {
                $errors[] = "Ошибка при загрузке файла";
                $modx->log(modX::LOG_LEVEL_ERROR, "[addTestForm] Failed to move uploaded file");
            }
        }
    } elseif ($uploadFile && (!isset($_FILES['questions_file']) || $_FILES['questions_file']['error'] !== UPLOAD_ERR_OK)) {
        $errors[] = "Выберите файл для загрузки или снимите галочку";
    }
    
    if (empty($errors)) {
        $categoryFolder = $modx->getObject('modResource', $parentId);
        
        if (!$categoryFolder) {
            $errors[] = "Папка-категория с ID {$parentId} не найдена";
            $modx->log(modX::LOG_LEVEL_ERROR, "[addTestForm] Parent folder not found: ID={$parentId}");
        } else {
            $parentContext = $categoryFolder->get('context_key');
            
            $testResource = $modx->newObject('modResource');
            $testResource->set('pagetitle', $title);
            $testResource->set('longtitle', $description);
            $testResource->set('template', 3);
            $testResource->set('parent', $categoryFolder->id);
            $testResource->set('context_key', $parentContext);
            $testResource->set('published', 1);
            $testResource->set('hidemenu', 0);
            $testResource->set('content', '[[!testRunner]]');
            $testResource->set('alias', '');
            
            if ($testResource->save()) {
                $resourceId = $testResource->get('id');
                
                $stmt = $modx->prepare("
                    INSERT INTO `{$prefix}test_tests` 
                    (resource_id, title, description, created_by, mode, time_limit, pass_score, 
                     questions_per_session, randomize_questions, randomize_answers, is_active, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 1, 1, NOW())
                ");                
                
                
                // ИСПРАВЛЕНО: Добавлен $currentUserId
                if ($stmt->execute([
                    $resourceId, 
                    $title, 
                    $description, 
                    $currentUserId,  // КРИТИЧНО: владелец теста
                    $mode, 
                    $timeLimit, 
                    $passScore, 
                    $questionsPerSession
                    ])) {                
                    $newTestId = $modx->lastInsertId();
                    
                    $testResource->set('alias', 'test-' . $newTestId);
                    if ($testResource->save()) {
                        $modx->log(modX::LOG_LEVEL_INFO, "[addTestForm] Test created: ID={$newTestId}, Resource={$resourceId}");
                        
                        // Очистка кеша
                        $modx->cacheManager->refresh([
                            'db' => [],
                            'auto_publish' => ['contexts' => ['web']],
                            'context_settings' => ['contexts' => ['web']],
                            'resource' => ['contexts' => ['web']],
                            'menu' => [],
                            'scripts' => [],
                        ]);
                        
                        $modx->cacheManager->delete($resourceId, [xPDO::OPT_CACHE_KEY => 'resource']);
                        $modx->cacheManager->delete($categoryFolder->id, [xPDO::OPT_CACHE_KEY => 'resource']);
                        
                        $parentIds = $modx->getParentIds($resourceId, 10, ['context' => 'web']);
                        foreach ($parentIds as $pid) {
                            $modx->cacheManager->delete($pid, [xPDO::OPT_CACHE_KEY => 'resource']);
                        }
                        
                        $cacheDir = MODX_CORE_PATH . 'cache/';
                        if (is_dir($cacheDir)) {
                            $modx->cacheManager->deleteTree($cacheDir, [
                                'deleteTop' => false,
                                'skipDirs' => false,
                                'extensions' => ['.cache.php', '.msg.php']
                            ]);
                        }
                        
                        // Редирект на страницу импорта
                        $params = ['test_id' => $newTestId];
                        if ($uploadedFilePath) {
                            $params['file'] = $uploadedFilePath;
                        }
                        
                        $importUrl = $modx->makeUrl($IMPORT_PAGE_ID, '', $params);
                        
                        if (empty($importUrl)) {
                            $baseUrl = rtrim($modx->getOption('site_url'), '/');
                            $importUrl = $baseUrl . '/' . http_build_query($params);
                        }
                        
                        $modx->log(modX::LOG_LEVEL_INFO, "[addTestForm] Redirecting to: {$importUrl}");
                        
                        $modx->sendRedirect($importUrl);
                        exit;
                    } else {
                        $errors[] = "Ошибка при обновлении alias ресурса";
                        $modx->log(modX::LOG_LEVEL_ERROR, "[addTestForm] Failed to update alias");
                    }
                } else {
                    $testResource->remove();
                    $errorInfo = $stmt->errorInfo();
                    $errors[] = "Ошибка при создании теста в БД: " . $errorInfo[2];
                    $modx->log(modX::LOG_LEVEL_ERROR, "[addTestForm] Failed to create test: " . print_r($errorInfo, true));
                }
            } else {
                $errors[] = "Ошибка при создании ресурса";
                $modx->log(modX::LOG_LEVEL_ERROR, "[addTestForm] Failed to create resource");
            }
        }
    }
    } // Закрываем else блок CSRF проверки
}

// ============================================
// ПОЛУЧЕНИЕ КАТЕГОРИЙ (ПАПОК)
// ============================================
$sql = "SELECT id, pagetitle as name 
        FROM `{$prefix}site_content` 
        WHERE parent = {$TESTS_ROOT_ID}
        AND isfolder = 1 
        AND deleted = 0
        AND published = 1
        ORDER BY menuindex";
$stmt = $modx->query($sql);
$categories = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

// ============================================
// ФОРМИРОВАНИЕ HTML
// ============================================
$output = "";
$output .= "<div class=\"row\">";
$output .= "<div class=\"col-md-8 offset-md-2\">";

if (!empty($errors)) {
    $output .= "<div class=\"alert alert-danger\"><ul class=\"mb-0\">";
    foreach ($errors as $error) {
        $output .= "<li>" . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . "</li>";
    }
    $output .= "</ul></div>";
}

$output .= "<div class=\"card\">";
$output .= "<div class=\"card-header bg-primary text-white\">";
$output .= "<h4 class=\"mb-0\">Создать новый тест</h4>";
$output .= "</div>";
$output .= "<div class=\"card-body\">";
$output .= "<form method=\"POST\" enctype=\"multipart/form-data\">";
$output .= CsrfProtection::getTokenField(); // CSRF Protection
$output .= "<input type=\"hidden\" name=\"add_test\" value=\"1\">";

$output .= "<div class=\"mb-3\">";
$output .= "<label class=\"form-label\">Категория *</label>";
$output .= "<select name=\"category_id\" class=\"form-select\" required>";
$output .= "<option value=\"\">-- Выберите категорию --</option>";
foreach ($categories as $cat) {
    $selected = (isset($_POST["category_id"]) && $_POST["category_id"] == $cat["id"]) ? "selected" : "";
    $output .= "<option value=\"" . (int)$cat["id"] . "\" " . $selected . ">" . htmlspecialchars($cat["name"], ENT_QUOTES, 'UTF-8') . "</option>";
}
$output .= "</select>";
if (empty($categories)) { 
    $output .= "<small class=\"form-text text-danger\">Категории не найдены. Создайте папки в разделе \"Тесты\" (ID: {$TESTS_ROOT_ID}).</small>";
} 
$output .= "</div>";

$output .= "<div class=\"mb-3\">";
$output .= "<label class=\"form-label\">Название теста *</label>";
$output .= "<input type=\"text\" name=\"title\" class=\"form-control\" value=\"" . htmlspecialchars($_POST["title"] ?? "", ENT_QUOTES, 'UTF-8') . "\" required>";
$output .= "<small class=\"form-text text-muted\">Например: Основы SQL</small>";
$output .= "</div>";

$output .= "<div class=\"mb-3\">";
$output .= "<label class=\"form-label\">Описание</label>";
$output .= "<textarea name=\"description\" class=\"form-control\" rows=\"3\">" . htmlspecialchars($_POST["description"] ?? "", ENT_QUOTES, 'UTF-8') . "</textarea>";
$output .= "</div>";

$output .= "<div class=\"row\">";
$output .= "<div class=\"col-md-6 mb-3\">";
$output .= "<label class=\"form-label\">Режим теста</label>";
$output .= "<select name=\"mode\" class=\"form-select\">";
$output .= "<option value=\"both\" " . (($_POST["mode"] ?? "both") == "both" ? "selected" : "") . ">Оба режима</option>";
$output .= "<option value=\"training\" " . (($_POST["mode"] ?? "") == "training" ? "selected" : "") . ">Только Training</option>";
$output .= "<option value=\"exam\" " . (($_POST["mode"] ?? "") == "exam" ? "selected" : "") . ">Только Exam</option>";
$output .= "</select>";
$output .= "</div>";

$output .= "<div class=\"col-md-6 mb-3\">";
$output .= "<label class=\"form-label\">Проходной балл (%)</label>";
$output .= "<input type=\"number\" name=\"pass_score\" class=\"form-control\" value=\"" . (int)($_POST["pass_score"] ?? 70) . "\" min=\"0\" max=\"100\">";
$output .= "</div>";
$output .= "</div>";

$output .= "<div class=\"row\">";
$output .= "<div class=\"col-md-6 mb-3\">";
$output .= "<label class=\"form-label\">Вопросов за попытку</label>";
$output .= "<input type=\"number\" name=\"questions_per_session\" class=\"form-control\" value=\"" . (int)($_POST["questions_per_session"] ?? 20) . "\" min=\"1\">";
$output .= "</div>";

$output .= "<div class=\"col-md-6 mb-3\">";
$output .= "<label class=\"form-label\">Время на тест (минут)</label>";
$output .= "<input type=\"number\" name=\"time_limit\" class=\"form-control\" value=\"" . (int)($_POST["time_limit"] ?? 0) . "\" min=\"0\">";
$output .= "<small class=\"form-text text-muted\">0 = без ограничения</small>";
$output .= "</div>";
$output .= "</div>";

// Блок загрузки файла - ЗАМЕНИТЬ ЭТОТ БЛОК
$output .= "<hr class=\"my-4\">";
$output .= "<div class=\"card bg-light\">";
$output .= "<div class=\"card-body\">";
$output .= "<div class=\"form-check form-switch mb-3\">";
$output .= "<input class=\"form-check-input\" type=\"checkbox\" id=\"upload_file_toggle\" name=\"upload_file\" value=\"1\" style=\"width: 3em; height: 1.5em; cursor: pointer;\">";
$output .= "<label class=\"form-check-label fw-bold ms-2\" for=\"upload_file_toggle\" style=\"cursor: pointer; font-size: 1.1em;\">";
$output .= "<i class=\"bi bi-file-earmark-spreadsheet\"></i> Загрузить вопросы из файла (CSV или Excel)";
$output .= "</label>";
$output .= "</div>";

$output .= "<div id=\"file_upload_block\" style=\"display: none;\">";
$output .= "<div class=\"mb-3\">";
$output .= "<label class=\"form-label\">Выберите файл *</label>";
$output .= "<input type=\"file\" name=\"questions_file\" class=\"form-control\" accept=\".csv,.xlsx,.xls\" id=\"questions_file_input\">";
$output .= "<small class=\"form-text text-muted\">";
$output .= "Поддерживаемые форматы: CSV, XLSX, XLS (максимум 10MB)<br>";
$output .= "После создания теста вы будете перенаправлены на страницу импорта с выбранным файлом";
$output .= "</small>";
$output .= "</div>";

$output .= "<div class=\"alert alert-info mb-0\">";
$output .= "<strong>Формат файла:</strong><br>";
$output .= "<small>";
$output .= "• <strong>Столбец A:</strong> Текст вопроса<br>";
$output .= "• <strong>Столбец B:</strong> Тип (single/multiple)<br>";
$output .= "• <strong>Столбец C:</strong> Ответ 1<br>";
$output .= "• <strong>Столбец D:</strong> Ответ 2<br>";
$output .= "• <strong>Столбец E:</strong> Ответ 3<br>";
$output .= "• <strong>Столбец F:</strong> Ответ 4<br>";
$output .= "• <strong>Столбец G:</strong> Правильные ответы (1,3 или 2)<br>";
$output .= "• <strong>Столбец H:</strong> Объяснение (необязательно)";
$output .= "</small>";
$output .= "</div>";

$output .= "</div>";
$output .= "</div>";
$output .= "</div>";

$output .= "<div class=\"alert alert-info mt-3\">";
$output .= "<strong>Обратите внимание:</strong> После создания теста вы будете перенаправлены на страницу импорта вопросов.";
$output .= "</div>";

$output .= "<div class=\"d-flex justify-content-between mt-4\">";
$testsUrl = $modx->makeUrl($modx->getOption("lms.tests_page", null, 35));
$output .= "<a href=\"" . htmlspecialchars($testsUrl, ENT_QUOTES, 'UTF-8') . "\" class=\"btn btn-secondary\">Отмена</a>";
$output .= "<button type=\"submit\" class=\"btn btn-primary btn-lg\">Создать тест и перейти к добавлению вопросов →</button>";
$output .= "</div>";

$output .= "</form>";
$output .= "</div>";
$output .= "</div>";
$output .= "</div>";
$output .= "</div>";

// JavaScript
$output .= "<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggle = document.getElementById('upload_file_toggle');
    const block = document.getElementById('file_upload_block');
    const fileInput = document.getElementById('questions_file_input');
    
    if (toggle && block) {
        toggle.addEventListener('change', function() {
            if (this.checked) {
                block.style.display = 'block';
                fileInput.setAttribute('required', 'required');
            } else {
                block.style.display = 'none';
                fileInput.removeAttribute('required');
                fileInput.value = '';
            }
        });
    }
});
</script>";

return $output;