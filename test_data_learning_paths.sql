-- ============================================
-- ТЕСТОВЫЕ ДАННЫЕ ДЛЯ ТРАЕКТОРИЙ ОБУЧЕНИЯ
-- ============================================
-- Выполни этот файл в phpMyAdmin для создания тестовых траекторий
--
-- Внимание: Замени ID материалов и тестов на реальные из твоей БД!
-- ============================================

-- Получить ID первого админа
SET @admin_id = (SELECT id FROM modx_users WHERE sudo = 1 LIMIT 1);

-- ============================================
-- 1. ТРАЕКТОРИЯ "Основы психологии"
-- ============================================

INSERT INTO modx_test_learning_paths
    (name, description, created_by, status, is_public, difficulty_level, estimated_hours, passing_score)
VALUES
    ('Основы психологии',
     'Введение в психологию: основные понятия, методы исследования, история развития науки. Идеально для начинающих.',
     @admin_id,
     'published',
     1,
     'beginner',
     20,
     70);

SET @path1_id = LAST_INSERT_ID();

-- Шаги для траектории "Основы психологии"
-- ВАЖНО: Замени item_id на реальные ID материалов и тестов из твоей БД!

INSERT INTO modx_test_learning_path_steps
    (path_id, step_number, step_type, item_id, name, description, is_required, min_score, estimated_minutes, unlock_condition)
VALUES
    -- Шаг 1: Вводный материал
    (@path1_id, 1, 'material', 1,
     'Что такое психология?',
     'Знакомство с предметом психологии, основными понятиями и направлениями',
     1, NULL, 45, NULL),

    -- Шаг 2: Первый тест
    (@path1_id, 2, 'test', 1,
     'Проверочный тест: Основы психологии',
     'Тест на усвоение базовых понятий психологии',
     1, 70, 20,
     '{"type": "previous_step", "required": true}'),

    -- Шаг 3: Углубленный материал
    (@path1_id, 3, 'material', 2,
     'История развития психологии',
     'От древних философов до современных направлений психологии',
     1, NULL, 60,
     '{"type": "previous_step_score", "min_score": 70}'),

    -- Шаг 4: Методы исследования
    (@path1_id, 4, 'material', 3,
     'Методы психологического исследования',
     'Эксперимент, наблюдение, опрос, тестирование',
     1, NULL, 50,
     '{"type": "previous_step", "required": true}'),

    -- Шаг 5: Итоговый тест
    (@path1_id, 5, 'test', 2,
     'Итоговый тест: Основы психологии',
     'Комплексная проверка знаний по всему курсу',
     1, 80, 30,
     '{"type": "all_previous_steps", "required": true}');


-- ============================================
-- 2. ТРАЕКТОРИЯ "Продвинутая когнитивная психология"
-- ============================================

INSERT INTO modx_test_learning_paths
    (name, description, created_by, status, is_public, difficulty_level, estimated_hours, passing_score)
VALUES
    ('Когнитивная психология',
     'Углубленное изучение процессов восприятия, внимания, памяти и мышления. Для тех, кто уже знаком с основами.',
     @admin_id,
     'published',
     1,
     'advanced',
     35,
     75);

SET @path2_id = LAST_INSERT_ID();

-- Шаги для траектории "Когнитивная психология"

INSERT INTO modx_test_learning_path_steps
    (path_id, step_number, step_type, item_id, name, description, is_required, min_score, estimated_minutes)
VALUES
    (@path2_id, 1, 'material', 4,
     'Введение в когнитивную психологию',
     'История и основные понятия когнитивной психологии',
     1, NULL, 45),

    (@path2_id, 2, 'material', 5,
     'Процессы восприятия',
     'Механизмы восприятия, гештальт-принципы, иллюзии',
     1, NULL, 60),

    (@path2_id, 3, 'test', 3,
     'Тест: Восприятие',
     'Проверка знаний о процессах восприятия',
     1, 75, 25),

    (@path2_id, 4, 'material', 6,
     'Внимание и его виды',
     'Произвольное и непроизвольное внимание, концентрация, распределение',
     1, NULL, 55),

    (@path2_id, 5, 'material', 7,
     'Память: виды и механизмы',
     'Кратковременная, долговременная, рабочая память. Процессы запоминания и забывания',
     1, NULL, 70),

    (@path2_id, 6, 'test', 4,
     'Тест: Память и внимание',
     'Комплексная проверка знаний',
     1, 75, 30),

    (@path2_id, 7, 'material', 8,
     'Мышление и решение задач',
     'Виды мышления, этапы решения задач, креативность',
     1, NULL, 65),

    (@path2_id, 8, 'test', 5,
     'Итоговый тест: Когнитивная психология',
     'Финальная проверка по всему курсу',
     1, 80, 40);


