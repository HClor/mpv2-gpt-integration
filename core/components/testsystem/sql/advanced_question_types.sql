-- ============================================
-- Test System Advanced Question Types
-- Sprint 12: Расширенные типы вопросов
-- ============================================

-- Обновление типов вопросов в существующей таблице
ALTER TABLE `modx_test_questions`
    MODIFY COLUMN `question_type` ENUM('single', 'multiple', 'matching', 'ordering', 'fill_blank', 'essay')
    DEFAULT 'single'
    COMMENT 'Тип вопроса: single, multiple, matching, ordering, fill_blank, essay';

-- Таблица для вопросов типа matching (сопоставление)
CREATE TABLE IF NOT EXISTS `modx_test_question_matching_pairs` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `question_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID вопроса',
    `left_item` VARCHAR(500) NOT NULL COMMENT 'Левый элемент (термин)',
    `right_item` VARCHAR(500) NOT NULL COMMENT 'Правый элемент (определение)',
    `sort_order` INT(11) DEFAULT 0 COMMENT 'Порядок отображения',
    PRIMARY KEY (`id`),
    KEY `idx_question` (`question_id`),
    CONSTRAINT `fk_matching_question` FOREIGN KEY (`question_id`)
        REFERENCES `modx_test_questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Пары для вопросов типа matching';

-- Таблица для вопросов типа ordering (упорядочивание)
CREATE TABLE IF NOT EXISTS `modx_test_question_ordering_items` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `question_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID вопроса',
    `item_text` TEXT NOT NULL COMMENT 'Текст элемента',
    `correct_position` INT(11) NOT NULL COMMENT 'Правильная позиция (1, 2, 3...)',
    PRIMARY KEY (`id`),
    KEY `idx_question` (`question_id`),
    KEY `idx_position` (`question_id`, `correct_position`),
    CONSTRAINT `fk_ordering_question` FOREIGN KEY (`question_id`)
        REFERENCES `modx_test_questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Элементы для вопросов типа ordering';

-- Таблица для вопросов типа fill_blank (заполнение пропусков)
CREATE TABLE IF NOT EXISTS `modx_test_question_fill_blanks` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `question_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID вопроса',
    `template_text` TEXT NOT NULL COMMENT 'Текст с маркерами пропусков {{1}}, {{2}} и т.д.',
    `case_sensitive` TINYINT(1) DEFAULT 0 COMMENT 'Учитывать регистр',
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_question` (`question_id`),
    CONSTRAINT `fk_fillblank_question` FOREIGN KEY (`question_id`)
        REFERENCES `modx_test_questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Шаблоны для вопросов типа fill_blank';

-- Таблица правильных ответов для пропусков
CREATE TABLE IF NOT EXISTS `modx_test_question_fill_blank_answers` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `fill_blank_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID записи fill_blank',
    `blank_number` INT(11) NOT NULL COMMENT 'Номер пропуска (1, 2, 3...)',
    `correct_answer` VARCHAR(500) NOT NULL COMMENT 'Правильный ответ',
    `alternative_answers` TEXT COMMENT 'Альтернативные правильные ответы (JSON массив)',
    PRIMARY KEY (`id`),
    KEY `idx_fillblank` (`fill_blank_id`),
    KEY `idx_blank_number` (`fill_blank_id`, `blank_number`),
    CONSTRAINT `fk_fillblank_answer` FOREIGN KEY (`fill_blank_id`)
        REFERENCES `modx_test_question_fill_blanks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Правильные ответы для пропусков';

-- Таблица для вопросов типа essay (эссе)
CREATE TABLE IF NOT EXISTS `modx_test_question_essays` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `question_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID вопроса',
    `min_words` INT(11) DEFAULT NULL COMMENT 'Минимальное количество слов',
    `max_words` INT(11) DEFAULT NULL COMMENT 'Максимальное количество слов',
    `rubric` TEXT COMMENT 'Критерии оценивания (для проверяющего)',
    `auto_check_keywords` TEXT COMMENT 'Ключевые слова для автопроверки (JSON)',
    `max_score` INT(11) DEFAULT 10 COMMENT 'Максимальный балл',
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_question` (`question_id`),
    CONSTRAINT `fk_essay_question` FOREIGN KEY (`question_id`)
        REFERENCES `modx_test_questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Настройки для вопросов типа essay';

