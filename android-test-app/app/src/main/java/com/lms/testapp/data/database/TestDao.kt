package com.lms.testapp.data.database

import androidx.room.*
import com.lms.testapp.data.models.*
import kotlinx.coroutines.flow.Flow

@Dao
interface CategoryDao {
    @Query("SELECT * FROM categories ORDER BY sortOrder ASC")
    fun getAllCategories(): Flow<List<Category>>

    @Query("SELECT * FROM categories WHERE parentId IS NULL ORDER BY sortOrder ASC")
    fun getRootCategories(): Flow<List<Category>>

    @Query("SELECT * FROM categories WHERE parentId = :parentId ORDER BY sortOrder ASC")
    fun getSubCategories(parentId: Long): Flow<List<Category>>

    @Query("SELECT * FROM categories WHERE id = :id")
    suspend fun getCategoryById(id: Long): Category?

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertCategory(category: Category)

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertCategories(categories: List<Category>)

    @Delete
    suspend fun deleteCategory(category: Category)

    @Query("DELETE FROM categories")
    suspend fun deleteAllCategories()
}

@Dao
interface TestDao {
    @Query("SELECT * FROM tests WHERE isActive = 1 ORDER BY title ASC")
    fun getAllTests(): Flow<List<Test>>

    @Query("SELECT * FROM tests WHERE categoryId = :categoryId AND isActive = 1 ORDER BY title ASC")
    fun getTestsByCategory(categoryId: Long): Flow<List<Test>>

    @Query("SELECT * FROM tests WHERE id = :id")
    suspend fun getTestById(id: Long): Test?

    @Query("SELECT COUNT(*) FROM questions WHERE testId = :testId AND isPublished = 1")
    suspend fun getQuestionCount(testId: Long): Int

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertTest(test: Test)

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertTests(tests: List<Test>)

    @Delete
    suspend fun deleteTest(test: Test)

    @Query("DELETE FROM tests")
    suspend fun deleteAllTests()
}

@Dao
interface QuestionDao {
    @Query("SELECT * FROM questions WHERE testId = :testId AND isPublished = 1 ORDER BY sortOrder ASC")
    suspend fun getQuestionsByTest(testId: Long): List<Question>

    @Query("SELECT * FROM questions WHERE id = :id")
    suspend fun getQuestionById(id: Long): Question?

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertQuestion(question: Question)

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertQuestions(questions: List<Question>)

    @Query("DELETE FROM questions WHERE testId = :testId")
    suspend fun deleteQuestionsByTest(testId: Long)
}

@Dao
interface AnswerDao {
    @Query("SELECT * FROM answers WHERE questionId = :questionId ORDER BY sortOrder ASC")
    suspend fun getAnswersByQuestion(questionId: Long): List<Answer>

    @Query("SELECT * FROM answers WHERE questionId IN (:questionIds) ORDER BY sortOrder ASC")
    suspend fun getAnswersByQuestions(questionIds: List<Long>): List<Answer>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertAnswer(answer: Answer)

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertAnswers(answers: List<Answer>)

    @Query("DELETE FROM answers WHERE questionId = :questionId")
    suspend fun deleteAnswersByQuestion(questionId: Long)
}

@Dao
interface MatchingPairDao {
    @Query("SELECT * FROM matching_pairs WHERE questionId = :questionId ORDER BY sortOrder ASC")
    suspend fun getMatchingPairsByQuestion(questionId: Long): List<MatchingPair>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertMatchingPairs(pairs: List<MatchingPair>)

    @Query("DELETE FROM matching_pairs WHERE questionId = :questionId")
    suspend fun deleteMatchingPairsByQuestion(questionId: Long)
}

@Dao
interface OrderingItemDao {
    @Query("SELECT * FROM ordering_items WHERE questionId = :questionId ORDER BY correctPosition ASC")
    suspend fun getOrderingItemsByQuestion(questionId: Long): List<OrderingItem>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertOrderingItems(items: List<OrderingItem>)

    @Query("DELETE FROM ordering_items WHERE questionId = :questionId")
    suspend fun deleteOrderingItemsByQuestion(questionId: Long)
}

@Dao
interface FillBlankDao {
    @Query("SELECT * FROM fill_blanks WHERE questionId = :questionId")
    suspend fun getFillBlankByQuestion(questionId: Long): FillBlank?

    @Query("SELECT * FROM fill_blank_answers WHERE fillBlankId = :fillBlankId ORDER BY blankNumber ASC")
    suspend fun getFillBlankAnswers(fillBlankId: Long): List<FillBlankAnswer>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertFillBlank(fillBlank: FillBlank)

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertFillBlankAnswers(answers: List<FillBlankAnswer>)
}

@Dao
interface TestSessionDao {
    @Query("SELECT * FROM test_sessions ORDER BY startTime DESC")
    fun getAllSessions(): Flow<List<TestSession>>

    @Query("SELECT * FROM test_sessions WHERE testId = :testId ORDER BY startTime DESC")
    fun getSessionsByTest(testId: Long): Flow<List<TestSession>>

    @Query("SELECT * FROM test_sessions WHERE id = :id")
    suspend fun getSessionById(id: Long): TestSession?

    @Query("SELECT * FROM test_sessions WHERE status = 'in_progress' AND testId = :testId ORDER BY startTime DESC LIMIT 1")
    suspend fun getActiveSession(testId: Long): TestSession?

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertSession(session: TestSession): Long

    @Update
    suspend fun updateSession(session: TestSession)

    @Query("UPDATE test_sessions SET status = 'abandoned' WHERE status = 'in_progress'")
    suspend fun abandonAllActiveSessions()

    @Delete
    suspend fun deleteSession(session: TestSession)

    @Query("DELETE FROM test_sessions")
    suspend fun deleteAllSessions()
}

@Dao
interface UserAnswerDao {
    @Query("SELECT * FROM user_answers WHERE sessionId = :sessionId ORDER BY answeredAt ASC")
    suspend fun getAnswersBySession(sessionId: Long): List<UserAnswer>

    @Query("SELECT * FROM user_answers WHERE sessionId = :sessionId AND questionId = :questionId")
    suspend fun getAnswer(sessionId: Long, questionId: Long): UserAnswer?

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertAnswer(answer: UserAnswer)

    @Update
    suspend fun updateAnswer(answer: UserAnswer)

    @Query("DELETE FROM user_answers WHERE sessionId = :sessionId")
    suspend fun deleteAnswersBySession(sessionId: Long)
}