-- ============================================
-- 3. ТРАЕКТОРИЯ "Черновик: Психология развития"
-- ============================================

INSERT INTO modx_test_learning_paths
    (name, description, created_by, status, is_public, difficulty_level, estimated_hours, passing_score)
VALUES
    ('Психология развития',
     'Этапы развития человека от рождения до старости. Курс в разработке.',
     @admin_id,
     'draft',
     0,
     'intermediate',
     25,
     70);

SET @path3_id = LAST_INSERT_ID();

-- Только несколько шагов для черновика
INSERT INTO modx_test_learning_path_steps
    (path_id, step_number, step_type, item_id, name, description, is_required, min_score)
VALUES
    (@path3_id, 1, 'material', 9,
     'Введение в возрастную психологию',
     'Основные понятия и периодизация развития',
     1, NULL),

    (@path3_id, 2, 'material', 10,
     'Младенчество и раннее детство',
     'Развитие в первые годы жизни',
     1, NULL);


-- ============================================
-- 4. СОЗДАНИЕ ДОСТИЖЕНИЙ ДЛЯ ТРАЕКТОРИЙ
-- ============================================

-- Достижение за завершение "Основы психологии"
INSERT INTO modx_test_learning_path_achievements
    (path_id, name, description, badge_icon, condition_type, condition_value)
VALUES
    (@path1_id, 'Новичок в психологии',
     'Успешно завершен курс "Основы психологии"',
     'trophy-fill',
     'complete_path',
     NULL),

    (@path1_id, 'Отличник',
     'Завершен курс с результатом выше 90%',
     'star-fill',
     'score_threshold',
     90),

    (@path1_id, 'Спринтер',
     'Курс завершен менее чем за 7 дней',
     'lightning-fill',
     'time_limit',
     7);

-- Достижение за завершение "Когнитивная психология"
INSERT INTO modx_test_learning_path_achievements
    (path_id, name, description, badge_icon, condition_type)
VALUES
    (@path2_id, 'Мастер когнитивистики',
     'Успешно завершен продвинутый курс по когнитивной психологии',
     'award-fill',
     'complete_path');


-- ============================================
-- 5. ТЕСТОВАЯ ЗАПИСЬ ПОЛЬЗОВАТЕЛЯ НА ТРАЕКТОРИЮ
-- ============================================
-- Раскомментируй и замени USER_ID на ID реального пользователя

-- SET @test_user_id = USER_ID;
--
-- INSERT INTO modx_test_learning_path_enrollments
--     (path_id, user_id, enrolled_by)
-- VALUES
--     (@path1_id, @test_user_id, @admin_id);
--
-- -- Триггер автоматически создаст запись прогресса и записи для всех шагов


-- ============================================
-- 6. ПРОВЕРКА СОЗДАННЫХ ДАННЫХ
-- ============================================

SELECT 'Траектории обучения:' as '';
SELECT id, name, status, difficulty_level, estimated_hours,
       (SELECT COUNT(*) FROM modx_test_learning_path_steps WHERE path_id = modx_test_learning_paths.id) as steps_count
FROM modx_test_learning_paths
ORDER BY id;

SELECT '' as '';
SELECT 'Достижения:' as '';
SELECT lpa.name as achievement, lp.name as path_name
FROM modx_test_learning_path_achievements lpa
JOIN modx_test_learning_paths lp ON lp.id = lpa.path_id
ORDER BY lpa.path_id, lpa.id;

SELECT '' as '';
SELECT 'ВАЖНО: Замени item_id в шагах на реальные ID материалов и тестов!' as 'Напоминание';