-- Таблица ручной проверки эссе
CREATE TABLE IF NOT EXISTS `modx_test_essay_reviews` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_answer_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID ответа пользователя',
    `question_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID вопроса',
    `session_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID сессии',
    `user_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID студента',
    `essay_text` TEXT NOT NULL COMMENT 'Текст эссе',
    `word_count` INT(11) DEFAULT 0 COMMENT 'Количество слов',
    `status` ENUM('pending', 'reviewing', 'reviewed') DEFAULT 'pending' COMMENT 'Статус проверки',
    `score` DECIMAL(5,2) DEFAULT NULL COMMENT 'Полученный балл',
    `reviewer_id` INT(11) UNSIGNED DEFAULT NULL COMMENT 'ID проверяющего',
    `reviewer_comment` TEXT COMMENT 'Комментарий проверяющего',
    `reviewed_at` DATETIME DEFAULT NULL COMMENT 'Дата проверки',
    `submitted_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Дата отправки',
    PRIMARY KEY (`id`),
    KEY `idx_user_answer` (`user_answer_id`),
    KEY `idx_session` (`session_id`),
    KEY `idx_user` (`user_id`),
    KEY `idx_status` (`status`),
    KEY `idx_reviewer` (`reviewer_id`),
    CONSTRAINT `fk_essay_review_user_answer` FOREIGN KEY (`user_answer_id`)
        REFERENCES `modx_test_user_answers` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_essay_review_question` FOREIGN KEY (`question_id`)
        REFERENCES `modx_test_questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Ручная проверка эссе';

-- Таблица для хранения ответов пользователей на новые типы вопросов
-- Используется JSON поле answer_data в modx_test_user_answers

-- Добавляем поле для хранения структурированных ответов
ALTER TABLE `modx_test_user_answers`
    ADD COLUMN IF NOT EXISTS `answer_data` JSON DEFAULT NULL COMMENT 'Структурированные данные ответа (для новых типов вопросов)';

-- Индексы для производительности
ALTER TABLE `modx_test_question_matching_pairs`
    ADD INDEX `idx_question_sort` (`question_id`, `sort_order`);

ALTER TABLE `modx_test_essay_reviews`
    ADD INDEX `idx_pending_reviews` (`status`, `submitted_at`);

-- ============================================
-- Описание форматов данных
-- ============================================

/*
MATCHING - Сопоставление:
Таблица test_question_matching_pairs содержит пары (левый-правый элемент).
При отображении правые элементы перемешиваются.

Пример:
left_item: "CPU", right_item: "Центральный процессор"
left_item: "RAM", right_item: "Оперативная память"

answer_data в user_answers:
{
    "type": "matching",
    "pairs": [
        {"left_id": 1, "right_id": 2},
        {"left_id": 2, "right_id": 1}
    ]
}

Правильный ответ: каждый left_item должен быть сопоставлен со своим right_item.
*/

/*
ORDERING - Упорядочивание:
Таблица test_question_ordering_items содержит элементы с correct_position.

Пример:
item_text: "Родился Пушкин", correct_position: 1
item_text: "Написал Евгения Онегина", correct_position: 2
item_text: "Погиб на дуэли", correct_position: 3

answer_data в user_answers:
{
    "type": "ordering",
    "order": [3, 1, 2]  // ID элементов в порядке, указанном пользователем
}

Правильный ответ: элементы должны быть в порядке correct_position.
*/

