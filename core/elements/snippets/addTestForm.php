<?php
/**
 * Сниппет: addTestForm - Форма создания теста
 * Вызывается из: MODX ресурсов (страница добавления теста)
 * Назначение: Форма создания/редактирования теста с импортом из Excel
 *
 * @package TestSystem
 * @version 4.9
 */

// Подключаем bootstrap для CSRF защиты
require_once MODX_CORE_PATH . 'components/testsystem/bootstrap.php';

// Подключаем QuestionImportHelper для импорта вопросов
require_once MODX_CORE_PATH . 'components/testsystem/helpers/QuestionImportHelper.php';
use MPV2\TestSystem\Helpers\QuestionImportHelper;

// ============================================
// НАСТРОЙКИ (ID ресурсов из конфигурации)
// ============================================
$TESTS_ROOT_ID = Config::getPageId('tests_root', 35);
$IMPORT_PAGE_ID = Config::getPageId('import_csv', 29);

// ============================================
// ПРОВЕРКА АВТОРИЗАЦИИ И ПРАВ
// ============================================
try {
    PermissionHelper::requireAuthentication($modx);
    PermissionHelper::requireEditRights($modx, 'Создание тестов доступно только экспертам и администраторам.');
} catch (AuthenticationException $e) {
    return $e->renderAlert($modx, 'Для добавления тестов необходимо войти в систему.');
} catch (PermissionException $e) {
    return "<div class=\"alert alert-danger\">
        <h4>Доступ запрещён</h4>
        <p>" . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</p>
    </div>";
}

