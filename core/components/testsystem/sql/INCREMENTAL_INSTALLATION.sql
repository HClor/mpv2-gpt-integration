-- ============================================
-- Test System v2.0 - Incremental Installation
-- MySQL 5.7.21 Compatible
-- ============================================
--
-- Этот скрипт создает ТОЛЬКО новые таблицы спринтов 7-17
-- НЕ удаляет существующие таблицы из спринтов 1-6
--
-- Версия: 2.0 (MySQL 5.7.21)
-- Дата: 2025-11-15
--
-- ============================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- ============================================
-- БАЗОВЫЕ ТАБЛИЦЫ (Sprint 7)
-- ============================================

-- Таблица тестов
CREATE TABLE IF NOT EXISTS `modx_test_tests` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `test_name` VARCHAR(255) NOT NULL COMMENT 'Название теста',
    `description` TEXT COMMENT 'Описание теста',
    `category_id` INT(11) UNSIGNED DEFAULT NULL COMMENT 'ID категории',
    `time_limit` INT(11) DEFAULT NULL COMMENT 'Ограничение времени (минуты)',
    `pass_score` INT(11) DEFAULT 70 COMMENT 'Проходной балл (%)',
    `attempts_allowed` INT(11) DEFAULT 0 COMMENT 'Разрешенное количество попыток',
    `randomize_questions` TINYINT(1) DEFAULT 0,
    `randomize_answers` TINYINT(1) DEFAULT 0,
    `show_correct_answers` TINYINT(1) DEFAULT 1,
    `published` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_by` INT(11) UNSIGNED DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_category` (`category_id`),
    KEY `idx_published` (`published`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица вопросов
CREATE TABLE IF NOT EXISTS `modx_test_questions` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `test_id` INT(11) UNSIGNED NOT NULL,
    `question_text` TEXT NOT NULL,
    `question_type` ENUM('single', 'multiple', 'matching', 'ordering', 'fill_blank', 'essay') DEFAULT 'single',
    `points` INT(11) DEFAULT 1,
    `explanation` TEXT,
    `image_url` VARCHAR(500) DEFAULT NULL,
    `order_num` INT(11) DEFAULT 0,
    `published` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_test` (`test_id`),
    KEY `idx_type` (`question_type`),
    KEY `idx_published` (`published`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица ответов
CREATE TABLE IF NOT EXISTS `modx_test_answers` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `question_id` INT(11) UNSIGNED NOT NULL,
    `answer_text` TEXT NOT NULL,
    `is_correct` TINYINT(1) DEFAULT 0,
    `order_num` INT(11) DEFAULT 0,
    `explanation` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_question` (`question_id`),
    KEY `idx_correct` (`is_correct`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица сессий
CREATE TABLE IF NOT EXISTS `modx_test_sessions` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `test_id` INT(11) UNSIGNED NOT NULL,
    `user_id` INT(11) UNSIGNED NOT NULL,
    `status` ENUM('in_progress', 'completed', 'abandoned', 'expired') DEFAULT 'in_progress',
    `score` DECIMAL(5,2) DEFAULT NULL,
    `total_questions` INT(11) DEFAULT 0,
    `correct_answers` INT(11) DEFAULT 0,
    `started_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `finished_at` DATETIME DEFAULT NULL,
    `time_spent` INT(11) DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_test` (`test_id`),
    KEY `idx_user` (`user_id`),
    KEY `idx_status` (`status`),
    KEY `idx_started` (`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица ответов пользователей
CREATE TABLE IF NOT EXISTS `modx_test_user_answers` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_id` INT(11) UNSIGNED NOT NULL,
    `question_id` INT(11) UNSIGNED NOT NULL,
    `answer_id` INT(11) UNSIGNED DEFAULT NULL,
    `answer_text` TEXT,
    `answer_data` TEXT COMMENT 'JSON данные',
    `is_correct` TINYINT(1) DEFAULT NULL,
    `points_earned` DECIMAL(5,2) DEFAULT 0,
    `answered_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `time_spent` INT(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_session` (`session_id`),
    KEY `idx_question` (`question_id`),
    KEY `idx_correct` (`is_correct`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица избранных вопросов
CREATE TABLE IF NOT EXISTS `modx_test_favorites` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) UNSIGNED NOT NULL,
    `question_id` INT(11) UNSIGNED NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_favorite` (`user_id`, `question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SPRINT 9: УЧЕБНЫЕ МАТЕРИАЛЫ
-- ============================================

CREATE TABLE IF NOT EXISTS `modx_test_learning_materials` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `category_id` INT(11) UNSIGNED DEFAULT NULL,
    `content_type` ENUM('text', 'video', 'document', 'presentation', 'interactive') DEFAULT 'text',
    `status` ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    `sort_order` INT(11) DEFAULT 0,
    `duration_minutes` INT(11) DEFAULT NULL,
    `created_by` INT(11) UNSIGNED NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `published_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_category` (`category_id`),
    KEY `idx_status` (`status`),
    KEY `idx_sort` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `modx_test_learning_content` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `material_id` INT(11) UNSIGNED NOT NULL,
    `block_type` ENUM('text', 'heading', 'image', 'video', 'code', 'quote', 'list') DEFAULT 'text',
    `content_html` LONGTEXT,
    `content_data` TEXT COMMENT 'JSON данные',
    `sort_order` INT(11) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_material` (`material_id`),
    KEY `idx_sort` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `modx_test_learning_attachments` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `material_id` INT(11) UNSIGNED NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `file_type` VARCHAR(100),
    `file_size` INT(11) UNSIGNED,
    `download_count` INT(11) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_material` (`material_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `modx_test_material_progress` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) UNSIGNED NOT NULL,
    `material_id` INT(11) UNSIGNED NOT NULL,
    `progress_percent` INT(11) DEFAULT 0,
    `is_completed` TINYINT(1) DEFAULT 0,
    `started_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `completed_at` DATETIME DEFAULT NULL,
    `last_position` TEXT COMMENT 'JSON позиция',
    `time_spent` INT(11) DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_progress` (`user_id`, `material_id`),
    KEY `idx_user` (`user_id`),
    KEY `idx_material` (`material_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `modx_test_material_test_links` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `material_id` INT(11) UNSIGNED NOT NULL,
    `test_id` INT(11) UNSIGNED NOT NULL,
    `link_type` ENUM('prerequisite', 'practice', 'assessment') DEFAULT 'practice',
    `required` TINYINT(1) DEFAULT 0,
    `sort_order` INT(11) DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_material` (`material_id`),
    KEY `idx_test` (`test_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `modx_test_material_tags` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `material_id` INT(11) UNSIGNED NOT NULL,
    `tag` VARCHAR(100) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_material` (`material_id`),
    KEY `idx_tag` (`tag`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SPRINT 10: ПРАВА ДОСТУПА
-- ============================================

CREATE TABLE IF NOT EXISTS `modx_test_category_permissions` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `category_id` INT(11) UNSIGNED NOT NULL,
    `user_id` INT(11) UNSIGNED DEFAULT NULL,
    `user_group_id` INT(11) UNSIGNED DEFAULT NULL,
    `role` ENUM('admin', 'expert', 'viewer') NOT NULL DEFAULT 'viewer',
    `can_view` TINYINT(1) DEFAULT 1,
    `can_edit` TINYINT(1) DEFAULT 0,
    `can_delete` TINYINT(1) DEFAULT 0,
    `can_publish` TINYINT(1) DEFAULT 0,
    `can_manage_users` TINYINT(1) DEFAULT 0,
    `granted_by` INT(11) UNSIGNED DEFAULT NULL,
    `granted_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    PRIMARY KEY (`id`),
    KEY `idx_category` (`category_id`),
    KEY `idx_user` (`user_id`),
    KEY `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `modx_test_category_hierarchy` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `category_id` INT(11) UNSIGNED NOT NULL,
    `parent_id` INT(11) UNSIGNED DEFAULT NULL,
    `path` VARCHAR(500),
    `level` INT(11) DEFAULT 0,
    `inherit_permissions` TINYINT(1) DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_category` (`category_id`),
    KEY `idx_parent` (`parent_id`),
    KEY `idx_path` (`path`(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `modx_test_permission_history` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `permission_id` INT(11) UNSIGNED NOT NULL,
    `action` ENUM('granted', 'revoked', 'modified') NOT NULL,
    `changed_by` INT(11) UNSIGNED NOT NULL,
    `changed_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `old_role` VARCHAR(50),
    `new_role` VARCHAR(50),
    `reason` TEXT,
    PRIMARY KEY (`id`),
    KEY `idx_permission` (`permission_id`),
    KEY `idx_changed_at` (`changed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SPRINT 11: ТРАЕКТОРИИ ОБУЧЕНИЯ
-- ============================================

CREATE TABLE IF NOT EXISTS `modx_test_learning_paths` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `category_id` INT(11) UNSIGNED DEFAULT NULL,
    `difficulty_level` ENUM('beginner', 'intermediate', 'advanced', 'expert') DEFAULT 'beginner',
    `estimated_duration` INT(11) DEFAULT NULL COMMENT 'Часы',
    `is_sequential` TINYINT(1) DEFAULT 1,
    `is_published` TINYINT(1) DEFAULT 0,
    `certificate_template_id` INT(11) UNSIGNED DEFAULT NULL,
    `created_by` INT(11) UNSIGNED NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_category` (`category_id`),
    KEY `idx_published` (`is_published`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `modx_test_path_steps` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `path_id` INT(11) UNSIGNED NOT NULL,
    `step_type` ENUM('material', 'test', 'quiz', 'project', 'survey') NOT NULL,
    `entity_id` INT(11) UNSIGNED NOT NULL,
    `step_order` INT(11) NOT NULL,
    `title` VARCHAR(255),
    `description` TEXT,
    `is_required` TINYINT(1) DEFAULT 1,
    `min_score` INT(11) DEFAULT NULL,
    `unlock_condition` TEXT COMMENT 'JSON условия',
    PRIMARY KEY (`id`),
    KEY `idx_path` (`path_id`),
    KEY `idx_order` (`step_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `modx_test_path_enrollments` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `path_id` INT(11) UNSIGNED NOT NULL,
    `user_id` INT(11) UNSIGNED NOT NULL,
    `enrolled_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `started_at` DATETIME DEFAULT NULL,
    `completed_at` DATETIME DEFAULT NULL,
    `status` ENUM('enrolled', 'in_progress', 'completed', 'abandoned') DEFAULT 'enrolled',
    `progress_percent` DECIMAL(5,2) DEFAULT 0.00,
    `current_step_id` INT(11) UNSIGNED DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_enrollment` (`path_id`, `user_id`),
    KEY `idx_user` (`user_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `modx_test_path_step_completion` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `enrollment_id` INT(11) UNSIGNED NOT NULL,
    `step_id` INT(11) UNSIGNED NOT NULL,
    `completed_at` DATETIME DEFAULT NULL,
    `score` DECIMAL(5,2) DEFAULT NULL,
    `attempts` INT(11) DEFAULT 0,
    `is_passed` TINYINT(1) DEFAULT 0,
    `session_id` INT(11) UNSIGNED DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_completion` (`enrollment_id`, `step_id`),
    KEY `idx_step` (`step_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SPRINT 12: РАСШИРЕННЫЕ ТИПЫ ВОПРОСОВ
-- ============================================

CREATE TABLE IF NOT EXISTS `modx_test_question_matching` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `question_id` INT(11) UNSIGNED NOT NULL,
    `left_item` VARCHAR(500) NOT NULL,
    `right_item` VARCHAR(500) NOT NULL,
    `correct_match_id` INT(11) UNSIGNED DEFAULT NULL,
    `sort_order` INT(11) DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_question` (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `modx_test_question_ordering` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `question_id` INT(11) UNSIGNED NOT NULL,
    `item_text` VARCHAR(500) NOT NULL,
    `correct_order` INT(11) NOT NULL,
    `display_order` INT(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_question` (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `modx_test_question_fillblank` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `question_id` INT(11) UNSIGNED NOT NULL,
    `blank_position` INT(11) NOT NULL,
    `correct_answer` VARCHAR(255) NOT NULL,
    `case_sensitive` TINYINT(1) DEFAULT 0,
    `accept_alternatives` TINYINT(1) DEFAULT 1,
    `alternatives` TEXT COMMENT 'JSON массив',
    PRIMARY KEY (`id`),
    KEY `idx_question` (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `modx_test_essay_reviews` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `answer_id` INT(11) UNSIGNED NOT NULL,
    `reviewer_id` INT(11) UNSIGNED NOT NULL,
    `score` DECIMAL(5,2) DEFAULT NULL,
    `feedback` TEXT,
    `reviewed_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `status` ENUM('pending', 'approved', 'rejected', 'needs_revision') DEFAULT 'pending',
    PRIMARY KEY (`id`),
    KEY `idx_answer` (`answer_id`),
    KEY `idx_reviewer` (`reviewer_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SPRINT 13: ГЕЙМИФИКАЦИЯ
-- ============================================

CREATE TABLE IF NOT EXISTS `modx_test_achievements` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `icon` VARCHAR(255),
    `achievement_type` ENUM('test_count', 'perfect_score', 'streak', 'category_master', 'speed_demon', 'first_place', 'custom') NOT NULL,
    `condition_data` TEXT COMMENT 'JSON условия',
    `xp_reward` INT(11) DEFAULT 0,
    `rarity` ENUM('common', 'uncommon', 'rare', 'epic', 'legendary') DEFAULT 'common',
    `is_active` TINYINT(1) DEFAULT 1,
    PRIMARY KEY (`id`),
    KEY `idx_type` (`achievement_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `modx_test_user_achievements` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) UNSIGNED NOT NULL,
    `achievement_id` INT(11) UNSIGNED NOT NULL,
    `earned_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `progress_data` TEXT COMMENT 'JSON прогресс',
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_achievement` (`user_id`, `achievement_id`),
    KEY `idx_user` (`user_id`),
    KEY `idx_earned` (`earned_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `modx_test_user_experience` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) UNSIGNED NOT NULL,
    `total_xp` INT(11) DEFAULT 0,
    `current_level` INT(11) DEFAULT 1,
    `xp_to_next_level` INT(11) DEFAULT 100,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_user` (`user_id`),
    KEY `idx_level` (`current_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `modx_test_xp_history` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) UNSIGNED NOT NULL,
    `xp_amount` INT(11) NOT NULL,
    `source_type` ENUM('test_completion', 'achievement', 'streak', 'bonus', 'manual') NOT NULL,
    `source_id` INT(11) UNSIGNED DEFAULT NULL,
    `description` VARCHAR(255),
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user` (`user_id`),
    KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `modx_test_user_streaks` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) UNSIGNED NOT NULL,
    `current_streak` INT(11) DEFAULT 0,
    `longest_streak` INT(11) DEFAULT 0,
    `last_activity_date` DATE DEFAULT NULL,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `modx_test_leaderboard` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) UNSIGNED NOT NULL,
    `period` ENUM('all_time', 'yearly', 'monthly', 'weekly') NOT NULL DEFAULT 'all_time',
    `category_id` INT(11) UNSIGNED DEFAULT NULL,
    `total_xp` INT(11) DEFAULT 0,
    `rank` INT(11) DEFAULT 0,
    `tests_completed` INT(11) DEFAULT 0,
    `achievements_count` INT(11) DEFAULT 0,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_leaderboard` (`user_id`, `period`, `category_id`),
    KEY `idx_period` (`period`),
    KEY `idx_rank` (`rank`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `modx_test_level_config` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `level_number` INT(11) NOT NULL,
    `level_name` VARCHAR(100) NOT NULL,
    `xp_required` INT(11) NOT NULL,
    `xp_to_next` INT(11) DEFAULT NULL,
    `rewards` TEXT COMMENT 'JSON награды',
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_level` (`level_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SPRINT 14: УВЕДОМЛЕНИЯ
-- ============================================

CREATE TABLE IF NOT EXISTS `modx_test_notifications` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) UNSIGNED NOT NULL,
    `notification_type` VARCHAR(50) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT,
    `icon` VARCHAR(100),
    `priority` ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    `is_read` TINYINT(1) DEFAULT 0,
    `read_at` DATETIME DEFAULT NULL,
    `related_type` VARCHAR(50),
    `related_id` INT(11) UNSIGNED DEFAULT NULL,
    `action_url` VARCHAR(500),
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_user` (`user_id`),
    KEY `idx_type` (`notification_type`),
    KEY `idx_read` (`is_read`),
    KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `modx_test_notification_templates` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `template_key` VARCHAR(100) NOT NULL,
    `notification_type` VARCHAR(50) NOT NULL,
    `title_template` VARCHAR(255),
    `message_template` TEXT,
    `email_subject` VARCHAR(255),
    `email_body_html` TEXT,
    `email_body_text` TEXT,
    `default_priority` ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    `is_active` TINYINT(1) DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_key` (`template_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `modx_test_notification_preferences` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) UNSIGNED NOT NULL,
    `notification_type` VARCHAR(50) NOT NULL,
    `channel` ENUM('system', 'email', 'push') NOT NULL DEFAULT 'system',
    `is_enabled` TINYINT(1) DEFAULT 1,
    `frequency` ENUM('immediate', 'daily_digest', 'weekly_digest') DEFAULT 'immediate',
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_preference` (`user_id`, `notification_type`, `channel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `modx_test_notification_delivery` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `notification_id` INT(11) UNSIGNED NOT NULL,
    `channel` ENUM('system', 'email', 'push') NOT NULL,
    `status` ENUM('pending', 'sent', 'failed', 'bounced') DEFAULT 'pending',
    `sent_at` DATETIME DEFAULT NULL,
    `error_message` TEXT,
    `attempts` INT(11) DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_notification` (`notification_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `modx_test_notification_queue` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) UNSIGNED NOT NULL,
    `template_key` VARCHAR(100) NOT NULL,
    `variables` TEXT COMMENT 'JSON переменные',
    `channel` ENUM('system', 'email', 'push') NOT NULL DEFAULT 'system',
    `priority` ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    `scheduled_for` DATETIME DEFAULT NULL,
    `status` ENUM('queued', 'processing', 'sent', 'failed') DEFAULT 'queued',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_status` (`status`),
    KEY `idx_scheduled` (`scheduled_for`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SPRINT 15: АНАЛИТИКА
-- ============================================

CREATE TABLE IF NOT EXISTS `modx_test_analytics_cache` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `cache_key` VARCHAR(255) NOT NULL,
    `user_id` INT(11) UNSIGNED DEFAULT NULL,
    `period` VARCHAR(50),
    `metric_data` TEXT COMMENT 'JSON данные',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_cache` (`cache_key`, `user_id`, `period`),
    KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `modx_test_reports` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `report_type` VARCHAR(100) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `filters` TEXT COMMENT 'JSON фильтры',
    `format` ENUM('csv', 'json', 'html', 'pdf') DEFAULT 'csv',
    `file_path` VARCHAR(500),
    `generated_by` INT(11) UNSIGNED NOT NULL,
    `generated_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_type` (`report_type`),
    KEY `idx_generated` (`generated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `modx_test_report_history` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `report_id` INT(11) UNSIGNED NOT NULL,
    `downloaded_by` INT(11) UNSIGNED NOT NULL,
    `downloaded_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `ip_address` VARCHAR(45),
    PRIMARY KEY (`id`),
    KEY `idx_report` (`report_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `modx_test_user_activity_log` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) UNSIGNED NOT NULL,
    `activity_type` VARCHAR(50) NOT NULL,
    `activity_date` DATE NOT NULL,
    `duration_seconds` INT(11) DEFAULT 0,
    `items_count` INT(11) DEFAULT 0,
    `details` TEXT COMMENT 'JSON детали',
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_activity` (`user_id`, `activity_type`, `activity_date`),
    KEY `idx_date` (`activity_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SPRINT 16: СЕРТИФИКАТЫ
-- ============================================

CREATE TABLE IF NOT EXISTS `modx_test_certificate_templates` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `template_html` LONGTEXT,
    `template_css` TEXT,
    `placeholders` TEXT COMMENT 'JSON плейсхолдеры',
    `entity_type` ENUM('test', 'path', 'course', 'achievement') NOT NULL,
    `valid_for_days` INT(11) DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_type` (`entity_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `modx_test_certificates` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `template_id` INT(11) UNSIGNED NOT NULL,
    `user_id` INT(11) UNSIGNED NOT NULL,
    `entity_type` ENUM('test', 'path', 'course', 'achievement') NOT NULL,
    `entity_id` INT(11) UNSIGNED NOT NULL,
    `certificate_number` VARCHAR(100),
    `issued_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME DEFAULT NULL,
    `is_revoked` TINYINT(1) DEFAULT 0,
    `revoked_at` DATETIME DEFAULT NULL,
    `revoked_by` INT(11) UNSIGNED DEFAULT NULL,
    `revoke_reason` TEXT,
    `verification_code` VARCHAR(64),
    `file_path` VARCHAR(500),
    `file_hash` VARCHAR(64),
    `metadata` TEXT COMMENT 'JSON метаданные',
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_cert_number` (`certificate_number`),
    UNIQUE KEY `unique_verification` (`verification_code`),
    KEY `idx_user` (`user_id`),
    KEY `idx_entity` (`entity_type`, `entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `modx_test_certificate_verifications` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `certificate_id` INT(11) UNSIGNED NOT NULL,
    `verified_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `verified_by_ip` VARCHAR(45),
    `verified_by_user` INT(11) UNSIGNED DEFAULT NULL,
    `verification_result` ENUM('valid', 'invalid', 'expired', 'revoked') NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_certificate` (`certificate_id`),
    KEY `idx_verified` (`verified_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `modx_test_certificate_requirements` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `template_id` INT(11) UNSIGNED NOT NULL,
    `requirement_type` ENUM('min_score', 'all_tests_passed', 'path_completed', 'achievement_earned', 'custom') NOT NULL,
    `requirement_value` VARCHAR(255),
    `requirement_data` TEXT COMMENT 'JSON данные',
    `is_required` TINYINT(1) DEFAULT 1,
    PRIMARY KEY (`id`),
    KEY `idx_template` (`template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `modx_test_certificate_signers` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `title` VARCHAR(255),
    `organization` VARCHAR(255),
    `signature_image` VARCHAR(500),
    `is_active` TINYINT(1) DEFAULT 1,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `modx_test_certificate_signatures` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `template_id` INT(11) UNSIGNED NOT NULL,
    `signer_id` INT(11) UNSIGNED NOT NULL,
    `sort_order` INT(11) DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_template` (`template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- НАЧАЛЬНЫЕ ДАННЫЕ
-- ============================================

-- Уровни геймификации
INSERT IGNORE INTO `modx_test_level_config` (`level_number`, `level_name`, `xp_required`, `xp_to_next`) VALUES
(1, 'Новичок', 0, 100),
(2, 'Ученик', 100, 200),
(3, 'Практикант', 300, 400),
(4, 'Специалист', 700, 800),
(5, 'Эксперт', 1500, 1500),
(6, 'Мастер', 3000, 3000),
(7, 'Гроссмейстер', 6000, 4000),
(8, 'Легенда', 10000, 6000),
(9, 'Чемпион', 16000, 8000),
(10, 'Титан', 24000, 8000);

-- Базовые достижения
INSERT IGNORE INTO `modx_test_achievements` (`name`, `description`, `achievement_type`, `condition_data`, `xp_reward`, `rarity`) VALUES
('Первые шаги', 'Пройдите первый тест', 'test_count', '{"count": 1}', 10, 'common'),
('Десятка', 'Пройдите 10 тестов', 'test_count', '{"count": 10}', 50, 'uncommon'),
('Сотня', 'Пройдите 100 тестов', 'test_count', '{"count": 100}', 200, 'rare'),
('Перфекционист', 'Наберите 100% в тесте', 'perfect_score', '{}', 25, 'uncommon'),
('Неделя подряд', 'Активность 7 дней подряд', 'streak', '{"days": 7}', 30, 'uncommon'),
('Месяц подряд', 'Активность 30 дней подряд', 'streak', '{"days": 30}', 150, 'epic'),
('Мастер категории', 'Пройдите все тесты в категории', 'category_master', '{}', 100, 'rare'),
('Скорость света', 'Пройдите тест за половину времени', 'speed_demon', '{"time_ratio": 0.5}', 40, 'rare');

-- Шаблоны уведомлений
INSERT IGNORE INTO `modx_test_notification_templates`
(`template_key`, `notification_type`, `title_template`, `message_template`, `email_subject`, `default_priority`) VALUES
('test_completed', 'test_completed', 'Тест завершен', 'Вы завершили тест "[[+test_name]]" с результатом [[+score]]%', 'Результаты теста: [[+test_name]]', 'normal'),
('achievement_earned', 'achievement_earned', 'Новое достижение!', 'Вы получили достижение "[[+achievement_name]]"', 'Получено новое достижение!', 'high'),
('level_up', 'level_up', 'Новый уровень!', 'Поздравляем! Вы достигли уровня [[+level]]', 'Повышение уровня!', 'high'),
('certificate_issued', 'certificate_issued', 'Сертификат выдан', 'Вам выдан сертификат за "[[+entity_name]]"', 'Ваш сертификат готов', 'high');

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- ВЕРИФИКАЦИЯ
-- ============================================

SELECT COUNT(*) as 'Tables Created'
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME LIKE 'modx_test%';

SELECT '✓ Incremental installation completed!' as 'Status';
