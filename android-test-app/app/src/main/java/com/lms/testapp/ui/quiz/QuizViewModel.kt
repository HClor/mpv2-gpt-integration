package com.lms.testapp.ui.quiz

import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.lms.testapp.data.models.*
import com.lms.testapp.data.repository.AnswerData
import com.lms.testapp.data.repository.TestRepository
import kotlinx.coroutines.launch

data class QuizState(
    val currentQuestionIndex: Int = 0,
    val totalQuestions: Int = 0,
    val currentQuestion: Question? = null,
    val currentAnswers: List<Answer> = emptyList(),
    val matchingPairs: List<MatchingPair> = emptyList(),
    val orderingItems: List<OrderingItem> = emptyList(),
    val fillBlankTemplate: String? = null,
    val fillBlankCount: Int = 0,
    val selectedAnswerIds: Set<Long> = emptySet(),
    val matchingUserPairs: Map<Long, Long> = emptyMap(),
    val orderingUserSequence: List<Long> = emptyList(),
    val fillBlankUserAnswers: Map<Int, String> = emptyMap(),
    val essayUserAnswer: String? = null,
    val remainingTimeMs: Long? = null
)

class QuizViewModel(
    private val repository: TestRepository
) : ViewModel() {

    private val _quizState = MutableLiveData<QuizState>()
    val quizState: LiveData<QuizState> = _quizState

    private val _testCompleted = MutableLiveData<Boolean>()
    val testCompleted: LiveData<Boolean> = _testCompleted

    var sessionId: Long = 0
        private set

    private var test: Test? = null
    private var questions: List<Question> = emptyList()
    private var currentIndex: Int = 0
    private var startTime: Long = 0
    private var remainingTimeMs: Long? = null

    // Store user answers for each question
    private val userAnswers = mutableMapOf<Long, AnswerData>()

    fun startTest(testId: Long) {
        viewModelScope.launch {
            test = repository.getTestById(testId) ?: return@launch
            questions = repository.getQuestionsByTest(testId)

            if (test!!.randomizeQuestions) {
                questions = questions.shuffled()
            }

            // Limit questions if needed
            test!!.questionsPerSession?.let { limit ->
                if (questions.size > limit) {
                    questions = questions.take(limit)
                }
            }

            startTime = System.currentTimeMillis()
            remainingTimeMs = test!!.timeLimit?.let { it * 60 * 1000L }

            // Create session
            val session = TestSession(
                testId = testId,
                startTime = startTime,
                totalQuestions = questions.size,
                status = "in_progress"
            )
            sessionId = repository.insertSession(session)

            currentIndex = 0
            loadCurrentQuestion()
        }
    }

    private suspend fun loadCurrentQuestion() {
        if (currentIndex >= questions.size) {
            finishTest()
            return
        }

        val question = questions[currentIndex]
        val questionType = QuestionType.fromValue(question.questionType)

        // Load question-specific data
        val answers = when (questionType) {
            QuestionType.SINGLE, QuestionType.MULTIPLE -> {
                val answersList = repository.getAnswersByQuestion(question.id)
                if (test?.randomizeAnswers == true) answersList.shuffled() else answersList
            }
            else -> emptyList()
        }

        val matchingPairs = if (questionType == QuestionType.MATCHING) {
            repository.getMatchingPairsByQuestion(question.id).shuffled()
        } else emptyList()

        val orderingItems = if (questionType == QuestionType.ORDERING) {
            repository.getOrderingItemsByQuestion(question.id).shuffled()
        } else emptyList()

        var fillBlankTemplate: String? = null
        var fillBlankCount = 0
        if (questionType == QuestionType.FILL_BLANK) {
            val fillBlank = repository.getFillBlankByQuestion(question.id)
            fillBlankTemplate = fillBlank?.templateText
            fillBlankCount = Regex("\\{\\{(\\d+)\\}\\}").findAll(fillBlankTemplate ?: "")
                .map { it.groupValues[1].toInt() }.maxOrNull() ?: 0
        }

        // Restore previous answers if any
        val savedAnswer = userAnswers[question.id]

        _quizState.value = QuizState(
            currentQuestionIndex = currentIndex,
            totalQuestions = questions.size,
            currentQuestion = question,
            currentAnswers = answers,
            matchingPairs = matchingPairs,
            orderingItems = orderingItems,
            fillBlankTemplate = fillBlankTemplate,
            fillBlankCount = fillBlankCount,
            selectedAnswerIds = savedAnswer?.selectedAnswerIds?.toSet()
                ?: savedAnswer?.selectedAnswerId?.let { setOf(it) }
                ?: emptySet(),
            matchingUserPairs = savedAnswer?.matchingPairs ?: emptyMap(),
            orderingUserSequence = savedAnswer?.orderingSequence ?: orderingItems.map { it.id },
            fillBlankUserAnswers = savedAnswer?.fillBlankAnswers ?: emptyMap(),
            essayUserAnswer = savedAnswer?.essayText,
            remainingTimeMs = remainingTimeMs
        )
    }

    fun selectAnswer(answerId: Long, isSelected: Boolean) {
        val state = _quizState.value ?: return
        val question = state.currentQuestion ?: return
        val questionType = QuestionType.fromValue(question.questionType)

        val newSelected = if (questionType == QuestionType.SINGLE) {
            if (isSelected) setOf(answerId) else emptySet()
        } else {
            if (isSelected) {
                state.selectedAnswerIds + answerId
            } else {
                state.selectedAnswerIds - answerId
            }
        }

        _quizState.value = state.copy(selectedAnswerIds = newSelected)
    }

    fun setMatchingPair(leftId: Long, rightId: Long) {
        val state = _quizState.value ?: return
        val newPairs = state.matchingUserPairs.toMutableMap()
        newPairs[leftId] = rightId
        _quizState.value = state.copy(matchingUserPairs = newPairs)
    }

    fun setOrderingSequence(sequence: List<Long>) {
        val state = _quizState.value ?: return
        _quizState.value = state.copy(orderingUserSequence = sequence)
    }

    fun setFillBlankAnswer(blankNumber: Int, answer: String) {
        val state = _quizState.value ?: return
        val newAnswers = state.fillBlankUserAnswers.toMutableMap()
        newAnswers[blankNumber] = answer
        _quizState.value = state.copy(fillBlankUserAnswers = newAnswers)
    }

    fun setEssayAnswer(text: String) {
        val state = _quizState.value ?: return
        _quizState.value = state.copy(essayUserAnswer = text)
    }

    fun updateRemainingTime(timeMs: Long) {
        remainingTimeMs = timeMs
    }

    fun saveCurrentAnswer(): Boolean {
        val state = _quizState.value ?: return false
        val question = state.currentQuestion ?: return false
        val questionType = QuestionType.fromValue(question.questionType)

        // Check if answer is provided
        val hasAnswer = when (questionType) {
            QuestionType.SINGLE -> state.selectedAnswerIds.isNotEmpty()
            QuestionType.MULTIPLE -> state.selectedAnswerIds.isNotEmpty()
            QuestionType.MATCHING -> state.matchingUserPairs.size == state.matchingPairs.size
            QuestionType.ORDERING -> state.orderingUserSequence.isNotEmpty()
            QuestionType.FILL_BLANK -> state.fillBlankUserAnswers.isNotEmpty()
            QuestionType.ESSAY -> !state.essayUserAnswer.isNullOrBlank()
        }

        if (!hasAnswer) return false

        // Create answer data
        val answerData = when (questionType) {
            QuestionType.SINGLE -> AnswerData(selectedAnswerId = state.selectedAnswerIds.firstOrNull())
            QuestionType.MULTIPLE -> AnswerData(selectedAnswerIds = state.selectedAnswerIds.toList())
            QuestionType.MATCHING -> AnswerData(matchingPairs = state.matchingUserPairs)
            QuestionType.ORDERING -> AnswerData(orderingSequence = state.orderingUserSequence)
            QuestionType.FILL_BLANK -> AnswerData(fillBlankAnswers = state.fillBlankUserAnswers)
            QuestionType.ESSAY -> AnswerData(essayText = state.essayUserAnswer)
        }

        userAnswers[question.id] = answerData
        return true
    }

    fun nextQuestion() {
        viewModelScope.launch {
            if (currentIndex < questions.size - 1) {
                currentIndex++
                loadCurrentQuestion()
            }
        }
    }

    fun previousQuestion() {
        viewModelScope.launch {
            if (currentIndex > 0) {
                currentIndex--
                loadCurrentQuestion()
            }
        }
    }

    fun finishTest() {
        viewModelScope.launch {
            saveCurrentAnswer()

            var correctCount = 0
            val totalQuestions = questions.size

            // Check all answers and save to database
            for (question in questions) {
                val answerData = userAnswers[question.id]
                val isCorrect = if (answerData != null) {
                    repository.checkAnswer(question, answerData)
                } else {
                    false
                }

                if (isCorrect == true) correctCount++

                // Save user answer
                val userAnswer = UserAnswer(
                    sessionId = sessionId,
                    questionId = question.id,
                    answerId = answerData?.selectedAnswerId,
                    userAnswerText = answerData?.essayText,
                    answerData = answerData?.toJson(),
                    isCorrect = isCorrect
                )
                repository.insertUserAnswer(userAnswer)
            }

            // Calculate score
            val score = if (totalQuestions > 0) {
                (correctCount * 100) / totalQuestions
            } else 0

            val passed = score >= (test?.passScore ?: 70)

            // Update session
            val session = repository.getSessionById(sessionId)?.copy(
                endTime = System.currentTimeMillis(),
                score = score,
                correctAnswers = correctCount,
                passed = passed,
                status = "completed"
            )
            session?.let { repository.updateSession(it) }

            _testCompleted.value = true
        }
    }

    fun abandonTest() {
        viewModelScope.launch {
            val session = repository.getSessionById(sessionId)?.copy(
                endTime = System.currentTimeMillis(),
                status = "abandoned"
            )
            session?.let { repository.updateSession(it) }
        }
    }
}