$prefix = $modx->getOption('table_prefix');
$currentUserId = (int)$modx->user->get('id');
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

    // Определяем приватность теста
    $isPrivate = isset($_POST["is_private"]) && $_POST["is_private"] === "1";
    $publicationStatus = $isPrivate ? 'private' : 'public';
    
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
        // ИСПРАВЛЕНО: Проверяем что категория существует в test_categories
        $stmt = $modx->prepare("SELECT id FROM `{$prefix}test_categories` WHERE id = ?");
        $stmt->execute([$parentId]);
        $categoryExists = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$categoryExists) {
            $errors[] = "Категория с ID {$parentId} не найдена";
            $modx->log(modX::LOG_LEVEL_ERROR, "[addTestForm] Category not found: ID={$parentId}");
        } else {
            // ИСПРАВЛЕНО: Создаём тест БЕЗ MODX ресурса, только запись в БД
            // Тесты отображаются через /test-run?testId=X

            $stmt = $modx->prepare("
                INSERT INTO `{$prefix}test_tests`
                (resource_id, title, description, created_by, mode, time_limit, pass_score,
                 questions_per_session, randomize_questions, randomize_answers, is_active, publication_status, created_at, category_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 1, 1, ?, NOW(), ?)
            ");

            // resource_id = category_id (НЕ ID страницы MODX!)
            if ($stmt->execute([
                $parentId,  // resource_id = category_id
                $title,
                $description,
                $currentUserId,  // КРИТИЧНО: владелец теста
                $mode,
                $timeLimit,
                $passScore,
                $questionsPerSession,
                $publicationStatus,  // 'public' или 'private'
                $parentId  // category_id
                ])) {
                $newTestId = $modx->lastInsertId();

                $modx->log(modX::LOG_LEVEL_INFO, "[addTestForm] Test created: ID={$newTestId}, Category={$parentId}");

                // ГИБРИДНЫЙ ПОДХОД: Если загружен файл → импортируем сразу, иначе → редирект
                if ($uploadedFilePath) {
                    // ============================================
                    // ИМПОРТ ВОПРОСОВ ИЗ ФАЙЛА
                    // ============================================
                    $uploadDir = MODX_ASSETS_PATH . 'uploads/test_imports/';
                    $fullFilePath = $uploadDir . $uploadedFilePath;
                    $fileExtension = strtolower(pathinfo($uploadedFilePath, PATHINFO_EXTENSION));

                    // Проверяем наличие PhpSpreadsheet
                    $hasPhpSpreadsheet = QuestionImportHelper::hasPhpSpreadsheet();

                    $modx->log(modX::LOG_LEVEL_INFO, "[addTestForm] Starting import: file={$fullFilePath}, extension={$fileExtension}");

                    // Используем QuestionImportHelper для импорта
                    $importResult = QuestionImportHelper::importFromFile(
                        $fullFilePath,
                        $fileExtension,
                        $newTestId,
                        $modx,
                        $prefix,
                        $hasPhpSpreadsheet
                    );

                    // Удаляем временный файл после импорта
                    @unlink($fullFilePath);

                    // Формируем URL теста для кнопки "Перейти к тесту"
                    $testRunPageId = Config::getPageId('test_run', 155);
                    $testUrl = $modx->makeUrl($testRunPageId, '', ['testId' => $newTestId], 'full');
                    if (empty($testUrl)) {
                        $siteUrl = rtrim($modx->getOption('site_url'), '/');
                        $testUrl = $siteUrl . '/test-run?testId=' . $newTestId;
                    }

                    // Результаты импорта
                    if ($importResult['success'] && $importResult['imported'] > 0) {
                        // УСПЕХ: Показываем сообщение об успешном создании и импорте
                        $output = '<div class="container my-4">';
                        $output .= '<div class="alert alert-success alert-dismissible fade show">';
                        $output .= '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
                        $output .= '<h4 class="alert-heading"><i class="bi bi-check-circle"></i> Тест успешно создан!</h4>';
                        $output .= '<p class="mb-3"><strong>ID теста:</strong> ' . $newTestId . '</p>';
                        $output .= '<p class="mb-3"><strong>Название:</strong> ' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</p>';
                        $output .= '<hr>';
                        $output .= '<h5><i class="bi bi-check-circle"></i> Импортировано вопросов: ' . $importResult['imported'] . '</h5>';

                        // Показываем первые 5 сообщений об успехе
                        if (!empty($importResult['success_messages']) && count($importResult['success_messages']) <= 5) {
                            $output .= '<ul class="mb-3">';
                            foreach ($importResult['success_messages'] as $msg) {
                                $output .= '<li>' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</li>';
                            }
                            $output .= '</ul>';
                        } elseif (!empty($importResult['success_messages'])) {
                            $output .= '<p class="text-muted">Показаны детали для первых 5 вопросов:</p>';
                            $output .= '<ul class="mb-3">';
                            foreach (array_slice($importResult['success_messages'], 0, 5) as $msg) {
                                $output .= '<li>' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</li>';
                            }
                            $output .= '</ul>';
                            $output .= '<p class="text-muted">...и ещё ' . (count($importResult['success_messages']) - 5) . ' вопросов</p>';
                        }

                        // Показываем ошибки, если были (частичный успех)
                        if (!empty($importResult['errors'])) {
                            $output .= '<div class="alert alert-warning mt-3">';
                            $output .= '<h6><i class="bi bi-exclamation-triangle"></i> Предупреждения при импорте:</h6>';
                            $output .= '<ul class="mb-0">';
                            foreach (array_slice($importResult['errors'], 0, 10) as $error) {
                                $output .= '<li>' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</li>';
                            }
                            $output .= '</ul>';
                            if (count($importResult['errors']) > 10) {
                                $output .= '<p class="mb-0 mt-2 text-muted">...и ещё ' . (count($importResult['errors']) - 10) . ' предупреждений</p>';
                            }
                            $output .= '</div>';
                        }

                        $output .= '<hr>';
                        $output .= '<div class="d-flex gap-2">';
                        $output .= '<a href="' . htmlspecialchars($testUrl, ENT_QUOTES, 'UTF-8') . '" class="btn btn-primary btn-lg">';
                        $output .= '<i class="bi bi-play-circle"></i> Перейти к тесту';
                        $output .= '</a>';
                        $output .= '<a href="' . $modx->makeUrl($modx->resource->id) . '" class="btn btn-secondary btn-lg">';
                        $output .= '<i class="bi bi-plus-circle"></i> Создать ещё один тест';
                        $output .= '</a>';
                        $output .= '</div>';
                        $output .= '</div>';
                        $output .= '</div>';

                        return $output;
                    } else {
                        // ЧАСТИЧНАЯ ОШИБКА: Тест создан, но импорт не удался
                        $errors[] = "Тест создан (ID: {$newTestId}), но импорт вопросов не удался:";
                        $errors = array_merge($errors, $importResult['errors']);

                        // Добавляем ссылку на ручной импорт
                        $importUrl = $modx->makeUrl($IMPORT_PAGE_ID, '', ['test_id' => $newTestId]);
                        $errors[] = "Вы можете <a href=\"{$importUrl}\">добавить вопросы вручную</a> или загрузить другой файл.";

                        $modx->log(modX::LOG_LEVEL_ERROR, "[addTestForm] Import failed for test {$newTestId}: " . implode('; ', $importResult['errors']));
                    }
                } else {
                    // НЕТ ФАЙЛА: Редиректим на csvImportForm для ручного добавления
                    $importUrl = $modx->makeUrl($IMPORT_PAGE_ID, '', ['test_id' => $newTestId]);

                    if (empty($importUrl)) {
                        $baseUrl = rtrim($modx->getOption('site_url'), '/');
                        $importUrl = $baseUrl . '/import-csv?test_id=' . $newTestId;
                    }

                    $modx->log(modX::LOG_LEVEL_INFO, "[addTestForm] No file uploaded, redirecting to: {$importUrl}");

                    $modx->sendRedirect($importUrl);
                    exit;
                }
            } else {
                $errorInfo = $stmt->errorInfo();
                $errors[] = "Ошибка при создании теста в БД: " . $errorInfo[2];
                $modx->log(modX::LOG_LEVEL_ERROR, "[addTestForm] Failed to create test: " . print_r($errorInfo, true));
            }
        }
    }
    } // Закрываем else блок CSRF проверки
}

