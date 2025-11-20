-- ============================================
-- ИСПРАВЛЕНИЕ: Установка publication_status для существующих тестов
-- ============================================
-- Проблема: Тесты созданные до исправления не имеют publication_status='public'
-- Решение: Обновляем все активные тесты, установив статус 'public'

-- 1. Проверка: Сколько тестов без publication_status='public'
SELECT
    COUNT(*) as 'Тестов без статуса public',
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as 'Из них активных'
FROM modx_test_tests
WHERE publication_status IS NULL
   OR publication_status != 'public';

-- 2. Показать затронутые тесты (для проверки перед обновлением)
SELECT
    id,
    title,
    created_by,
    is_active,
    publication_status,
    created_at
FROM modx_test_tests
WHERE publication_status IS NULL
   OR publication_status != 'public'
ORDER BY created_at DESC;

-- 3. ОБНОВЛЕНИЕ: Установить publication_status='public' для всех активных тестов
-- ВАЖНО: Запускайте только после проверки результатов запроса выше!
UPDATE modx_test_tests
SET publication_status = 'public'
WHERE is_active = 1
  AND (publication_status IS NULL OR publication_status != 'public');

-- 4. Проверка результата
SELECT
    publication_status,
    COUNT(*) as count,
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_count
FROM modx_test_tests
GROUP BY publication_status;

-- 5. Проверка что тесты теперь отображаются
SELECT
    c.id as category_id,
    c.name as category_name,
    COUNT(DISTINCT t.id) AS test_count
FROM modx_test_categories c
LEFT JOIN modx_test_tests t ON t.resource_id = c.id
WHERE t.publication_status = 'public' AND t.is_active = 1
GROUP BY c.id, c.name
ORDER BY c.sort_order;
