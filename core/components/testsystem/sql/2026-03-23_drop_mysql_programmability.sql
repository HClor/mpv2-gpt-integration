-- Удаление MySQL views / triggers / procedures после переноса логики в PHP
-- Дата: 2026-03-23
-- Выполнять вручную через PHPMyAdmin после выката PHP-кода.

-- Views
DROP VIEW IF EXISTS `modx_test_user_statistics`;
DROP VIEW IF EXISTS `modx_test_test_statistics`;
DROP VIEW IF EXISTS `modx_test_question_statistics`;
DROP VIEW IF EXISTS `modx_test_category_statistics`;

-- Triggers
DROP TRIGGER IF EXISTS `trg_category_permission_grant`;
DROP TRIGGER IF EXISTS `trg_category_permission_modify`;
DROP TRIGGER IF EXISTS `trg_category_permission_revoke`;
DROP TRIGGER IF EXISTS `trg_enrollment_create_progress`;
DROP TRIGGER IF EXISTS `trg_step_completion_update_progress`;
DROP TRIGGER IF EXISTS `trg_session_complete_award_xp`;
DROP TRIGGER IF EXISTS `trg_xp_update_level`;
DROP TRIGGER IF EXISTS `trg_achievement_notify`;
DROP TRIGGER IF EXISTS `trg_level_up_notify`;
DROP TRIGGER IF EXISTS `trg_essay_reviewed_notify`;
DROP TRIGGER IF EXISTS `trg_cert_generate_number`;
DROP TRIGGER IF EXISTS `trg_cert_generate_verification`;
DROP TRIGGER IF EXISTS `trg_cert_set_expiration`;
DROP TRIGGER IF EXISTS `trg_cert_issue_notify`;

-- Stored procedures
DROP PROCEDURE IF EXISTS `update_user_streak`;
DROP PROCEDURE IF EXISTS `process_notification_queue`;
DROP PROCEDURE IF EXISTS `cleanup_old_notifications`;
DROP PROCEDURE IF EXISTS `update_user_analytics_cache`;
DROP PROCEDURE IF EXISTS `update_test_analytics_cache`;
DROP PROCEDURE IF EXISTS `get_hardest_questions`;
DROP PROCEDURE IF EXISTS `get_cohort_analysis`;
DROP PROCEDURE IF EXISTS `cleanup_analytics_cache`;
DROP PROCEDURE IF EXISTS `get_user_activity_summary`;
DROP PROCEDURE IF EXISTS `verify_certificate`;
DROP PROCEDURE IF EXISTS `cleanup_expired_certificates`;
DROP PROCEDURE IF EXISTS `get_certificate_statistics`;
