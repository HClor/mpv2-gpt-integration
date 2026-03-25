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

// Гарантируем подключение стилей интерфейса импорта на любых страницах/шаблонах
$assetsUrl = rtrim($modx->getOption('assets_url', null, MODX_ASSETS_URL), '/') . '/';
$modx->regClientCSS($assetsUrl . 'components/testsystem/css/ts-variables.css');
$modx->regClientCSS($assetsUrl . 'components/testsystem/css/ts-layout.css');
$modx->regClientCSS($assetsUrl . 'components/testsystem/css/ts-components.css');
$modx->regClientCSS($assetsUrl . 'components/testsystem/css/testsystem-extended.css');

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
    $allowGuestPass = isset($_POST["allow_guest_pass"]) && $_POST["allow_guest_pass"] === "1" ? 1 : 0;

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
                 questions_per_session, randomize_questions, randomize_answers, is_active, publication_status, allow_guest_pass, created_at, category_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 1, 1, ?, ?, NOW(), ?)
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
                $allowGuestPass,
                $parentId  // category_id
                ])) {
                $newTestId = $modx->lastInsertId();

                $modx->log(modX::LOG_LEVEL_INFO, "[addTestForm] Test created: ID={$newTestId}, Category={$parentId}");

                // ЕДИНЫЙ ПОТОК ИМПОРТА: addTestForm только создаёт тест и (опционально) загружает файл,
                // а csvImportForm выполняет сам импорт и рендерит актуальный UI результатов.
                $importParams = ['test_id' => $newTestId];
                if (!empty($uploadedFilePath)) {
                    $importParams['file'] = $uploadedFilePath;
                    $modx->log(modX::LOG_LEVEL_INFO, "[addTestForm] Uploaded file queued for csvImportForm: {$uploadedFilePath}");
                }

                $importPageId = (int)$IMPORT_PAGE_ID;
                $importUrl = $importPageId > 0
                    ? $modx->makeUrl($importPageId, '', $importParams)
                    : '';

                if (empty($importUrl)) {
                    $baseUrl = rtrim($modx->getOption('site_url'), '/');
                    $query = http_build_query($importParams);
                    $importUrl = $baseUrl . '/import-csv?' . $query;
                }

                $modx->log(modX::LOG_LEVEL_INFO, "[addTestForm] Redirecting to csvImportForm: {$importUrl}");
                $modx->sendRedirect($importUrl);
                exit;
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

$output .= "<div class=\"mb-4\">";
$output .= "<div class=\"form-check form-switch\">";
$output .= "<input class=\"form-check-input\" type=\"checkbox\" id=\"allow_guest_pass\" name=\"allow_guest_pass\" value=\"1\" " . (isset($_POST["allow_guest_pass"]) && $_POST["allow_guest_pass"] === "1" ? "checked" : "") . ">";
$output .= "<label class=\"form-check-label\" for=\"allow_guest_pass\">";
$output .= "<i class=\"bi bi-person-check\"></i> <strong>Разрешить гостевое прохождение</strong> ";
$output .= "<small class=\"text-muted\">(без регистрации, с фиксированными 10 вопросами)</small>";
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

$isUploadChecked = (isset($_POST['upload_file']) && $_POST['upload_file'] === '1');

$output .= "<section class=\"ts-import-step ts-import-step-questions\">";
$output .= "<p class=\"ts-import-step-kicker\">Шаг 2</p>";
$output .= "<h5 class=\"ts-import-step-title\"><i class=\"bi bi-upload\"></i> Добавление вопросов</h5>";
$output .= "<p class=\"ts-import-step-subtitle\">Выберите удобный сценарий: загрузка файла сейчас или ручное добавление вопросов после создания теста.</p>";
$output .= "</section>";

$output .= "<div class=\"ts-card ts-import-upload-main\">";
$output .= "<div class=\"ts-card-header\">";
$output .= "<h2 class=\"ts-card-title\">Загрузить файл с вопросами</h2>";
$output .= "<p class=\"ts-card-description\">Поддерживаются CSV, XLSX и XLS. После создания теста вы перейдёте в единый интерфейс импорта, где система проверит структуру и покажет результат.</p>";
$output .= "</div>";
$output .= "<div class=\"ts-card-body\">";
$output .= "<div class=\"ts-import-upload-toggle\">";
$output .= "<div class=\"form-check form-switch mb-0\">";
$output .= "<input class=\"form-check-input ts-import-upload-switch\" type=\"checkbox\" id=\"upload_file_toggle\" name=\"upload_file\" value=\"1\" " . ($isUploadChecked ? "checked" : "") . ">";
$output .= "<label class=\"form-check-label fw-semibold ms-2\" for=\"upload_file_toggle\">";
$output .= "<i class=\"bi bi-file-earmark-spreadsheet\"></i> Импортировать вопросы из CSV/Excel";
$output .= "</label>";
$output .= "</div>";
$output .= "<p class=\"ts-import-upload-toggle-note\">Если переключатель выключен, вы перейдёте к ручному добавлению вопросов на следующем экране.</p>";
$output .= "</div>";

$output .= "<div id=\"file_upload_block\" class=\"ts-import-upload-block" . ($isUploadChecked ? "" : " d-none") . "\">";
$output .= "<div class=\"row g-4\">";
$output .= "<div class=\"col-lg-6\">";
$output .= "<label class=\"form-label fw-semibold\" for=\"questions_file_input\">Выберите файл *</label>";
$output .= "<input type=\"file\" name=\"questions_file\" class=\"form-control\" accept=\".csv,.xlsx,.xls\" id=\"questions_file_input\"" . ($isUploadChecked ? " required" : "") . ">";
$output .= "<small class=\"form-text text-muted\">Поддерживаемые форматы: CSV, XLSX, XLS. Максимальный размер: 10MB.</small>";
$output .= "<p class=\"ts-import-upload-hint\">После создания теста вы перейдёте в единый интерфейс импорта (csvImportForm), где выполняется загрузка и отображается результат.</p>";
$output .= "</div>";

$output .= "<div class=\"col-lg-6\">";
$output .= "<div class=\"ts-import-format-panel\">";
$output .= "<h6 class=\"ts-import-format-panel-title\"><i class=\"bi bi-table\"></i> Структура файла</h6>";
$output .= "<ul class=\"ts-import-format-list\">";
$output .= "<li><span class=\"ts-import-col-badge\">A</span><div>Текст вопроса</div></li>";
$output .= "<li><span class=\"ts-import-col-badge\">B</span><div>Тип: <span class=\"ts-import-inline-example\">single</span> или <span class=\"ts-import-inline-example\">multiple</span></div></li>";
$output .= "<li><span class=\"ts-import-col-badge\">C–F</span><div>Варианты ответов</div></li>";
$output .= "<li><span class=\"ts-import-col-badge\">G</span><div>Правильные ответы: <span class=\"ts-import-inline-example\">2</span> или <span class=\"ts-import-inline-example\">1,3</span></div></li>";
$output .= "<li><span class=\"ts-import-col-badge\">H</span><div>Объяснение (необязательно)</div></li>";
$output .= "</ul>";
$output .= "<p class=\"ts-import-format-panel-note mb-0\">Рекомендуется использовать шаблон с заголовком в первой строке.</p>";
$output .= "</div>";
$output .= "</div>";
$output .= "</div>";
$output .= "</div>";

$output .= "</div>";
$output .= "</div>";

$output .= "<div class=\"alert alert-info border-0 mt-4\">";
$output .= "<i class=\"bi bi-info-circle-fill\"></i> <strong>Обратите внимание:</strong> ";
$output .= "После создания теста вы будете перенаправлены в интерфейс импорта/добавления вопросов.";
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
                <button type="button" class="ts-btn ts-btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="ts-btn ts-btn-success" onclick="createCategory()">
                    <i class="bi bi-check-circle"></i> Создать
                </button>
            </div>
        </div>
    </div>
</div>';

// JavaScript
$output .= "<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggle = document.getElementById('upload_file_toggle');
    const block = document.getElementById('file_upload_block');
    const fileInput = document.getElementById('questions_file_input');

    if (toggle && block) {
        toggle.addEventListener('change', function() {
            if (this.checked) {
                block.classList.remove('d-none');
                fileInput.setAttribute('required', 'required');
            } else {
                block.classList.add('d-none');
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
        // Используем глобальный хелпер tsApiRequest с автоматическим CSRF
        const result = await tsApiRequest('createCategory', {
            name: name,
            description: description
        });

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
