package com.lms.testapp.data.repository

import com.google.gson.Gson
import com.google.gson.reflect.TypeToken
import com.lms.testapp.data.database.*
import com.lms.testapp.data.models.*
import kotlinx.coroutines.flow.Flow

class TestRepository(
    private val categoryDao: CategoryDao,
    private val testDao: TestDao,
    private val questionDao: QuestionDao,
    private val answerDao: AnswerDao,
    private val matchingPairDao: MatchingPairDao,
    private val orderingItemDao: OrderingItemDao,
    private val fillBlankDao: FillBlankDao,
    private val testSessionDao: TestSessionDao,
    private val userAnswerDao: UserAnswerDao
) {
    private val gson = Gson()

    // Categories
    fun getAllCategories(): Flow<List<Category>> = categoryDao.getAllCategories()
    fun getRootCategories(): Flow<List<Category>> = categoryDao.getRootCategories()
    fun getSubCategories(parentId: Long): Flow<List<Category>> = categoryDao.getSubCategories(parentId)
    suspend fun getCategoryById(id: Long): Category? = categoryDao.getCategoryById(id)
    suspend fun insertCategories(categories: List<Category>) = categoryDao.insertCategories(categories)

    // Tests
    fun getAllTests(): Flow<List<Test>> = testDao.getAllTests()
    fun getTestsByCategory(categoryId: Long): Flow<List<Test>> = testDao.getTestsByCategory(categoryId)
    suspend fun getTestById(id: Long): Test? = testDao.getTestById(id)
    suspend fun getQuestionCount(testId: Long): Int = testDao.getQuestionCount(testId)
    suspend fun insertTests(tests: List<Test>) = testDao.insertTests(tests)

    // Questions with related data
    suspend fun getQuestionsByTest(testId: Long): List<Question> = questionDao.getQuestionsByTest(testId)
    suspend fun getQuestionById(id: Long): Question? = questionDao.getQuestionById(id)
    suspend fun insertQuestions(questions: List<Question>) = questionDao.insertQuestions(questions)

    // Answers
    suspend fun getAnswersByQuestion(questionId: Long): List<Answer> = answerDao.getAnswersByQuestion(questionId)
    suspend fun insertAnswers(answers: List<Answer>) = answerDao.insertAnswers(answers)

    // Matching pairs
    suspend fun getMatchingPairsByQuestion(questionId: Long): List<MatchingPair> =
        matchingPairDao.getMatchingPairsByQuestion(questionId)
    suspend fun insertMatchingPairs(pairs: List<MatchingPair>) = matchingPairDao.insertMatchingPairs(pairs)

    // Ordering items
    suspend fun getOrderingItemsByQuestion(questionId: Long): List<OrderingItem> =
        orderingItemDao.getOrderingItemsByQuestion(questionId)
    suspend fun insertOrderingItems(items: List<OrderingItem>) = orderingItemDao.insertOrderingItems(items)

    // Fill blank
    suspend fun getFillBlankByQuestion(questionId: Long): FillBlank? = fillBlankDao.getFillBlankByQuestion(questionId)
    suspend fun getFillBlankAnswers(fillBlankId: Long): List<FillBlankAnswer> =
        fillBlankDao.getFillBlankAnswers(fillBlankId)
    suspend fun insertFillBlank(fillBlank: FillBlank) = fillBlankDao.insertFillBlank(fillBlank)
    suspend fun insertFillBlankAnswers(answers: List<FillBlankAnswer>) = fillBlankDao.insertFillBlankAnswers(answers)

    // Sessions
    fun getAllSessions(): Flow<List<TestSession>> = testSessionDao.getAllSessions()
    fun getSessionsByTest(testId: Long): Flow<List<TestSession>> = testSessionDao.getSessionsByTest(testId)
    suspend fun getSessionById(id: Long): TestSession? = testSessionDao.getSessionById(id)
    suspend fun getActiveSession(testId: Long): TestSession? = testSessionDao.getActiveSession(testId)
    suspend fun insertSession(session: TestSession): Long = testSessionDao.insertSession(session)
    suspend fun updateSession(session: TestSession) = testSessionDao.updateSession(session)
    suspend fun abandonAllActiveSessions() = testSessionDao.abandonAllActiveSessions()

    // User answers
    suspend fun getAnswersBySession(sessionId: Long): List<UserAnswer> = userAnswerDao.getAnswersBySession(sessionId)
    suspend fun getUserAnswer(sessionId: Long, questionId: Long): UserAnswer? =
        userAnswerDao.getAnswer(sessionId, questionId)
    suspend fun insertUserAnswer(answer: UserAnswer) = userAnswerDao.insertAnswer(answer)
    suspend fun updateUserAnswer(answer: UserAnswer) = userAnswerDao.updateAnswer(answer)

    // Answer checking logic for all 6 question types

    /**
     * Check answer for SINGLE choice question
     */
    suspend fun checkSingleAnswer(questionId: Long, selectedAnswerId: Long): Boolean {
        val answers = answerDao.getAnswersByQuestion(questionId)
        val correctAnswer = answers.find { it.isCorrect }
        return correctAnswer?.id == selectedAnswerId
    }

    /**
     * Check answer for MULTIPLE choice question
     */
    suspend fun checkMultipleAnswer(questionId: Long, selectedAnswerIds: List<Long>): Boolean {
        val answers = answerDao.getAnswersByQuestion(questionId)
        val correctIds = answers.filter { it.isCorrect }.map { it.id }.toSet()
        val selectedSet = selectedAnswerIds.toSet()
        return correctIds == selectedSet
    }

    /**
     * Check answer for MATCHING question
     * @param userPairs map of leftId to rightId as selected by user
     */
    suspend fun checkMatchingAnswer(questionId: Long, userPairs: Map<Long, Long>): Boolean {
        val correctPairs = matchingPairDao.getMatchingPairsByQuestion(questionId)
        if (userPairs.size != correctPairs.size) return false

        // Create map of correct pairs (leftId -> rightId based on position)
        val correctMap = correctPairs.associate { it.id to it.id }

        // For matching, we need to check if user connected correct left items to correct right items
        // Since left and right items have same id in our simple model
        return userPairs.all { (leftId, rightId) -> leftId == rightId }
    }

    /**
     * Check answer for ORDERING question
     * @param userOrder list of item IDs in user's order
     */
    suspend fun checkOrderingAnswer(questionId: Long, userOrder: List<Long>): Boolean {
        val correctItems = orderingItemDao.getOrderingItemsByQuestion(questionId)
        val correctOrder = correctItems.sortedBy { it.correctPosition }.map { it.id }
        return userOrder == correctOrder
    }

    /**
     * Check answer for FILL_BLANK question
     * @param userAnswers map of blank number to user's answer text
     */
    suspend fun checkFillBlankAnswer(questionId: Long, userAnswers: Map<Int, String>): Boolean {
        val fillBlank = fillBlankDao.getFillBlankByQuestion(questionId) ?: return false
        val correctAnswers = fillBlankDao.getFillBlankAnswers(fillBlank.id)

        return correctAnswers.all { correct ->
            val userAnswer = userAnswers[correct.blankNumber] ?: return false
            val normalizedUser = if (fillBlank.caseSensitive) userAnswer.trim() else userAnswer.trim().lowercase()
            val normalizedCorrect = if (fillBlank.caseSensitive) correct.correctAnswer.trim() else correct.correctAnswer.trim().lowercase()

            if (normalizedUser == normalizedCorrect) return@all true

            // Check alternative answers
            correct.alternativeAnswers?.let { altJson ->
                try {
                    val type = object : TypeToken<List<String>>() {}.type
                    val alternatives: List<String> = gson.fromJson(altJson, type)
                    alternatives.any { alt ->
                        val normalizedAlt = if (fillBlank.caseSensitive) alt.trim() else alt.trim().lowercase()
                        normalizedUser == normalizedAlt
                    }
                } catch (e: Exception) {
                    false
                }
            } ?: false
        }
    }

    /**
     * Essay answers are not automatically checked - always returns null
     * Essays require manual review
     */
    fun checkEssayAnswer(questionId: Long, essayText: String): Boolean? {
        return null // Essays cannot be automatically graded
    }

    /**
     * Universal answer checker that handles all question types
     */
    suspend fun checkAnswer(question: Question, answerData: AnswerData): Boolean? {
        return when (QuestionType.fromValue(question.questionType)) {
            QuestionType.SINGLE -> {
                answerData.selectedAnswerId?.let { checkSingleAnswer(question.id, it) } ?: false
            }
            QuestionType.MULTIPLE -> {
                answerData.selectedAnswerIds?.let { checkMultipleAnswer(question.id, it) } ?: false
            }
            QuestionType.MATCHING -> {
                answerData.matchingPairs?.let { checkMatchingAnswer(question.id, it) } ?: false
            }
            QuestionType.ORDERING -> {
                answerData.orderingSequence?.let { checkOrderingAnswer(question.id, it) } ?: false
            }
            QuestionType.FILL_BLANK -> {
                answerData.fillBlankAnswers?.let { checkFillBlankAnswer(question.id, it) } ?: false
            }
            QuestionType.ESSAY -> {
                checkEssayAnswer(question.id, answerData.essayText ?: "")
            }
        }
    }
}

/**
 * Universal answer data class for all question types
 */
data class AnswerData(
    val selectedAnswerId: Long? = null,           // for SINGLE
    val selectedAnswerIds: List<Long>? = null,    // for MULTIPLE
    val matchingPairs: Map<Long, Long>? = null,   // for MATCHING (leftId -> rightId)
    val orderingSequence: List<Long>? = null,     // for ORDERING (item IDs in order)
    val fillBlankAnswers: Map<Int, String>? = null, // for FILL_BLANK (blankNum -> answer)
    val essayText: String? = null                 // for ESSAY
) {
    fun toJson(): String {
        return Gson().toJson(this)
    }

    companion object {
        fun fromJson(json: String): AnswerData {
            return Gson().fromJson(json, AnswerData::class.java)
        }
    }
}
