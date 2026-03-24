// assets/components/testsystem/js/mytests.js - IMPROVED VERSION

// CSRF Protection: получаем токен из meta тега
function getCsrfToken() {
    const metaTag = document.querySelector('meta[name="csrf-token"]');
    return metaTag ? metaTag.content : null;
}

document.addEventListener('DOMContentLoaded', function() {
    // Проверяем наличие контейнера - если его нет, это не страница управления тестами
    const myTestsContainer = document.getElementById('my-tests-container');
    if (!myTestsContainer) {
        console.log('My tests container not found, skipping initialization');
        return;
    }

    loadMyTests();
    loadSharedTests();
    loadPublicTests();
});

function getStatusBadge(status) {
    const badges = {
        'draft': '<span class="badge bg-secondary">📝 Черновик</span>',
        'private': '<span class="badge bg-danger">🔒 Приватный</span>',
        'unlisted': '<span class="badge bg-warning text-dark">🔗 По ссылке</span>',
        'public': '<span class="badge bg-success">🌐 Публичный</span>'
    };
    return badges[status] || `<span class="badge bg-secondary">${status}</span>`;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Построение URL для теста (fallback если API не вернул URL)
function buildTestUrl(testId) {
    // Пытаемся получить базовый URL страницы с тестами из мета-тега или используем текущий домен
    const baseUrl = document.querySelector('meta[name="test-page-url"]')?.content;
    if (baseUrl) {
        return baseUrl + '?testId=' + testId;
    }
    // Fallback: используем относительный URL
    return '/test-run?testId=' + testId;
}

async function loadMyTests() {
    try {
        const csrfToken = getCsrfToken();
        const response = await fetch('/assets/components/testsystem/ajax/testsystem.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'getMyTests',
                data: { csrf_token: csrfToken }
            })
        });

        const result = await response.json();

        if (result.success) {
            // Добавляем fallback для test_url
            result.data.forEach(test => {
                if (!test.test_url || test.test_url === '#') {
                    test.test_url = buildTestUrl(test.id);
                }
            });
            renderMyTests(result.data);
        }
    } catch (error) {
        console.error('Error loading tests:', error);
    }
}

async function loadSharedTests() {
    try {
        const csrfToken = getCsrfToken();
        const response = await fetch('/assets/components/testsystem/ajax/testsystem.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'getSharedWithMe',
                data: { csrf_token: csrfToken }
            })
        });

        const result = await response.json();

        if (result.success) {
            // Добавляем fallback для test_url
            result.data.forEach(test => {
                if (!test.test_url || test.test_url === '#') {
                    test.test_url = buildTestUrl(test.id);
                }
            });
            renderSharedTests(result.data);
        } else {
            console.error('Error loading shared tests:', result.message);
            // XSS Protection: экранируем сообщение об ошибке
            document.getElementById('shared').innerHTML =
                '<div class="alert alert-danger">Ошибка загрузки: ' + escapeHtml(result.message) + '</div>';
        }
    } catch (error) {
        console.error('Error loading shared tests:', error);
        document.getElementById('shared').innerHTML =
            '<div class="alert alert-danger">Ошибка при загрузке тестов</div>';
    }
}

