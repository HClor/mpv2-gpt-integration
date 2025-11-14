<?php
/* My Favorites v2.2 - Modal view + responsive */

if (!$modx->user->hasSessionContext('web')) {
    $authUrl = $modx->makeUrl($modx->getOption('lms.auth_page', null, 24));
    return '<div class="alert alert-warning">
        <h4>Требуется авторизация</h4>
        <p><a href="' . $authUrl . '" class="btn btn-primary">Войти</a></p>
    </div>';
}

$userId = $modx->user->id;
$prefix = $modx->getOption('table_prefix');

// Получаем избранные вопросы с информацией о тестах И С ОБЪЯСНЕНИЯМИ
$stmt = $modx->prepare("
    SELECT 
        f.question_id,
        f.added_at,
        q.question_text,
        q.question_type,
        q.explanation,
        q.question_image,
        q.explanation_image,
        t.id as test_id,
        t.title as test_title,
        t.resource_id,
        sc.pagetitle as test_page_title,
        sc.parent as category_id,
        cat.pagetitle as category_name
    FROM {$prefix}test_favorites f
    JOIN {$prefix}test_questions q ON q.id = f.question_id
    JOIN {$prefix}test_tests t ON t.id = q.test_id
    LEFT JOIN {$prefix}site_content sc ON sc.id = t.resource_id
    LEFT JOIN {$prefix}site_content cat ON cat.id = sc.parent
    WHERE f.user_id = ?
    ORDER BY f.added_at DESC
");
$stmt->execute([$userId]);
$favorites = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Добавляем CSRF токен для JavaScript
$output = CsrfProtection::getTokenMeta();
$output .= '<div class="favorites-page">';

if (empty($favorites)) {
    $testsUrl = $modx->makeUrl(35);
    $output .= '<div class="alert alert-info">';
    $output .= '<h4>У вас пока нет избранных вопросов</h4>';
    $output .= '<p>Добавляйте интересные вопросы в избранное, чтобы легко находить их позже.</p>';
    $output .= '<a href="' . $testsUrl . '" class="btn btn-primary mt-2">Перейти к тестам</a>';
    $output .= '</div>';
    $output .= '</div>';
    return $output;
}

$output .= '<div class="d-flex justify-content-between align-items-center mb-4">';
$output .= '<h1><i class="bi bi-star-fill text-warning"></i> Мои избранные вопросы</h1>';
$output .= '<span class="badge bg-warning text-dark fs-5">' . count($favorites) . '</span>';
$output .= '</div>';

// Группируем по категориям
$byCategory = [];
foreach ($favorites as $fav) {
    $categoryName = $fav['category_name'] ?: 'Без категории';
    $categoryId = $fav['category_id'] ?: 0;
    
    if (!isset($byCategory[$categoryName])) {
        $byCategory[$categoryName] = [
            'id' => $categoryId,
            'tests' => []
        ];
    }
    
    $testTitle = $fav['test_page_title'] ?: $fav['test_title'];
    if (!isset($byCategory[$categoryName]['tests'][$testTitle])) {
        $byCategory[$categoryName]['tests'][$testTitle] = [
            'resource_id' => $fav['resource_id'],
            'questions' => []
        ];
    }
    
    $byCategory[$categoryName]['tests'][$testTitle]['questions'][] = $fav;
}

// Выводим по категориям
foreach ($byCategory as $categoryName => $categoryData) {
    $output .= '<div class="category-section mb-4">';
    $output .= '<h2 class="h4 mb-3 border-bottom pb-2">';
    $output .= '<i class="bi bi-folder text-primary"></i> ' . htmlspecialchars($categoryName);
    $output .= '</h2>';
    
    // Выводим по тестам
    foreach ($categoryData['tests'] as $testTitle => $testData) {
        $testUrl = $testData['resource_id'] ? $modx->makeUrl($testData['resource_id']) : '#';
        
        $output .= '<div class="card mb-3">';
        $output .= '<div class="card-header bg-light">';
        $output .= '<div class="d-flex justify-content-between align-items-center">';
        $output .= '<h5 class="mb-0">' . htmlspecialchars($testTitle) . '</h5>';
        $output .= '<a href="' . $testUrl . '" class="btn btn-sm btn-primary">Пройти тест</a>';
        $output .= '</div>';
        $output .= '</div>';
        $output .= '<div class="list-group list-group-flush">';
        
        // Выводим вопросы
        foreach ($testData['questions'] as $question) {
            $questionText = strip_tags($question['question_text']);
            $questionShort = mb_strlen($questionText) > 150 ? mb_substr($questionText, 0, 150) . '...' : $questionText;
            
            $typeIcon = $question['question_type'] === 'multiple' ? '☑️' : '⭕';
            $typeName = $question['question_type'] === 'multiple' ? 'Несколько ответов' : 'Один ответ';
            
            $addedDate = date('d.m.Y H:i', strtotime($question['added_at']));
            
            // ДОБАВЛЯЕМ data-атрибуты для модального окна
            $questionData = htmlspecialchars(json_encode([
                'id' => $question['question_id'],
                'text' => $question['question_text'],
                'image' => $question['question_image'],
                'explanation' => $question['explanation'],
                'explanation_image' => $question['explanation_image'],
                'type' => $question['question_type'],
                'type_name' => $typeName
            ]), ENT_QUOTES, 'UTF-8');
            
            $output .= '<div class="list-group-item">';
            $output .= '<div class="d-flex justify-content-between align-items-start gap-3">';
            $output .= '<div class="flex-grow-1 favorite-question-clickable" data-question=\'' . $questionData . '\' style="cursor: pointer;">';
            $output .= '<p class="mb-1 text-primary"><strong>' . htmlspecialchars($questionShort) . '</strong></p>';
            $output .= '<small class="text-muted">';
            $output .= $typeIcon . ' ' . $typeName . ' • Добавлено: ' . $addedDate;
            $output .= '</small>';
            $output .= '</div>';
            
            // УЛУЧШЕННЫЙ TOGGLE-SWITCH
            $output .= '<div class="favorite-toggle-wrapper-large flex-shrink-0">';
            $output .= '<label class="favorite-toggle-switch-large">';
            $output .= '<input type="checkbox" class="favorite-toggle-checkbox-large" data-question-id="' . $question['question_id'] . '" checked>';
            $output .= '<span class="favorite-toggle-slider-large"></span>';
            $output .= '</label>';
            $output .= '<span class="favorite-toggle-label-text">В избранном</span>';
            $output .= '</div>';
            
            $output .= '</div>';
            $output .= '</div>';
        }
        
        $output .= '</div>';
        $output .= '</div>';
    }
    
    $output .= '</div>';
}

$output .= '</div>';

// МОДАЛЬНОЕ ОКНО для просмотра вопроса
$output .= '
<div class="modal fade" id="favoriteQuestionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">
                    <i class="bi bi-star-fill text-white"></i> 
                    <span id="modal-question-type"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="modal-question-text" class="mb-4"></div>
                <div id="modal-explanation" class="alert alert-info"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
            </div>
        </div>
    </div>
</div>
';

// УЛУЧШЕННЫЙ CSS
$output .= '<style>
/* Большой toggle-switch для страницы избранного */
.favorite-toggle-wrapper-large {
    display: flex;
    align-items: center;
    gap: 10px;
}

.favorite-toggle-switch-large {
    position: relative;
    display: inline-block;
    width: 60px;
    height: 32px;
    cursor: pointer;
    flex-shrink: 0;
}

.favorite-toggle-switch-large input {
    opacity: 0;
    width: 0;
    height: 0;
}

.favorite-toggle-slider-large {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ffc107;
    transition: 0.3s;
    border-radius: 32px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}

.favorite-toggle-slider-large:before {
    position: absolute;
    content: "★";
    height: 24px;
    width: 24px;
    left: 4px;
    bottom: 4px;
    background-color: white;
    transition: 0.3s;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    color: #ffc107;
}

input:checked + .favorite-toggle-slider-large {
    background-color: #ffc107;
}

input:not(:checked) + .favorite-toggle-slider-large {
    background-color: #ccc;
}

input:not(:checked) + .favorite-toggle-slider-large:before {
    color: #ccc;
}

input:checked + .favorite-toggle-slider-large:before {
    transform: translateX(28px);
}

.favorite-toggle-label-text {
    font-size: 14px;
    font-weight: 500;
    color: #666;
    white-space: nowrap;
}

input:checked ~ .favorite-toggle-label-text {
    color: #ffc107;
    font-weight: 600;
}

/* Hover для кликабельного вопроса */
.favorite-question-clickable:hover {
    background-color: #f8f9fa;
    border-radius: 4px;
    padding: 8px;
    margin: -8px;
}

.favorite-question-clickable:active {
    background-color: #e9ecef;
}

/* Hover эффекты для toggle */
.favorite-toggle-switch-large:hover .favorite-toggle-slider-large {
    box-shadow: 0 4px 8px rgba(0,0,0,0.3);
}

/* Анимация удаления */
.removing-item {
    transition: all 0.3s ease;
    opacity: 0.5;
}

/* Адаптивность - скрываем текст на маленьких экранах */
@media (max-width: 576px) {
    .favorite-toggle-label-text {
        display: none;
    }
    
    .favorite-toggle-wrapper-large {
        gap: 0;
    }
}

/* Изображения в модальном окне */
#modal-question-text img,
#modal-explanation img {
    max-width: 100% !important;
    height: auto !important;
    display: block;
    margin: 10px auto;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

#modal-question-text,
#modal-explanation {
    overflow-x: hidden;
}
</style>';

