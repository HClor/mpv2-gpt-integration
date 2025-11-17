<?php
/**
 * Question Type Service
 *
 * Сервис для работы с различными типами вопросов
 *
 * @package TestSystem
 * @version 1.0
 * @created 2025-11-15
 */

class QuestionTypeService
{
    /**
     * Проверка ответа на вопрос типа matching
     *
     * @param modX $modx
     * @param int $questionId
     * @param array $userAnswer Массив пар {"left_id": X, "right_id": Y}
     * @return bool
     */
    public static function checkMatchingAnswer($modx, $questionId, $userAnswer)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        // Получаем правильные пары
        $stmt = $modx->prepare("SELECT id, left_item, right_item
                                FROM {$prefix}test_question_matching_pairs
                                WHERE question_id = ?
                                ORDER BY sort_order");
        $stmt->execute([$questionId]);
        $correctPairs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($correctPairs)) {
            return false;
        }

        // Создаем мапу правильных ответов: left_id => right_id
        $correctMap = [];
        foreach ($correctPairs as $pair) {
            $correctMap[$pair['id']] = $pair['id'];
        }

        // Проверяем ответ пользователя
        if (!is_array($userAnswer) || !isset($userAnswer['pairs'])) {
            return false;
        }

        $userPairs = $userAnswer['pairs'];

        // Все пары должны быть сопоставлены правильно
        $correctCount = 0;
        foreach ($userPairs as $userPair) {
            if (!isset($userPair['left_id']) || !isset($userPair['right_id'])) {
                continue;
            }

            $leftId = (int)$userPair['left_id'];
            $rightId = (int)$userPair['right_id'];

            // Для matching каждый левый элемент должен быть сопоставлен со своим правым
            if (isset($correctMap[$leftId]) && $correctMap[$leftId] === $rightId) {
                $correctCount++;
            }
        }

