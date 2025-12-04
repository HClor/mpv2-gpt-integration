// ============================================
// НОВЫЕ API МЕТОДЫ ДЛЯ УПРАВЛЕНИЯ ЭКСПЕРТАМИ КАТЕГОРИЙ
// Вставить после deleteKnowledgeArea в testsystem.php
// ============================================

        case 'assignCategoryExpert':
            // Назначить эксперта на категорию
            PermissionHelper::requireAuthentication($modx, 'Login required');

            // Только админы могут назначать экспертов
            if (!PermissionHelper::isAdmin($modx)) {
                throw new PermissionException('Access denied. Admin only.');
            }

            $userId = PermissionHelper::getCurrentUserId($modx);

            // Валидация
            $categoryId = ValidationHelper::requireInt($data, 'category_id', 'Category ID required');
            $expertUserId = ValidationHelper::requireInt($data, 'expert_user_id', 'Expert user ID required');
            $canManageTests = ValidationHelper::optionalBool($data, 'can_manage_tests', true);
            $canManageQuestions = ValidationHelper::optionalBool($data, 'can_manage_questions', true);
            $canApprove = ValidationHelper::optionalBool($data, 'can_approve', false);

            // Проверяем что категория существует
            $stmt = $modx->prepare("SELECT id FROM modx_test_categories WHERE id = ?");
            $stmt->execute([$categoryId]);
            if (!$stmt->fetch()) {
                throw new Exception('Category not found');
            }

            // Проверяем что пользователь существует и является экспертом
            $stmt = $modx->prepare("
                SELECT u.id, u.username
                FROM modx_users u
                JOIN modx_member_groups mg ON mg.member = u.id
                JOIN modx_membergroup_names mgn ON mgn.id = mg.user_group
                WHERE u.id = ? AND mgn.name = 'LMS Experts'
            ");
            $stmt->execute([$expertUserId]);
            $expert = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$expert) {
                throw new Exception('User not found or not an expert');
            }

            // Назначаем эксперта (INSERT or UPDATE)
            $stmt = $modx->prepare("
                INSERT INTO modx_test_category_experts
                (category_id, user_id, assigned_by, can_manage_tests, can_manage_questions, can_approve)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    can_manage_tests = VALUES(can_manage_tests),
                    can_manage_questions = VALUES(can_manage_questions),
                    can_approve = VALUES(can_approve),
                    assigned_by = VALUES(assigned_by),
                    assigned_at = CURRENT_TIMESTAMP
            ");

            $stmt->execute([
                $categoryId,
                $expertUserId,
                $userId,
                $canManageTests ? 1 : 0,
                $canManageQuestions ? 1 : 0,
                $canApprove ? 1 : 0
            ]);

            $response = ResponseHelper::success(null, 'Expert assigned successfully');
            break;

        case 'removeCategoryExpert':
            // Убрать эксперта из категории
            PermissionHelper::requireAuthentication($modx, 'Login required');

            if (!PermissionHelper::isAdmin($modx)) {
                throw new PermissionException('Access denied. Admin only.');
            }

            $categoryId = ValidationHelper::requireInt($data, 'category_id', 'Category ID required');
            $expertUserId = ValidationHelper::requireInt($data, 'expert_user_id', 'Expert user ID required');

            $stmt = $modx->prepare("
                DELETE FROM modx_test_category_experts
                WHERE category_id = ? AND user_id = ?
            ");
            $stmt->execute([$categoryId, $expertUserId]);

            $response = ResponseHelper::success(null, 'Expert removed from category');
            break;

        case 'getCategoryExperts':
            // Получить список экспертов категории
            PermissionHelper::requireAuthentication($modx, 'Login required');

            $categoryId = ValidationHelper::requireInt($data, 'category_id', 'Category ID required');

            $stmt = $modx->prepare("
                SELECT
                    ce.id,
                    ce.user_id,
                    u.username,
                    up.email,
                    ce.can_manage_tests,
                    ce.can_manage_questions,
                    ce.can_approve,
                    ce.assigned_at,
                    au.username as assigned_by_username
                FROM modx_test_category_experts ce
                JOIN modx_users u ON u.id = ce.user_id
                LEFT JOIN modx_user_attributes up ON up.internalKey = u.id
                LEFT JOIN modx_users au ON au.id = ce.assigned_by
                WHERE ce.category_id = ?
                ORDER BY u.username
            ");
            $stmt->execute([$categoryId]);
            $experts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response = ResponseHelper::success($experts);
            break;

        case 'getUserCategories':
            // Получить категории текущего пользователя (где он эксперт)
            PermissionHelper::requireAuthentication($modx, 'Login required');

            $userId = PermissionHelper::getCurrentUserId($modx);

            // Админы видят все категории
            if (PermissionHelper::isAdmin($modx)) {
                $stmt = $modx->prepare("
                    SELECT c.*,
                           COUNT(DISTINCT t.id) as tests_count,
                           1 as can_manage_tests,
                           1 as can_manage_questions,
                           1 as can_approve
                    FROM modx_test_categories c
                    LEFT JOIN modx_test_tests t ON t.category_id = c.id
                    GROUP BY c.id
                    ORDER BY c.name
                ");
                $stmt->execute();
            } else {
                // Эксперты видят только свои категории
                $stmt = $modx->prepare("
                    SELECT c.*,
                           COUNT(DISTINCT t.id) as tests_count,
                           ce.can_manage_tests,
                           ce.can_manage_questions,
                           ce.can_approve
                    FROM modx_test_category_experts ce
                    JOIN modx_test_categories c ON c.id = ce.category_id
                    LEFT JOIN modx_test_tests t ON t.category_id = c.id
                    WHERE ce.user_id = ?
                    GROUP BY c.id, ce.can_manage_tests, ce.can_manage_questions, ce.can_approve
                    ORDER BY c.name
                ");
                $stmt->execute([$userId]);
            }

            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response = ResponseHelper::success($categories);
            break;

        case 'checkCategoryPermission':
            // Проверить права пользователя на категорию
            PermissionHelper::requireAuthentication($modx, 'Login required');

            $userId = PermissionHelper::getCurrentUserId($modx);
            $categoryId = ValidationHelper::requireInt($data, 'category_id', 'Category ID required');

            // Админы имеют все права
            if (PermissionHelper::isAdmin($modx)) {
                $response = ResponseHelper::success([
                    'has_access' => true,
                    'can_manage_tests' => true,
                    'can_manage_questions' => true,
                    'can_approve' => true,
                    'role' => 'admin'
                ]);
                break;
            }

            // Проверяем права эксперта
            $stmt = $modx->prepare("
                SELECT
                    can_manage_tests,
                    can_manage_questions,
                    can_approve
                FROM modx_test_category_experts
                WHERE category_id = ? AND user_id = ?
            ");
            $stmt->execute([$categoryId, $userId]);
            $permissions = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($permissions) {
                $response = ResponseHelper::success([
                    'has_access' => true,
                    'can_manage_tests' => (bool)$permissions['can_manage_tests'],
                    'can_manage_questions' => (bool)$permissions['can_manage_questions'],
                    'can_approve' => (bool)$permissions['can_approve'],
                    'role' => 'expert'
                ]);
            } else {
                $response = ResponseHelper::success([
                    'has_access' => false,
                    'can_manage_tests' => false,
                    'can_manage_questions' => false,
                    'can_approve' => false,
                    'role' => 'student'
                ]);
            }
            break;

        case 'getAvailableExperts':
            // Получить список доступных экспертов для назначения
            PermissionHelper::requireAuthentication($modx, 'Login required');

            if (!PermissionHelper::isAdmin($modx)) {
                throw new PermissionException('Access denied. Admin only.');
            }

            // Получаем всех пользователей с ролью Expert
            $stmt = $modx->prepare("
                SELECT DISTINCT
                    u.id,
                    u.username,
                    up.email,
                    up.fullname
                FROM modx_users u
                JOIN modx_user_attributes up ON up.internalKey = u.id
                JOIN modx_member_groups mg ON mg.member = u.id
                JOIN modx_membergroup_names mgn ON mgn.id = mg.user_group
                WHERE mgn.name = 'LMS Experts'
                AND up.blocked = 0
                ORDER BY u.username
            ");
            $stmt->execute();
            $experts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response = ResponseHelper::success($experts);
            break;
