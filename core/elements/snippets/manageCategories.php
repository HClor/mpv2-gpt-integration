<?php
/**
 * Сниппет: manageCategories - Управление категориями
 * Вызывается из: MODX ресурсов (админ-панель)
 * Назначение: CRUD операции с категориями тестов (только для админов)
 *
 * @package TestSystem
 * @version 1.1
 */

// Подключаем bootstrap для CSRF защиты
require_once MODX_CORE_PATH . 'components/testsystem/bootstrap.php';

// Проверяем права (только админы)
try {
    PermissionHelper::requireAuthentication($modx);
} catch (AuthenticationException $e) {
    return $e->renderAlert($modx, 'Для управления категориями необходимо войти в систему.');
}

if (!PermissionHelper::isAdmin($modx)) {
    return "<div class=\"alert alert-danger\">
        <h4>Доступ запрещён</h4>
        <p>Управление категориями доступно только администраторам.</p>
    </div>";
}

$errors = [];
$success = "";

// ДОБАВЛЕНИЕ категории
if ($_POST && isset($_POST["add_category"])) {
    // CSRF Protection
    if (!CsrfProtection::validateRequest($_POST)) {
        $errors[] = "Ошибка безопасности. Обновите страницу и попробуйте снова.";
    } else {
    $name = trim($_POST["name"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $sortOrder = (int)($_POST["sort_order"] ?? 99);

    if (empty($name)) {
        $errors[] = "Введите название категории";
    }

    if (empty($errors)) {
        $prefix = $modx->getOption('table_prefix');
        $stmt = $modx->prepare("
            INSERT INTO {$prefix}test_categories (name, description, sort_order)
            VALUES (?, ?, ?)
        ");

        if ($stmt->execute([$name, $description, $sortOrder])) {
            $newCatId = $modx->lastInsertId();
            
            // /* TS AUTO FOLDER v1 */ Автосоздание папки для категории
            $testsContainer = $modx->getObject('modResource', ['alias' => 'tests']);
            if ($testsContainer) {
                $folder = $modx->newObject('modResource');
                $folder->set('pagetitle', $name);
                $folder->set('alias', 'cat-' . $newCatId);
                $folder->set('template', 0);
                $folder->set('parent', $testsContainer->id);
                $folder->set('published', 1);
                $folder->set('hidemenu', 0);
                $folder->set('isfolder', 1);
                $folder->set('content', "<h1>$name</h1><p>" . htmlspecialchars($description) . "</p>");
                $folder->save();
                $success = "Категория '$name' создана! Папка: /tests/cat-$newCatId (ID: {$folder->id})";
            } else {
                $success = "Категория создана, но папка /tests не найдена";
            }
            
            $modx->cacheManager->refresh();
        } else {
            $errors[] = "Ошибка при создании категории";
        }
    }
    } // Закрываем else блок CSRF проверки (add_category)
}

// РЕДАКТИРОВАНИЕ категории
if ($_POST && isset($_POST["edit_category"])) {
    // CSRF Protection
    if (!CsrfProtection::validateRequest($_POST)) {
        $errors[] = "Ошибка безопасности. Обновите страницу и попробуйте снова.";
    } else {
    $catId = (int)($_POST["category_id"] ?? 0);
    $name = trim($_POST["name"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $sortOrder = (int)($_POST["sort_order"] ?? 99);

    if (empty($name)) {
        $errors[] = "Введите название категории";
    }

    if (empty($errors) && $catId) {
        $prefix = $modx->getOption('table_prefix');
        $stmt = $modx->prepare("
            UPDATE {$prefix}test_categories
            SET name = ?, description = ?, sort_order = ?
            WHERE id = ?
        ");
        
        if ($stmt->execute([$name, $description, $sortOrder, $catId])) {
            $success = "Категория успешно обновлена!";
            $modx->cacheManager->refresh();
        } else {
            $errors[] = "Ошибка при обновлении категории";
        }
    }
    } // Закрываем else блок CSRF проверки (edit_category)
}

// УДАЛЕНИЕ категории
if ($_POST && isset($_POST["delete_category"])) {
    // CSRF Protection
    if (!CsrfProtection::validateRequest($_POST)) {
        $errors[] = "Ошибка безопасности. Обновите страницу и попробуйте снова.";
    } else {
    $catId = (int)($_POST["category_id"] ?? 0);

    if ($catId) {
        $prefix = $modx->getOption('table_prefix');
        // Проверяем есть ли тесты в категории
        $stmt = $modx->prepare("SELECT COUNT(*) FROM {$prefix}test_tests WHERE category_id = ?");
        $stmt->execute([$catId]);
        $testCount = $stmt->fetchColumn();

        if ($testCount > 0) {
            $errors[] = "Нельзя удалить категорию, в которой есть тесты ($testCount шт.)";
        } else {
            $stmt = $modx->prepare("DELETE FROM {$prefix}test_categories WHERE id = ?");
            if ($stmt->execute([$catId])) {
                $success = "Категория успешно удалена!";
                $modx->cacheManager->refresh();
            } else {
                $errors[] = "Ошибка при удалении категории";
            }
        }
    }
    } // Закрываем else блок CSRF проверки (delete_category)
}

// Получаем все категории
$prefix = $modx->getOption('table_prefix');
$stmt = $modx->query("
    SELECT
        c.id,
        c.name,
        c.description,
        c.sort_order,
        COUNT(t.id) as test_count
    FROM {$prefix}test_categories c
    LEFT JOIN {$prefix}test_tests t ON t.category_id = c.id
    GROUP BY c.id
    ORDER BY c.sort_order, c.name
");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$html = [];
$modals = []; // Массив для модальных окон

$html[] = "<div class=\"container mt-4\">";

// Сообщения
if ($success) {
    $html[] = "<div class=\"alert alert-success alert-dismissible fade show\">";
    $html[] = $success;
    $html[] = "<button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"alert\"></button>";
    $html[] = "</div>";
}

if (!empty($errors)) {
    $html[] = "<div class=\"alert alert-danger alert-dismissible fade show\"><ul class=\"mb-0\">";
    foreach ($errors as $error) {
        $html[] = "<li>" . htmlspecialchars($error) . "</li>";
    }
    $html[] = "</ul><button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"alert\"></button></div>";
}

$html[] = "<div class=\"row\">";

// ЛЕВАЯ КОЛОНКА - Форма добавления
$html[] = "<div class=\"col-md-4\">";
$html[] = "<div class=\"card\">";
$html[] = "<div class=\"card-header bg-primary text-white\"><h5 class=\"mb-0\"><i class=\"bi bi-plus-circle\"></i> Добавить категорию</h5></div>";
$html[] = "<div class=\"card-body\">";

$html[] = "<form method=\"POST\">";
$html[] = CsrfProtection::getTokenField(); // CSRF Protection
$html[] = "<input type=\"hidden\" name=\"add_category\" value=\"1\">";

$html[] = "<div class=\"mb-3\">";
$html[] = "<label class=\"form-label\">Название *</label>";
$html[] = "<input type=\"text\" name=\"name\" class=\"form-control\" required>";
$html[] = "</div>";

$html[] = "<div class=\"mb-3\">";
$html[] = "<label class=\"form-label\">Описание</label>";
$html[] = "<textarea name=\"description\" class=\"form-control\" rows=\"3\"></textarea>";
$html[] = "</div>";

$html[] = "<div class=\"mb-3\">";
$html[] = "<label class=\"form-label\">Порядок сортировки</label>";
$html[] = "<input type=\"number\" name=\"sort_order\" class=\"form-control\" value=\"99\">";
$html[] = "<small class=\"form-text text-muted\">Чем меньше число, тем выше в списке</small>";
$html[] = "</div>";

$html[] = "<button type=\"submit\" class=\"btn btn-success w-100\"><i class=\"bi bi-check-circle\"></i> Добавить</button>";

$html[] = "</form>";

$html[] = "</div>";
$html[] = "</div>";
$html[] = "</div>";

// ПРАВАЯ КОЛОНКА - Список категорий
$html[] = "<div class=\"col-md-8\">";
$html[] = "<div class=\"card\">";
$html[] = "<div class=\"card-header bg-light\"><h5 class=\"mb-0\"><i class=\"bi bi-folder-fill text-primary\"></i> Все категории (" . count($categories) . ")</h5></div>";
$html[] = "<div class=\"card-body p-0\">";

if (empty($categories)) {
    $html[] = "<div class=\"p-4 text-center text-muted\">Нет категорий</div>";
} else {
    $html[] = "<div class=\"table-responsive\">";
    $html[] = "<table class=\"table table-hover mb-0\">";
    $html[] = "<thead class=\"table-light\">";
    $html[] = "<tr>";
    $html[] = "<th style=\"width: 60px;\">Порядок</th>";
    $html[] = "<th>Название</th>";
    $html[] = "<th style=\"width: 100px;\">Тестов</th>";
    $html[] = "<th style=\"width: 250px;\">Действия</th>";
    $html[] = "</tr>";
    $html[] = "</thead>";
    $html[] = "<tbody>";
    
    foreach ($categories as $cat) {
        $html[] = "<tr>";
        $html[] = "<td>" . $cat["sort_order"] . "</td>";
        $html[] = "<td>";
        $html[] = "<strong>" . htmlspecialchars($cat["name"]) . "</strong>";
        if ($cat["description"]) {
            $html[] = "<br><small class=\"text-muted\">" . htmlspecialchars($cat["description"]) . "</small>";
        }
        $html[] = "</td>";
        $html[] = "<td><span class=\"badge bg-secondary\">" . $cat["test_count"] . "</span></td>";
        $html[] = "<td>";

        // Кнопка управления экспертами
        $html[] = "<button class=\"btn btn-sm btn-info\" data-bs-toggle=\"modal\" data-bs-target=\"#expertsModal" . $cat["id"] . "\"><i class=\"bi bi-people\"></i> Эксперты</button> ";

        // Кнопка редактирования
        $html[] = "<button class=\"btn btn-sm btn-warning\" data-bs-toggle=\"modal\" data-bs-target=\"#editModal" . $cat["id"] . "\"><i class=\"bi bi-pencil\"></i> Редактировать</button> ";

        // Кнопка удаления
        if ($cat["test_count"] == 0) {
            $html[] = "<button class=\"btn btn-sm btn-danger\" data-bs-toggle=\"modal\" data-bs-target=\"#deleteModal" . $cat["id"] . "\"><i class=\"bi bi-trash\"></i> Удалить</button>";
        } else {
            $html[] = "<button class=\"btn btn-sm btn-secondary\" disabled title=\"Есть тесты\"><i class=\"bi bi-trash\"></i> Удалить</button>";
        }

        $html[] = "</td>";
        $html[] = "</tr>";

        // Модальное окно редактирования
        $modals[] = "<div class=\"modal fade\" id=\"editModal" . $cat["id"] . "\" tabindex=\"-1\">";
        $modals[] = "<div class=\"modal-dialog\">";
        $modals[] = "<div class=\"modal-content\">";
        $modals[] = "<form method=\"POST\">";
        $modals[] = CsrfProtection::getTokenField(); // CSRF Protection
        $modals[] = "<input type=\"hidden\" name=\"edit_category\" value=\"1\">";
        $modals[] = "<input type=\"hidden\" name=\"category_id\" value=\"" . $cat["id"] . "\">";

        $modals[] = "<div class=\"modal-header\">";
        $modals[] = "<h5 class=\"modal-title\">Редактировать категорию</h5>";
        $modals[] = "<button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"modal\"></button>";
        $modals[] = "</div>";

        $modals[] = "<div class=\"modal-body\">";
        $modals[] = "<div class=\"mb-3\">";
        $modals[] = "<label class=\"form-label\">Название</label>";
        $modals[] = "<input type=\"text\" name=\"name\" class=\"form-control\" value=\"" . htmlspecialchars($cat["name"]) . "\" required>";
        $modals[] = "</div>";
        $modals[] = "<div class=\"mb-3\">";
        $modals[] = "<label class=\"form-label\">Описание</label>";
        $modals[] = "<textarea name=\"description\" class=\"form-control\" rows=\"3\">" . htmlspecialchars($cat["description"]) . "</textarea>";
        $modals[] = "</div>";
        $modals[] = "<div class=\"mb-3\">";
        $modals[] = "<label class=\"form-label\">Порядок сортировки</label>";
        $modals[] = "<input type=\"number\" name=\"sort_order\" class=\"form-control\" value=\"" . $cat["sort_order"] . "\">";
        $modals[] = "</div>";
        $modals[] = "</div>";

        $modals[] = "<div class=\"modal-footer\">";
        $modals[] = "<button type=\"button\" class=\"btn btn-secondary\" data-bs-dismiss=\"modal\">Отмена</button>";
        $modals[] = "<button type=\"submit\" class=\"btn btn-primary\">Сохранить</button>";
        $modals[] = "</div>";

        $modals[] = "</form>";
        $modals[] = "</div>";
        $modals[] = "</div>";
        $modals[] = "</div>";

        // Модальное окно удаления
        $modals[] = "<div class=\"modal fade\" id=\"deleteModal" . $cat["id"] . "\" tabindex=\"-1\">";
        $modals[] = "<div class=\"modal-dialog\">";
        $modals[] = "<div class=\"modal-content\">";
        $modals[] = "<form method=\"POST\">";
        $modals[] = CsrfProtection::getTokenField(); // CSRF Protection
        $modals[] = "<input type=\"hidden\" name=\"delete_category\" value=\"1\">";
        $modals[] = "<input type=\"hidden\" name=\"category_id\" value=\"" . $cat["id"] . "\">";

        $modals[] = "<div class=\"modal-header\">";
        $modals[] = "<h5 class=\"modal-title\">Удалить категорию?</h5>";
        $modals[] = "<button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"modal\"></button>";
        $modals[] = "</div>";

        $modals[] = "<div class=\"modal-body\">";
        $modals[] = "<p>Вы уверены, что хотите удалить категорию <strong>" . htmlspecialchars($cat["name"]) . "</strong>?</p>";
        $modals[] = "<p class=\"text-danger\">Это действие нельзя отменить!</p>";
        $modals[] = "</div>";

        $modals[] = "<div class=\"modal-footer\">";
        $modals[] = "<button type=\"button\" class=\"btn btn-secondary\" data-bs-dismiss=\"modal\">Отмена</button>";
        $modals[] = "<button type=\"submit\" class=\"btn btn-danger\">Удалить</button>";
        $modals[] = "</div>";

        $modals[] = "</form>";
        $modals[] = "</div>";
        $modals[] = "</div>";
        $modals[] = "</div>";

        // Модальное окно управления экспертами
        $modals[] = "<div class=\"modal fade\" id=\"expertsModal" . $cat["id"] . "\" tabindex=\"-1\">";
        $modals[] = "<div class=\"modal-dialog modal-lg\">";
        $modals[] = "<div class=\"modal-content\">";

        $modals[] = "<div class=\"modal-header\">";
        $modals[] = "<h5 class=\"modal-title\">Управление экспертами: " . htmlspecialchars($cat["name"]) . "</h5>";
        $modals[] = "<button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"modal\"></button>";
        $modals[] = "</div>";

        $modals[] = "<div class=\"modal-body\">";

        // Список текущих экспертов
        $modals[] = "<h6>Текущие эксперты</h6>";
        $modals[] = "<div id=\"expertsList" . $cat["id"] . "\" class=\"mb-4\">";
        $modals[] = "<div class=\"text-center text-muted py-3\">Загрузка...</div>";
        $modals[] = "</div>";

        // Форма добавления эксперта
        $modals[] = "<hr>";
        $modals[] = "<h6>Назначить эксперта</h6>";
        $modals[] = "<form id=\"addExpertForm" . $cat["id"] . "\" class=\"needs-validation\" novalidate>";
        $modals[] = "<input type=\"hidden\" name=\"category_id\" value=\"" . $cat["id"] . "\">";

        $modals[] = "<div class=\"mb-3\">";
        $modals[] = "<label class=\"form-label\">Выберите эксперта</label>";
        $modals[] = "<select name=\"expert_user_id\" class=\"form-select\" id=\"expertSelect" . $cat["id"] . "\" required>";
        $modals[] = "<option value=\"\">Загрузка...</option>";
        $modals[] = "</select>";
        $modals[] = "</div>";

        $modals[] = "<div class=\"mb-3\">";
        $modals[] = "<label class=\"form-label\">Права доступа</label>";
        $modals[] = "<div class=\"form-check\">";
        $modals[] = "<input class=\"form-check-input\" type=\"checkbox\" name=\"can_manage_tests\" id=\"canManageTests" . $cat["id"] . "\" checked>";
        $modals[] = "<label class=\"form-check-label\" for=\"canManageTests" . $cat["id"] . "\">Управление тестами</label>";
        $modals[] = "</div>";
        $modals[] = "<div class=\"form-check\">";
        $modals[] = "<input class=\"form-check-input\" type=\"checkbox\" name=\"can_manage_questions\" id=\"canManageQuestions" . $cat["id"] . "\" checked>";
        $modals[] = "<label class=\"form-check-label\" for=\"canManageQuestions" . $cat["id"] . "\">Управление вопросами</label>";
        $modals[] = "</div>";
        $modals[] = "<div class=\"form-check\">";
        $modals[] = "<input class=\"form-check-input\" type=\"checkbox\" name=\"can_approve\" id=\"canApprove" . $cat["id"] . "\">";
        $modals[] = "<label class=\"form-check-label\" for=\"canApprove" . $cat["id"] . "\">Подтверждение изменений</label>";
        $modals[] = "</div>";
        $modals[] = "</div>";

        $modals[] = "<button type=\"submit\" class=\"btn btn-primary\">Назначить эксперта</button>";
        $modals[] = "</form>";

        $modals[] = "</div>";

        $modals[] = "<div class=\"modal-footer\">";
        $modals[] = "<button type=\"button\" class=\"btn btn-secondary\" data-bs-dismiss=\"modal\">Закрыть</button>";
        $modals[] = "</div>";

        $modals[] = "</div>";
        $modals[] = "</div>";
        $modals[] = "</div>";
    }

    $html[] = "</tbody>";
    $html[] = "</table>";
    $html[] = "</div>";
}

$html[] = "</div>";
$html[] = "</div>";
$html[] = "</div>";

$html[] = "</div>";
$html[] = "</div>";

// Выводим модальные окна вне контейнера
$html = array_merge($html, $modals);

// Подключаем JavaScript для управления экспертами
$html[] = "<script src=\"/assets/components/testsystem/js/category-experts.js\"></script>";

return implode("", $html);