/*
FILL_BLANK - Заполнение пропусков:
Таблица test_question_fill_blanks содержит текст с маркерами {{1}}, {{2}}.
Таблица test_question_fill_blank_answers содержит правильные ответы.

Пример:
template_text: "Столица России - {{1}}, а Франции - {{2}}."

blank_number: 1, correct_answer: "Москва", alternative_answers: ["москва", "МОСКВА"]
blank_number: 2, correct_answer: "Париж", alternative_answers: ["париж"]

answer_data в user_answers:
{
    "type": "fill_blank",
    "answers": {
        "1": "Москва",
        "2": "Париж"
    }
}

Правильный ответ: каждый пропуск должен быть заполнен правильно.
Проверка с учетом alternative_answers и case_sensitive флага.
*/

/*
ESSAY - Эссе:
Таблица test_question_essays содержит настройки (мин/макс слова, критерии).
Таблица test_essay_reviews содержит отправленные эссе и результаты проверки.

answer_data в user_answers:
{
    "type": "essay",
    "text": "Текст эссе...",
    "word_count": 150
}

Проверка:
1. Автоматическая: проверка количества слов, наличие ключевых слов
2. Ручная: эксперт выставляет балл и комментарий
*/

-- ============================================
-- Примеры использования
-- ============================================

/*
-- MATCHING вопрос
INSERT INTO modx_test_questions (test_id, question_text, question_type, published)
VALUES (1, 'Сопоставьте термины с определениями:', 'matching', 1);

SET @question_id = LAST_INSERT_ID();

INSERT INTO modx_test_question_matching_pairs (question_id, left_item, right_item, sort_order)
VALUES
    (@question_id, 'HTML', 'Язык разметки гипертекста', 1),
    (@question_id, 'CSS', 'Каскадные таблицы стилей', 2),
    (@question_id, 'JavaScript', 'Язык программирования для веб', 3);

-- ORDERING вопрос
INSERT INTO modx_test_questions (test_id, question_text, question_type, published)
VALUES (1, 'Расположите события в хронологическом порядке:', 'ordering', 1);

SET @question_id = LAST_INSERT_ID();

INSERT INTO modx_test_question_ordering_items (question_id, item_text, correct_position)
VALUES
    (@question_id, 'Октябрьская революция', 1),
    (@question_id, 'Создание СССР', 2),
    (@question_id, 'Начало ВОВ', 3);

-- FILL_BLANK вопрос
INSERT INTO modx_test_questions (test_id, question_text, question_type, published)
VALUES (1, 'Заполните пропуски:', 'fill_blank', 1);

SET @question_id = LAST_INSERT_ID();

INSERT INTO modx_test_question_fill_blanks (question_id, template_text, case_sensitive)
VALUES (@question_id, 'Столица России - {{1}}, а Франции - {{2}}.', 0);

SET @fillblank_id = LAST_INSERT_ID();

INSERT INTO modx_test_question_fill_blank_answers (fill_blank_id, blank_number, correct_answer, alternative_answers)
VALUES
    (@fillblank_id, 1, 'Москва', '["москва", "МОСКВА"]'),
    (@fillblank_id, 2, 'Париж', '["париж", "Paris"]');

-- ESSAY вопрос
INSERT INTO modx_test_questions (test_id, question_text, question_type, published)
VALUES (1, 'Опишите основные принципы ООП', 'essay', 1);

SET @question_id = LAST_INSERT_ID();

INSERT INTO modx_test_question_essays (question_id, min_words, max_words, rubric, max_score)
VALUES (
    @question_id,
    100,
    500,
    'Критерии: полнота ответа, примеры, структура',
    10
);
*/

-- ============================================
-- Примечания
-- ============================================

/*
1. Для matching и ordering используется стандартная таблица answers для совместимости
2. answer_data в user_answers хранит JSON с детальной информацией ответа
3. Essay требует ручной проверки через интерфейс для экспертов
4. fill_blank поддерживает несколько правильных вариантов ответа
5. Все новые типы полностью интегрированы с существующей системой сессий и подсчета баллов
*/
