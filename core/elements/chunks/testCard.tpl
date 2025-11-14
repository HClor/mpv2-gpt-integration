<!--
    testCard - Карточка теста для списка

    Используется сниппетом getTestInfoBatch

    Доступные плейсхолдеры:
    - [[+id]] - ID ресурса
    - [[+test_id]] - ID теста
    - [[+pagetitle]] - Название теста
    - [[+longtitle]] - Длинное название
    - [[+introtext]] - Краткое описание
    - [[+url]] - URL теста
    - [[+testQuestions]] - Количество вопросов
    - [[+testQuestionsPerSession]] - Вопросов за попытку
    - [[+testPassScore]] - Проходной балл
    - [[+publication_status]] - Статус: draft, public, private
    - [[+canView]] - Может ли просматривать (1 или 0)
    - [[+canEdit]] - Может ли редактировать (1 или 0)
    - [[+canManageAccess]] - Может ли управлять доступом (1 или 0)
    - [[+canChangeStatus]] - Может ли менять статус (1 или 0)
    - [[+userRole]] - Роль: author, editor, viewer, none
    - [[+isCreator]] - Создатель теста (1 или 0)
    - [[+isAdminOrExpert]] - Админ или эксперт (1 или 0)
-->

<div class="col-md-6 col-lg-4 mb-4">
    <div class="card h-100 test-card">
        <div class="card-body">
            <!-- Заголовок с бейджем статуса -->
            <div class="d-flex justify-content-between align-items-start mb-2">
                <h5 class="card-title mb-0">
                    <a href="[[+url]]" class="text-decoration-none">[[+pagetitle]]</a>
                </h5>

                <!-- Статус теста -->
                [[+publication_status:is=`draft`:and:is=`[[+isAdminOrExpert]]`:eq=`1`:then=`
                    <span class="badge bg-warning text-dark" title="Только для админов и экспертов">
                        <i class="bi bi-pencil-fill"></i> Черновик
                    </span>
                `]]

                [[+publication_status:is=`private`:then=`
                    <span class="badge bg-secondary" title="Приватный тест">
                        <i class="bi bi-lock-fill"></i> Приватный
                    </span>
                `]]

                [[+publication_status:is=`public`:and:is=`[[+isAdminOrExpert]]`:eq=`1`:then=`
                    <span class="badge bg-success" title="Публичный тест">
                        <i class="bi bi-globe"></i> Публичный
                    </span>
                `]]
            </div>

            <!-- Описание -->
            [[+introtext:notempty=`<p class="card-text text-muted small">[[+introtext]]</p>`]]

            <!-- Информация о тесте -->
            <div class="test-info small text-muted mt-2">
                <div class="d-flex justify-content-between">
                    <span><i class="bi bi-question-circle"></i> [[+testQuestions]] вопросов</span>
                    <span><i class="bi bi-clipboard-check"></i> [[+testPassScore]]%</span>
                </div>
            </div>
        </div>

        <div class="card-footer bg-transparent border-top-0">
            <div class="d-flex justify-content-between align-items-center">
                <!-- Кнопка старта -->
                <a href="[[+url]]" class="btn btn-primary btn-sm">
                    <i class="bi bi-play-fill"></i> Начать тест
                </a>

                <!-- Кнопки управления для админов/редакторов -->
                <div class="btn-group btn-group-sm" role="group">
                    [[+canEdit:is=`1`:then=`
                        <a href="[[+url]]?action=edit" class="btn btn-outline-secondary" title="Редактировать">
                            <i class="bi bi-pencil"></i>
                        </a>
                    `]]

                    [[+canManageAccess:is=`1`:then=`
                        <button class="btn btn-outline-secondary" title="Управление доступом" onclick="openAccessModal([[+test_id]])">
                            <i class="bi bi-people"></i>
                        </button>
                    `]]
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Стили для карточек тестов */
.test-card {
    transition: transform 0.2s, box-shadow 0.2s;
    border: 1px solid #dee2e6;
}

.test-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.test-card .card-title a {
    color: #212529;
    font-weight: 600;
}

.test-card .card-title a:hover {
    color: #0d6efd;
}

/* Бейджи статусов */
.badge {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
}

.badge i {
    font-size: 0.7rem;
}
</style>