        return $correctCount === count($correctPairs);
    }

    /**
     * Проверка ответа на вопрос типа ordering
     *
     * @param modX $modx
     * @param int $questionId
     * @param array $userAnswer Массив ID элементов в указанном порядке
     * @return bool
     */
    public static function checkOrderingAnswer($modx, $questionId, $userAnswer)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        // Получаем правильный порядок
        $stmt = $modx->prepare("SELECT id, correct_position
                                FROM {$prefix}test_question_ordering_items
                                WHERE question_id = ?
                                ORDER BY correct_position");
        $stmt->execute([$questionId]);
        $correctItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($correctItems)) {
            return false;
        }

        // Проверяем ответ пользователя
        if (!is_array($userAnswer) || !isset($userAnswer['order'])) {
            return false;
        }

        $userOrder = $userAnswer['order'];

        // Порядок должен совпадать с correct_position
        if (count($userOrder) !== count($correctItems)) {
            return false;
        }

        for ($i = 0; $i < count($correctItems); $i++) {
            if ((int)$userOrder[$i] !== (int)$correctItems[$i]['id']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Проверка ответа на вопрос типа fill_blank
     *
     * @param modX $modx
     * @param int $questionId
     * @param array $userAnswer Массив ответов {"1": "ответ1", "2": "ответ2"}
     * @return array ['is_correct' => bool, 'details' => array]
     */
    public static function checkFillBlankAnswer($modx, $questionId, $userAnswer)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        // Получаем настройки и правильные ответы
        $stmt = $modx->prepare("SELECT fb.id, fb.case_sensitive,
                                       fba.blank_number, fba.correct_answer, fba.alternative_answers
                                FROM {$prefix}test_question_fill_blanks fb
                                JOIN {$prefix}test_question_fill_blank_answers fba ON fba.fill_blank_id = fb.id
                                WHERE fb.question_id = ?
                                ORDER BY fba.blank_number");
        $stmt->execute([$questionId]);
        $blanks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($blanks)) {
            return ['is_correct' => false, 'details' => []];
        }

        $caseSensitive = (bool)$blanks[0]['case_sensitive'];

        // Проверяем каждый пропуск
        if (!is_array($userAnswer) || !isset($userAnswer['answers'])) {
            return ['is_correct' => false, 'details' => []];
        }

        $userAnswers = $userAnswer['answers'];
        $details = [];
        $totalBlanks = count($blanks);
        $correctBlanks = 0;

        foreach ($blanks as $blank) {
            $blankNum = $blank['blank_number'];
            $userText = $userAnswers[$blankNum] ?? '';

            $correctAnswer = $blank['correct_answer'];
            $alternatives = !empty($blank['alternative_answers'])
                ? json_decode($blank['alternative_answers'], true)
                : [];

            if (!is_array($alternatives)) {
                $alternatives = [];
            }

            // Добавляем основной ответ в список для проверки
            $allCorrectAnswers = array_merge([$correctAnswer], $alternatives);

            // Проверяем с учетом регистра
            $isCorrect = false;
            foreach ($allCorrectAnswers as $correct) {
                if ($caseSensitive) {
                    if (trim($userText) === trim($correct)) {
                        $isCorrect = true;
                        break;
                    }
                } else {
                    if (mb_strtolower(trim($userText)) === mb_strtolower(trim($correct))) {
                        $isCorrect = true;
                        break;
                    }
                }
            }

            if ($isCorrect) {
                $correctBlanks++;
            }

            $details[$blankNum] = [
                'user_answer' => $userText,
                'is_correct' => $isCorrect
            ];
        }

        return [
            'is_correct' => ($correctBlanks === $totalBlanks),
            'details' => $details,
            'score' => $totalBlanks > 0 ? round(($correctBlanks / $totalBlanks) * 100) : 0
        ];
    }

    /**
     * Проверка и сохранение ответа на вопрос типа essay
     *
     * @param modX $modx
     * @param int $questionId
     * @param int $sessionId
     * @param int $userId
     * @param int $userAnswerId
     * @param array $userAnswer Текст эссе
     * @return array ['needs_review' => true, 'word_count' => int]
     */
    public static function saveEssayAnswer($modx, $questionId, $sessionId, $userId, $userAnswerId, $userAnswer)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        if (!is_array($userAnswer) || !isset($userAnswer['text'])) {
            throw new Exception('Essay text required');
        }

        $essayText = trim($userAnswer['text']);
        $wordCount = str_word_count($essayText);

        // Получаем настройки вопроса
        $stmt = $modx->prepare("SELECT min_words, max_words, max_score, auto_check_keywords
                                FROM {$prefix}test_question_essays
                                WHERE question_id = ?");
        $stmt->execute([$questionId]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$settings) {
            throw new Exception('Essay settings not found');
        }

        // Проверяем количество слов
        if ($settings['min_words'] && $wordCount < $settings['min_words']) {
            throw new ValidationException("Minimum {$settings['min_words']} words required");
        }

        if ($settings['max_words'] && $wordCount > $settings['max_words']) {
            throw new ValidationException("Maximum {$settings['max_words']} words allowed");
        }

        // Сохраняем эссе для ручной проверки
        $stmt = $modx->prepare("INSERT INTO {$prefix}test_essay_reviews
                                (user_answer_id, question_id, session_id, user_id, essay_text, word_count, status)
                                VALUES (?, ?, ?, ?, ?, ?, 'pending')
                                ON DUPLICATE KEY UPDATE
                                    essay_text = VALUES(essay_text),
                                    word_count = VALUES(word_count),
                                    status = 'pending'");
        $stmt->execute([$userAnswerId, $questionId, $sessionId, $userId, $essayText, $wordCount]);

        return [
            'needs_review' => true,
            'word_count' => $wordCount,
            'status' => 'pending'
        ];
    }

    /**
     * Ручная проверка эссе
     *
     * @param modX $modx
     * @param int $reviewId
     * @param int $reviewerId
     * @param float $score
     * @param string $comment
     * @return bool
     */
    public static function reviewEssay($modx, $reviewId, $reviewerId, $score, $comment = '')
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        // Обновляем review
        $stmt = $modx->prepare("UPDATE {$prefix}test_essay_reviews
                                SET status = 'reviewed',
                                    score = ?,
                                    reviewer_id = ?,
                                    reviewer_comment = ?,
                                    reviewed_at = NOW()
                                WHERE id = ?");

        if (!$stmt->execute([$score, $reviewerId, $comment, $reviewId])) {
            return false;
        }

        // Обновляем балл в user_answers
        $stmt = $modx->prepare("UPDATE {$prefix}test_user_answers ua
                                JOIN {$prefix}test_essay_reviews er ON er.user_answer_id = ua.id
                                SET ua.is_correct = CASE WHEN er.score >= (
                                    SELECT max_score * 0.7 FROM {$prefix}test_question_essays WHERE question_id = ua.question_id
                                ) THEN 1 ELSE 0 END
                                WHERE er.id = ?");
        $stmt->execute([$reviewId]);

        return true;
    }

    /**
     * Получение эссе для проверки
     *
     * @param modX $modx
     * @param array $filters
     * @return array
     */
    public static function getEssaysForReview($modx, $filters = [])
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $where = ['1=1'];
        $params = [];

        if (isset($filters['status'])) {
            $where[] = 'er.status = ?';
            $params[] = $filters['status'];
        }

        if (isset($filters['test_id'])) {
            $where[] = 'ts.test_id = ?';
            $params[] = $filters['test_id'];
        }

        if (isset($filters['reviewer_id'])) {
            $where[] = 'er.reviewer_id = ?';
            $params[] = $filters['reviewer_id'];
        }

        $sql = "SELECT er.*, q.question_text, u.username, t.title as test_title,
                       qe.max_score, qe.rubric
                FROM {$prefix}test_essay_reviews er
                JOIN {$prefix}test_questions q ON q.id = er.question_id
                JOIN {$prefix}users u ON u.id = er.user_id
                JOIN {$prefix}test_sessions ts ON ts.id = er.session_id
                JOIN {$prefix}test_tests t ON t.id = ts.test_id
                LEFT JOIN {$prefix}test_question_essays qe ON qe.question_id = er.question_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY er.submitted_at DESC";

        $stmt = $modx->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Создание данных для вопроса типа matching
     *
     * @param modX $modx
     * @param int $questionId
     * @param array $pairs Массив пар [{"left": "...", "right": "..."}]
     * @return bool
     */
    public static function createMatchingPairs($modx, $questionId, $pairs)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $sortOrder = 1;
        foreach ($pairs as $pair) {
            $stmt = $modx->prepare("INSERT INTO {$prefix}test_question_matching_pairs
                                    (question_id, left_item, right_item, sort_order)
                                    VALUES (?, ?, ?, ?)");
            $stmt->execute([$questionId, $pair['left'], $pair['right'], $sortOrder]);
            $sortOrder++;
        }

        return true;
    }

    /**
     * Создание данных для вопроса типа ordering
     *
     * @param modX $modx
     * @param int $questionId
     * @param array $items Массив элементов [{"text": "...", "position": 1}]
     * @return bool
     */
    public static function createOrderingItems($modx, $questionId, $items)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        foreach ($items as $item) {
            $stmt = $modx->prepare("INSERT INTO {$prefix}test_question_ordering_items
                                    (question_id, item_text, correct_position)
                                    VALUES (?, ?, ?)");
            $stmt->execute([$questionId, $item['text'], $item['position']]);
        }

        return true;
    }

    /**
     * Создание данных для вопроса типа fill_blank
     *
     * @param modX $modx
     * @param int $questionId
     * @param string $templateText Текст с маркерами {{1}}, {{2}}
     * @param array $answers Массив ответов [{"blank": 1, "answer": "...", "alternatives": [...]}]
     * @param bool $caseSensitive
     * @return bool
     */
    public static function createFillBlankData($modx, $questionId, $templateText, $answers, $caseSensitive = false)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        // Создаем запись шаблона
        $stmt = $modx->prepare("INSERT INTO {$prefix}test_question_fill_blanks
                                (question_id, template_text, case_sensitive)
                                VALUES (?, ?, ?)");
        $stmt->execute([$questionId, $templateText, $caseSensitive ? 1 : 0]);

        $fillBlankId = (int)$modx->lastInsertId();

        // Создаем правильные ответы
        foreach ($answers as $answer) {
            $alternatives = isset($answer['alternatives']) && is_array($answer['alternatives'])
                ? json_encode($answer['alternatives'])
                : null;

            $stmt = $modx->prepare("INSERT INTO {$prefix}test_question_fill_blank_answers
                                    (fill_blank_id, blank_number, correct_answer, alternative_answers)
                                    VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $fillBlankId,
                $answer['blank'],
                $answer['answer'],
                $alternatives
            ]);
        }

        return true;
    }

    /**
     * Создание данных для вопроса типа essay
     *
     * @param modX $modx
     * @param int $questionId
     * @param array $settings Настройки эссе
     * @return bool
     */
    public static function createEssaySettings($modx, $questionId, $settings)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $autoCheckKeywords = isset($settings['auto_check_keywords']) && is_array($settings['auto_check_keywords'])
            ? json_encode($settings['auto_check_keywords'])
            : null;

        $stmt = $modx->prepare("INSERT INTO {$prefix}test_question_essays
                                (question_id, min_words, max_words, rubric, auto_check_keywords, max_score)
                                VALUES (?, ?, ?, ?, ?, ?)");

        return $stmt->execute([
            $questionId,
            $settings['min_words'] ?? null,
            $settings['max_words'] ?? null,
            $settings['rubric'] ?? null,
            $autoCheckKeywords,
            $settings['max_score'] ?? 10
        ]);
    }

    /**
     * Получение данных вопроса с учетом типа
     *
     * @param modX $modx
     * @param int $questionId
     * @param string $questionType
     * @return array
     */
    public static function getQuestionTypeData($modx, $questionId, $questionType)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        switch ($questionType) {
            case 'matching':
                $stmt = $modx->prepare("SELECT * FROM {$prefix}test_question_matching_pairs
                                        WHERE question_id = ?
                                        ORDER BY sort_order");
                $stmt->execute([$questionId]);
                return ['pairs' => $stmt->fetchAll(PDO::FETCH_ASSOC)];

            case 'ordering':
                $stmt = $modx->prepare("SELECT * FROM {$prefix}test_question_ordering_items
                                        WHERE question_id = ?
                                        ORDER BY correct_position");
                $stmt->execute([$questionId]);
                return ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC)];

            case 'fill_blank':
                $stmt = $modx->prepare("SELECT fb.*, fba.blank_number, fba.correct_answer, fba.alternative_answers
                                        FROM {$prefix}test_question_fill_blanks fb
                                        LEFT JOIN {$prefix}test_question_fill_blank_answers fba ON fba.fill_blank_id = fb.id
                                        WHERE fb.question_id = ?
                                        ORDER BY fba.blank_number");
                $stmt->execute([$questionId]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (empty($rows)) {
                    return [];
                }

                $data = [
                    'template_text' => $rows[0]['template_text'],
                    'case_sensitive' => (bool)$rows[0]['case_sensitive'],
                    'answers' => []
                ];

                foreach ($rows as $row) {
                    if ($row['blank_number']) {
                        $data['answers'][] = [
                            'blank' => $row['blank_number'],
                            'correct_answer' => $row['correct_answer'],
                            'alternative_answers' => json_decode($row['alternative_answers'] ?? '[]', true)
                        ];
                    }
                }

                return $data;

            case 'essay':
                $stmt = $modx->prepare("SELECT * FROM {$prefix}test_question_essays
                                        WHERE question_id = ?");
                $stmt->execute([$questionId]);
                $settings = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($settings && !empty($settings['auto_check_keywords'])) {
                    $settings['auto_check_keywords'] = json_decode($settings['auto_check_keywords'], true);
                }

                return $settings ?: [];

            default:
                return [];
        }
    }
}