function renderSharedTests(tests) {
    const container = document.getElementById('shared');
    
    if (tests.length === 0) {
        container.innerHTML = '<div class="alert alert-info">Вам не предоставлен доступ к тестам других пользователей</div>';
        return;
    }
    
    let html = '<div class="list-group tests-list-improved">';
    
    tests.forEach(test => {
        const accessBadge = test.can_edit 
            ? '<span class="badge bg-warning text-dark">Редактирование</span>' 
            : '<span class="badge bg-info">Просмотр</span>';
        
        const testUrl = test.test_url || '#';
        
        html += `<div class="list-group-item test-list-item-minimal">
            <div class="ts-mytests-row">
                <div class="ts-mytests-main">
                    <div class="test-info-block">
                        <h6 class="mb-2 test-title-clickable" onclick="window.location.href='${testUrl}'">
                            ${escapeHtml(test.title)}
                        </h6>
                        <p class="mb-2 text-muted small">${escapeHtml(test.description || 'Нет описания')}</p>
                        <div class="test-meta-info">
                            ${getStatusBadge(test.publication_status)}
                            ${accessBadge}
                            <span class="text-muted ms-2">
                                <i class="bi bi-person-circle"></i> ${escapeHtml(test.owner_name || 'N/A')}
                            </span>
                            <span class="text-muted ms-2">
                                <i class="bi bi-question-circle"></i> ${test.questions_count}
                            </span>
                        </div>
                    </div>
                </div>
                <div class="test-actions-compact" aria-label="Действия с тестом">
                    ${test.can_edit ? `
                        <button class="ts-btn ts-btn-secondary ts-btn-icon-only btn-test-action" onclick="editTest(${test.id}, '${escapeHtml(test.title)}', '${escapeHtml(test.description || '')}', '${test.publication_status}')" title="Редактировать">
                            <i class="bi bi-pencil"></i>
                        </button>
                    ` : ''}
                    <a class="ts-btn ts-btn-success ts-btn-icon-only btn-test-action" href="${testUrl}" title="Пройти тест">
                        <i class="bi bi-play-fill"></i>
                    </a>
                </div>
            </div>
        </div>`;
    });
    
    html += '</div>';
    container.innerHTML = html;
}

function renderMyTests(tests) {
    const container = document.getElementById('created');
    
    if (tests.length === 0) {
        container.innerHTML = '<div class="alert alert-info">У вас пока нет созданных тестов</div>';
        return;
    }
    
    // Группируем тесты по статусу публикации
    const groupedTests = {
        public: tests.filter(t => t.publication_status === 'public'),
        unlisted: tests.filter(t => t.publication_status === 'unlisted'),
        private: tests.filter(t => t.publication_status === 'private'),
        draft: tests.filter(t => t.publication_status === 'draft')
    };
    
    // Фильтры
    let html = '<div class="tests-filters-container mb-3" role="tablist" aria-label="Фильтр моих тестов">';
    html += '<div class="ts-mytests-filters">';
    html += `<button type="button" class="ts-btn ts-btn-secondary ts-mytests-filter-btn is-active" data-filter="all" onclick="filterMyTests('all', this)">
        Все <span class="ts-mytests-filter-count">${tests.length}</span>
    </button>`;
    html += `<button type="button" class="ts-btn ts-btn-secondary ts-mytests-filter-btn" data-filter="public" onclick="filterMyTests('public', this)">
        Публичные <span class="ts-mytests-filter-count">${groupedTests.public.length}</span>
    </button>`;
    html += `<button type="button" class="ts-btn ts-btn-secondary ts-mytests-filter-btn" data-filter="unlisted" onclick="filterMyTests('unlisted', this)">
        По ссылке <span class="ts-mytests-filter-count">${groupedTests.unlisted.length}</span>
    </button>`;
    html += `<button type="button" class="ts-btn ts-btn-secondary ts-mytests-filter-btn" data-filter="private" onclick="filterMyTests('private', this)">
        Приватные <span class="ts-mytests-filter-count">${groupedTests.private.length}</span>
    </button>`;
    html += `<button type="button" class="ts-btn ts-btn-secondary ts-mytests-filter-btn" data-filter="draft" onclick="filterMyTests('draft', this)">
        Черновики <span class="ts-mytests-filter-count">${groupedTests.draft.length}</span>
    </button>`;
    html += '</div>';
    html += '</div>';
    
    html += '<div class="list-group tests-list-improved">';
    
    tests.forEach(test => {
        const itemClass = test.publication_status === 'draft' ? 'list-group-item-secondary' : '';
        const testUrl = test.test_url || '#';
        
        html += `<div class="list-group-item test-list-item-minimal ${itemClass}" data-status="${test.publication_status}">
            <div class="ts-mytests-row">
                <div class="ts-mytests-main">
                    <div class="test-info-block">
                        <h6 class="mb-2 test-title-clickable" onclick="window.location.href='${testUrl}'">
                            ${escapeHtml(test.title)}
                        </h6>
                        <p class="mb-2 text-muted small">${escapeHtml(test.description || 'Нет описания')}</p>
                        <div class="test-meta-info">
                            ${getStatusBadge(test.publication_status)}
                            <span class="text-muted ms-2">
                                <i class="bi bi-question-circle"></i> Вопросов: ${test.questions_count}
                            </span>
                            <span class="text-muted ms-2">
                                <i class="bi bi-people"></i> Доступ: ${test.shared_with_count} чел.
                            </span>
                        </div>
                    </div>
                </div>
                <div class="test-actions-compact" aria-label="Действия с тестом">
                    <button class="ts-btn ts-btn-secondary ts-btn-icon-only btn-test-action" onclick="editTest(${test.id}, '${escapeHtml(test.title)}', '${escapeHtml(test.description || '')}', '${test.publication_status}')" title="Редактировать тест">
                        <i class="bi bi-gear"></i>
                    </button>
                    <button class="ts-btn ts-btn-secondary ts-btn-icon-only btn-test-action" onclick="manageAccess(${test.id})" title="Управление доступом">
                        <i class="bi bi-people"></i>
                    </button>
                    <a class="ts-btn ts-btn-success ts-btn-icon-only btn-test-action" href="${testUrl}" title="Пройти тест">
                        <i class="bi bi-play-fill"></i>
                    </a>
                    <button class="ts-btn ts-btn-ghost-danger ts-btn-icon-only btn-test-action" onclick="deleteTest(${test.id})" title="Удалить тест">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        </div>`;
    });
    
    html += '</div>';
    container.innerHTML = html;
}