// УЛУЧШЕННЫЙ JavaScript
$output .= '<script>
document.addEventListener("DOMContentLoaded", function() {
    const toggles = document.querySelectorAll(".favorite-toggle-checkbox-large");
    const modal = new bootstrap.Modal(document.getElementById("favoriteQuestionModal"));
    
    // Обработчик кликов по вопросам
    document.querySelectorAll(".favorite-question-clickable").forEach(element => {
        element.addEventListener("click", function(e) {
            e.stopPropagation();
            
            try {
                const questionData = JSON.parse(this.dataset.question);
                
                // Заполняем модальное окно
                document.getElementById("modal-question-type").textContent = questionData.type_name;
                
                let questionHtml = questionData.text;
                if (questionData.image) {
                    questionHtml += `<div class="mt-3"><img src="${questionData.image}" class="img-fluid"></div>`;
                }
                document.getElementById("modal-question-text").innerHTML = questionHtml;
                
                let explanationHtml = `<strong>Объяснение:</strong><br><div class="mt-2">${questionData.explanation || "Объяснение отсутствует"}</div>`;
                if (questionData.explanation_image) {
                    explanationHtml += `<div class="mt-3"><img src="${questionData.explanation_image}" class="img-fluid"></div>`;
                }
                document.getElementById("modal-explanation").innerHTML = explanationHtml;
                
                // Показываем модальное окно
                modal.show();
            } catch (error) {
                console.error("Error parsing question data:", error);
                alert("Ошибка загрузки вопроса");
            }
        });
    });
    
    // Обработчик toggle избранного
    toggles.forEach(toggle => {
        // Предотвращаем открытие модального окна при клике на toggle
        toggle.closest(".favorite-toggle-wrapper-large").addEventListener("click", function(e) {
            e.stopPropagation();
        });
        
        toggle.addEventListener("change", async function(e) {
            const questionId = parseInt(this.dataset.questionId);
            const listItem = this.closest(".list-group-item");
            
            if (!this.checked) {
                const confirmed = confirm("Вы уверены, что хотите убрать этот вопрос из избранного?");
                
                if (!confirmed) {
                    this.checked = true;
                    e.preventDefault();
                    return;
                }
                
                listItem.classList.add("removing-item");

                try {
                    // Получаем CSRF токен из meta тега
                    const csrfToken = document.querySelector("meta[name=\"csrf-token\"]")?.content;
                    const requestData = {question_id: questionId};

                    // Добавляем CSRF токен если он есть
                    if (csrfToken) {
                        requestData.csrf_token = csrfToken;
                    }

                    const response = await fetch("/assets/components/testsystem/ajax/testsystem.php", {
                        method: "POST",
                        headers: {"Content-Type": "application/json"},
                        body: JSON.stringify({
                            action: "toggleFavorite",
                            data: requestData
                        })
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        showFavoriteNotification("Вопрос убран из избранного", "info");
                        
                        setTimeout(() => {
                            listItem.style.maxHeight = listItem.scrollHeight + "px";
                            listItem.style.overflow = "hidden";
                            listItem.style.transition = "max-height 0.4s ease, margin 0.4s ease, padding 0.4s ease, opacity 0.4s ease";
                            
                            setTimeout(() => {
                                listItem.style.maxHeight = "0";
                                listItem.style.marginBottom = "0";
                                listItem.style.paddingTop = "0";
                                listItem.style.paddingBottom = "0";
                                listItem.style.borderWidth = "0";
                                listItem.style.opacity = "0";
                                
                                setTimeout(() => {
                                    listItem.remove();
                                    
                                    const remainingItems = document.querySelectorAll(".favorites-page .list-group-item");
                                    if (remainingItems.length === 0) {
                                        window.location.reload();
                                    }
                                }, 400);
                            }, 10);
                        }, 300);
                    } else {
                        this.checked = true;
                        listItem.classList.remove("removing-item");
                        showFavoriteNotification("Ошибка: " + result.message, "error");
                    }
                } catch (error) {
                    this.checked = true;
                    listItem.classList.remove("removing-item");
                    showFavoriteNotification("Ошибка соединения с сервером", "error");
                    console.error("Favorite toggle error:", error);
                }
            }
        });
    });
    
    function showFavoriteNotification(message, type) {
        const notificationClass = type === "error" ? "alert-danger" : type === "info" ? "alert-info" : "alert-success";
        const icon = type === "error" ? "❌" : type === "info" ? "ℹ️" : "✅";
        
        const notification = document.createElement("div");
        notification.className = `alert ${notificationClass} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3`;
        notification.style.zIndex = "9999";
        notification.style.minWidth = "300px";
        notification.innerHTML = `
            ${icon} ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.classList.remove("show");
            setTimeout(() => notification.remove(), 150);
        }, 3000);
    }
});
</script>';

return $output;