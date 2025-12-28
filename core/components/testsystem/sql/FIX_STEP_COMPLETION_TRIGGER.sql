-- ============================================
-- FIX: Удаление проблемного триггера
-- ============================================
-- Триггер trg_step_completion_update_progress пытается обновить таблицу
-- modx_test_learning_path_step_completion из триггера на ту же таблицу,
-- что вызывает ошибку MySQL 1442:
-- "Can't update table in stored function/trigger because it is already used by statement"
--
-- Решение: логика перенесена в PHP (LearningPathService::completeStep)
-- ============================================

-- Удаляем проблемный триггер
DROP TRIGGER IF EXISTS `trg_step_completion_update_progress`;

-- Примечание: триггер trg_enrollment_create_progress оставляем - он работает корректно
-- (создает записи в step_completion при записи на траекторию)