// ============================================
// ПОЛУЧЕНИЕ КАТЕГОРИЙ
// ============================================
// ИСПРАВЛЕНО: Берем категории из test_categories, а не из site_content
$sql = "SELECT id, name
        FROM `{$prefix}test_categories`
        WHERE 1=1
        ORDER BY sort_order, name";
$stmt = $modx->query($sql);
$categories = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

// ============================================
// ФОРМИРОВАНИЕ HTML
// ============================================
$output = "";
$output .= "<div class=\"container-fluid\">";

if (!empty($errors)) {
    $output .= "<div class=\"alert alert-danger alert-dismissible fade show\">";
    $output .= "<strong>Ошибки при создании теста:</strong>";
    $output .= "<ul class=\"mb-0 mt-2\">";
    foreach ($errors as $error) {
        $output .= "<li>" . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . "</li>";
    }
    $output .= "</ul>";
    $output .= "<button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"alert\"></button>";
    $output .= "</div>";
}

$output .= "<div class=\"card shadow-sm\">";
$output .= "<div class=\"card-header border-bottom\">";
$output .= "<h4 class=\"mb-0\"><i class=\"bi bi-plus-circle\"></i> Создать новый тест</h4>";
$output .= "</div>";
$output .= "<div class=\"card-body\">";
$output .= "<form method=\"POST\" enctype=\"multipart/form-data\">";
$output .= CsrfProtection::getTokenField(); // CSRF Protection
$output .= "<input type=\"hidden\" name=\"add_test\" value=\"1\">";

// Основная информация
$output .= "<h5 class=\"mb-3\"><i class=\"bi bi-info-circle\"></i> Основная информация</h5>";

$output .= "<div class=\"row\">";
$output .= "<div class=\"col-md-6 mb-3\">";
$output .= "<label class=\"form-label\"><i class=\"bi bi-folder\"></i> Категория *</label>";
$output .= "<div class=\"input-group\">";
$output .= "<select name=\"category_id\" id=\"category_select\" class=\"form-select\" required>";
$output .= "<option value=\"\">-- Выберите категорию --</option>";
foreach ($categories as $cat) {
    $selected = (isset($_POST["category_id"]) && $_POST["category_id"] == $cat["id"]) ? "selected" : "";
    $output .= "<option value=\"" . (int)$cat["id"] . "\" " . $selected . ">" . htmlspecialchars($cat["name"], ENT_QUOTES, 'UTF-8') . "</option>";
}
$output .= "</select>";
$output .= "<button type=\"button\" class=\"btn btn-outline-success\" onclick=\"openCreateCategoryModal()\" title=\"Создать новую категорию\">";
$output .= "<i class=\"bi bi-plus-lg\"></i>";
$output .= "</button>";
$output .= "</div>";
if (empty($categories)) {
    $output .= "<small class=\"form-text text-warning\">Нет категорий. Нажмите + чтобы создать.</small>";
}
$output .= "</div>";

