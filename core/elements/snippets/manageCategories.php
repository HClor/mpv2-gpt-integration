<?php
/* TS MANAGE CATEGORIES v1.1 AUTO FOLDER */

// Подключаем bootstrap для CSRF защиты
require_once MODX_CORE_PATH . 'components/testsystem/bootstrap.php';

// Проверяем права (только админы)
if (!$modx->user->hasSessionContext("web")) {
    $authUrl = $modx->makeUrl($modx->getOption("lms.auth_page", null, 0));
    return "<div class=\"alert alert-warning\">
        <a href=\"" . $authUrl . "\">Войдите</a>, чтобы управлять категориями
    </div>";
}

$userId = $modx->user->id;
$isAdmin = $modx->user->isMember("LMS Admins") || $userId == 1;

if (!$isAdmin) {
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
        $stmt = $modx->prepare("
            INSERT INTO modx_test_categories (name, description, sort_order) 
            VALUES (?, ?, ?)
        ");
        
        if ($stmt->execute([$name, $description, $sortOrder])) {
            $newCatId = $modx->pdo->lastInsertId();
            
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
        $stmt = $modx->prepare("
            UPDATE modx_test_categories 
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
        // Проверяем есть ли тесты в категории
        $stmt = $modx->prepare("SELECT COUNT(*) FROM modx_test_tests WHERE category_id = ?");
        $stmt->execute([$catId]);
        $testCount = $stmt->fetchColumn();
        
        if ($testCount > 0) {
            $errors[] = "Нельзя удалить категорию, в которой есть тесты ($testCount шт.)";
        } else {
            $stmt = $modx->prepare("DELETE FROM modx_test_categories WHERE id = ?");
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
$stmt = $modx->query("
    SELECT 
        c.id,
        c.name,
        c.description,
        c.sort_order,
        COUNT(t.id) as test_count
    FROM modx_test_categories c
    LEFT JOIN modx_test_tests t ON t.category_id = c.id
    GROUP BY c.id
    ORDER BY c.sort_order, c.name
");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$html = [];

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
$html[] = "<div class=\"card-header\"><h5 class=\"mb-0\">Добавить категорию</h5></div>";
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

$html[] = "<button type=\"submit\" class=\"btn btn-success w-100\">Добавить</button>";

$html[] = "</form>";

$html[] = "</div>";
$html[] = "</div>";
$html[] = "</div>";

// ПРАВАЯ КОЛОНКА - Список категорий
$html[] = "<div class=\"col-md-8\">";
$html[] = "<div class=\"card\">";
$html[] = "<div class=\"card-header\"><h5 class=\"mb-0\">Все категории (" . count($categories) . ")</h5></div>";
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
    $html[] = "<th style=\"width: 150px;\">Действия</th>";
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
        
        // Кнопка редактирования
        $html[] = "<button class=\"btn btn-sm btn-warning\" data-bs-toggle=\"modal\" data-bs-target=\"#editModal" . $cat["id"] . "\">Редактировать</button> ";
        
        // Кнопка удаления
        if ($cat["test_count"] == 0) {
            $html[] = "<button class=\"btn btn-sm btn-danger\" data-bs-toggle=\"modal\" data-bs-target=\"#deleteModal" . $cat["id"] . "\">Удалить</button>";
        } else {
            $html[] = "<button class=\"btn btn-sm btn-secondary\" disabled title=\"Есть тесты\">Удалить</button>";
        }
        
        $html[] = "</td>";
        $html[] = "</tr>";
        
        // Модальное окно редактирования
        $html[] = "<div class=\"modal fade\" id=\"editModal" . $cat["id"] . "\" tabindex=\"-1\">";
        $html[] = "<div class=\"modal-dialog\">";
        $html[] = "<div class=\"modal-content\">";
        $html[] = "<form method=\"POST\">";
        $html[] = CsrfProtection::getTokenField(); // CSRF Protection
        $html[] = "<input type=\"hidden\" name=\"edit_category\" value=\"1\">";
        $html[] = "<input type=\"hidden\" name=\"category_id\" value=\"" . $cat["id"] . "\">";

        $html[] = "<div class=\"modal-header\">";
        $html[] = "<h5 class=\"modal-title\">Редактировать категорию</h5>";
        $html[] = "<button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"modal\"></button>";
        $html[] = "</div>";
        
        $html[] = "<div class=\"modal-body\">";
        $html[] = "<div class=\"mb-3\">";
        $html[] = "<label class=\"form-label\">Название</label>";
        $html[] = "<input type=\"text\" name=\"name\" class=\"form-control\" value=\"" . htmlspecialchars($cat["name"]) . "\" required>";
        $html[] = "</div>";
        $html[] = "<div class=\"mb-3\">";
        $html[] = "<label class=\"form-label\">Описание</label>";
        $html[] = "<textarea name=\"description\" class=\"form-control\" rows=\"3\">" . htmlspecialchars($cat["description"]) . "</textarea>";
        $html[] = "</div>";
        $html[] = "<div class=\"mb-3\">";
        $html[] = "<label class=\"form-label\">Порядок сортировки</label>";
        $html[] = "<input type=\"number\" name=\"sort_order\" class=\"form-control\" value=\"" . $cat["sort_order"] . "\">";
        $html[] = "</div>";
        $html[] = "</div>";
        
        $html[] = "<div class=\"modal-footer\">";
        $html[] = "<button type=\"button\" class=\"btn btn-secondary\" data-bs-dismiss=\"modal\">Отмена</button>";
        $html[] = "<button type=\"submit\" class=\"btn btn-primary\">Сохранить</button>";
        $html[] = "</div>";
        
        $html[] = "</form>";
        $html[] = "</div>";
        $html[] = "</div>";
        $html[] = "</div>";
        
        // Модальное окно удаления
        $html[] = "<div class=\"modal fade\" id=\"deleteModal" . $cat["id"] . "\" tabindex=\"-1\">";
        $html[] = "<div class=\"modal-dialog\">";
        $html[] = "<div class=\"modal-content\">";
        $html[] = "<form method=\"POST\">";
        $html[] = CsrfProtection::getTokenField(); // CSRF Protection
        $html[] = "<input type=\"hidden\" name=\"delete_category\" value=\"1\">";
        $html[] = "<input type=\"hidden\" name=\"category_id\" value=\"" . $cat["id"] . "\">";

        $html[] = "<div class=\"modal-header\">";
        $html[] = "<h5 class=\"modal-title\">Удалить категорию?</h5>";
        $html[] = "<button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"modal\"></button>";
        $html[] = "</div>";
        
        $html[] = "<div class=\"modal-body\">";
        $html[] = "<p>Вы уверены, что хотите удалить категорию <strong>" . htmlspecialchars($cat["name"]) . "</strong>?</p>";
        $html[] = "<p class=\"text-danger\">Это действие нельзя отменить!</p>";
        $html[] = "</div>";
        
        $html[] = "<div class=\"modal-footer\">";
        $html[] = "<button type=\"button\" class=\"btn btn-secondary\" data-bs-dismiss=\"modal\">Отмена</button>";
        $html[] = "<button type=\"submit\" class=\"btn btn-danger\">Удалить</button>";
        $html[] = "</div>";
        
        $html[] = "</form>";
        $html[] = "</div>";
        $html[] = "</div>";
        $html[] = "</div>";
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

return implode("", $html);