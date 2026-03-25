-- Добавляет флаг гостевого прохождения для тестов
ALTER TABLE `modx_test_tests`
    ADD COLUMN `allow_guest_pass` TINYINT(1) NOT NULL DEFAULT 0 AFTER `publication_status`;

CREATE INDEX `idx_allow_guest_pass` ON `modx_test_tests` (`allow_guest_pass`);