$output .= "<div class=\"col-md-6 mb-3\">";
$output .= "<label class=\"form-label\"><i class=\"bi bi-file-text\"></i> Название теста *</label>";
$output .= "<input type=\"text\" name=\"title\" class=\"form-control\" value=\"" . htmlspecialchars($_POST["title"] ?? "", ENT_QUOTES, 'UTF-8') . "\" required placeholder=\"Например: Основы SQL\">";
$output .= "</div>";
$output .= "</div>";

$output .= "<div class=\"mb-3\">";
$output .= "<label class=\"form-label\"><i class=\"bi bi-card-text\"></i> Описание</label>";
$output .= "<textarea name=\"description\" class=\"form-control\" rows=\"3\" placeholder=\"Краткое описание теста\">" . htmlspecialchars($_POST["description"] ?? "", ENT_QUOTES, 'UTF-8') . "</textarea>";
$output .= "</div>";

// Чекбокс "Приватный тест"
$output .= "<div class=\"mb-4\">";
$output .= "<div class=\"form-check form-switch\">";
$output .= "<input class=\"form-check-input\" type=\"checkbox\" id=\"is_private\" name=\"is_private\" value=\"1\" " . (isset($_POST["is_private"]) && $_POST["is_private"] === "1" ? "checked" : "") . ">";
$output .= "<label class=\"form-check-label\" for=\"is_private\">";
$output .= "<i class=\"bi bi-lock\"></i> <strong>Приватный тест</strong> ";
$output .= "<small class=\"text-muted\">(доступен только вам, но вы можете предоставить доступ другим пользователям)</small>";
$output .= "</label>";
$output .= "</div>";
$output .= "</div>";

$output .= "<hr class=\"my-4\">";

// Настройки теста
$output .= "<h5 class=\"mb-3\"><i class=\"bi bi-gear\"></i> Настройки теста</h5>";

$output .= "<div class=\"row\">";
$output .= "<div class=\"col-md-6 mb-3\">";
$output .= "<label class=\"form-label\"><i class=\"bi bi-trophy\"></i> Режим теста</label>";
$output .= "<select name=\"mode\" class=\"form-select\">";
$output .= "<option value=\"both\" " . (($_POST["mode"] ?? "both") == "both" ? "selected" : "") . ">🎯 Оба режима (Training + Exam)</option>";
$output .= "<option value=\"training\" " . (($_POST["mode"] ?? "") == "training" ? "selected" : "") . ">📚 Только Training (обучение)</option>";
$output .= "<option value=\"exam\" " . (($_POST["mode"] ?? "") == "exam" ? "selected" : "") . ">🏆 Только Exam (экзамен)</option>";
$output .= "</select>";
$output .= "</div>";

$output .= "<div class=\"col-md-6 mb-3\">";
$output .= "<label class=\"form-label\"><i class=\"bi bi-percent\"></i> Проходной балл (%)</label>";
$output .= "<input type=\"number\" name=\"pass_score\" class=\"form-control\" value=\"" . (int)($_POST["pass_score"] ?? 70) . "\" min=\"0\" max=\"100\" placeholder=\"70\">";
$output .= "<small class=\"form-text text-muted\">Минимальный процент для успешного прохождения</small>";
$output .= "</div>";
$output .= "</div>";

$output .= "<div class=\"row\">";
$output .= "<div class=\"col-md-6 mb-3\">";
$output .= "<label class=\"form-label\"><i class=\"bi bi-question-circle\"></i> Вопросов за попытку</label>";
$output .= "<input type=\"number\" name=\"questions_per_session\" class=\"form-control\" value=\"" . (int)($_POST["questions_per_session"] ?? 20) . "\" min=\"1\" placeholder=\"20\">";
$output .= "<small class=\"form-text text-muted\">Количество вопросов в одной сессии</small>";
$output .= "</div>";