function filterMyTests(status, buttonEl = null) {
    const items = document.querySelectorAll('#created .test-list-item-minimal');
    const buttons = document.querySelectorAll('.ts-mytests-filter-btn');
    buttons.forEach(btn => btn.classList.remove('is-active'));

    const activeButton = buttonEl || document.querySelector(`.ts-mytests-filter-btn[data-filter="${status}"]`);
    if (activeButton) {
        activeButton.classList.add('is-active');
    }

    items.forEach(item => {
        item.style.display = (status === 'all' || item.dataset.status === status) ? '' : 'none';
    });
}

function showCreateTestModal() {
    const modal = new bootstrap.Modal(document.getElementById('createTestModal'));
    modal.show();
}

async function createTest() {
    const title = document.getElementById('new-test-title').value.trim();
    const description = document.getElementById('new-test-description').value.trim();
    const publicationStatus = document.getElementById('new-test-publication-status').value;
    
    if (!title) {
        alert('Введите название теста');
        return;
    }
    
    const createBtn = document.querySelector('#createTestModal .btn-primary');
    if (createBtn) {
        createBtn.disabled = true;
        createBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Создание...';
    }
    
    try {
        const csrfToken = getCsrfToken();
        const response = await fetch('/assets/components/testsystem/ajax/testsystem.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'createTestWithPage',
                data: {
                    title,
                    description,
                    publication_status: publicationStatus,
                    csrf_token: csrfToken
                }
            })
        });
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.message || 'Ошибка создания теста');
        }
        
        const testId = result.test_id;
        const testUrl = result.test_url;
        
        showNotification('success', `✅ Тест "${title}" успешно создан!`);
        
        const modal = bootstrap.Modal.getInstance(document.getElementById('createTestModal'));
        if (modal) {
            modal.hide();
        }
        
        // Спрашиваем, куда перейти
        if (confirm('Тест создан! Хотите импортировать вопросы сейчас?\n\nДа - перейти к импорту\nНет - остаться на этой странице')) {
            window.location.href = `/import-csv?test_id=${testId}`;
        } else {
            loadMyTests(); // Перезагружаем список
        }
        
    } catch (error) {
        console.error('Error creating test:', error);
        showNotification('danger', 'Ошибка при создании теста: ' + error.message);
        
        if (createBtn) {
            createBtn.disabled = false;
            createBtn.textContent = 'Создать';
        }
    }
}

