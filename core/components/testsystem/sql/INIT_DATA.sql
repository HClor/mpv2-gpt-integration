-- ============================================================================
-- ИНИЦИАЛИЗАЦИЯ ДАННЫХ LMS SYSTEM v2.0
-- ============================================================================
-- Этот скрипт создает необходимые базовые данные если их нет
-- Выполнить: mysql -u user -p database < INIT_DATA.sql

-- ============================================================================
-- 1. УРОВНИ ОПЫТА (10 уровней)
-- ============================================================================

-- Проверить есть ли уже уровни
SET @level_count = (SELECT COUNT(*) FROM modx_test_level_config);

-- Если уровней нет, создать
INSERT INTO modx_test_level_config (level, xp_required, title, perks)
SELECT * FROM (
    SELECT 1, 0, 'Новичок', JSON_OBJECT('badge_color', '#gray')
    UNION ALL
    SELECT 2, 100, 'Ученик', JSON_OBJECT('badge_color', '#blue')
    UNION ALL
    SELECT 3, 250, 'Адепт', JSON_OBJECT('badge_color', '#cyan')
    UNION ALL
    SELECT 4, 500, 'Мастер', JSON_OBJECT('badge_color', '#green')
    UNION ALL
    SELECT 5, 1000, 'Эксперт', JSON_OBJECT('badge_color', '#lime')
    UNION ALL
    SELECT 6, 1500, 'Гений', JSON_OBJECT('badge_color', '#yellow')
    UNION ALL
    SELECT 7, 2500, 'Ученый', JSON_OBJECT('badge_color', '#orange')
    UNION ALL
    SELECT 8, 3500, 'Профессор', JSON_OBJECT('badge_color', '#red')
    UNION ALL
    SELECT 9, 4500, 'Академик', JSON_OBJECT('badge_color', '#purple')
    UNION ALL
    SELECT 10, 5000, 'Мудрец', JSON_OBJECT('badge_color', '#gold')
) AS tmp
WHERE @level_count = 0
ON DUPLICATE KEY UPDATE xp_required = VALUES(xp_required);

-- ============================================================================
-- 2. ШАБЛОНЫ УВЕДОМЛЕНИЙ (8 шаблонов)
-- ============================================================================

SET @template_count = (SELECT COUNT(*) FROM modx_test_notification_templates);

INSERT INTO modx_test_notification_templates
(template_key, notification_type, channel, subject_template, body_template, is_active)
SELECT * FROM (
    SELECT 'test_completed', 'test_completed', 'system', 'Тест завершен',
           'Вы завершили тест "{test_name}" с результатом {score}%', 1
    UNION ALL
    SELECT 'achievement_earned', 'achievement_earned', 'system', 'Получено достижение',
           'Поздравляем! Вы получили достижение "{achievement_name}"', 1
    UNION ALL
    SELECT 'level_up', 'level_up', 'system', 'Повышение уровня',
           'Вы достигли уровня {level} - "{level_title}"', 1
    UNION ALL
    SELECT 'path_step_unlocked', 'path_step_unlocked', 'system', 'Разблокирован новый этап',
           'Следующий этап траектории "{path_name}" теперь доступен', 1
    UNION ALL
    SELECT 'essay_reviewed', 'essay_reviewed', 'system', 'Эссе проверено',
           'Ваше эссе получило оценку {score} баллов', 1
    UNION ALL
    SELECT 'material_available', 'material_available', 'system', 'Новый материал',
           'Для вас доступен новый учебный материал "{material_name}"', 1
    UNION ALL
    SELECT 'certificate_issued', 'custom', 'system', 'Получен сертификат',
           'Вам выдан сертификат за "{entity_name}"', 1
    UNION ALL
    SELECT 'deadline_reminder', 'custom', 'system', 'Напоминание о дедлайне',
           'Напоминание: дедлайн для "{task_name}" истекает {deadline}', 1
) AS tmp
WHERE @template_count = 0 AND NOT EXISTS (
    SELECT 1 FROM modx_test_notification_templates WHERE template_key = tmp.template_key
)
ON DUPLICATE KEY UPDATE is_active = VALUES(is_active);

-- ============================================================================
-- 3. ПРОВЕРКА ЦЕЛОСТНОСТИ ДАННЫХ
-- ============================================================================

-- Проверить количество тестов
SELECT CONCAT('✓ Тестов в системе: ', COUNT(*)) as status FROM modx_test_tests;

-- Проверить количество вопросов
SELECT CONCAT('✓ Вопросов в системе: ', COUNT(*)) as status FROM modx_test_questions;

-- Проверить количество ответов
SELECT CONCAT('✓ Ответов в системе: ', COUNT(*)) as status FROM modx_test_answers;

-- Проверить количество уровней
SELECT CONCAT('✓ Уровней в системе: ', COUNT(*)) as status FROM modx_test_level_config;

-- Проверить количество шаблонов уведомлений
SELECT CONCAT('✓ Шаблонов уведомлений: ', COUNT(*)) as status FROM modx_test_notification_templates;

-- Проверить количество достижений
SELECT CONCAT('✓ Достижений в системе: ', COUNT(*)) as status FROM modx_test_achievements;

-- Проверить количество категорий
SELECT CONCAT('✓ Категорий тестов: ', COUNT(*)) as status FROM modx_test_categories;

-- Проверить количество сессий
SELECT CONCAT('✓ Сессий тестирования: ', COUNT(*)) as status FROM modx_test_sessions;

-- ============================================================================
-- 4. ВЫВОД ИТОГОВОЙ СТАТИСТИКИ
-- ============================================================================

SELECT
    '✅ ИНИЦИАЛИЗАЦИЯ ДАННЫХ ЗАВЕРШЕНА' as status,
    NOW() as timestamp;

SELECT
    COUNT(DISTINCT t.id) as total_tests,
    COUNT(DISTINCT q.id) as total_questions,
    COUNT(DISTINCT a.id) as total_answers,
    COUNT(DISTINCT s.id) as total_sessions,
    COUNT(DISTINCT lc.level) as total_levels
FROM modx_test_tests t
LEFT JOIN modx_test_questions q ON t.id = q.test_id
LEFT JOIN modx_test_answers a ON q.id = a.question_id
LEFT JOIN modx_test_sessions s ON t.id = s.test_id
LEFT JOIN modx_test_level_config lc ON 1=1;

-- ============================================================================
-- КОНЕЦ СКРИПТА ИНИЦИАЛИЗАЦИИ
-- ============================================================================
