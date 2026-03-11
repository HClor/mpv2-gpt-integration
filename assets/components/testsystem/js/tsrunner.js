/* TS RUNNER v3.6.0 - Knowledge Areas support */

(function() {
    
    const API_URL = "/assets/components/testsystem/ajax/testsystem.php";
    
    let currentSessionId = null;
    let currentQuestionId = null;
    let currentQuestionType = null;
    let totalQuestions = 0;
    let currentQuestionNumber = 0;
    let testMode = "training";
    let canEdit = false;
    let canDelete = false;

    let allQuestions = [];
    let currentIndex = 0;
    let currentQuestionsFilter = 'all';

    // Timer variables
    let timerInterval = null;
    let timeRemaining = 0;
    
    // ИСПРАВЛЕНИЕ: Определяем параметры ОДИН РАЗ в начале
    const urlParams = new URLSearchParams(window.location.search);
    const viewParam = urlParams.get('view');
    const isLearningView = viewParam === 'learning';
    const isFavoritesView = viewParam === 'favorites';
    const isQuestionsView = viewParam === 'questions';
    const isManageView = viewParam === 'manage';

    const container = document.getElementById("test-container");
    const testId = container ? container.dataset.testId : null;
    const importQuestionsUrl = container ? (container.dataset.importUrl || '') : '';
    canDelete = container ? container.dataset.canDelete === '1' : false;

    // ПОДДЕРЖКА ОБЛАСТЕЙ ЗНАНИЙ
    const knowledgeAreaId = container ? (container.dataset.knowledgeAreaId || null) : null;
    const isKnowledgeArea = knowledgeAreaId !== null && parseInt(knowledgeAreaId) > 0;

    console.log("=== TS RUNNER INIT ===");
    console.log("viewParam:", viewParam);
    console.log("isLearningView:", isLearningView);
    console.log("isFavoritesView:", isFavoritesView);
    console.log("isQuestionsView:", isQuestionsView);
    console.log("isManageView:", isManageView);
    console.log("testId:", testId);
    console.log("knowledgeAreaId:", knowledgeAreaId);
    console.log("isKnowledgeArea:", isKnowledgeArea);
    console.log("container:", container);

    // ИСПРАВЛЕНИЕ: Единая точка входа
    if (isFavoritesView) {
        console.log("Loading favorites view...");
        loadFavoritesView();
    } else if (isLearningView) {
        if (!testId) {
            console.error("Test ID not found for learning view");
            return;
        }
        console.log("Loading learning view...");
        loadLearningView();
    } else if (isQuestionsView) {
        if (!testId) {
            console.error("Test ID not found for questions view");
            return;
        }
        console.log("Loading questions view...");

        // СРАЗУ скрываем промежуточный контент
        const testInfo = document.getElementById('test-info');
        const modeSelector = document.querySelector('.test-mode-selector');
        if (testInfo) testInfo.style.display = 'none';
        if (modeSelector) modeSelector.style.display = 'none';

        checkEditRights();
        // Ждем загрузки прав, затем показываем список вопросов
        setTimeout(() => {
            if (typeof showAllQuestionsView === 'function') {
                showAllQuestionsView();
            }
        }, 500);
    } else if (isManageView) {
        if (!testId) {
            console.error("Test ID not found for manage view");
            return;
        }
        console.log("Loading manage view...");

        // СРАЗУ скрываем промежуточный контент
        const testInfo = document.getElementById('test-info');
        const modeSelector = document.querySelector('.test-mode-selector');
        if (testInfo) testInfo.style.display = 'none';
        if (modeSelector) modeSelector.style.display = 'none';

        checkEditRights();
        // Ждем загрузки прав, затем открываем модальное окно управления
        setTimeout(() => {
            if (typeof openTestManagementModal === 'function') {
                const currentStatus = container ? container.dataset.testMode : 'both';
                openTestManagementModal(testId, currentStatus);
            }
        }, 500);
    } else if (testId && testId !== 'favorites') {
        console.log("Loading test mode...");
        checkEditRights();
        initializeTestMode();
    } else if (isKnowledgeArea) {
        console.log("Loading knowledge area mode...");
        checkEditRights();
        initializeTestMode();
    } else {
        console.log("No valid mode detected");
    }



    
    async function refreshCsrfToken() {
        const response = await fetch(API_URL, {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({ action: "getCsrfToken", data: {} })
        });

        const text = await response.text();
        const result = JSON.parse(text);

        if (!result.success || !result.data || !result.data.csrf_token) {
            throw new Error(result.message || "Failed to refresh CSRF token");
        }

        let meta = document.querySelector('meta[name="csrf-token"]');
        if (!meta) {
            meta = document.createElement('meta');
            meta.setAttribute('name', 'csrf-token');
            document.head.appendChild(meta);
        }
        meta.setAttribute('content', result.data.csrf_token);
    }

    async function apiCall(action, data, retryOnCsrfFail = true) {
        try {
            // CSRF Protection: Получаем токен из meta тега
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

            if (csrfToken) {
                // Добавляем CSRF токен к данным
                data = data || {};
                data.csrf_token = csrfToken;
            }

            const response = await fetch(API_URL, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({ action, data })
            });

            const text = await response.text();
            try {
                const parsed = JSON.parse(text);

                if (
                    retryOnCsrfFail &&
                    parsed &&
                    parsed.success === false &&
                    typeof parsed.message === 'string' &&
                    parsed.message.toLowerCase().includes('csrf token validation failed')
                ) {
                    await refreshCsrfToken();
                    return apiCall(action, data, false);
                }

                return parsed;
            } catch (e) {
                console.error("JSON parse error:", text);
                throw new Error("Invalid server response");
            }
        } catch (error) {
            console.error("API Error:", error);
            alert("Ошибка соединения с сервером");
            throw error;
        }
    }

    /**
     * Продолжить существующую сессию теста
     * Вызывается когда пользователь переходит по прямой ссылке с sessionId
     */
    window.continueExistingSession = async function(sessionId, mode) {
        console.log("=== continueExistingSession CALLED ===");
        console.log("Session ID:", sessionId);
        console.log("Mode:", mode);

        currentSessionId = sessionId;
        testMode = mode;

        try {
            // Получаем информацию о сессии
            const result = await apiCall("getSessionInfo", { session_id: sessionId });

            if (result.success) {
                totalQuestions = result.data.total_questions || 0;
                currentQuestionNumber = result.data.current_question_number || 0;

                console.log("Session info loaded:", result.data);

                // Обновляем UI
                const totalQElement = document.getElementById("total-q");
                if (totalQElement) {
                    totalQElement.textContent = totalQuestions;
                }

                // Устанавливаем badge режима
                const modeBadge = document.getElementById("mode-badge");
                if (modeBadge) {
                    if (mode === "training") {
                        modeBadge.className = "badge bg-success";
                        modeBadge.textContent = "Тренировка";
                    } else {
                        modeBadge.className = "badge bg-danger";
                        modeBadge.textContent = "Экзамен";
                    }
                }

                // Загружаем следующий вопрос
                loadNextQuestion();
            } else {
                console.error("Failed to get session info:", result.message);
                alert("Ошибка загрузки сессии: " + result.message);
            }
        } catch (error) {
            console.error("Error continuing session:", error);
            alert("Ошибка при продолжении теста");
        }
    };

    // ===== TIMER FUNCTIONS =====
    function startTimer(minutes) {
        if (minutes <= 0) return;

        timeRemaining = minutes * 60; // Convert to seconds
        const timerElement = document.getElementById("exam-timer");
        const timerDisplay = document.getElementById("timer-display");

        if (timerElement && timerDisplay) {
            timerElement.style.display = "inline-block";
            updateTimerDisplay();

            timerInterval = setInterval(() => {
                timeRemaining--;

                if (timeRemaining <= 0) {
                    stopTimer();
                    alert("Время вышло! Тест будет завершён автоматически.");
                    finishTest();
                } else {
                    updateTimerDisplay();

                    // Warning when 1 minute left
                    if (timeRemaining === 60) {
                        timerElement.classList.remove("bg-warning", "text-dark");
                        timerElement.classList.add("bg-danger", "text-white");
                    }
                }
            }, 1000);
        }
    }

    function updateTimerDisplay() {
        const timerDisplay = document.getElementById("timer-display");
        if (timerDisplay) {
            const minutes = Math.floor(timeRemaining / 60);
            const seconds = timeRemaining % 60;
            timerDisplay.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
        }
    }

    function stopTimer() {
        if (timerInterval) {
            clearInterval(timerInterval);
            timerInterval = null;
        }
    }
    // ===== END TIMER FUNCTIONS =====

    async function checkEditRights() {
        try {
            const result = await apiCall("checkEditRights", {});
            if (result.success) {
                canEdit = result.data.canEdit;
            }
        } catch (error) {
            console.log("No edit rights");
        }
    }

    async function loadLearningView() {
        console.log("=== loadLearningView START ===");
        try {
            // Скрываем test-info
            const testInfo = document.getElementById("test-info");
            if (testInfo) {
                console.log("Hiding test-info");
                testInfo.style.display = "none";
            }
            
            await checkEditRights();
            console.log("Calling getAllQuestions with testId:", testId);
            const result = await apiCall("getAllQuestions", { test_id: testId });
            
            if (result.success) {
                console.log("Got questions:", result.data.length);
                // Фильтруем только вопросы с is_learning = 1
                allQuestions = result.data.filter(q => parseInt(q.is_learning) === 1);
                console.log("Learning questions:", allQuestions.length);
                
                if (allQuestions.length === 0) {
                    alert("В этом тесте нет обучающих материалов");
                    window.location.href = document.referrer || '/';
                    return;
                }
                
                showLearningInterface();
                showLearningQuestion(0);
            } else {
                alert("Ошибка загрузки вопросов");
            }
        } catch (error) {
            console.error("Load questions error:", error);
        }
        console.log("=== loadLearningView END ===");
    }

    async function loadFavoritesView() {
        console.log("=== loadFavoritesView START ===");
        try {
            // Скрываем test-info
            const testInfo = document.getElementById("test-info");
            if (testInfo) {
                console.log("Hiding test-info");
                testInfo.style.display = "none";
            }
            
            await checkEditRights();
            
            // Получаем все избранные вопросы текущего пользователя
            console.log("Calling getFavoriteQuestions...");
            const result = await apiCall("getFavoriteQuestions", {});
            console.log("getFavoriteQuestions result:", result);
            
            if (!result.success) {
                alert("Ошибка загрузки избранного: " + (result.message || "неизвестная ошибка"));
                window.location.href = '/materialyi-dlya-obucheni/';
                return;
            }
            
            allQuestions = result.data;
            console.log("Favorite questions loaded:", allQuestions.length);
            
            if (allQuestions.length === 0) {
                alert("У вас нет избранных вопросов");
                window.location.href = '/materialyi-dlya-obucheni/';
                return;
            }
            
            console.log("Showing favorites interface...");
            showFavoritesInterface();
            showFavoriteQuestion(0);
        } catch (error) {
            console.error("Load favorites error:", error);
            alert("Ошибка загрузки избранного: " + error.message);
        }
        console.log("=== loadFavoritesView END ===");
    }
    

    function showFavoritesInterface() {
        console.log("=== showFavoritesInterface START ===");
        const testInfo = document.getElementById("test-info");
        const questionContainer = document.getElementById("question-container");
        
        console.log("testInfo:", testInfo);
        console.log("questionContainer:", questionContainer);
        
        if (testInfo) testInfo.style.display = "none";
        if (questionContainer) {
            questionContainer.style.display = "block";
            questionContainer.style.marginTop = "3rem"; // ОТСТУП СВЕРХУ
            console.log("Question container displayed");
            
            // Удаляем старые элементы
            const oldHeader = questionContainer.querySelector('.learning-header-block');
            const oldProgress = questionContainer.querySelector('.learning-progress-container');
            const oldTestProgress = questionContainer.querySelector('#test-progress-bar');
            
            if (oldHeader) oldHeader.remove();
            if (oldProgress) oldProgress.remove();
            if (oldTestProgress && oldTestProgress.parentElement) {
                oldTestProgress.parentElement.remove();
            }
            
            // ИСПРАВЛЕНИЕ: Добавляем заголовок БЕЗ card-header (он уже есть в HTML)
            const headerAndProgressHtml = `
                <div class="learning-progress-container mb-3 px-3 pt-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3 class="mb-0">
                            <i class="bi bi-star-fill text-warning"></i> Мои избранные вопросы
                        </h3>
                        <a href="/materialyi-dlya-obucheni" class="btn btn-secondary btn-sm">
                            <i class="bi bi-arrow-left"></i> К списку материалов
                        </a>
                    </div>
                    <div class="progress" style="height: 10px; border-radius: 10px;">
                        <div id="learning-progress-bar" class="progress-bar bg-warning progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                    </div>
                </div>
            `;
            questionContainer.insertAdjacentHTML('afterbegin', headerAndProgressHtml);
            console.log("Header and progress added");
            
            const cardBody = questionContainer.querySelector(".card-body");
            if (cardBody) {
                cardBody.innerHTML = `
                    <div id="favorite-test-title" class="mb-2 text-muted small"></div>
                    <h4 id="learning-question-text" class="mb-4"></h4>
                    <div id="learning-explanation" class="alert alert-info"></div>
                `;
                console.log("Card body updated");
            }
            
            const cardFooter = questionContainer.querySelector(".card-footer");
            if (cardFooter) {
                cardFooter.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center mb-2 learning-but">
                        <button id="prev-card-btn" class="btn btn-secondary btn-sm">
                            <i class="bi bi-arrow-left"></i> Предыдущий
                        </button>
                        <span id="card-counter" class="text-muted fw-bold"></span>
                        <button id="next-card-btn" class="btn btn-secondary btn-sm">
                            Следующий <i class="bi bi-arrow-right"></i>
                        </button>
                    </div>
                `;
                console.log("Card footer updated");
            }
    
            document.getElementById("prev-card-btn")?.addEventListener("click", () => {
                if (currentIndex > 0) {
                    showFavoriteQuestion(currentIndex - 1);
                }
            });
            
            document.getElementById("next-card-btn")?.addEventListener("click", () => {
                if (currentIndex < allQuestions.length - 1) {
                    showFavoriteQuestion(currentIndex + 1);
                }
            });
            
            console.log("Event listeners attached");
        }
        console.log("=== showFavoritesInterface END ===");
    }
    


    function showFavoriteQuestion(index) {
        console.log("=== showFavoriteQuestion START ===", "index:", index);
        currentIndex = index;
        const question = allQuestions[index];
        
        if (!question) {
            console.error("Question not found at index:", index);
            return;
        }
        
        console.log("Question:", question);
        
        // Обновляем прогресс-бар
        const progressBar = document.getElementById("learning-progress-bar");
        if (progressBar && allQuestions.length > 0) {
            const percentage = Math.round(((index + 1) / allQuestions.length) * 100);
            progressBar.style.width = percentage + '%';
            progressBar.setAttribute('aria-valuenow', percentage);
            console.log("Progress updated:", percentage + "%");
        }
        
        document.getElementById("card-counter").textContent = `${index + 1} из ${allQuestions.length}`;
        
        // Показываем название теста (XSS Protection: экранируем текст)
        const testTitleEl = document.getElementById("favorite-test-title");
        if (testTitleEl && question.test_title) {
            testTitleEl.innerHTML = `<i class="bi bi-folder2-open"></i> ${escapeHtml(question.test_title)}`;
        }

        // Формируем HTML вопроса с изображением (XSS Protection: санитизация HTML)
        let questionHtml = sanitizeHtml(question.question_text) || 'Текст вопроса отсутствует';
        if (question.question_image) {
            questionHtml += `<div class="mt-3"><img src="${escapeHtml(question.question_image)}" class="img-fluid"></div>`;
        }
        document.getElementById("learning-question-text").innerHTML = questionHtml;

        // Формируем HTML объяснения с изображением (XSS Protection: санитизация HTML)
        let expHtml = `<strong>Объяснение:</strong><br><div class="explanation-content">${sanitizeHtml(question.explanation) || "Объяснение отсутствует"}</div>`;
        if (question.explanation_image) {
            expHtml += `<div class="mt-3"><img src="${escapeHtml(question.explanation_image)}" class="img-fluid" style="max-width: 600px;"></div>`;
        }
        document.getElementById("learning-explanation").innerHTML = expHtml;

        const prevBtn = document.getElementById("prev-card-btn");
        const nextBtn = document.getElementById("next-card-btn");
        
        if (prevBtn) prevBtn.disabled = (index === 0);
        if (nextBtn) nextBtn.disabled = (index === allQuestions.length - 1);
        
        console.log("Question displayed, adding favorite toggle...");
        
        // Добавляем кнопку избранного
        addFavoritesViewToggle(question.id);
        
        console.log("=== showFavoriteQuestion END ===");

        // ✅ ДОБАВИТЬ в конце функции:
        setTimeout(() => {
            scrollToQuestion();
        }, 100);
        
        
    }
    
async function addFavoritesViewToggle(questionId) {
    console.log("=== addFavoritesViewToggle START ===", "questionId:", questionId);
    if (!questionId) return;
    
    const cardHeader = document.querySelector("#question-container .card-header");
    if (!cardHeader) {
        console.error("Card header not found");
        return;
    }
    
    // Удаляем старый toggle
    const oldToggle = document.getElementById("favorites-view-toggle-container");
    if (oldToggle) oldToggle.remove();
    
    // Создаем toggle в правой части header
    const toggleContainer = document.createElement("div");
    toggleContainer.id = "favorites-view-toggle-container";
    toggleContainer.className = "favorite-toggle-wrapper ms-auto";
    toggleContainer.innerHTML = `
        <label class="favorite-toggle-switch">
            <input type="checkbox" id="favorites-view-toggle-input" checked>
            <span class="favorite-toggle-slider"></span>
        </label>
        <span class="favorite-toggle-label">В избранном</span>
    `;
    
    // ИСПРАВЛЕНИЕ: Добавляем в конец header (справа)
    cardHeader.appendChild(toggleContainer);
    console.log("Toggle added to card header");
    
    // Обработчик удаления из избранного
    const toggleInput = document.getElementById("favorites-view-toggle-input");
    toggleInput.addEventListener("change", async function() {
        if (!this.checked) {
            const confirmed = confirm("Убрать этот вопрос из избранного?");
            
            if (!confirmed) {
                this.checked = true;
                return;
            }
            
            try {
                const result = await apiCall("toggleFavorite", {
                    question_id: questionId
                });
                
                if (result.success) {
                    showNotification("Убрано из избранного", "info");
                    
                    allQuestions = allQuestions.filter(q => q.id !== questionId);
                    
                    if (allQuestions.length === 0) {
                        alert("Больше нет избранных вопросов");
                        window.location.href = '/materialyi-dlya-obucheni/';
                        return;
                    }
                    
                    const newIndex = currentIndex >= allQuestions.length ? allQuestions.length - 1 : currentIndex;
                    showFavoriteQuestion(newIndex);
                } else {
                    this.checked = true;
                    alert("Ошибка: " + result.message);
                }
            } catch (error) {
                this.checked = true;
                alert("Ошибка: " + error.message);
            }
        }
    });
    
    console.log("=== addFavoritesViewToggle END ===");
}

    function showLearningInterface() {
        const testInfo = document.getElementById("test-info");
        const questionContainer = document.getElementById("question-container");
        
        if (testInfo) testInfo.style.display = "none";
        if (questionContainer) {
            questionContainer.style.display = "block";
            
            // ИСПРАВЛЕНИЕ: Удаляем ВСЕ старые элементы
            const oldHeader = questionContainer.querySelector('.learning-header-block');
            const oldProgress = questionContainer.querySelector('.learning-progress-container');
            const oldTestProgress = questionContainer.querySelector('#test-progress-bar');
            
            if (oldHeader) oldHeader.remove();
            if (oldProgress) oldProgress.remove();
            
            // КРИТИЧНО: Удаляем старый test-progress-bar с родителем
            if (oldTestProgress && oldTestProgress.parentElement) {
                oldTestProgress.parentElement.remove();
            }
            
            // Получаем название теста
            const testTitle = testInfo ? 
                (testInfo.querySelector('h2')?.textContent || testInfo.querySelector('h1')?.textContent || 'Обучающий материал') : 
                'Обучающий материал';
            
            // Добавляем заголовок и прогресс-бар
            const headerAndProgressHtml = `
                <div class="learning-header-block mb-3">
                    <h3 class="mb-3">
                        <i class="bi bi-book-half text-primary"></i> ${testTitle}
                    </h3>
                </div>
                <div class="learning-progress-container mb-3">
                    <div class="progress" style="height: 10px; border-radius: 10px;">
                        <div id="learning-progress-bar" class="progress-bar bg-info progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                    </div>
                </div>
            `;
            questionContainer.insertAdjacentHTML('afterbegin', headerAndProgressHtml);
            
            const cardBody = questionContainer.querySelector(".card-body");
            if (cardBody) {
                cardBody.innerHTML = `
                    <h4 id="learning-question-text" class="mb-4"></h4>
                    <div id="learning-explanation" class="alert alert-info"></div>
                `;
            }
            
            const cardFooter = questionContainer.querySelector(".card-footer");
            if (cardFooter) {
                cardFooter.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center mb-2 learning-but">
                        <button id="prev-card-btn" class="btn btn-secondary btn-sm">← Назад</button>
                        <span id="card-counter" class="text-muted fw-bold"></span>
                        <button id="next-card-btn" class="btn btn-secondary btn-sm">Вперед →</button>
                    </div>
                    <div id="learning-edit-controls-row" class="border-top pt-2" style="display: none;"></div>
                `;
            }
    
            document.getElementById("prev-card-btn")?.addEventListener("click", () => {
                if (currentIndex > 0) {
                    showLearningQuestion(currentIndex - 1);
                }
            });
            
            document.getElementById("next-card-btn")?.addEventListener("click", () => {
                if (currentIndex < allQuestions.length - 1) {
                    showLearningQuestion(currentIndex + 1);
                }
            });
        }
    }





    function showLearningQuestion(index) {
        currentIndex = index;
        const question = allQuestions[index];
        
        // Обновляем прогресс-бар
        const progressBar = document.getElementById("learning-progress-bar");
        if (progressBar && allQuestions.length > 0) {
            const percentage = Math.round(((index + 1) / allQuestions.length) * 100);
            progressBar.style.width = percentage + '%';
            progressBar.setAttribute('aria-valuenow', percentage);
        }        

        // ИСПРАВЛЕНИЕ: Проверяем наличие вопроса
        if (!question) {
            console.error("Question not found at index:", index);
            return;
        }
        
        document.getElementById("current-q").textContent = index + 1;
        document.getElementById("total-q").textContent = allQuestions.length;
        document.getElementById("card-counter").textContent = `${index + 1} из ${allQuestions.length}`;
        
        // Формируем HTML вопроса с изображением (XSS Protection: санитизация HTML)
        let questionHtml = sanitizeHtml(question.question_text) || 'Текст вопроса отсутствует';
        if (question.question_image) {
            questionHtml += `<div class="mt-3"><img src="${escapeHtml(question.question_image)}" class="img-fluid"></div>`;
        }
        document.getElementById("learning-question-text").innerHTML = questionHtml;

        // Формируем HTML объяснения с изображением (XSS Protection: санитизация HTML)
        let expHtml = `<strong>Объяснение:</strong><br><div class="explanation-content">${sanitizeHtml(question.explanation) || "Объяснение отсутствует"}</div>`;
        if (question.explanation_image) {
            expHtml += `<div class="mt-3"><img src="${escapeHtml(question.explanation_image)}" class="img-fluid" style="max-width: 600px;"></div>`;
        }
        document.getElementById("learning-explanation").innerHTML = expHtml;
    
        const prevBtn = document.getElementById("prev-card-btn");
        const nextBtn = document.getElementById("next-card-btn");
        
        if (prevBtn) prevBtn.disabled = (index === 0);
        if (nextBtn) nextBtn.disabled = (index === allQuestions.length - 1);
        
        // ИСПРАВЛЕНИЕ: Показываем кнопки редактирования если есть права
        if (canEdit) {
            showLearningEditControls(question.id);
        } else {
            // Очищаем контейнер если нет прав
            const editControlsContainer = document.getElementById("learning-edit-controls-container");
            if (editControlsContainer) {
                editControlsContainer.innerHTML = '';
            }
        }

        // ДОБАВИТЬ: Кнопка избранного
        addLearningFavoriteButton(question.id);
        // ✅ ДОБАВИТЬ в конце функции:
        setTimeout(() => {
            scrollToQuestion();
        }, 100);        
        
    }

    
    async function addLearningFavoriteButton(questionId) {
        if (!questionId) return;
        
        const statusResult = await apiCall("getFavoriteStatus", {
            question_id: questionId
        });
        
        if (!statusResult.success) return;
        
        const isFavorite = statusResult.is_favorite;
        
        // ИСПРАВЛЕНИЕ: Ищем card-header в режиме обучения
        const cardHeader = document.querySelector("#question-container .card-header");
        if (!cardHeader) return;
        
        // Удаляем старый toggle
        const oldToggle = document.getElementById("learning-favorite-toggle-container");
        if (oldToggle) oldToggle.remove();
        
        // Создаем toggle в card-header
        const toggleContainer = document.createElement("div");
        toggleContainer.id = "learning-favorite-toggle-container";
        toggleContainer.className = "favorite-toggle-wrapper";
        toggleContainer.innerHTML = `
            <label class="favorite-toggle-switch">
                <input type="checkbox" id="learning-favorite-toggle-input" ${isFavorite ? 'checked' : ''}>
                <span class="favorite-toggle-slider"></span>
            </label>
            <span class="favorite-toggle-label">${isFavorite ? 'В избранном' : 'В избранное'}</span>
        `;
        
        // ИСПРАВЛЕНИЕ: Добавляем в конец card-header
        cardHeader.appendChild(toggleContainer);
        
        // Обработчик
        const toggleInput = document.getElementById("learning-favorite-toggle-input");
        const toggleLabel = toggleContainer.querySelector('.favorite-toggle-label');
        toggleInput.addEventListener("change", async function() {
            const newState = this.checked;
            if (toggleLabel) {
                toggleLabel.textContent = newState ? 'В избранном' : 'В избранное';
            }

            try {
                const result = await apiCall("toggleFavorite", {
                    question_id: questionId
                });

                if (result.success) {
                    const isFavorite = result.data?.is_favorite ?? result.is_favorite;
                    if (isFavorite) {
                        showNotification("⭐ Добавлено в избранное", "success");
                    } else {
                        showNotification("Убрано из избранного", "info");
                    }
                } else {
                    this.checked = !newState;
                    if (toggleLabel) {
                        toggleLabel.textContent = this.checked ? 'В избранном' : 'В избранное';
                    }
                }
            } catch (error) {
                this.checked = !newState;
                if (toggleLabel) {
                    toggleLabel.textContent = this.checked ? 'В избранном' : 'В избранное';
                }
                alert("Ошибка: " + error.message);
            }
        });
    }

    window.addLearningFavoriteButton = addLearningFavoriteButton;


    function showLearningEditControls(questionId) {
        const editRow = document.getElementById("learning-edit-controls-row");
        if (!editRow) return;
        
        editRow.innerHTML = '';
        editRow.style.display = 'block';
        
        const btnGroup = document.createElement("div");
        btnGroup.className = "btn-group btn-group-sm w-100";
        btnGroup.innerHTML = `
            <button class="btn btn-outline-success" onclick="openAddQuestionModal()" title="Добавить новый вопрос">
                <i class="bi bi-plus-circle-fill"></i> Добавить
            </button>
            <button class="btn btn-outline-primary" onclick="editLearningQuestion(${questionId})" title="Редактировать">
                <i class="bi bi-pencil-fill"></i> Редактировать
            </button>
            <button class="btn btn-outline-danger" onclick="deleteLearningQuestion(${questionId})" title="Удалить">
                <i class="bi bi-trash-fill"></i> Удалить
            </button>
        `;
        
        editRow.appendChild(btnGroup);
    }


    // Добавьте функцию удаления для режима обучения
    async function deleteLearningQuestion(questionId) {
        if (!confirm("Вы уверены, что хотите удалить этот вопрос?")) {
            return;
        }
        
        try {
            const result = await apiCall("deleteQuestion", {
                question_id: questionId,
                session_id: 0
            });
            
            if (result.success) {
                showNotification("✅ Вопрос удален", "success");
                
                // Удаляем вопрос из массива
                allQuestions = allQuestions.filter(q => q.id !== questionId);
                
                if (allQuestions.length === 0) {
                    alert("Все вопросы удалены. Возвращаемся к списку.");
                    window.location.reload();
                    return;
                }
                
                // Показываем предыдущий или текущий вопрос
                const newIndex = currentIndex >= allQuestions.length ? allQuestions.length - 1 : currentIndex;
                showLearningQuestion(newIndex);
            } else {
                alert("Ошибка удаления: " + result.message);
            }
        } catch (error) {
            console.error("Delete error:", error);
            alert("Ошибка: " + error.message);
        }
    }
    
    window.deleteLearningQuestion = deleteLearningQuestion;






    function openEditModal(question, questionId, isLearning = false) {
        const modalHtml = `
            <div class="modal fade" id="editQuestionModal" tabindex="-1">
                <div class="modal-dialog modal-xl">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Редактирование вопроса</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <!-- Текст вопроса с Quill -->
                            <div class="mb-3">
                                <label class="form-label fw-bold">Текст вопроса</label>
                                <div id="edit-question-text-editor" style="min-height: 150px; background: white;"></div>
                                <textarea id="edit-question-text" style="display:none;">${escapeHtml(question.question_text)}</textarea>
                            </div>
                            
                            <!-- КОМПАКТНОЕ ПОЛЕ: Изображение к вопросу -->
                            <div class="mb-3">
                                <label class="form-label fw-bold">📷 Дополнительное изображение к вопросу</label>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <button type="button" class="btn btn-outline-primary w-100" id="upload-question-image-btn">
                                            <i class="bi bi-upload"></i> Загрузить
                                        </button>
                                        <input type="file" id="question-image-input" accept="image/*" style="display:none;">
                                        <input type="hidden" id="question-image-url" value="${question.question_image || ''}">
                                        <small class="text-muted d-block mt-2">Отображается под текстом вопроса</small>
                                    </div>
                                    <div class="col-md-8">
                                        <div id="question-image-preview" class="border rounded p-2 bg-light" style="min-height: 80px;">
                                            ${question.question_image ? `<div><img src="${question.question_image}" class="img-fluid" style="max-height: 150px;"><br><button type="button" class="btn btn-sm btn-danger mt-2" onclick="window.removeImage('question-image')">Удалить</button></div>` : '<small class="text-muted">Изображение не загружено</small>'}
                                        </div>
                                    </div>
                                </div>
                            </div>
        
                            <!-- Тип вопроса -->
                            <div class="mb-3">
                                <label class="form-label fw-bold">Тип вопроса</label>
                                <select class="form-select" id="edit-question-type">
                                    <option value="single" ${question.question_type === 'single' ? 'selected' : ''}>Один правильный ответ</option>
                                    <option value="multiple" ${question.question_type === 'multiple' ? 'selected' : ''}>Несколько правильных ответов</option>
                                </select>
                            </div>
        
                            <!-- Объяснение с Quill -->
                            <div class="mb-3">
                                <label class="form-label fw-bold">Объяснение</label>
                                <div id="edit-explanation-editor" style="min-height: 150px; background: white;"></div>
                                <textarea id="edit-explanation" style="display:none;">${question.explanation || ''}</textarea>
                            </div>
                            
                            <!-- КОМПАКТНОЕ ПОЛЕ: Изображение к объяснению -->
                            <div class="mb-3">
                                <label class="form-label fw-bold">📷 Дополнительное изображение к объяснению</label>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <button type="button" class="btn btn-outline-primary w-100" id="upload-explanation-image-btn">
                                            <i class="bi bi-upload"></i> Загрузить
                                        </button>
                                        <input type="file" id="explanation-image-input" accept="image/*" style="display:none;">
                                        <input type="hidden" id="explanation-image-url" value="${question.explanation_image || ''}">
                                        <small class="text-muted d-block mt-2">Отображается под текстом объяснения</small>
                                    </div>
                                    <div class="col-md-8">
                                        <div id="explanation-image-preview" class="border rounded p-2 bg-light" style="min-height: 80px;">
                                            ${question.explanation_image ? `<div><img src="${question.explanation_image}" class="img-fluid" style="max-height: 150px;"><br><button type="button" class="btn btn-sm btn-danger mt-2" onclick="window.removeImage('explanation-image')">Удалить</button></div>` : '<small class="text-muted">Изображение не загружено</small>'}
                                        </div>
                                    </div>
                                </div>
                            </div>
        
                            <!-- Варианты ответов -->
                            <div class="mb-3">
                                <label class="form-label fw-bold">Варианты ответов</label>
                                <div id="edit-answers-list">
                                    ${question.answers.map(ans => `
                                        <div class="input-group mb-2" data-answer-id="${ans.id}">
                                            <div class="input-group-text">
                                                <input type="checkbox" class="answer-correct-check" ${ans.is_correct ? 'checked' : ''}>
                                            </div>
                                            <input type="text" class="form-control answer-text-input" value="${escapeHtml(ans.answer_text)}">
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer d-flex justify-content-between align-items-center">
                            <!-- ДВА чекбокса слева -->
                            <div class="d-flex gap-4">
                                <!-- Чекбокс 1: Опубликован -->
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="edit-question-published" ${question.published == 1 ? 'checked' : ''} style="width: 3em; height: 1.5em;">
                                    <label class="form-check-label ms-2 fw-bold" for="edit-question-published">
                                        <i class="bi bi-eye-fill text-success"></i> Опубликован
                                    </label>
                                </div>
                                
                                <!-- Чекбокс 2: Добавить в обучение -->
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="edit-question-learning" ${question.is_learning == 1 ? 'checked' : ''} style="width: 3em; height: 1.5em;">
                                    <label class="form-check-label ms-2 fw-bold" for="edit-question-learning">
                                        <i class="bi bi-book-half text-primary"></i> В обучение
                                    </label>
                                </div>

                            </div>
                            
                            <!-- Кнопки справа -->
                            <div>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                                <button type="button" class="btn btn-primary" id="save-question-btn">
                                    <i class="bi bi-check-circle"></i> Сохранить
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    
        const oldModal = document.getElementById("editQuestionModal");
        if (oldModal) oldModal.remove();
        
        document.body.insertAdjacentHTML("beforeend", modalHtml);
        
        const modal = new bootstrap.Modal(document.getElementById("editQuestionModal"));
        modal.show();
        
        setTimeout(() => {
            const questionQuill = initQuillEditorWithImage('edit-question-text');
            const explanationQuill = initQuillEditorWithImage('edit-explanation');
            
            setupSeparateImageUpload('question-image');
            setupSeparateImageUpload('explanation-image');
        }, 100);
    
        document.getElementById("save-question-btn").addEventListener("click", async function() {
            await saveQuestion(questionId, isLearning, !isLearning && !currentSessionId);
            modal.hide();
        });
    }


    function setupSeparateImageUpload(prefix) {
        const uploadBtn = document.getElementById(`upload-${prefix}-btn`);
        const fileInput = document.getElementById(`${prefix}-input`);
        const preview = document.getElementById(`${prefix}-preview`);
        const urlInput = document.getElementById(`${prefix}-url`);
        
        if (!uploadBtn || !fileInput) return;
        
        uploadBtn.onclick = () => fileInput.click();
        
        fileInput.onchange = async () => {
            const file = fileInput.files[0];
            if (!file) return;
            
            if (file.size > 5 * 1024 * 1024) {
                alert('Файл слишком большой (макс. 5MB)');
                return;
            }
            
            const formData = new FormData();
            formData.append('image', file);

            // Добавляем CSRF токен
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            if (csrfToken) {
                formData.append('csrf_token', csrfToken);
            }

            uploadBtn.disabled = true;
            uploadBtn.textContent = 'Загрузка...';

            try {
                const response = await fetch('/assets/components/testsystem/ajax/upload-image.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    urlInput.value = result.url;
                    preview.innerHTML = `
                        <div>
                            <img src="${result.url}" class="img-fluid border" style="max-height: 200px;">
                            <br>
                            <button type="button" class="btn btn-sm btn-danger mt-2" onclick="window.removeImage('${prefix}')">Удалить</button>
                        </div>
                    `;
                } else {
                    alert('Ошибка загрузки: ' + result.message);
                }
            } catch (error) {
                alert('Ошибка: ' + error.message);
            } finally {
                uploadBtn.disabled = false;
                uploadBtn.textContent = 'Загрузить изображение';
            }
        };
    }
    
    window.removeImage = function(prefix) {
        const preview = document.getElementById(`${prefix}-preview`);
        const urlInput = document.getElementById(`${prefix}-url`);
        
        if (preview) {
            preview.innerHTML = '<small class="text-muted">Изображение не загружено</small>';
        }
        if (urlInput) {
            urlInput.value = '';
        }
    };


    function initQuillEditorWithImage(textareaId) {
        const textarea = document.getElementById(textareaId);
        if (!textarea) return null;
        
        const editorDiv = document.getElementById(textareaId + '-editor');
        if (!editorDiv) return null;
        
        const initialValue = textarea.value;
        
        const quill = new Quill('#' + editorDiv.id, {
            theme: 'snow',
            modules: {
                toolbar: {
                    container: [
                        ['bold', 'italic', 'underline'],
                        ['link', 'code-block'],
                        [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                        ['image'],
                        ['clean']
                    ],
                    handlers: {
                        image: function() {
                            selectLocalImageForQuill(quill);
                        }
                    }
                }
            },
            placeholder: 'Введите текст...'
        });
        
        quill.root.innerHTML = initialValue;
        
        quill.on('text-change', function() {
            textarea.value = quill.root.innerHTML;
        });
        
        return quill;
    }
    
    function selectLocalImageForQuill(quill) {
        const input = document.createElement('input');
        input.setAttribute('type', 'file');
        input.setAttribute('accept', 'image/png, image/gif, image/jpeg, image/jpg, image/webp');
        input.click();
        
        input.onchange = async () => {
            const file = input.files[0];
            
            if (!file) return;
            
            if (file.size > 5 * 1024 * 1024) {
                alert('Файл слишком большой (макс. 5MB)');
                return;
            }
            
            const formData = new FormData();
            formData.append('image', file);

            // Добавляем CSRF токен
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            if (csrfToken) {
                formData.append('csrf_token', csrfToken);
            }

            try {
                const range = quill.getSelection(true);
                quill.insertText(range.index, 'Загрузка...', 'user');

                const response = await fetch('/assets/components/testsystem/ajax/upload-image.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                quill.deleteText(range.index, 11);
                
                if (result.success) {
                    quill.insertEmbed(range.index, 'image', result.url, 'user');
                    quill.insertText(range.index + 1, '\n', 'user');
                    quill.setSelection(range.index + 2);
                } else {
                    alert('Ошибка загрузки: ' + result.message);
                }
            } catch (error) {
                alert('Ошибка: ' + error.message);
            }
        };
    }
    






    async function saveQuestion(questionId, isLearning, fromList = false) {
        const questionText = document.getElementById("edit-question-text").value.trim();
        const questionType = document.getElementById("edit-question-type").value;
        const explanation = document.getElementById("edit-explanation").value.trim();
        const questionImage = document.getElementById("question-image-url").value;
        const explanationImage = document.getElementById("explanation-image-url").value;
        const published = document.getElementById("edit-question-published").checked ? 1 : 0;
        const isLearningMaterial = document.getElementById("edit-question-learning").checked ? 1 : 0;
        
        const answers = [];
        document.querySelectorAll("#edit-answers-list .input-group").forEach(group => {
            const answerId = group.dataset.answerId;
            const text = group.querySelector(".answer-text-input").value.trim();
            const isCorrect = group.querySelector(".answer-correct-check").checked ? 1 : 0;
            
            if (text) {
                answers.push({
                    id: answerId,
                    text: text,
                    is_correct: isCorrect
                });
            }
        });
        
        if (!questionText || answers.length === 0) {
            alert("Заполните текст вопроса и хотя бы один ответ");
            return;
        }
        
        try {
            const result = await apiCall("updateQuestion", {
                question_id: questionId,
                question_text: questionText,
                question_type: questionType,
                explanation: explanation,
                question_image: questionImage,
                explanation_image: explanationImage,
                published: published,
                is_learning: isLearningMaterial,
                answers: answers
            });
            
            if (result.success) {
                showNotification("✅ Вопрос успешно обновлен!", "success");
                
                if (isLearning) {
                    // Режим обучения - перезагружаем вопрос
                    await reloadLearningQuestion(questionId);
                } else if (fromList) {
                    // Сохранение из списка - ничего не делаем
                    return;
                } else if (currentSessionId && currentQuestionId === questionId) {
                    // ИСПРАВЛЕНИЕ: Редактирование в режиме тренинга
                    // Перезагружаем ТЕКУЩИЙ вопрос вместо перехода к следующему
                    await reloadCurrentQuestion();
                } else {
                    // Другие случаи - переходим к следующему
                    loadNextQuestion();
                }
            } else {
                throw new Error(result.message || "Ошибка сохранения");
            }
        } catch (error) {
            console.error("Save question error:", error);
            alert("Ошибка: " + error.message);
        }
    }


    // ДОБАВИТЬ НОВУЮ ФУНКЦИЮ: Перезагрузка вопроса в режиме обучения
    async function reloadLearningQuestion(questionId) {
        try {
            // Перезагружаем все вопросы из базы
            const result = await apiCall("getAllQuestions", { test_id: testId });
            
            if (!result.success) {
                throw new Error("Не удалось перезагрузить вопросы");
            }
            
            allQuestions = result.data;
            
            // Находим индекс обновленного вопроса
            const newIndex = allQuestions.findIndex(q => q.id == questionId);
            
            if (newIndex === -1) {
                // Вопрос был снят с публикации или удален
                alert("Вопрос больше не доступен. Возвращаемся к началу.");
                if (allQuestions.length > 0) {
                    showLearningQuestion(0);
                } else {
                    // Нет вопросов - перезагружаем страницу
                    window.location.reload();
                }
                return;
            }
            
            // Показываем обновленный вопрос
            currentIndex = newIndex;
            showLearningQuestion(currentIndex);
            
        } catch (error) {
            console.error("Reload question error:", error);
            alert("Ошибка перезагрузки: " + error.message);
            // В случае ошибки просто показываем текущий вопрос
            showLearningQuestion(currentIndex);
        }
    }



    window.tsEditQuestion = function() {
        console.log("tsEditQuestion called:", { canEdit, currentQuestionId, currentQuestionNumber });

        if (!canEdit) {
            alert("У вас нет прав для редактирования");
            return;
        }

        if (!currentQuestionId) {
            console.error("❌ currentQuestionId is null/undefined!");
            alert("Вопрос не загружен");
            return;
        }
        
        apiCall("getQuestion", {
            question_id: currentQuestionId
        }).then(result => {
            if (result.success) {
                openEditModal(result.data, currentQuestionId, false);
            } else {
                alert("Ошибка загрузки вопроса: " + result.message);
            }
        }).catch(error => {
            console.error("Edit error:", error);
            alert("Ошибка: " + error.message);
        });
    };
    
    window.tsDeleteQuestion = function() {
        if (!canEdit) {
            alert("У вас нет прав для удаления");
            return;
        }
        
        if (!currentQuestionId) {
            alert("Вопрос не загружен");
            return;
        }
        
        if (!confirm("Вы уверены, что хотите удалить этот вопрос?")) {
            return;
        }
        
        apiCall("deleteQuestion", {
            question_id: currentQuestionId,
            session_id: currentSessionId
        }).then(result => {
            if (result.success) {
                alert("✅ Вопрос удален");
                loadNextQuestion();
            } else {
                alert("Ошибка удаления: " + result.message);
            }
        }).catch(error => {
            console.error("Delete error:", error);
            alert("Ошибка: " + error.message);
        });
    };
    

    window.tsTogglePublished = async function() {
        if (!canEdit) {
            alert("У вас нет прав");
            return;
        }
        
        if (!currentQuestionId) {
            alert("Вопрос не загружен");
            return;
        }
        
        // ДОБАВЛЕНО: Подтверждение действия
        if (!confirm("Вы уверены, что хотите снять этот вопрос с публикации?\n\nВопрос станет недоступен для прохождения теста.")) {
            return;
        }
        
        try {
            const result = await apiCall("togglePublished", {
                question_id: currentQuestionId
            });
            
            if (result.success) {
                loadNextQuestion();
            } else {
                alert("Ошибка: " + result.message);
            }
        } catch (error) {
            console.error("Toggle error:", error);
            alert("Ошибка: " + error.message);
        }
    };

    
    function initializeTestMode() {
        setupTestCardMenuHandlers();

        const trainingCard = document.getElementById("training-card");
        if (trainingCard) {
            trainingCard.classList.add("active");
        }
        

        const startButtons = document.querySelectorAll(".start-test-btn");
        startButtons.forEach(btn => {
            btn.addEventListener("click", function() {
                console.log("Start button clicked!");

                // Защита от двойного клика
                if (this.disabled) {
                    console.log("Button already disabled, ignoring click");
                    return;
                }

                this.disabled = true;
                const originalText = this.textContent;
                this.textContent = "Загрузка...";

                const mode = this.dataset.mode || "training";
                let questionsCount = null;

                if (mode === "training" && !isKnowledgeArea) {
                    const input = document.getElementById("training-questions-count");
                    if (input) {
                        questionsCount = parseInt(input.value, 10);
                    }
                }

                console.log("Starting test:", { mode, questionsCount, isKnowledgeArea });

                // Если startTest завершится с ошибкой, разблокируем кнопку
                startTest(mode, questionsCount).catch(error => {
                    console.error("Start test error:", error);
                    this.disabled = false;
                    this.textContent = originalText;
                });
            });
        });


        // ✅ УБРАЛИ ДУБЛИКАТ - оставили только одно объявление
        const modeToggle = document.getElementById("mode-toggle");
        if (modeToggle) {
            modeToggle.addEventListener("change", function() {
                const isExam = this.checked;
                const selectedMode = isExam ? "exam" : "training";
                
                const trainingCard = document.getElementById("training-card");
                const examCard = document.getElementById("exam-card");
                const trainingLabel = document.querySelector(".mode-label-training");
                const examLabel = document.querySelector(".mode-label-exam");
                const startBtn = document.getElementById("start-test-unified");
                const btnText = document.getElementById("start-btn-text");
                
                // ✅ ДОБАВЛЕНО: Скрываем/показываем контрол количества вопросов
                const trainingQuestionsControl = document.querySelector(".training-questions-control");
                
                if (isExam) {
                    trainingCard?.classList.remove("active");
                    examCard?.classList.add("active");
                    trainingLabel?.classList.remove("active");
                    examLabel?.classList.add("active");
                    if (btnText) btnText.textContent = "Начать Exam";
                    if (trainingQuestionsControl) trainingQuestionsControl.style.display = "none";
                } else {
                    trainingCard?.classList.add("active");
                    examCard?.classList.remove("active");
                    trainingLabel?.classList.add("active");
                    examLabel?.classList.remove("active");
                    if (btnText) btnText.textContent = "Начать Training";
                    if (trainingQuestionsControl) trainingQuestionsControl.style.display = "block";
                }
                
                if (startBtn) {
                    startBtn.dataset.mode = selectedMode;
                    // ✅ ДОБАВЛЕНО: Убираем disabled если был
                    startBtn.disabled = false;
                }
            });
        }
        
        const trainingAllBtn = document.getElementById("training-all-questions");
        if (trainingAllBtn) {
            trainingAllBtn.addEventListener("click", function() {
                const total = parseInt(this.dataset.total, 10);
                const input = document.getElementById("training-questions-count");
                if (input) {
                    input.value = total;
                }
            });
        }
        
        const trainingInput = document.getElementById("training-questions-count");
        if (trainingInput) {
            trainingInput.addEventListener("input", function() {
                const max = parseInt(this.dataset.max, 10);
                let val = parseInt(this.value, 10);
                
                if (isNaN(val) || val < 1) val = 1;
                if (val > max) val = max;
                
                this.value = val;
            });
        }
    }

    function setupTestCardMenuHandlers() {
        document.addEventListener('click', function(e) {
            const toggle = e.target.closest('.test-menu-toggle');

            if (toggle) {
                e.stopPropagation();
                const testId = toggle.dataset.testId;
                const menu = document.getElementById('menu-' + testId);
                if (!menu) return;

                document.querySelectorAll('.test-menu-dropdown').forEach(function(m) {
                    if (m !== menu) {
                        m.style.display = 'none';
                    }
                });

                menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
                return;
            }

            if (!e.target.closest('.test-menu-dropdown')) {
                document.querySelectorAll('.test-menu-dropdown').forEach(function(m) {
                    m.style.display = 'none';
                });
            }
        });
    }

    

    async function startTest(mode, questionsCount = null) {
        console.log("=== startTest CALLED ===");
        console.log("Mode:", mode);
        console.log("Questions count:", questionsCount);
        console.log("Is Knowledge Area:", isKnowledgeArea);
        console.log("Knowledge Area ID:", knowledgeAreaId);
        
        testMode = mode;
        
        try {
            const data = {
                mode: mode
            };
            
            // ПОДДЕРЖКА ОБЛАСТЕЙ ЗНАНИЙ
            if (isKnowledgeArea) {
                console.log("Starting knowledge area session...");
                data.area_id = parseInt(knowledgeAreaId);
                
                // Для областей знаний используем специальный экшен
                const result = await apiCall("startKnowledgeAreaSession", data);
                
                console.log("Knowledge area session result:", result);
                
                if (result.success) {
                    currentSessionId = result.data.session_id;
                    totalQuestions = result.data.total_questions;
                    currentQuestionNumber = 0;
                    
                    console.log("Session started:", currentSessionId);
                    
                    document.getElementById("test-info").style.display = "none";
                    document.getElementById("question-container").style.display = "block";
                    
                    const totalQElement = document.getElementById("total-q");
                    if (totalQElement) {
                        totalQElement.textContent = totalQuestions;
                    }
                    
                    loadNextQuestion();
                } else {
                    console.error("Knowledge area session failed:", result.message);
                    alert("Ошибка запуска теста: " + result.message);
                }
                
            } else {
                // ОБЫЧНЫЙ ТЕСТ
                console.log("Starting regular test session...");
                data.test_id = testId;
                
                if (questionsCount !== null) {
                    data.questions_count = questionsCount;
                }
                
                const result = await apiCall("startSession", data);
                
                console.log("Regular session result:", result);
                
                if (result.success) {
                    currentSessionId = result.data.session_id;
                    totalQuestions = result.data.total_questions;
                    currentQuestionNumber = 0;
                    
                    document.getElementById("test-info").style.display = "none";
                    document.getElementById("question-container").style.display = "block";
                    
                    const modeBadge = document.getElementById("mode-badge");
                    if (modeBadge) {
                        modeBadge.textContent = mode.toUpperCase();
                        modeBadge.className = mode === "exam" ? "badge bg-danger" : "badge bg-primary";
                    }

                    const totalQElement = document.getElementById("total-q");
                    if (totalQElement) {
                        totalQElement.textContent = totalQuestions;
                    }

                    // Start timer for exam mode
                    if (mode === "exam" && result.data.time_limit > 0) {
                        startTimer(result.data.time_limit);
                    }

                    loadNextQuestion();
                } else {
                    console.error("Regular session failed:", result.message);
                    alert("Ошибка запуска теста: " + result.message);
                }
            }
        } catch (error) {
            console.error("Start test error:", error);
            alert("Ошибка: " + error.message);
        }
    }
    

    async function loadNextQuestion() {
        console.log("📞 loadNextQuestion() called");
        try {
            const result = await apiCall("getNextQuestion", {
                session_id: currentSessionId
            });

            console.log("📥 getNextQuestion response:", result);

            if (!result.success) {
                console.error("Ошибка загрузки вопроса:", result.message);
                alert("Ошибка загрузки вопроса");
                return;
            }

            if (result.data && result.data.finished) {
                console.log("🏁 Test finished! Calling finishTest()");
                finishTest();
                return;
            }

            if (!result.data || !result.data.question) {
                console.error("Неверная структура ответа от сервера");
                alert("Ошибка: неверная структура ответа от сервера");
                return;
            }

            currentQuestionId = result.data.question.id;
            currentQuestionType = result.data.question.question_type;
            currentQuestionNumber++;

            console.log("✅ Question loaded, currentQuestionId set to:", currentQuestionId);

            displayQuestion(result.data.question, result.data.answers);
            
            // КРИТИЧЕСКОЕ ИСПРАВЛЕНИЕ: Управляем кнопками ПЕРЕД setupAnswerListeners
            const submitBtn = document.getElementById("submit-answer-btn");
            const nextBtn = document.getElementById("next-question-btn");
            
            if (submitBtn) {
                submitBtn.style.display = "inline-block";
                submitBtn.textContent = "Ответить";
            }
            
            if (nextBtn) {
                nextBtn.style.display = "none";
            }
            
            setupAnswerListeners();
            
            // ✅ ДОБАВИТЬ: Прокрутка к началу вопроса
            setTimeout(() => {
                scrollToQuestion();
            }, 100); // Небольшая задержка для корректной отрисовки
            
        } catch (error) {
            console.error("Load question error:", error);
        }
    }



    function displayQuestion(question, answers) {
        // ИСПРАВЛЕНИЕ: Проверка наличия данных
        if (!question) {
            console.error("No question data provided to displayQuestion");
            return;
        }
        
        document.getElementById("current-q").textContent = currentQuestionNumber;
        // Обновляем прогресс-бар
        updateProgressBar();
        
        // НОВОЕ: Показываем название теста для областей знаний
        let questionHtml = '';
        
        if (isKnowledgeArea && question.test_title) {
            questionHtml += `
                <div class="knowledge-area-test-badge mb-3">
                    <span class="badge bg-gradient-info text-white px-3 py-2">
                        <i class="bi bi-folder2-open me-1"></i>
                        ${escapeHtml(question.test_title)}
                    </span>
                </div>`;
        }
        
        // Формируем HTML вопроса с изображением (XSS Protection: санитизация HTML)
        questionHtml += sanitizeHtml(question.question_text) || 'Текст вопроса отсутствует';
        if (question.question_image) {
            questionHtml += `<div class="mt-3"><img src="${escapeHtml(question.question_image)}" class="img-fluid"></div>`;
        }

        document.getElementById("question-text").innerHTML = questionHtml;
    
        const typeHint = document.getElementById("question-type-hint");
        if (typeHint) {
            if (currentQuestionType === "multiple") {
                typeHint.innerHTML = "☑️ Выберите все правильные ответы";
                typeHint.className = "alert alert-warning";
            } else {
                typeHint.innerHTML = "⭕ Выберите один правильный ответ";
                typeHint.className = "alert alert-primary";
            }
            typeHint.style.display = "block";
        }
    
        const optionsContainer = document.getElementById("answer-options");
        if (!optionsContainer) {
            console.error("Answer options container not found");
            return;
        }
        
        // Показываем кнопку "К началу теста"

        // Показываем кнопку "К началу теста"

        const restartBtn = document.getElementById("restart-test-btn");
        if (restartBtn) {
            restartBtn.style.display = "inline-block";
        }

       // ✅ ДОБАВИТЬ: Настроить обработчик
        setupRestartButton();

        optionsContainer.innerHTML = "";
        
        const inputType = currentQuestionType === "multiple" ? "checkbox" : "radio";
        
        // ИСПРАВЛЕНИЕ: Проверка наличия ответов
        if (!answers || answers.length === 0) {
            console.error("No answers provided");
            optionsContainer.innerHTML = '<div class="alert alert-warning">Нет вариантов ответов</div>';
            return;
        }
        
        answers.forEach(answer => {
            const div = document.createElement("div");
            div.className = "form-check mb-2";
            
            const input = document.createElement("input");
            input.type = inputType;
            input.className = "form-check-input";
            input.id = `option-${answer.id}`;
            input.value = answer.id;
            input.name = "answer";
            
            const label = document.createElement("label");
            label.className = "form-check-label";
            label.setAttribute("for", `option-${answer.id}`);
            label.textContent = answer.answer_text || 'Текст ответа отсутствует';
            
            div.appendChild(input);
            div.appendChild(label);
            optionsContainer.appendChild(div);
        });
        
        const explanationBlock = document.getElementById("explanation-block");
        if (explanationBlock) {
            explanationBlock.style.display = "none";
        }
        
        if (canEdit) {
            addEditButtons();
        }
        
        // ДОБАВИТЬ: Кнопка избранного для всех пользователей
        addFavoriteButton();
        
    }
    

    // Добавить toggle избранного В РЕЖИМЕ ТЕСТА
    async function addFavoriteButton() {
        if (!currentQuestionId) return;
        
        // Проверяем статус избранного
        const statusResult = await apiCall("getFavoriteStatus", {
            question_id: currentQuestionId
        });
        
        if (!statusResult.success) return;
        
        const isFavorite = statusResult.is_favorite;
        
        // Ищем контейнер для toggle
        const questionHeader = document.querySelector("#question-container .card-header");
        if (!questionHeader) return;
        
        // Удаляем старый toggle если есть
        const oldToggle = document.getElementById("favorite-toggle-container");
        if (oldToggle) oldToggle.remove();
        
        // Создаем toggle-switch
        const toggleContainer = document.createElement("div");
        toggleContainer.id = "favorite-toggle-container";
        toggleContainer.className = "favorite-toggle-wrapper ms-auto";
        toggleContainer.innerHTML = `
            <label class="favorite-toggle-switch">
                <input type="checkbox" id="favorite-toggle-input" ${isFavorite ? 'checked' : ''}>
                <span class="favorite-toggle-slider"></span>
            </label>
            <span class="favorite-toggle-label">${isFavorite ? 'В избранном' : 'В избранное'}</span>
        `;
        
        questionHeader.appendChild(toggleContainer);
        
        // Обработчик переключения
        const toggleInput = document.getElementById("favorite-toggle-input");
        const toggleLabel = toggleContainer.querySelector('.favorite-toggle-label');
        toggleInput.addEventListener("change", async function() {
            const newState = this.checked;
            if (toggleLabel) {
                toggleLabel.textContent = newState ? 'В избранном' : 'В избранное';
            }

            try {
                const result = await apiCall("toggleFavorite", {
                    question_id: currentQuestionId
                });

                if (result.success) {
                    const isFavorite = result.data?.is_favorite ?? result.is_favorite;
                    if (isFavorite) {
                        showNotification("⭐ Добавлено в избранное", "success");
                    } else {
                        showNotification("Убрано из избранного", "info");
                    }
                } else {
                    this.checked = !newState;
                    if (toggleLabel) {
                        toggleLabel.textContent = this.checked ? 'В избранном' : 'В избранное';
                    }
                }
            } catch (error) {
                this.checked = !newState;
                if (toggleLabel) {
                    toggleLabel.textContent = this.checked ? 'В избранном' : 'В избранное';
                }
                alert("Ошибка: " + error.message);
            }
        });
    }


    // Экспортируем функцию
    window.addFavoriteButton = addFavoriteButton;    
        


    // Функция открытия модального окна для добавления нового вопроса
    async function openAddQuestionModal() {
        if (!canEdit) {
            alert("У вас нет прав для добавления вопросов");
            return;
        }
        
        if (!testId) {
            alert("ID теста не определен");
            return;
        }
        
        // Создаем пустой объект вопроса
        const emptyQuestion = {
            id: 0,
            question_text: '',
            question_type: 'single',
            explanation: '',
            question_image: '',
            explanation_image: '',
            published: 1,
            is_learning: 0,
            answers: [
                { id: 'new1', answer_text: '', is_correct: 0 },
                { id: 'new2', answer_text: '', is_correct: 0 },
                { id: 'new3', answer_text: '', is_correct: 0 },
                { id: 'new4', answer_text: '', is_correct: 0 }
            ]
        };
        
        const modalHtml = `
            <div class="modal fade" id="addQuestionModal" tabindex="-1">
                <div class="modal-dialog modal-xl">
                    <div class="modal-content">
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title">
                                <i class="bi bi-plus-circle-fill"></i> Добавление нового вопроса
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <!-- Текст вопроса с Quill -->
                            <div class="mb-3">
                                <label class="form-label fw-bold">Текст вопроса</label>
                                <div id="add-question-text-editor" style="min-height: 150px; background: white;"></div>
                                <textarea id="add-question-text" style="display:none;"></textarea>
                            </div>
                            
                            <!-- КОМПАКТНОЕ ПОЛЕ: Изображение к вопросу -->
                            <div class="mb-3">
                                <label class="form-label fw-bold">📷 Дополнительное изображение к вопросу</label>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <button type="button" class="btn btn-outline-primary w-100" id="upload-add-question-image-btn">
                                            <i class="bi bi-upload"></i> Загрузить
                                        </button>
                                        <input type="file" id="add-question-image-input" accept="image/*" style="display:none;">
                                        <input type="hidden" id="add-question-image-url" value="">
                                        <small class="text-muted d-block mt-2">Отображается под текстом вопроса</small>
                                    </div>
                                    <div class="col-md-8">
                                        <div id="add-question-image-preview" class="border rounded p-2 bg-light" style="min-height: 80px;">
                                            <small class="text-muted">Изображение не загружено</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
        
                            <!-- Тип вопроса -->
                            <div class="mb-3">
                                <label class="form-label fw-bold">Тип вопроса</label>
                                <select class="form-select" id="add-question-type">
                                    <option value="single">Один правильный ответ</option>
                                    <option value="multiple">Несколько правильных ответов</option>
                                </select>
                            </div>
        
                            <!-- Объяснение с Quill -->
                            <div class="mb-3">
                                <label class="form-label fw-bold">Объяснение</label>
                                <div id="add-explanation-editor" style="min-height: 150px; background: white;"></div>
                                <textarea id="add-explanation" style="display:none;"></textarea>
                            </div>
                            
                            <!-- КОМПАКТНОЕ ПОЛЕ: Изображение к объяснению -->
                            <div class="mb-3">
                                <label class="form-label fw-bold">📷 Дополнительное изображение к объяснению</label>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <button type="button" class="btn btn-outline-primary w-100" id="upload-add-explanation-image-btn">
                                            <i class="bi bi-upload"></i> Загрузить
                                        </button>
                                        <input type="file" id="add-explanation-image-input" accept="image/*" style="display:none;">
                                        <input type="hidden" id="add-explanation-image-url" value="">
                                        <small class="text-muted d-block mt-2">Отображается под текстом объяснения</small>
                                    </div>
                                    <div class="col-md-8">
                                        <div id="add-explanation-image-preview" class="border rounded p-2 bg-light" style="min-height: 80px;">
                                            <small class="text-muted">Изображение не загружено</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
        
                            <!-- Варианты ответов -->
                            <div class="mb-3">
                                <label class="form-label fw-bold">Варианты ответов</label>
                                <div id="add-answers-list">
                                    ${emptyQuestion.answers.map(ans => `
                                        <div class="input-group mb-2" data-answer-id="${ans.id}">
                                            <div class="input-group-text">
                                                <input type="checkbox" class="answer-correct-check">
                                            </div>
                                            <input type="text" class="form-control answer-text-input" placeholder="Введите вариант ответа">
                                        </div>
                                    `).join('')}
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="add-more-answers-btn">
                                    <i class="bi bi-plus-circle"></i> Добавить еще вариант
                                </button>
                            </div>
                        </div>
                        <div class="modal-footer d-flex justify-content-between align-items-center">
                            <!-- ДВА чекбокса слева -->
                            <div class="d-flex gap-4">
                                <!-- Чекбокс 1: Опубликован -->
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="add-question-published" checked style="width: 3em; height: 1.5em;">
                                    <label class="form-check-label ms-2 fw-bold" for="add-question-published">
                                        <i class="bi bi-eye-fill text-success"></i> Опубликован
                                    </label>
                                </div>
                                
                                <!-- Чекбокс 2: Добавить в обучение -->
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="add-question-learning" style="width: 3em; height: 1.5em;">
                                    <label class="form-check-label ms-2 fw-bold" for="add-question-learning">
                                        <i class="bi bi-book-half text-primary"></i> В обучение
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Кнопки справа -->
                            <div>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                                <button type="button" class="btn btn-success" id="create-question-btn">
                                    <i class="bi bi-check-circle"></i> Создать вопрос
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    
        const oldModal = document.getElementById("addQuestionModal");
        if (oldModal) oldModal.remove();
        
        document.body.insertAdjacentHTML("beforeend", modalHtml);
        
        const modal = new bootstrap.Modal(document.getElementById("addQuestionModal"));
        modal.show();
        
        setTimeout(() => {
            initQuillEditorWithImage('add-question-text');
            initQuillEditorWithImage('add-explanation');
            
            setupSeparateImageUpload('add-question-image');
            setupSeparateImageUpload('add-explanation-image');
            
            // Обработчик добавления вариантов ответов
            document.getElementById("add-more-answers-btn").addEventListener("click", function() {
                const answersList = document.getElementById("add-answers-list");
                const newAnswerId = 'new' + Date.now();
                const newAnswerHtml = `
                    <div class="input-group mb-2" data-answer-id="${newAnswerId}">
                        <div class="input-group-text">
                            <input type="checkbox" class="answer-correct-check">
                        </div>
                        <input type="text" class="form-control answer-text-input" placeholder="Введите вариант ответа">
                        <button class="btn btn-outline-danger" type="button" onclick="this.parentElement.remove()">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                `;
                answersList.insertAdjacentHTML('beforeend', newAnswerHtml);
            });
        }, 100);
    
        document.getElementById("create-question-btn").addEventListener("click", async function() {
            await createNewQuestion();
            modal.hide();
        });
    }
    
    // Функция создания нового вопроса
    async function createNewQuestion() {
        const questionText = document.getElementById("add-question-text").value.trim();
        const questionType = document.getElementById("add-question-type").value;
        const explanation = document.getElementById("add-explanation").value.trim();
        const questionImage = document.getElementById("add-question-image-url").value;
        const explanationImage = document.getElementById("add-explanation-image-url").value;
        const published = document.getElementById("add-question-published").checked ? 1 : 0;
        const isLearning = document.getElementById("add-question-learning").checked ? 1 : 0;
        
        const answers = [];
        document.querySelectorAll("#add-answers-list .input-group").forEach(group => {
            const text = group.querySelector(".answer-text-input").value.trim();
            const isCorrect = group.querySelector(".answer-correct-check").checked ? 1 : 0;
            
            if (text) {
                answers.push({
                    text: text,
                    is_correct: isCorrect
                });
            }
        });
        
        if (!questionText) {
            alert("Введите текст вопроса");
            return;
        }
        
        if (answers.length < 2) {
            alert("Добавьте минимум 2 варианта ответа");
            return;
        }
        
        const hasCorrect = answers.some(a => a.is_correct === 1);
        if (!hasCorrect) {
            alert("Отметьте хотя бы один правильный ответ");
            return;
        }
        
        try {
            const result = await apiCall("createQuestion", {
                test_id: testId,
                question_text: questionText,
                question_type: questionType,
                explanation: explanation,
                question_image: questionImage,
                explanation_image: explanationImage,
                published: published,
                is_learning: isLearning,
                answers: answers
            });
            
            if (result.success) {
                showNotification("✅ Вопрос успешно создан!", "success");
                
                // Если мы в списке вопросов - обновляем список
                const listContainer = document.getElementById("questions-list-container");
                if (listContainer && listContainer.style.display !== "none") {
                    const activeBtn = document.querySelector('#questions-list-container .btn-primary, #questions-list-container .btn-success');
                    let currentFilter = 'all';
                    
                    if (activeBtn) {
                        const btnText = activeBtn.textContent;
                        if (btnText.includes('Опубликованные')) {
                            currentFilter = 'published';
                        } else if (btnText.includes('Неопубликованные')) {
                            currentFilter = 'unpublished';
                        }
                    }
                    
                    await showAllQuestionsView(currentFilter);
                }
            } else {
                throw new Error(result.message || "Ошибка создания вопроса");
            }
        } catch (error) {
            console.error("Create question error:", error);
            alert("Ошибка: " + error.message);
        }
    }
    
    // Экспортируем функцию
    window.openAddQuestionModal = openAddQuestionModal;
    


    function addEditButtons() {
        const editRow = document.getElementById("edit-buttons-row");
        if (!editRow) return;
        
        // Очищаем и показываем строку
        editRow.innerHTML = '';
        editRow.style.display = 'block';
        
        const btnGroup = document.createElement("div");
        btnGroup.className = "btn-group btn-group-sm w-100";
        btnGroup.innerHTML = `
            <button class="btn btn-outline-secondary" onclick="window.tsTogglePublished()" title="Снять с публикации">
                <i class="bi bi-eye-slash"></i> Снять
            </button>
            <button class="btn btn-outline-primary" onclick="window.tsEditQuestion()" title="Редактировать">
                <i class="bi bi-pencil"></i> Редактировать
            </button>
            <button class="btn btn-outline-danger" onclick="window.tsDeleteQuestion()" title="Удалить">
                <i class="bi bi-trash"></i> Удалить
            </button>
        `;
        
        editRow.appendChild(btnGroup);
    }



    function setupAnswerListeners() {
        const allInputs = document.querySelectorAll('#answer-options input');
        
        // ИСПРАВЛЕНИЕ: Сначала клонируем кнопку, ПОТОМ работаем с ней
        let submitBtn = document.getElementById("submit-answer-btn");
        
        if (submitBtn) {
            const newSubmitBtn = submitBtn.cloneNode(true);
            submitBtn.parentNode.replaceChild(newSubmitBtn, submitBtn);
            
            // Теперь работаем с НОВОЙ кнопкой
            submitBtn = newSubmitBtn;
            submitBtn.disabled = true;
            
            // Привязываем обработчик клика
            submitBtn.addEventListener("click", submitAnswer);
        }
        
        // Функция проверки выбранных ответов - теперь submitBtn указывает на правильную кнопку
        function checkAnswers() {
            const checked = document.querySelectorAll('#answer-options input:checked');
            if (submitBtn) {
                submitBtn.disabled = checked.length === 0;
            }
        }
        
        // Привязываем обработчики к каждому инпуту
        allInputs.forEach(input => {
            input.addEventListener("change", checkAnswers);
            input.addEventListener("click", checkAnswers);
        });
    

        // Обработчик для кнопки "Следующий вопрос"
        const nextBtn = document.getElementById("next-question-btn");
        if (nextBtn) {
            // ИСПРАВЛЕНИЕ: Используем onclick вместо addEventListener после клонирования
            const newNextBtn = nextBtn.cloneNode(true);
            nextBtn.parentNode.replaceChild(newNextBtn, nextBtn);
            
            newNextBtn.onclick = function(e) {
                e.preventDefault();
                e.stopPropagation();
                loadNextQuestion();
                return false;
            };
        }


    }
    
    async function submitAnswer() {
        const checked = document.querySelectorAll('#answer-options input:checked');
        if (checked.length === 0) {
            alert("Выберите ответ");
            return;
        }
        
        const selectedAnswers = Array.from(checked).map(input => parseInt(input.value, 10));
        
        const submitBtn = document.getElementById("submit-answer-btn");
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = "Отправка...";
        }
        
        try {

            const result = await apiCall("submitAnswer", {
                session_id: currentSessionId,
                question_id: currentQuestionId,
                answer_ids: selectedAnswers
            });

            if (result.success) {
                if (testMode === "training") {
                    showTrainingFeedback(result.data);
                } else {
                    loadNextQuestion();
                }
            } else {
                alert("Ошибка отправки ответа: " + result.message);
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = "Ответить";
                }
            }
        } catch (error) {
            console.error("Submit answer error:", error);
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = "Ответить";
            }
        }
    }
    


    function showTrainingFeedback(data) {
        console.log("showTrainingFeedback called with:", data); // Отладка
        
        // ИСПРАВЛЕНИЕ: Проверяем наличие данных
        if (!data) {
            console.error("No feedback data provided");
            return;
        }
        
        const inputs = document.querySelectorAll('#answer-options input');
        inputs.forEach(input => input.disabled = true);
        
        const correctIds = data.correct_answer_ids || [];
        const userAnswerIds = data.user_answer_ids || [];
        
        const questionType = inputs[0] ? inputs[0].type : 'checkbox';
        const isMultipleChoice = questionType === 'checkbox';
        
        document.querySelectorAll('#answer-options .form-check').forEach(div => {
            const input = div.querySelector('input');
            if (!input) return;
            
            const answerId = parseInt(input.value, 10);
            
            div.classList.remove('correct-answer', 'incorrect-answer', 'missed-answer');
            
            const isCorrect = correctIds.includes(answerId);
            const wasSelected = userAnswerIds.includes(answerId);
            
            if (isMultipleChoice) {
                if (isCorrect && wasSelected) {
                    div.classList.add('correct-answer');
                } else if (isCorrect && !wasSelected) {
                    div.classList.add('missed-answer');
                } else if (!isCorrect && wasSelected) {
                    div.classList.add('incorrect-answer');
                }
            } else {
                if (isCorrect) {
                    div.classList.add('correct-answer');
                } else if (wasSelected) {
                    div.classList.add('incorrect-answer');
                }
            }
        });
        
        // Показываем объяснение если есть
        if (data.explanation) {
            const explanationBlock = document.getElementById("explanation-block");
            if (explanationBlock) {
                // XSS Protection: санитизация HTML в объяснении
                let expHtml = `<strong>Объяснение:</strong><br><div class="explanation-content">${sanitizeHtml(data.explanation)}</div>`;
                if (data.explanation_image) {
                    expHtml += `<img src="${escapeHtml(data.explanation_image)}" class="img-fluid mt-2">`;
                }
                explanationBlock.innerHTML = expHtml;
                explanationBlock.style.display = "block";
            }
        }

        // Управляем кнопками
        const submitBtn = document.getElementById("submit-answer-btn");
        const nextBtn = document.getElementById("next-question-btn");

        if (submitBtn) submitBtn.style.display = "none";

        // ИСПРАВЛЕНИЕ: Проверяем, был ли это последний вопрос
        if (currentQuestionNumber >= totalQuestions) {
            // Последний вопрос - автоматически завершаем тест
            console.log("✅ Last question answered, auto-finishing test");
            console.log(`   currentQuestionNumber=${currentQuestionNumber}, totalQuestions=${totalQuestions}`);
            if (nextBtn) nextBtn.style.display = "none";

            // Даем пользователю время посмотреть результат последнего вопроса
            setTimeout(() => {
                console.log("⏱️ Timeout triggered after 2s, calling loadNextQuestion()");
                loadNextQuestion(); // Это вызовет finishTest() так как вопросов больше нет
            }, 2000); // 2 секунды на просмотр feedback
        } else {
            // Не последний вопрос - показываем кнопку "Следующий"
            if (nextBtn) {
                nextBtn.style.display = "inline-block";
                nextBtn.disabled = false;

                // Клонируем кнопку для удаления старых обработчиков
                const newNextBtn = nextBtn.cloneNode(true);
                nextBtn.parentNode.replaceChild(newNextBtn, nextBtn);

                newNextBtn.onclick = function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    loadNextQuestion();
                    return false;
                };
            }
        }
    }
    

    async function finishTest() {
        console.log("🎯 finishTest() called, session_id:", currentSessionId);
        // Stop timer if running
        stopTimer();

        try {
            console.log("📞 Calling API: finishTest");
            const result = await apiCall("finishTest", {
                session_id: currentSessionId
            });

            console.log("📥 finishTest API response:", result);

            if (result.success) {
                console.log("✅ finishTest succeeded, showing results");
                const data = result.data;

                document.getElementById("question-container").style.display = "none";
                document.getElementById("results-container").style.display = "block";

                const scoreElement = document.getElementById("final-score");
                const messageElement = document.getElementById("result-message");

                if (scoreElement) {
                    scoreElement.textContent = `Ваш результат: ${data.score}%`;
                }

                if (messageElement) {
                    if (testMode === "exam") {
                        if (data.passed) {
                            messageElement.innerHTML = '<span class="text-success">✅ Тест пройден успешно!</span>';
                        } else {
                            messageElement.innerHTML = '<span class="text-danger">❌ Тест не пройден. Попробуйте еще раз.</span>';
                        }
                    } else {
                        messageElement.innerHTML = '<span class="text-info">📚 Режим обучения завершен</span>';
                    }
                }

                const details = document.getElementById("result-details");
                details.innerHTML = `
                    <p><strong>Правильных ответов:</strong> ${data.correct_count}</p>
                    <p><strong>Неправильных ответов:</strong> ${data.incorrect_count}</p>
                    <p><strong>Проходной балл:</strong> ${data.pass_score}%</p>
                `;
            } else {
                console.error("❌ finishTest failed:", result.message);
            }
        } catch (error) {
            console.error("💥 Finish test error:", error);
            console.error("Error stack:", error.stack);
        }
    }
    
    function showTestSettingsButton() {
        const testInfo = document.getElementById("test-info");
        if (!testInfo) return;
        
        const settingsBtn = document.createElement("button");
        settingsBtn.id = "test-settings-btn";
        settingsBtn.className = "btn btn-warning btn-sm mb-3 btn-test-settings";
        settingsBtn.innerHTML = '<i class="bi bi-gear-fill"></i> Настройки теста';
        settingsBtn.onclick = function() {
            openTestSettingsModal(testId);
        };
        
        const cardBody = testInfo.querySelector(".card-body");
        if (cardBody) {
            cardBody.insertBefore(settingsBtn, cardBody.firstChild);
        }
    }
    
    async function openTestSettingsModal(currentTestId) {
        try {
            const result = await apiCall("getTestSettings", { test_id: currentTestId });
            
            if (!result.success) {
                throw new Error(result.message || "Ошибка загрузки настроек теста");
            }
            
            const test = result.data;
            
            const modalHtml = `
            <div class="modal fade" id="testSettingsModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title">
                                <i class="bi bi-gear-fill"></i> Настройки теста
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Название теста</label>
                                <input type="text" class="form-control" id="test-title" 
                                       value="${escapeHtml(test.title)}" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Описание</label>
                                <textarea class="form-control" id="test-description" 
                                          rows="3">${escapeHtml(test.description || "")}</textarea>
                            </div>
                            
                            <div class="card mb-3">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0">
                                        <i class="bi bi-eye-fill"></i> Настройки отображения
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" 
                                               id="test-is-active" value="1" 
                                               ${test.is_active == 1 ? "checked" : ""}>
                                        <label class="form-check-label" for="test-is-active">
                                            <strong>✅ Тест активен</strong>
                                        </label>
                                        <div class="form-text">
                                            Тест будет доступен пользователям для прохождения
                                        </div>
                                    </div>
                                    
                                    <div class="mb-0 form-check">
                                        <input type="checkbox" class="form-check-input" 
                                               id="test-is-learning" value="1" 
                                               ${test.is_learning_material == 1 ? "checked" : ""}>
                                        <label class="form-check-label" for="test-is-learning">
                                            <strong>📚 Добавить в обучающие материалы</strong>
                                        </label>
                                        <div class="form-text">
                                            Тест будет отображаться в разделе "Обучение" как учебный материал для самостоятельного изучения
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="alert alert-info mb-0">
                                <small>
                                    <i class="bi bi-info-circle-fill"></i> 
                                    <strong>ID теста:</strong> ${test.id} &nbsp;|&nbsp;
                                    <strong>Режим:</strong> ${test.mode === "exam" ? "Экзамен" : "Тренировка"}
                                </small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="bi bi-x-circle"></i> Отмена
                            </button>
                            <button type="button" class="btn btn-primary" id="save-test-settings-btn">
                                <i class="bi bi-check-circle"></i> Сохранить изменения
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
            
            const oldModal = document.getElementById("testSettingsModal");
            if (oldModal) oldModal.remove();
            
            document.body.insertAdjacentHTML("beforeend", modalHtml);
            
            const modal = new bootstrap.Modal(document.getElementById("testSettingsModal"));
            modal.show();
            
            document.getElementById("save-test-settings-btn").addEventListener("click", async function() {
                await saveTestSettings(currentTestId);
                modal.hide();
            });
            
        } catch (error) {
            console.error("Error opening test settings modal:", error);
            alert("Ошибка: " + error.message);
        }
    }
    
    async function saveTestSettings(currentTestId) {
        const title = document.getElementById("test-title").value.trim();
        const description = document.getElementById("test-description").value.trim();
        const isActive = document.getElementById("test-is-active").checked ? 1 : 0;
        const isLearningMaterial = document.getElementById("test-is-learning").checked ? 1 : 0;
        
        if (!title) {
            alert("Название теста не может быть пустым");
            return;
        }
        
        try {
            const result = await apiCall("updateTestSettings", {
                test_id: currentTestId,
                title: title,
                description: description,
                is_active: isActive,
                is_learning_material: isLearningMaterial
            });
            
            if (result.success) {
                alert("✅ Настройки теста успешно сохранены!");
                
                const titleElement = document.querySelector("#test-info h2");
                if (titleElement) {
                    titleElement.textContent = title;
                }
                
                const descElement = document.querySelector("#test-info .card-body p");
                if (descElement) {
                    // XSS Protection: санитизация HTML в описании
                    descElement.innerHTML = sanitizeHtml(description.replace(/\n/g, "<br>"));
                }
                
            } else {
                throw new Error(result.message || "Не удалось сохранить настройки");
            }
        } catch (error) {
            console.error("Error saving test settings:", error);
            alert("Ошибка сохранения: " + error.message);
        }
    }
    
    function escapeHtml(text) {
        const div = document.createElement("div");
        div.textContent = text;
        return div.innerHTML;
    }

    // XSS Protection: Санитизация HTML с помощью DOMPurify
    // Разрешаем безопасные теги для форматирования текста из Quill.js
    function sanitizeHtml(html) {
        if (!html) return '';
        if (typeof DOMPurify === 'undefined') {
            console.warn('DOMPurify not loaded, using escapeHtml fallback');
            return escapeHtml(html);
        }

        // Настройки санитизации: разрешаем только безопасные теги
        const config = {
            ALLOWED_TAGS: ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'ol', 'ul', 'li', 'a', 'img', 'h1', 'h2', 'h3', 'blockquote', 'code', 'pre', 'div', 'span'],
            ALLOWED_ATTR: ['href', 'src', 'alt', 'title', 'class', 'style'],
            ALLOWED_URI_REGEXP: /^(?:(?:(?:f|ht)tps?|mailto|tel|callto|sms|cid|xmpp):|[^a-z]|[a-z+.\-]+(?:[^a-z+.\-:]|$))/i,
            KEEP_CONTENT: true,
            RETURN_DOM: false,
            RETURN_DOM_FRAGMENT: false,
            FORCE_BODY: false
        };

        return DOMPurify.sanitize(html, config);
    }

    // Инициализация Quill редактора для объяснений


        function initQuillEditorSimple(textareaId) {
            const textarea = document.getElementById(textareaId);
            if (!textarea) return null;
            
            const editorDiv = document.getElementById(textareaId + '-editor');
            if (!editorDiv) return null;
            
            const initialValue = textarea.value;
            
            const quill = new Quill('#' + editorDiv.id, {
                theme: 'snow',
                modules: {
                    toolbar: [
                        ['bold', 'italic', 'underline'],
                        ['link', 'code-block'],
                        [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                        ['clean']
                    ]
                },
                placeholder: 'Введите текст...'
            });
            
            quill.root.innerHTML = initialValue;
            
            quill.on('text-change', function() {
                textarea.value = quill.root.innerHTML;
            });
            
            // Добавляем перенос строки после вставки
            quill.root.addEventListener('paste', function(e) {
                setTimeout(() => {
                    const selection = quill.getSelection();
                    if (selection) {
                        quill.insertText(selection.index, '\n');
                    }
                }, 10);
            });
            
            return quill;
        }
        
        function setupImageUpload(prefix, quill) {
            const uploadBtn = document.getElementById(`upload-${prefix}-btn`);
            const fileInput = document.getElementById(`${prefix}-input`);
            const preview = document.getElementById(`${prefix}-preview`);
            const urlInput = document.getElementById(`${prefix}-url`);
            
            if (!uploadBtn || !fileInput) return;
            
            uploadBtn.onclick = () => fileInput.click();
            
            fileInput.onchange = async () => {
                const file = fileInput.files[0];
                if (!file) return;
                
                if (file.size > 5 * 1024 * 1024) {
                    alert('Файл слишком большой (макс. 5MB)');
                    return;
                }
                
                const formData = new FormData();
                formData.append('image', file);

                // Добавляем CSRF токен
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                if (csrfToken) {
                    formData.append('csrf_token', csrfToken);
                }

                uploadBtn.disabled = true;
                uploadBtn.textContent = 'Загрузка...';

                try {
                    const response = await fetch('/assets/components/testsystem/ajax/upload-image.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        urlInput.value = result.url;
                        preview.innerHTML = `
                            <img src="${result.url}" class="img-fluid" style="max-height: 200px;">
                            <button type="button" class="btn btn-sm btn-danger mt-2" onclick="removeImage('${prefix}')">Удалить</button>
                        `;
                    } else {
                        alert('Ошибка загрузки: ' + result.message);
                    }
                } catch (error) {
                    alert('Ошибка: ' + error.message);
                } finally {
                    uploadBtn.disabled = false;
                    uploadBtn.textContent = '📷 Загрузить изображение';
                }
            };
        }
        
        window.removeImage = function(prefix) {
            const preview = document.getElementById(`${prefix}-preview`);
            const urlInput = document.getElementById(`${prefix}-url`);
            
            if (preview) {
                preview.innerHTML = '<small class="text-muted">Изображение не загружено</small>';
            }
            if (urlInput) {
                urlInput.value = '';
            }
        };


    
    function selectLocalImage(quill) {
        const input = document.createElement('input');
        input.setAttribute('type', 'file');
        input.setAttribute('accept', 'image/png, image/gif, image/jpeg, image/jpg, image/webp');
        input.click();
        
        input.onchange = async () => {
            const file = input.files[0];
            
            if (!file) return;
            
            if (file.size > 5 * 1024 * 1024) {
                alert('Файл слишком большой (макс. 5MB)');
                return;
            }
            
            const formData = new FormData();
            formData.append('image', file);

            // Добавляем CSRF токен
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            if (csrfToken) {
                formData.append('csrf_token', csrfToken);
            }

            try {
                const range = quill.getSelection(true);
                quill.insertText(range.index, 'Загрузка...', 'user');

                const response = await fetch('/assets/components/testsystem/ajax/upload-image.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                quill.deleteText(range.index, 11);
                
                if (result.success) {
                    quill.insertEmbed(range.index, 'image', result.url, 'user');
                    quill.setSelection(range.index + 1);
                } else {
                    alert('Ошибка загрузки: ' + result.message);
                }
            } catch (error) {
                alert('Ошибка: ' + error.message);
            }
        };
    }



    window.loadNextQuestion = loadNextQuestion;    
    window.openTestSettingsModal = openTestSettingsModal; // Делаем функцию доступной из HTML
    



    // Показать все вопросы списком для редактирования



    async function showAllQuestionsView(filterStatus = 'all') {
        console.log("=== showAllQuestionsView START ===");
        currentQuestionsFilter = filterStatus;

        try {
            if (!canEdit) {
                await checkEditRights();
            }

            const result = await apiCall("getAllQuestions", { test_id: testId });
            
            if (!result.success) {
                alert("Ошибка загрузки вопросов: " + result.message);
                return;
            }
            
            allQuestions = result.data;
            
            // Скрываем основной интерфейс
            const testInfo = document.getElementById("test-info");
            const questionContainer = document.getElementById("question-container");
            const resultsContainer = document.getElementById("results-container");
            
            if (testInfo) testInfo.style.display = "none";
            if (questionContainer) questionContainer.style.display = "none";
            if (resultsContainer) resultsContainer.style.display = "none";
            
            // Создаем контейнер для списка вопросов
            let listContainer = document.getElementById("questions-list-container");
            
            if (!listContainer) {
                listContainer = document.createElement("div");
                listContainer.id = "questions-list-container";
                container.appendChild(listContainer);
            }
    
            // Фильтруем вопросы
            let filteredQuestions = allQuestions;
            if (filterStatus === 'published') {
                filteredQuestions = allQuestions.filter(q => parseInt(q.published) === 1);
            } else if (filterStatus === 'unpublished') {
                filteredQuestions = allQuestions.filter(q => parseInt(q.published) === 0);
            } else if (filterStatus === 'learning') {
                filteredQuestions = allQuestions.filter(q => parseInt(q.is_learning) === 1);
            }
            
            // Подсчет статистики
            const totalCount = allQuestions.length;
            const publishedCount = allQuestions.filter(q => parseInt(q.published) === 1).length;
            const unpublishedCount = totalCount - publishedCount;
            const learningCount = allQuestions.filter(q => parseInt(q.is_learning) === 1).length;
            
            // Формируем HTML списка вопросов
            let html = '<div class="card mb-4">';
            html += '<div class="card-header bg-light">';
            
            const sharedRenderer = window.QuestionListShared && typeof window.QuestionListShared.requireSharedApi === 'function'
                ? window.QuestionListShared.requireSharedApi(['buildHeaderHtml', 'buildFiltersHtml', 'buildBulkPanelHtml', 'buildQuestionPreview', 'toggleSelectAllCheckboxes'])
                : null;
            if (!sharedRenderer) {
                throw new Error('QuestionListShared is not available');
            }

            // ШАПКА
            let headerControlsHtml = '';
            if (canEdit) {
                headerControlsHtml += '<button class="btn btn-success btn-sm" onclick="openAddQuestionModal()">';
                headerControlsHtml += '<i class="bi bi-plus-circle-fill"></i> Добавить вопрос';
                headerControlsHtml += '</button>';
                if (importQuestionsUrl) {
                    headerControlsHtml += '<a class="btn btn-outline-success btn-sm" href="' + importQuestionsUrl + '">';
                    headerControlsHtml += '<i class="bi bi-file-earmark-arrow-down"></i> Импорт вопросов';
                    headerControlsHtml += '</a>';
                }
            }
            if (canDelete) {
                headerControlsHtml += '<button class="btn btn-outline-danger btn-sm" onclick="deleteAllQuestionsInTest()">';
                headerControlsHtml += '<i class="bi bi-trash3"></i> Удалить всё';
                headerControlsHtml += '</button>';
            }

            // Контролы запуска теста
            headerControlsHtml += '<div class="d-flex gap-1 align-items-center">';
            headerControlsHtml += '<input type="number" id="questions-count-header" class="form-control form-control-sm" style="width: 70px;" value="20" min="1" placeholder="20">';
            headerControlsHtml += '<button class="btn btn-outline-secondary btn-sm" style="white-space: nowrap;" onclick="document.getElementById(\'questions-count-header\').value = ' + totalCount + '">Все</button>';
            headerControlsHtml += '<button class="btn btn-primary btn-sm" style="white-space: nowrap;" onclick="startTestFromQuestions(\'training\')"><i class="bi bi-play-circle"></i> Тренировка</button>';
            headerControlsHtml += '<button class="btn btn-danger btn-sm" style="white-space: nowrap;" onclick="startTestFromQuestions(\'exam\')"><i class="bi bi-clipboard-check"></i> Экзамен</button>';
            headerControlsHtml += '</div>';

            html += sharedRenderer.buildHeaderHtml({
                title: 'Список вопросов теста',
                controlsHtml: headerControlsHtml,
                titleClass: 'mb-3',
                controlsClass: 'd-flex flex-column flex-sm-row gap-2 w-100 align-items-stretch align-items-sm-center mb-3'
            });
            
            // ФИЛЬТРЫ
            const filtersHtml = sharedRenderer.buildFiltersHtml({
                total: totalCount,
                published: publishedCount,
                unpublished: unpublishedCount,
                learning: learningCount
            }, filterStatus);
            html += filtersHtml;

            if (canEdit) {
                let bulkActionsHtml = '';
                bulkActionsHtml += '<button class="btn btn-sm btn-outline-primary" onclick="selectAllVisibleQuestions()"><i class="bi bi-check2-square"></i> Выбрать все</button>';
                bulkActionsHtml += '<button class="btn btn-sm btn-outline-success" onclick="bulkPublishQuestions()"><i class="bi bi-eye"></i> Опубликовать</button>';
                bulkActionsHtml += '<button class="btn btn-sm btn-outline-warning" onclick="bulkUnpublishQuestions()"><i class="bi bi-eye-slash"></i> Снять с публикации</button>';
                if (canDelete) {
                    bulkActionsHtml += '<button class="btn btn-sm btn-outline-danger" onclick="bulkDeleteQuestions()"><i class="bi bi-trash"></i> Удалить</button>';
                }
                bulkActionsHtml += '<button class="btn btn-sm btn-outline-secondary" onclick="clearBulkSelection()"><i class="bi bi-x-circle"></i> Сбросить</button>';

                html += sharedRenderer.buildBulkPanelHtml({ selectedCount: 0, actionsHtml: bulkActionsHtml });
            }
            html += '</div>';
            
            html += '<div class="card-body p-2 p-md-3">';
            
            if (filteredQuestions.length === 0) {
                html += '<div class="alert alert-warning">Нет вопросов для отображения</div>';
            } else {
                html += '<div class="list-group">';
                
                filteredQuestions.forEach((question, index) => {
                    const preview = window.QuestionListShared.buildQuestionPreview(question);
                    const isPublished = parseInt(question.published) === 1;
                    const isLearning = parseInt(question.is_learning) === 1;
                    const hasExplanation = preview.hasExplanation ? '✓' : '—';
                    
                    const realIndex = allQuestions.findIndex(q => q.id === question.id);
                    const publishClass = isPublished ? '' : 'list-group-item-secondary';
                    
                    // Подготовка данных для модального окна
                    const questionData = htmlspecialchars(JSON.stringify({
                        id: question.id,
                        text: question.question_text,
                        image: question.question_image,
                        explanation: question.explanation,
                        explanation_image: question.explanation_image,
                        type: question.question_type,
                        type_name: question.question_type === 'single' ? 'Один правильный ответ' : 'Несколько правильных ответов'
                    }));
                    
                    html += `<div class="list-group-item ${publishClass} question-list-item-minimal">`;
                    if (canEdit) {
                        html += '<div class="mb-2">';
                        html += `<label class="d-inline-flex align-items-center gap-2 small text-muted">`;
                        html += `<input type="checkbox" class="question-select-checkbox" value="${question.id}" onchange="updateBulkActionsState()">`;
                        html += '<span>Выбрать</span></label></div>';
                    }
                    html += '<div class="d-flex flex-column gap-2">';
                    
                    // КЛИКАБЕЛЬНЫЙ ТЕКСТ ВОПРОСА
                    html += `<div class="question-text-clickable-list" data-question='${questionData}' onclick="openQuestionViewModal(this)">`;
                    html += '<div class="d-flex align-items-start gap-2">';
                    html += `<span class="badge bg-primary flex-shrink-0" style="min-width: 36px; font-size: 0.9rem;">${realIndex + 1}</span>`;
                    html += '<div class="flex-grow-1">';
                    html += `<p class="mb-1 fw-bold text-dark">${escapeHtml(preview.shortText)}${preview.isTrimmed ? '...' : ''}</p>`;

                    html += `<small class="text-muted">`;
                    html += `${preview.questionTypeLabel} • `;
                    html += `Объяснение: ${hasExplanation}`;
                    html += `</small></div></div></div>`;

                    // КОМПАКТНЫЕ КНОПКИ УПРАВЛЕНИЯ
                    html += '<div class="question-actions-minimal">';
                    
                    // Иконки-кнопки
                    const publishIcon = isPublished ? 'eye-slash' : 'eye';
                    const publishTitle = isPublished ? 'Снять с публикации' : 'Опубликовать';
                    const publishColor = isPublished ? 'warning' : 'success';
                    
                    if (canEdit) {
                        html += `<button class="btn btn-sm btn-outline-${publishColor}" onclick="event.stopPropagation(); togglePublishedFromList(${question.id}, ${isPublished ? 1 : 0})" title="${publishTitle}">`;
                        html += `<i class="bi bi-${publishIcon}"></i>`;
                        html += '</button>';
                    }
                    
                    const learningIcon = isLearning ? 'book-fill' : 'book';
                    const learningTitle = isLearning ? 'Убрать из обучения' : 'Добавить в обучение';
                    // ИЗМЕНЕНИЕ: используем btn-info вместо btn-outline-info когда вопрос в обучении
                    const learningBtnClass = isLearning ? 'btn-info' : 'btn-outline-secondary';
                    
                    if (canEdit) {
                        html += `<button class="btn btn-sm ${learningBtnClass}" onclick="event.stopPropagation(); toggleLearningFromList(${question.id}, ${isLearning ? 1 : 0})" title="${learningTitle}">`;
                        html += `<i class="bi bi-${learningIcon}"></i>`;
                        html += '</button>';
                    }

                    if (canEdit) {
                        html += `<button class="btn btn-sm btn-outline-primary" onclick="event.stopPropagation(); editQuestionFromList(${question.id})" title="Редактировать">`;
                        html += '<i class="bi bi-pencil"></i>';
                        html += '</button>';
                    }

                    if (canDelete) {
                        html += `<button class="btn btn-sm btn-outline-danger" onclick="event.stopPropagation(); deleteQuestionFromList(${question.id})" title="Удалить">`;
                        html += '<i class="bi bi-trash"></i>';
                        html += '</button>';
                    }
                    
                    html += '</div></div></div>';
                });
                
                html += '</div>';
            }
            
            html += '</div></div>';
            
            // МОДАЛЬНОЕ ОКНО для просмотра вопроса
            html += `
            <div class="modal fade" id="questionViewModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title">
                                <i class="bi bi-question-circle-fill"></i> 
                                <span id="modal-view-question-type"></span>
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div id="modal-view-question-text" class="mb-4"></div>
                            <div id="modal-view-explanation" class="alert alert-info"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary" onclick="editQuestionFromModal()">
                                <i class="bi bi-pencil"></i> Редактировать
                            </button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                        </div>
                    </div>
                </div>
            </div>`;
            
            listContainer.innerHTML = html;
            listContainer.style.display = "block";
            
        } catch (error) {
            console.error("Error loading questions list:", error);
            alert("Ошибка: " + error.message);
        }
    }
    
    // Функция открытия модального окна просмотра вопроса
    let currentViewQuestionId = null;
    
    function openQuestionViewModal(element) {
        try {
            const questionData = JSON.parse(element.dataset.question);
            currentViewQuestionId = questionData.id;
            
            document.getElementById("modal-view-question-type").textContent = questionData.type_name;

            // XSS Protection: санитизация HTML в модальном окне
            let questionHtml = sanitizeHtml(questionData.text);
            if (questionData.image) {
                questionHtml += `<div class="mt-3"><img src="${escapeHtml(questionData.image)}" class="img-fluid"></div>`;
            }
            document.getElementById("modal-view-question-text").innerHTML = questionHtml;

            let explanationHtml = `<strong>Объяснение:</strong><br><div class="mt-2">${sanitizeHtml(questionData.explanation) || "Объяснение отсутствует"}</div>`;
            if (questionData.explanation_image) {
                explanationHtml += `<div class="mt-3"><img src="${escapeHtml(questionData.explanation_image)}" class="img-fluid"></div>`;
            }
            document.getElementById("modal-view-explanation").innerHTML = explanationHtml;
            
            const modal = new bootstrap.Modal(document.getElementById("questionViewModal"));
            modal.show();
        } catch (error) {
            console.error("Error opening question view modal:", error);
            alert("Ошибка загрузки вопроса");
        }
    }
    
    // Функция редактирования из модального окна
    function editQuestionFromModal() {
        const modal = bootstrap.Modal.getInstance(document.getElementById("questionViewModal"));
        if (modal) modal.hide();
        
        if (currentViewQuestionId) {
            editQuestionFromList(currentViewQuestionId);
        }
    }
    
    // Вспомогательная функция экранирования HTML для атрибутов
    function htmlspecialchars(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
    
    window.openQuestionViewModal = openQuestionViewModal;

    if (!window.__tsRunnerFilterDelegationBound) {
        document.addEventListener('click', function (e) {
            const filterBtn = e.target.closest('#questions-list-container .questions-filters-container button[data-filter]');
            if (!filterBtn) {
                return;
            }

            const filter = filterBtn.getAttribute('data-filter');
            if (!filter) {
                return;
            }

            e.preventDefault();
            showAllQuestionsView(filter);
        });
        window.__tsRunnerFilterDelegationBound = true;
    }
    window.editQuestionFromModal = editQuestionFromModal;
    
    

    // НОВАЯ ФУНКЦИЯ: Перезагрузка текущего вопроса в режиме тренинга
    async function reloadCurrentQuestion() {
        if (!currentSessionId || !currentQuestionId) {
            console.error("No active session or question");
            return;
        }
        
        try {
            // Загружаем данные вопроса
            const result = await apiCall("getQuestion", {
                question_id: currentQuestionId
            });
            
            if (!result.success) {
                throw new Error("Не удалось загрузить вопрос");
            }
            
            const question = result.data;
            
            // Загружаем варианты ответов и состояние ответа
            const answersResult = await apiCall("getQuestionAnswers", {
                question_id: currentQuestionId,
                session_id: currentSessionId
            });
            
            console.log("getQuestionAnswers result:", answersResult); // Отладка
            
            if (!answersResult.success) {
                throw new Error("Не удалось загрузить ответы");
            }
            
            // Отображаем вопрос
            displayQuestion(question, answersResult.data.answers);

            // ✅ Обновляем прогресс
            updateProgressBar();            
            
            // Проверяем, был ли уже дан ответ
            if (answersResult.data.user_answered && answersResult.data.feedback) {
                // ИСПРАВЛЕНИЕ: Проверяем наличие feedback и его структуру
                const feedback = answersResult.data.feedback;
                
                // Убедимся что все необходимые поля есть
                if (feedback.correct_answer_ids && feedback.user_answer_ids) {
                    showTrainingFeedback(feedback);
                } else {
                    console.error("Invalid feedback structure:", feedback);
                    // Показываем кнопку "Следующий вопрос" без feedback
                    const submitBtn = document.getElementById("submit-answer-btn");
                    const nextBtn = document.getElementById("next-question-btn");
                    
                    if (submitBtn) submitBtn.style.display = "none";
                    if (nextBtn) {
                        nextBtn.style.display = "inline-block";
                        nextBtn.disabled = false;
                    }
                }
            } else {
                // Вопрос еще не отвечен - настраиваем обработчики
                setupAnswerListeners();
            }
            
        } catch (error) {
            console.error("Reload current question error:", error);
            alert("Ошибка перезагрузки вопроса: " + error.message);
        }
    }

    // Переключить статус публикации вопроса из списка
    async function togglePublishedFromList(questionId, currentStatus) {
        const action = currentStatus ? 'снят с публикации' : 'опубликован';
        
        try {
            const result = await apiCall("togglePublished", {
                question_id: questionId
            });
            
            if (result.success) {
                // Показываем уведомление
                showNotification(`✅ Вопрос ${action}`, 'success');
                
                // Обновляем список с текущим фильтром
                const activeFilter = document.querySelector('#questions-list-container .btn-primary, #questions-list-container .btn-success, #questions-list-container .btn-secondary');
                let currentFilter = 'all';
                if (activeFilter) {
                    if (activeFilter.textContent.includes('Опубликованные')) {
                        currentFilter = 'published';
                    } else if (activeFilter.textContent.includes('Неопубликованные')) {
                        currentFilter = 'unpublished';
                    }
                }
                
                showAllQuestionsView(currentFilter);
            } else {
                alert("Ошибка: " + result.message);
            }
        } catch (error) {
            console.error("Toggle published error:", error);
            alert("Ошибка: " + error.message);
        }
    }
    

    // Переключить статус "В обучении" из списка
    async function toggleLearningFromList(questionId, currentStatus) {
        const action = currentStatus ? 'убран из обучения' : 'добавлен в обучение';
        
        try {
            const result = await apiCall("toggleLearning", {
                question_id: questionId
            });
            
            if (result.success) {
                showNotification(`✅ Вопрос ${action}`, 'success');
                
                // Обновляем список с текущим фильтром
                const activeFilter = document.querySelector('#questions-list-container .btn-primary, #questions-list-container .btn-success, #questions-list-container .btn-secondary, #questions-list-container .btn-info');
                let currentFilter = 'all';
                if (activeFilter) {
                    const btnText = activeFilter.textContent;
                    if (btnText.includes('Опубликованные')) {
                        currentFilter = 'published';
                    } else if (btnText.includes('Неопубликованные')) {
                        currentFilter = 'unpublished';
                    } else if (btnText.includes('В обучении')) {
                        currentFilter = 'learning';
                    }
                }
                
                showAllQuestionsView(currentFilter);
            } else {
                alert("Ошибка: " + result.message);
            }
        } catch (error) {
            console.error("Toggle learning error:", error);
            alert("Ошибка: " + error.message);
        }
    }
    
    // Делаем функцию глобальной
    window.toggleLearningFromList = toggleLearningFromList;
    
    
    // Функция показа уведомлений
    function showNotification(message, type = 'success') {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3`;
        notification.style.zIndex = '9999';
        notification.style.minWidth = '300px';
        notification.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(notification);
        
        // Удаляем уведомление через 3 секунды
        setTimeout(() => {
            notification.remove();
        }, 3000);
    }
    
    // Делаем функции глобальными
    window.showNotification = showNotification;

    // Делаем функцию глобальной
    window.togglePublishedFromList = togglePublishedFromList;    
    
    // Скрыть список вопросов и вернуться к тесту
    function hideAllQuestionsView() {
        const listContainer = document.getElementById("questions-list-container");
        const testInfo = document.getElementById("test-info");

        if (listContainer) listContainer.style.display = "none";
        if (testInfo) testInfo.style.display = "block";
    }

    // Запуск теста из списка вопросов
    async function startTestFromQuestions(mode) {
        console.log("Starting test from questions view, mode:", mode);

        const questionsCountInput = document.getElementById('questions-count-header');
        const questionsCount = questionsCountInput ? parseInt(questionsCountInput.value) || 20 : 20;

        // Показываем индикатор загрузки
        const overlay = document.createElement('div');
        overlay.id = 'loading-overlay';
        overlay.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); display: flex; align-items: center; justify-content: center; z-index: 9999;';
        overlay.innerHTML = '<div style="background: white; padding: 30px; border-radius: 8px; text-align: center;"><div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;"></div><div style="margin-top: 15px; font-size: 1.1em;">Запуск теста...</div></div>';
        document.body.appendChild(overlay);

        try {
            const requestData = {
                test_id: parseInt(testId),
                mode: mode
            };

            if (mode === 'training' && questionsCount) {
                requestData.questions_count = questionsCount;
            }

            const result = await apiCall('startSession', requestData);

            if (result.success) {
                // Редирект на страницу теста с sessionId
                window.location.href = '/test-run?sessionId=' + result.data.session_id;
            } else {
                document.body.removeChild(overlay);
                alert('Ошибка при запуске теста: ' + (result.message || 'Неизвестная ошибка'));
            }
        } catch (error) {
            document.body.removeChild(overlay);
            alert('Ошибка сети: ' + error.message);
        }
    }
    window.startTestFromQuestions = startTestFromQuestions;

    // Редактировать вопрос из списка
    async function editQuestionFromList(questionId) {
        try {
            const result = await apiCall("getQuestion", { question_id: questionId });
            
            if (!result.success) {
                throw new Error(result.message || "Не удалось загрузить вопрос");
            }
            
            openEditModal(result.data, questionId, false);
            
            // ИСПРАВЛЕНИЕ: После закрытия модального окна обновляем список
            const modalElement = document.getElementById("editQuestionModal");
            if (modalElement) {
                modalElement.addEventListener('hidden.bs.modal', async function () {
                    // Определяем текущий активный фильтр
                    const activeBtn = document.querySelector('#questions-list-container .btn-primary, #questions-list-container .btn-success');
                    let currentFilter = 'all';
                    
                    if (activeBtn) {
                        const btnText = activeBtn.textContent;
                        if (btnText.includes('Опубликованные')) {
                            currentFilter = 'published';
                        } else if (btnText.includes('Неопубликованные')) {
                            currentFilter = 'unpublished';
                        }
                    }
                    
                    // Перезагружаем список с текущим фильтром
                    await showAllQuestionsView(currentFilter);
                }, { once: true });
            }
        } catch (error) {
            console.error("Edit question error:", error);
            alert("Ошибка: " + error.message);
        }
    }


    // Удалить вопрос из списка
    async function deleteQuestionFromList(questionId) {
        if (!canDelete) {
            alert("Недостаточно прав для удаления вопросов");
            return;
        }

        if (!confirm("Вы уверены, что хотите удалить этот вопрос?")) {
            return;
        }
        
        try {
            const result = await apiCall("deleteQuestion", {
                question_id: questionId,
                session_id: 0
            });
            
            if (result.success) {
                alert("✅ Вопрос удален");
                showAllQuestionsView(); // Обновляем список
            } else {
                alert("Ошибка удаления: " + result.message);
            }
        } catch (error) {
            console.error("Delete error:", error);
            alert("Ошибка: " + error.message);
        }
    }

    function getSelectedQuestionIds() {
        const checkboxes = document.querySelectorAll('.question-select-checkbox:checked');
        return Array.from(checkboxes).map(cb => parseInt(cb.value, 10)).filter(Boolean);
    }

    var questionListActions = null;

    function getQuestionListActions() {
        if (questionListActions) {
            return questionListActions;
        }

        const sharedRenderer = window.QuestionListShared && typeof window.QuestionListShared.requireSharedApi === 'function'
            ? window.QuestionListShared.requireSharedApi(['createQuestionListActions', 'toggleSelectAllCheckboxes'])
            : null;
        if (!sharedRenderer) {
            throw new Error('QuestionListShared actions are not available');
        }

        questionListActions = sharedRenderer.createQuestionListActions({
            getSelectedIds: getSelectedQuestionIds,
            noSelectionMessage: 'Выберите хотя бы один вопрос',
            onSelectAll: function () {
                sharedRenderer.toggleSelectAllCheckboxes('.question-select-checkbox');
                updateBulkActionsState();
            },
            onClearSelection: function () {
                document.querySelectorAll('.question-select-checkbox').forEach(cb => {
                    cb.checked = false;
                });
                updateBulkActionsState();
            },
            onPublish: function (selectedIds) {
                return bulkSetPublished(1, selectedIds);
            },
            onUnpublish: function (selectedIds) {
                return bulkSetPublished(0, selectedIds);
            },
            onDelete: function (selectedIds) {
                return bulkDeleteByIds(selectedIds);
            }
        });

        return questionListActions;
    }

    let questionListActions = null;

    function getQuestionListActions() {
        if (questionListActions) {
            return questionListActions;
        }

        const sharedRenderer = window.QuestionListShared && typeof window.QuestionListShared.requireSharedApi === 'function'
            ? window.QuestionListShared.requireSharedApi(['createQuestionListActions', 'toggleSelectAllCheckboxes'])
            : null;
        if (!sharedRenderer) {
            throw new Error('QuestionListShared actions are not available');
        }

        questionListActions = sharedRenderer.createQuestionListActions({
            getSelectedIds: getSelectedQuestionIds,
            noSelectionMessage: 'Выберите хотя бы один вопрос',
            onSelectAll: function () {
                sharedRenderer.toggleSelectAllCheckboxes('.question-select-checkbox');
                updateBulkActionsState();
            },
            onClearSelection: function () {
                document.querySelectorAll('.question-select-checkbox').forEach(cb => {
                    cb.checked = false;
                });
                updateBulkActionsState();
            },
            onPublish: function (selectedIds) {
                return bulkSetPublished(1, selectedIds);
            },
            onUnpublish: function (selectedIds) {
                return bulkSetPublished(0, selectedIds);
            },
            onDelete: function (selectedIds) {
                return bulkDeleteByIds(selectedIds);
            }
        });

        return questionListActions;
    }

    let questionListActions = null;

    function getQuestionListActions() {
        if (questionListActions) {
            return questionListActions;
        }

        const sharedRenderer = window.QuestionListShared && typeof window.QuestionListShared.requireSharedApi === 'function'
            ? window.QuestionListShared.requireSharedApi(['createQuestionListActions', 'toggleSelectAllCheckboxes'])
            : null;
        if (!sharedRenderer) {
            throw new Error('QuestionListShared actions are not available');
        }

        questionListActions = sharedRenderer.createQuestionListActions({
            getSelectedIds: getSelectedQuestionIds,
            noSelectionMessage: 'Выберите хотя бы один вопрос',
            onSelectAll: function () {
                sharedRenderer.toggleSelectAllCheckboxes('.question-select-checkbox');
                updateBulkActionsState();
            },
            onClearSelection: function () {
                document.querySelectorAll('.question-select-checkbox').forEach(cb => {
                    cb.checked = false;
                });
                updateBulkActionsState();
            },
            onPublish: function (selectedIds) {
                return bulkSetPublished(1, selectedIds);
            },
            onUnpublish: function (selectedIds) {
                return bulkSetPublished(0, selectedIds);
            },
            onDelete: function (selectedIds) {
                return bulkDeleteByIds(selectedIds);
            }
        });

        return questionListActions;
    }

    function updateBulkActionsState() {
        const selectedIds = getSelectedQuestionIds();
        const panel = document.getElementById('bulk-actions-panel');
        const countEl = document.getElementById('selected-questions-count');

        if (countEl) {
            countEl.textContent = String(selectedIds.length);
        }

        if (panel) {
            if (selectedIds.length > 0) {
                panel.classList.remove('d-none');
            } else {
                panel.classList.add('d-none');
            }
        }
    }

    function selectAllVisibleQuestions() {
        try {
            getQuestionListActions().selectAll();
        } catch (error) {
            alert('Ошибка: ' + error.message);
        }
    }

    function clearBulkSelection() {
        try {
            getQuestionListActions().clearSelection();
        } catch (error) {
            alert('Ошибка: ' + error.message);
        }
    }

    async function bulkSetPublished(targetPublished, selectedIdsInput) {
        if (!canEdit) {
            alert("Недостаточно прав для изменения публикации");
            return;
        }

        const selectedIds = selectedIdsInput || getSelectedQuestionIds();
        if (selectedIds.length === 0) {
            alert('Выберите хотя бы один вопрос');
            return;
        }

        const selectedSet = new Set(selectedIds);
        const questionsToChange = allQuestions.filter(q => selectedSet.has(parseInt(q.id, 10)) && parseInt(q.published, 10) !== targetPublished);

        if (questionsToChange.length === 0) {
            alert(targetPublished === 1 ? 'Все выбранные вопросы уже опубликованы' : 'Все выбранные вопросы уже сняты с публикации');
            return;
        }

        for (const q of questionsToChange) {
            const res = await apiCall('togglePublished', { question_id: q.id });
            if (!res.success) {
                throw new Error(res.message || 'Ошибка при массовом изменении публикации');
            }
        }

        alert(`✅ Обновлено вопросов: ${questionsToChange.length}`);
        await showAllQuestionsView(currentQuestionsFilter);
    }

    async function bulkPublishQuestions() {
        try {
            await getQuestionListActions().publishSelected();
        } catch (error) {
            alert('Ошибка: ' + error.message);
        }
    }

    async function bulkUnpublishQuestions() {
        try {
            await getQuestionListActions().unpublishSelected();
        } catch (error) {
            alert('Ошибка: ' + error.message);
        }
    }

    async function bulkDeleteByIds(selectedIds) {
        if (!canDelete) {
            alert("Недостаточно прав для удаления вопросов");
            return;
        }

        if (!selectedIds.length) {
            alert('Выберите хотя бы один вопрос');
            return;
        }

        if (!confirm(`Удалить выбранные вопросы (${selectedIds.length} шт.)?`)) {
            return;
        }

        for (const questionId of selectedIds) {
            const result = await apiCall('deleteQuestion', {
                question_id: questionId,
                session_id: 0
            });

            if (!result.success) {
                throw new Error(result.message || `Ошибка удаления вопроса ID ${questionId}`);
            }
        }

        alert(`✅ Удалено вопросов: ${selectedIds.length}`);
        await showAllQuestionsView(currentQuestionsFilter);
    }

    async function bulkDeleteQuestions() {
        try {
            await getQuestionListActions().deleteSelected();
        } catch (error) {
            alert('Ошибка: ' + error.message);
        }
    }

    async function bulkDeleteQuestions() {
        try {
            await getQuestionListActions().deleteSelected();
        } catch (error) {
            alert('Ошибка: ' + error.message);
        }
    }

    async function bulkDeleteQuestions() {
        try {
            await getQuestionListActions().deleteSelected();
        } catch (error) {
            alert('Ошибка: ' + error.message);
        }
    }

    async function deleteAllQuestionsInTest() {
        if (!canDelete) {
            alert("Недостаточно прав для удаления вопросов");
            return;
        }

        if (!allQuestions || allQuestions.length === 0) {
            alert('В тесте нет вопросов для удаления');
            return;
        }

        if (!confirm(`Удалить ВСЕ вопросы в тесте (${allQuestions.length} шт.)? Это действие нельзя отменить.`)) {
            return;
        }

        for (const q of allQuestions) {
            const result = await apiCall('deleteQuestion', {
                question_id: q.id,
                session_id: 0
            });

            if (!result.success) {
                throw new Error(result.message || `Ошибка удаления вопроса ID ${q.id}`);
            }
        }

        alert('✅ Все вопросы в тесте удалены');
        await showAllQuestionsView(currentQuestionsFilter);
    }

    async function editLearningQuestion(questionId) {
        try {
            const result = await apiCall("getQuestion", {
                question_id: questionId
            });
            
            if (!result.success) {
                throw new Error(result.message || "Не удалось загрузить вопрос");
            }
            
            const question = result.data;
            openEditModal(question, questionId, true);
        } catch (error) {
            console.error("Edit question error:", error);
            alert("Ошибка: " + error.message);
        }
    }
    
    // ДОБАВЬТЕ ЭТУ СТРОКУ СРАЗУ ПОСЛЕ ФУНКЦИИ:
    window.editLearningQuestion = editLearningQuestion;
    
    
    // Вспомогательная функция для удаления HTML тегов
    function stripHtml(html) {
        const tmp = document.createElement("div");
        tmp.innerHTML = html;
        return tmp.textContent || tmp.innerText || "";
    }
    
    // Делаем функции глобальными
    window.showAllQuestionsView = showAllQuestionsView;
    window.hideAllQuestionsView = hideAllQuestionsView;
    window.editQuestionFromList = editQuestionFromList;
    window.deleteQuestionFromList = deleteQuestionFromList;
    window.updateBulkActionsState = updateBulkActionsState;
    window.selectAllVisibleQuestions = selectAllVisibleQuestions;
    window.clearBulkSelection = clearBulkSelection;
    window.bulkPublishQuestions = bulkPublishQuestions;
    window.bulkUnpublishQuestions = bulkUnpublishQuestions;
    window.bulkDeleteQuestions = bulkDeleteQuestions;
    window.deleteAllQuestionsInTest = deleteAllQuestionsInTest;
    
    function updateProgressBar() {
        // Для основного теста
        const progressBar = document.getElementById("test-progress-bar");
        if (progressBar && totalQuestions > 0) {
            const percentage = Math.round((currentQuestionNumber / totalQuestions) * 100);
            progressBar.style.width = percentage + '%';
            progressBar.setAttribute('aria-valuenow', percentage);
        }
        
        // Для режима обучения
        const learningProgressBar = document.getElementById("learning-progress-bar");
        if (learningProgressBar && allQuestions.length > 0) {
            const percentage = Math.round(((currentIndex + 1) / allQuestions.length) * 100);
            learningProgressBar.style.width = percentage + '%';
            learningProgressBar.setAttribute('aria-valuenow', percentage);
        }
    }
    // Функция плавной прокрутки к вопросу
    function scrollToQuestion() {
        const questionContainer = document.getElementById("question-container");
        if (questionContainer) {
            questionContainer.scrollIntoView({
                behavior: "smooth",
                block: "start"
            });
        }
    }

    // Обработчик кнопки "Начать сначала"

    // Обработчик кнопки "Начать сначала"
    function setupRestartButton() {
        const restartBtn = document.getElementById("restart-test-btn");
        
        if (restartBtn) {
            const newRestartBtn = restartBtn.cloneNode(true);
            restartBtn.parentNode.replaceChild(newRestartBtn, restartBtn);
            
            newRestartBtn.addEventListener("click", function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                if (confirm("Вы уверены, что хотите начать тест сначала?\n\nВесь прогресс будет потерян.")) {
                    currentSessionId = null;
                    currentQuestionId = null;
                    currentQuestionNumber = 0;
                    window.location.reload();
                }
                
                return false;
            });
        }
    }

    // ============================================
    // УПРАВЛЕНИЕ ДОСТУПОМ К ПРИВАТНЫМ ТЕСТАМ
    // ============================================
    
    async function openAccessManagementModal(testId) {
        try {
            // Загружаем текущие разрешения
            const result = await apiCall('getTestPermissions', {
                test_id: testId
            });
            
            if (!result.success) {
                throw new Error(result.message);
            }
            
            const modalHtml = `
                <div class="modal fade" id="accessManagementModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header bg-primary text-white">
                                <h5 class="modal-title"><i class="bi bi-people-fill"></i> Управление доступом</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <!-- Поиск пользователей -->
                                <div class="mb-4">
                                    <h6>Добавить пользователя</h6>
                                    <div class="input-group mb-2">
                                        <input type="text" class="form-control" id="user-search-input" 
                                               placeholder="Поиск по имени, email или username...">
                                        <button class="btn btn-outline-primary" onclick="searchUsersForAccess(${testId})">
                                            <i class="bi bi-search"></i> Найти
                                        </button>
                                    </div>
                                    <div id="search-results"></div>
                                </div>
                                
                                <hr>
                                
                                <!-- Список пользователей с доступом -->
                                <div>
                                    <h6>Пользователи с доступом (${result.data.permissions.length})</h6>
                                    <div id="permissions-list" class="list-group">
                                        ${renderPermissionsList(result.data.permissions, testId)}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            const oldModal = document.getElementById('accessManagementModal');
            if (oldModal) oldModal.remove();
            
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            const modal = new bootstrap.Modal(document.getElementById('accessManagementModal'));
            modal.show();
            
        } catch (error) {
            console.error('Error opening access modal:', error);
            alert('Ошибка: ' + error.message);
        }
    }
    
    function renderPermissionsList(permissions, testId) {
        if (permissions.length === 0) {
            return '<div class="alert alert-info">Никому не предоставлен доступ</div>';
        }
        
        let html = '';
        
        permissions.forEach(perm => {
            const userDisplay = perm.fullname || perm.username;
            const email = perm.email ? `(${perm.email})` : '';
            const canEditBadge = perm.can_edit 
                ? '<span class="badge bg-warning">Может редактировать</span>' 
                : '<span class="badge bg-secondary">Только просмотр</span>';
            
            html += `
                <div class="list-group-item">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong>${escapeHtml(userDisplay)}</strong> ${email}
                            <br>
                            <small class="text-muted">
                                Доступ предоставлен: ${new Date(perm.granted_at).toLocaleDateString('ru-RU')}
                            </small>
                            <br>
                            ${canEditBadge}
                        </div>
                        <div class="btn-group">
                            <button class="btn btn-sm btn-outline-warning" 
                                    onclick="toggleEditPermission(${testId}, ${perm.user_id}, ${perm.can_edit ? 0 : 1})"
                                    title="${perm.can_edit ? 'Убрать право редактирования' : 'Дать право редактирования'}">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" 
                                    onclick="revokeAccessFromTest(${testId}, ${perm.user_id})">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
        });
        
        return html;
    }
    
    async function searchUsersForAccess(testId) {
        const query = document.getElementById('user-search-input').value.trim();
        
        if (query.length < 2) {
            alert('Введите минимум 2 символа для поиска');
            return;
        }
        
        try {
            const result = await apiCall('searchUsers', {
                query: query,
                test_id: testId
            });
            
            if (result.success) {
                renderSearchResults(result.data, testId);
            } else {
                alert('Ошибка поиска: ' + result.message);
            }
        } catch (error) {
            alert('Ошибка: ' + error.message);
        }
    }
    
    function renderSearchResults(users, testId) {
        const container = document.getElementById('search-results');
        
        if (users.length === 0) {
            container.innerHTML = '<div class="alert alert-warning">Пользователи не найдены</div>';
            return;
        }
        
        let html = '<div class="list-group">';
        
        users.forEach(user => {
            const userDisplay = user.fullname || user.username;
            const email = user.email ? `(${user.email})` : '';
            const hasAccess = user.has_access;
            
            if (hasAccess) {
                html += `
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                ${escapeHtml(userDisplay)} ${email}
                                <span class="badge bg-success ms-2">Уже имеет доступ</span>
                            </div>
                        </div>
                    </div>
                `;
            } else {
                html += `
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>${escapeHtml(userDisplay)} ${email}</div>
                            <div class="btn-group">
                                <button class="btn btn-sm btn-success" 
                                        onclick="grantAccessToTest(${testId}, ${user.id}, 0)">
                                    <i class="bi bi-eye"></i> Просмотр
                                </button>
                                <button class="btn btn-sm btn-warning" 
                                        onclick="grantAccessToTest(${testId}, ${user.id}, 1)">
                                    <i class="bi bi-pencil"></i> Редактирование
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            }
        });
        
        html += '</div>';
        container.innerHTML = html;
    }
    
    async function grantAccessToTest(testId, userId, canEdit) {
        try {
            const result = await apiCall('grantAccess', {
                test_id: testId,
                user_id: userId,
                can_edit: canEdit
            });
            
            if (result.success) {
                showNotification('✅ Доступ предоставлен', 'success');
                // Перезагружаем модальное окно
                const modal = bootstrap.Modal.getInstance(document.getElementById('accessManagementModal'));
                modal.hide();
                openAccessManagementModal(testId);
            } else {
                alert('Ошибка: ' + result.message);
            }
        } catch (error) {
            alert('Ошибка: ' + error.message);
        }
    }
    
    async function toggleEditPermission(testId, userId, canEdit) {
        if (!confirm(`${canEdit ? 'Предоставить' : 'Убрать'} право редактирования?`)) {
            return;
        }
        
        await grantAccessToTest(testId, userId, canEdit);
    }
    
    async function revokeAccessFromTest(testId, userId) {
        if (!confirm('Вы уверены, что хотите отозвать доступ у этого пользователя?')) {
            return;
        }
        
        try {
            const result = await apiCall('revokeAccess', {
                test_id: testId,
                user_id: userId
            });
            
            if (result.success) {
                showNotification('Доступ отозван', 'info');
                const modal = bootstrap.Modal.getInstance(document.getElementById('accessManagementModal'));
                modal.hide();
                openAccessManagementModal(testId);
            } else {
                alert('Ошибка: ' + result.message);
            }
        } catch (error) {
            alert('Ошибка: ' + error.message);
        }
    }
    
    // Экспортируем функции
    window.openAccessManagementModal = openAccessManagementModal;
    window.searchUsersForAccess = searchUsersForAccess;
    window.grantAccessToTest = grantAccessToTest;
    window.toggleEditPermission = toggleEditPermission;
    window.revokeAccessFromTest = revokeAccessFromTest;
    
    // В tsrunner.js
    
    async function openPublicationModal(testId, currentStatus) {
        const modalHtml = `
            <div class="modal fade" id="publicationModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title"><i class="bi bi-globe"></i> Изменить статус публикации</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p>Текущий статус: <strong>${getStatusName(currentStatus)}</strong></p>
                            
                            <div class="mb-3">
                                <label class="form-label">Новый статус</label>
                                <select class="form-select" id="new-publication-status">
                                    <option value="draft" ${currentStatus === 'draft' ? 'selected' : ''}>📝 Черновик</option>
                                    <option value="private" ${currentStatus === 'private' ? 'selected' : ''}>🔒 Приватный</option>
                                    <option value="unlisted" ${currentStatus === 'unlisted' ? 'selected' : ''}>🔗 По ссылке</option>
                                    <option value="public" ${currentStatus === 'public' ? 'selected' : ''}>🌐 Публичный</option>
                                </select>
                            </div>
                            
                            <div class="alert alert-info">
                                <small>
                                    <strong>Публичный:</strong> доступен всем<br>
                                    <strong>По ссылке:</strong> доступен по прямой ссылке<br>
                                    <strong>Приватный:</strong> только по приглашению<br>
                                    <strong>Черновик:</strong> только владелец
                                </small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                            <button type="button" class="btn btn-primary" onclick="changePublicationStatus(${testId})">
                                Изменить статус
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        const oldModal = document.getElementById('publicationModal');
        if (oldModal) oldModal.remove();
        
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        const modal = new bootstrap.Modal(document.getElementById('publicationModal'));
        modal.show();
    }
    
    async function changePublicationStatus(testId) {
        const newStatus = document.getElementById('new-publication-status').value;
        
        if (!confirm(`Изменить статус на "${getStatusName(newStatus)}"?`)) {
            return;
        }
        
        try {
            const result = await apiCall('publishTest', {
                test_id: testId,
                status: newStatus
            });
            
            if (result.success) {
                showNotification('✅ Статус изменён', 'success');
                setTimeout(() => window.location.reload(), 1500);
            } else {
                alert('Ошибка: ' + result.message);
            }
        } catch (error) {
            alert('Ошибка: ' + error.message);
        }
    }
    
    function getStatusName(status) {
        const names = {
            'draft': '📝 Черновик',
            'private': '🔒 Приватный',
            'unlisted': '🔗 По ссылке',
            'public': '🌐 Публичный'
        };
        return names[status] || status;
    }
    
    // ============================================
    // ОБЪЕДИНЕННОЕ МОДАЛЬНОЕ ОКНО УПРАВЛЕНИЯ ТЕСТОМ
    // ============================================

    async function openTestManagementModal(testId, currentStatus) {
        try {
            // Загружаем все необходимые данные параллельно
            const [settingsResult, permissionsResult] = await Promise.all([
                apiCall('getTestSettings', { test_id: testId }),
                apiCall('getTestPermissions', { test_id: testId })
            ]);

            if (!settingsResult.success) {
                throw new Error('Ошибка загрузки настроек теста');
            }

            const settings = settingsResult.data;
            const permissions = permissionsResult.success ? permissionsResult.data.permissions : [];
            const testInfo = permissionsResult.success ? permissionsResult.data.test : null;

            const modalHtml = `
                <div class="modal fade" id="testManagementModal" tabindex="-1">
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title"><i class="bi bi-gear"></i> Управление тестом</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <!-- Вкладки -->
                                <ul class="nav nav-tabs mb-4" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#settings-tab" type="button">
                                            <i class="bi bi-sliders"></i> Настройки
                                        </button>
                                    </li>
                                    ${currentStatus === 'private' ? `
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#access-tab" type="button">
                                            <i class="bi bi-people"></i> Доступ <span class="badge bg-secondary">${permissions.length}</span>
                                        </button>
                                    </li>
                                    ` : ''}
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#publication-tab" type="button">
                                            <i class="bi bi-globe"></i> Публикация
                                        </button>
                                    </li>
                                </ul>

                                <div class="tab-content">
                                    <!-- Вкладка "Настройки" -->
                                    <div class="tab-pane fade show active" id="settings-tab">
                                        <form id="test-settings-form-${testId}">
                                            <input type="hidden" name="test_id" value="${testId}">

                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Название теста</label>
                                                    <input type="text" name="title" class="form-control" value="${escapeHtml(settings.title)}" required>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Режим теста</label>
                                                    <select name="mode" class="form-select">
                                                        <option value="training" ${settings.mode === 'training' ? 'selected' : ''}>🎯 Тренировка</option>
                                                        <option value="exam" ${settings.mode === 'exam' ? 'selected' : ''}>🏆 Экзамен</option>
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Описание</label>
                                                <textarea name="description" class="form-control" rows="3">${escapeHtml(settings.description || '')}</textarea>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label">Проходной балл (%)</label>
                                                    <input type="number" name="pass_score" class="form-control" value="${settings.pass_score}" min="0" max="100">
                                                </div>
                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label">Время (минут, 0 = без ограничений)</label>
                                                    <input type="number" name="time_limit" class="form-control" value="${settings.time_limit}" min="0">
                                                </div>
                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label">Вопросов за попытку</label>
                                                    <input type="number" name="questions_per_session" class="form-control" value="${settings.questions_per_session}" min="1">
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" name="randomize_questions" id="randomize-q-${testId}" ${settings.randomize_questions ? 'checked' : ''}>
                                                        <label class="form-check-label" for="randomize-q-${testId}">Перемешивать вопросы</label>
                                                    </div>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" name="randomize_answers" id="randomize-a-${testId}" ${settings.randomize_answers ? 'checked' : ''}>
                                                        <label class="form-check-label" for="randomize-a-${testId}">Перемешивать ответы</label>
                                                    </div>
                                                </div>
                                            </div>

                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-save"></i> Сохранить настройки
                                            </button>
                                        </form>
                                    </div>

                                    ${currentStatus === 'private' ? `
                                    <!-- Вкладка "Доступ" -->
                                    <div class="tab-pane fade" id="access-tab">
                                        <div class="mb-4">
                                            <h6>Добавить пользователя</h6>
                                            <div class="input-group mb-2">
                                                <input type="text" class="form-control" id="user-search-input-${testId}"
                                                       placeholder="Поиск по имени, email или username...">
                                                <button class="btn btn-primary" onclick="searchUsersForAccess(${testId})">
                                                    <i class="bi bi-search"></i> Найти
                                                </button>
                                            </div>
                                            <div id="search-results-${testId}"></div>
                                        </div>

                                        <hr>

                                        <div>
                                            <h6>Пользователи с доступом (${permissions.length})</h6>
                                            <div id="permissions-list-${testId}" class="list-group">
                                                ${renderPermissionsList(permissions, testId)}
                                            </div>
                                        </div>
                                    </div>
                                    ` : ''}

                                    <!-- Вкладка "Публикация" -->
                                    <div class="tab-pane fade" id="publication-tab">
                                        <style>
                                            /* Исправляем видимость текста на активном элементе при hover */
                                            .list-group-item-action.active:hover h6,
                                            .list-group-item-action.active:hover small {
                                                color: #fff !important;
                                            }
                                            .list-group-item-action:not(.active):hover {
                                                background-color: #f8f9fa;
                                            }
                                        </style>

                                        <div class="alert alert-info">
                                            <strong>Текущий статус:</strong> ${getStatusName(currentStatus)}
                                        </div>

                                        <p>Выберите новый статус публикации:</p>

                                        <div class="list-group">
                                            <button type="button" class="list-group-item list-group-item-action ${currentStatus === 'draft' ? 'active' : ''}"
                                                    onclick="changePublicationStatusFromModal(${testId}, 'draft')">
                                                <h6 class="mb-1">📝 Черновик</h6>
                                                <small class="d-block">Тест виден только вам. Используйте для подготовки.</small>
                                            </button>
                                            <button type="button" class="list-group-item list-group-item-action ${currentStatus === 'private' ? 'active' : ''}"
                                                    onclick="changePublicationStatusFromModal(${testId}, 'private')">
                                                <h6 class="mb-1">🔒 Приватный</h6>
                                                <small class="d-block">Доступ только по приглашению. Вы можете предоставить доступ конкретным пользователям.</small>
                                            </button>
                                            <button type="button" class="list-group-item list-group-item-action ${currentStatus === 'unlisted' ? 'active' : ''}"
                                                    onclick="changePublicationStatusFromModal(${testId}, 'unlisted')">
                                                <h6 class="mb-1">🔗 По ссылке</h6>
                                                <small class="d-block">Доступен всем, у кого есть ссылка. Не отображается в общем каталоге.</small>
                                            </button>
                                            <button type="button" class="list-group-item list-group-item-action ${currentStatus === 'public' ? 'active' : ''}"
                                                    onclick="changePublicationStatusFromModal(${testId}, 'public')">
                                                <h6 class="mb-1">🌐 Публичный</h6>
                                                <small class="d-block">Доступен всем пользователям системы. Отображается в каталоге.</small>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Удаляем старое модальное окно если есть
            const oldModal = document.getElementById('testManagementModal');
            if (oldModal) oldModal.remove();

            document.body.insertAdjacentHTML('beforeend', modalHtml);

            // Открываем модальное окно
            const modal = new bootstrap.Modal(document.getElementById('testManagementModal'));
            modal.show();

            // Обработчик формы настроек
            const form = document.getElementById(`test-settings-form-${testId}`);
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                await saveTestSettings(testId, form);
            });

        } catch (error) {
            console.error('Error opening test management modal:', error);
            alert('Ошибка: ' + error.message);
        }
    }

    async function saveTestSettings(testId, form) {
        try {
            const formData = new FormData(form);
            const data = {
                test_id: testId,
                title: formData.get('title'),
                description: formData.get('description'),
                mode: formData.get('mode'),
                pass_score: parseInt(formData.get('pass_score')),
                time_limit: parseInt(formData.get('time_limit')),
                questions_per_session: parseInt(formData.get('questions_per_session')),
                randomize_questions: formData.get('randomize_questions') ? 1 : 0,
                randomize_answers: formData.get('randomize_answers') ? 1 : 0
            };

            const result = await apiCall('updateTestSettings', data);

            if (result.success) {
                alert('✅ Настройки сохранены!');
                location.reload(); // Перезагружаем страницу чтобы применить изменения
            } else {
                throw new Error(result.message || 'Ошибка сохранения');
            }
        } catch (error) {
            console.error('Error saving test settings:', error);
            alert('Ошибка: ' + error.message);
        }
    }

    async function changePublicationStatusFromModal(testId, newStatus) {
        if (!confirm(`Изменить статус публикации на "${getStatusName(newStatus)}"?`)) {
            return;
        }

        try {
            const result = await apiCall('changePublicationStatus', {
                test_id: testId,
                new_status: newStatus
            });

            if (result.success) {
                alert('✅ Статус изменён!');
                location.reload();
            } else {
                alert('Ошибка: ' + result.message);
            }
        } catch (error) {
            alert('Ошибка: ' + error.message);
        }
    }

    async function deleteTestConfirm(testId) {
        if (!confirm('⚠️ Вы уверены, что хотите удалить этот тест?\n\nЭто действие необратимо. Будут удалены:\n• Все вопросы теста\n• Все результаты прохождения\n• Вся статистика\n• Все настройки доступа')) {
            return;
        }

        try {
            const result = await apiCall('deleteTest', { test_id: testId });

            if (result.success) {
                alert('✅ Тест успешно удален');
                // Перенаправляем на страницу "Мои тесты"
                window.location.href = '/moi-testyi';
            } else {
                throw new Error(result.message || 'Ошибка удаления теста');
            }
        } catch (error) {
            console.error('Delete test error:', error);
            alert('❌ Ошибка удаления: ' + error.message);
        }
    }

    window.openTestManagementModal = openTestManagementModal;
    window.openPublicationModal = openPublicationModal;
    window.changePublicationStatus = changePublicationStatus;
    window.deleteTestConfirm = deleteTestConfirm;


})();
