-- Проверка структуры таблицы modx_test_knowledge_areas
-- Выполните этот скрипт для проверки и обновления структуры

-- 1. Показать текущую структуру таблицы
DESCRIBE modx_test_knowledge_areas;

-- 2. Показать данные в таблице
SELECT * FROM modx_test_knowledge_areas LIMIT 5;

-- 3. Добавить недостающее поле min_questions_per_test (если его нет)
ALTER TABLE modx_test_knowledge_areas
ADD COLUMN IF NOT EXISTS `min_questions_per_test` INT(11) DEFAULT 3
COMMENT 'Минимальное количество вопросов из каждого теста'
AFTER `question_distribution_mode`;