$output .= "<div class=\"col-md-6 mb-3\">";
$output .= "<label class=\"form-label\"><i class=\"bi bi-clock\"></i> Время на тест (минут)</label>";
$output .= "<input type=\"number\" name=\"time_limit\" class=\"form-control\" value=\"" . (int)($_POST["time_limit"] ?? 0) . "\" min=\"0\" placeholder=\"0 = без ограничения\">";
$output .= "<small class=\"form-text text-muted\">0 = без ограничения по времени</small>";
$output .= "</div>";
$output .= "</div>";

// Блок загрузки файла
$output .= "<hr class=\"my-4\">";

$output .= "<h5 class=\"mb-3\"><i class=\"bi bi-upload\"></i> Импорт вопросов</h5>";

$output .= "<div class=\"card bg-light border-0\">";
$output .= "<div class=\"card-body\">";
$output .= "<div class=\"form-check form-switch mb-3\">";
$output .= "<input class=\"form-check-input\" type=\"checkbox\" id=\"upload_file_toggle\" name=\"upload_file\" value=\"1\" style=\"width: 3em; height: 1.5em; cursor: pointer;\">";
$output .= "<label class=\"form-check-label fw-bold ms-2\" for=\"upload_file_toggle\" style=\"cursor: pointer;\">";
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

$output .= "<div class=\"alert alert-info border-0 mt-4\">";
$output .= "<i class=\"bi bi-info-circle-fill\"></i> <strong>Обратите внимание:</strong> ";
$output .= "После создания теста вы будете перенаправлены на страницу добавления вопросов.";
$output .= "</div>";

$output .= "<div class=\"d-flex justify-content-between align-items-center mt-4\">";
$testsUrl = $modx->makeUrl($modx->getOption("lms.tests_page", null, 35));
$output .= "<a href=\"" . htmlspecialchars($testsUrl, ENT_QUOTES, 'UTF-8') . "\" class=\"btn btn-outline-secondary\">";
$output .= "<i class=\"bi bi-arrow-left\"></i> Отмена";
$output .= "</a>";
$output .= "<button type=\"submit\" class=\"btn btn-primary btn-lg\">";
$output .= "<i class=\"bi bi-check-circle\"></i> Создать тест и перейти к вопросам";
$output .= "</button>";
$output .= "</div>";

$output .= "</form>";
$output .= "</div>";  // card-body
$output .= "</div>";  // card
$output .= "</div>";  // container-fluid

// Модальное окно создания категории
$output .= '
<div class="modal fade" id="createCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-folder-plus"></i> Создать категорию</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Название категории *</label>
                    <input type="text" id="new_category_name" class="form-control" placeholder="Например: Базы данных" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Описание (необязательно)</label>
                    <textarea id="new_category_description" class="form-control" rows="2" placeholder="Краткое описание категории"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-success" onclick="createCategory()">
                    <i class="bi bi-check-circle"></i> Создать
                </button>
            </div>
        </div>
    </div>
</div>';

// JavaScript
$output .= "<script>
const API_URL = '/assets/components/testsystem/ajax/testsystem.php';

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

function openCreateCategoryModal() {
    document.getElementById('new_category_name').value = '';
    document.getElementById('new_category_description').value = '';
    const modal = new bootstrap.Modal(document.getElementById('createCategoryModal'));
    modal.show();
}

async function createCategory() {
    const name = document.getElementById('new_category_name').value.trim();
    const description = document.getElementById('new_category_description').value.trim();

    if (!name) {
        alert('Введите название категории');
        return;
    }

    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'createCategory',
                data: { name: name, description: description }
            })
        });

        const result = await response.json();

        if (result.success) {
            // Добавляем новую категорию в select и выбираем её
            const select = document.getElementById('category_select');
            const option = document.createElement('option');
            option.value = result.data.id;
            option.textContent = result.data.name;
            option.selected = true;
            select.appendChild(option);

            // Закрываем модальное окно
            const modal = bootstrap.Modal.getInstance(document.getElementById('createCategoryModal'));
            modal.hide();

            alert('Категория \"' + name + '\" успешно создана!');
        } else {
            alert('Ошибка: ' + (result.message || 'Неизвестная ошибка'));
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Ошибка создания категории: ' + error.message);
    }
}
</script>";

return $output;