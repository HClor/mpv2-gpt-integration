-- ПРОВЕРКА СИСТЕМЫ РОЛЕЙ И ПРАВ

-- 1. Группы пользователей (роли)
SELECT
    mg.id as 'ID группы',
    mg.name as 'Название группы',
    COUNT(DISTINCT mgm.member) as 'Пользователей в группе',
    GROUP_CONCAT(DISTINCT u.username SEPARATOR ', ') as 'Пользователи'
FROM modx_membergroup_names mg
LEFT JOIN modx_member_groups mgm ON mgm.user_group = mg.id
LEFT JOIN modx_users u ON u.id = mgm.member
WHERE mg.name LIKE 'LMS %'
GROUP BY mg.id, mg.name
ORDER BY mg.name;

-- 2. Список всех пользователей с их ролями
SELECT
    u.id,
    u.username,
    up.email,
    up.blocked as 'Заблокирован',
    GROUP_CONCAT(mgn.name SEPARATOR ', ') as 'Группы (Роли)',
    CASE
        WHEN GROUP_CONCAT(mgn.name) LIKE '%LMS Admins%' THEN 'Admin'
        WHEN GROUP_CONCAT(mgn.name) LIKE '%LMS Experts%' THEN 'Expert'
        WHEN GROUP_CONCAT(mgn.name) LIKE '%LMS Students%' THEN 'Student'
        ELSE 'Без роли'
    END as 'Роль в LMS'
FROM modx_users u
LEFT JOIN modx_user_attributes up ON up.internalKey = u.id
LEFT JOIN modx_member_groups mg ON mg.member = u.id
LEFT JOIN modx_membergroup_names mgn ON mgn.id = mg.user_group
GROUP BY u.id, u.username, up.email, up.blocked
ORDER BY u.id;

-- 3. Права доступа к тестам (modx_test_permissions)
SELECT
    p.id,
    p.test_id,
    t.title as 'Тест',
    p.user_id,
    u.username as 'Пользователь',
    p.can_edit as 'Может редактировать',
    gu.username as 'Дал доступ',
    p.granted_at as 'Дата выдачи'
FROM modx_test_permissions p
LEFT JOIN modx_test_tests t ON t.id = p.test_id
LEFT JOIN modx_users u ON u.id = p.user_id
LEFT JOIN modx_users gu ON gu.id = p.granted_by
ORDER BY p.granted_at DESC;

-- 4. Эксперты категорий (новая таблица)
SELECT
    ce.id,
    ce.category_id,
    c.name as 'Категория',
    ce.user_id,
    u.username as 'Эксперт',
    ce.can_manage_tests as 'Управление тестами',
    ce.can_manage_questions as 'Управление вопросами',
    ce.can_approve as 'Утверждение',
    au.username as 'Назначил',
    ce.assigned_at as 'Дата назначения'
FROM modx_test_category_experts ce
LEFT JOIN modx_test_categories c ON c.id = ce.category_id
LEFT JOIN modx_users u ON u.id = ce.user_id
LEFT JOIN modx_users au ON au.id = ce.assigned_by
ORDER BY c.name, u.username;

-- 5. Проверка: у каких экспертов какие категории
SELECT
    u.username as 'Эксперт',
    GROUP_CONCAT(c.name SEPARATOR ', ') as 'Назначенные категории',
    COUNT(ce.id) as 'Количество категорий'
FROM modx_users u
JOIN modx_member_groups mg ON mg.member = u.id
JOIN modx_membergroup_names mgn ON mgn.id = mg.user_group AND mgn.name = 'LMS Experts'
LEFT JOIN modx_test_category_experts ce ON ce.user_id = u.id
LEFT JOIN modx_test_categories c ON c.id = ce.category_id
GROUP BY u.id, u.username
ORDER BY u.username;