async function editTest(testId, title, description, publicationStatus) {
    // Создаем модальное окно
    const modalHtml = `
        <div class="modal fade" id="editTestModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Редактировать тест</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Название теста *</label>
                            <input type="text" class="form-control" id="edit-test-title" value="${title}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Описание</label>
                            <textarea class="form-control" id="edit-test-description" rows="3">${description}</textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Статус публикации</label>
                            <select class="form-select" id="edit-test-publication-status">
                                <option value="draft" ${publicationStatus === 'draft' ? 'selected' : ''}>📝 Черновик</option>
                                <option value="private" ${publicationStatus === 'private' ? 'selected' : ''}>🔒 Приватный</option>
                                <option value="unlisted" ${publicationStatus === 'unlisted' ? 'selected' : ''}>🔗 По ссылке</option>
                                <option value="public" ${publicationStatus === 'public' ? 'selected' : ''}>🌐 Публичный</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="button" class="btn btn-primary" onclick="saveTestChanges(${testId})">Сохранить</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Удаляем старое модальное окно если есть
    const oldModal = document.getElementById('editTestModal');
    if (oldModal) {
        oldModal.remove();
    }
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    const modal = new bootstrap.Modal(document.getElementById('editTestModal'));
    modal.show();
}

async function saveTestChanges(testId) {
    const title = document.getElementById('edit-test-title').value.trim();
    const description = document.getElementById('edit-test-description').value.trim();
    const publicationStatus = document.getElementById('edit-test-publication-status').value;
    
    if (!title) {
        alert('Введите название теста');
        return;
    }
    
    const saveBtn = document.querySelector('#editTestModal .btn-primary');
    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Сохранение...';
    }
    
    try {
        const csrfToken = getCsrfToken();
        const response = await fetch('/assets/components/testsystem/ajax/testsystem.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'updateTest',
                data: {
                    test_id: testId,
                    title: title,
                    description: description,
                    publication_status: publicationStatus,
                    csrf_token: csrfToken
                }
            })
        });
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.message || 'Ошибка сохранения');
        }
        
        showNotification('success', '✅ Тест обновлен!');
        
        const modal = bootstrap.Modal.getInstance(document.getElementById('editTestModal'));
        if (modal) {
            modal.hide();
        }
        
        loadMyTests(); // Перезагружаем список
        
    } catch (error) {
        console.error('Error updating test:', error);
        showNotification('danger', 'Ошибка при сохранении: ' + error.message);
        
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Сохранить';
        }
    }
}

async function deleteTest(testId) {
    if (!confirm('Вы уверены, что хотите удалить этот тест? Это действие необратимо!')) {
        return;
    }
    
    try {
        const csrfToken = getCsrfToken();
        const response = await fetch('/assets/components/testsystem/ajax/testsystem.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'deleteTest',
                data: {
                    test_id: testId,
                    csrf_token: csrfToken
                }
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('success', '✅ Тест удален');
            loadMyTests();
        } else {
            showNotification('danger', 'Ошибка: ' + result.message);
        }
    } catch (error) {
        console.error('Error deleting test:', error);
        showNotification('danger', 'Ошибка при удалении теста');
    }
}

async function manageAccess(testId) {
    const csrfToken = getCsrfToken();
    const permsResponse = await fetch('/assets/components/testsystem/ajax/testsystem.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'getTestPermissions',
            data: {
                test_id: testId,
                csrf_token: csrfToken
            }
        })
    });
    
    const permsResult = await permsResponse.json();

    if (!permsResult.success) {
        alert('Ошибка загрузки разрешений: ' + (permsResult.message || 'Неизвестная ошибка'));
        return;
    }

    // API возвращает {test, permissions}
    const permissions = permsResult.data.permissions || [];
    const test = permsResult.data.test || {};

    const modalHtml = `
        <div class="modal fade" id="manageAccessModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-people"></i> Управление доступом: ${escapeHtml(test.title || 'Тест')}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-4">
                            <h6>Добавить пользователя</h6>
                            <div class="input-group mb-2">
                                <input type="text" class="form-control" id="user-search-input"
                                       placeholder="Поиск по имени, email или username...">
                                <button class="btn btn-outline-primary" onclick="searchUsers(${testId})">
                                    <i class="bi bi-search"></i> Найти
                                </button>
                            </div>
                            <div id="search-results"></div>
                        </div>

                        <hr>

                        <div>
                            <h6>Пользователи с доступом (${permissions.length})</h6>
                            <div id="permissions-list" class="list-group">
                                ${renderPermissionsList(permissions, testId)}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    const oldModal = document.getElementById('manageAccessModal');
    if (oldModal) {
        oldModal.remove();
    }
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    const modal = new bootstrap.Modal(document.getElementById('manageAccessModal'));
    modal.show();
    
    document.getElementById('user-search-input').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            searchUsers(testId);
        }
    });
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
            ? '<span class="badge bg-warning text-dark">Может редактировать</span>' 
            : '<span class="badge bg-info">Только просмотр</span>';
        
        html += `
            <div class="list-group-item">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong>${escapeHtml(userDisplay)}</strong> ${email}
                        <br>
                        <small class="text-muted">
                            Доступ предоставлен: ${new Date(perm.granted_at).toLocaleDateString('ru-RU')}
                            ${perm.granted_by_name ? `пользователем ${perm.granted_by_name}` : ''}
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
                                onclick="revokeAccess(${testId}, ${perm.user_id})">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
    });
    
    return html;
}

async function searchUsers(testId) {
    const query = document.getElementById('user-search-input').value.trim();
    
    if (query.length < 2) {
        alert('Введите минимум 2 символа для поиска');
        return;
    }
    
    try {
        const csrfToken = getCsrfToken();
        const response = await fetch('/assets/components/testsystem/ajax/testsystem.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'searchUsers',
                data: {
                    query: query,
                    test_id: testId,
                    csrf_token: csrfToken
                }
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            renderSearchResults(result.data, testId);
        } else {
            alert('Ошибка поиска: ' + result.message);
        }
    } catch (error) {
        console.error('Error searching users:', error);
        alert('Ошибка при поиске пользователей');
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
        // XSS Protection: экранируем email
        const email = user.email ? `(${escapeHtml(user.email)})` : '';
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
                                    onclick="grantAccessToUser(${testId}, ${user.id}, 0)">
                                <i class="bi bi-eye"></i> Просмотр
                            </button>
                            <button class="btn btn-sm btn-warning"
                                    onclick="grantAccessToUser(${testId}, ${user.id}, 1)">
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

async function grantAccessToUser(testId, userId, canEdit) {
    try {
        const csrfToken = getCsrfToken();
        const response = await fetch('/assets/components/testsystem/ajax/testsystem.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'grantTestAccess',
                data: {
                    test_id: testId,
                    user_id: userId,
                    can_view: true,
                    can_edit: canEdit
                },
                csrf_token: csrfToken
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('success', '✅ Доступ предоставлен!');
            bootstrap.Modal.getInstance(document.getElementById('manageAccessModal')).hide();
            manageAccess(testId);
        } else {
            showNotification('danger', 'Ошибка: ' + result.message);
        }
    } catch (error) {
        console.error('Error granting access:', error);
        showNotification('danger', 'Ошибка при предоставлении доступа');
    }
}

async function toggleEditPermission(testId, userId, canEdit) {
    if (!confirm(`${canEdit ? 'Предоставить' : 'Убрать'} право редактирования?`)) {
        return;
    }
    
    await grantAccessToUser(testId, userId, canEdit);
}

async function revokeAccess(testId, userId) {
    if (!confirm('Вы уверены, что хотите отозвать доступ у этого пользователя?')) {
        return;
    }

    try {
        const csrfToken = getCsrfToken();
        const response = await fetch('/assets/components/testsystem/ajax/testsystem.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'revokeTestAccess',
                data: {
                    test_id: testId,
                    user_id: userId
                },
                csrf_token: csrfToken
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('success', '✅ Доступ отозван');
            bootstrap.Modal.getInstance(document.getElementById('manageAccessModal')).hide();
            manageAccess(testId);
        } else {
            showNotification('danger', 'Ошибка: ' + result.message);
        }
    } catch (error) {
        console.error('Error revoking access:', error);
        showNotification('danger', 'Ошибка при отзыве доступа');
    }
}

function showNotification(type, message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    alertDiv.style.cssText = 'top: 20px; left: 50%; transform: translateX(-50%); z-index: 9999; min-width: 300px;';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(alertDiv);
    
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}

// Загрузка публичных тестов
async function loadPublicTests() {
    try {
        const csrfToken = getCsrfToken();
        const response = await fetch('/assets/components/testsystem/ajax/testsystem.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'getPublicTests',
                data: { csrf_token: csrfToken }
            })
        });

        const result = await response.json();

        if (result.success) {
            // Добавляем fallback для test_url
            result.data.forEach(test => {
                if (!test.test_url || test.test_url === '#') {
                    test.test_url = buildTestUrl(test.id);
                }
            });
            renderPublicTests(result.data);
        } else {
            console.error('Error loading public tests:', result.message);
            document.getElementById('public').innerHTML =
                '<div class="alert alert-danger">Ошибка загрузки: ' + escapeHtml(result.message) + '</div>';
        }
    } catch (error) {
        console.error('Error loading public tests:', error);
        document.getElementById('public').innerHTML =
            '<div class="alert alert-danger">Ошибка при загрузке публичных тестов</div>';
    }
}

function renderPublicTests(tests) {
    const container = document.getElementById('public');

    if (tests.length === 0) {
        container.innerHTML = '<div class="alert alert-info">Публичных тестов пока нет</div>';
        return;
    }

    let html = '<div class="list-group tests-list-improved">';

    tests.forEach(test => {
        const testUrl = test.test_url || '#';
        const questionsText = test.questions_count === 1 ? 'вопрос' : test.questions_count < 5 ? 'вопроса' : 'вопросов';

        html += `<div class="list-group-item test-list-item-minimal">
            <div class="d-flex justify-content-between align-items-start flex-wrap">
                <div class="flex-grow-1 mb-2 mb-md-0">
                    <h5 class="mb-1">
                        <a href="${escapeHtml(testUrl)}" class="text-decoration-none">${escapeHtml(test.title)}</a>
                    </h5>
                    ${test.description ? `<p class="mb-1 text-muted small">${escapeHtml(test.description)}</p>` : ''}
                    <div class="text-muted small mt-2">
                        <span class="me-3"><i class="bi bi-patch-question"></i> ${test.questions_count} ${questionsText}</span>
                        ${test.creator_name ? `<span class="me-3"><i class="bi bi-person"></i> ${escapeHtml(test.creator_name)}</span>` : ''}
                    </div>
                </div>
                <div>
                    <a href="${escapeHtml(testUrl)}" class="btn btn-sm btn-primary">
                        <i class="bi bi-play-fill"></i> Пройти тест
                    </a>
                </div>
            </div>
        </div>`;
    });

    html += '</div>';
    container.innerHTML = html;
}
