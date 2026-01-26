package com.lms.testapp.ui.results

import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.lms.testapp.data.repository.TestRepository
import kotlinx.coroutines.launch

data class ResultsState(
    val score: Int = 0,
    val correctAnswers: Int = 0,
    val totalQuestions: Int = 0,
    val passed: Boolean = false,
    val timeSpentMs: Long? = null,
    val answerResults: List<AnswerResult> = emptyList()
)

data class AnswerResult(
    val questionNumber: Int,
    val questionText: String,
    val isCorrect: Boolean?,
    val explanation: String?
)

class ResultsViewModel(
    private val repository: TestRepository
) : ViewModel() {

    private val _resultsState = MutableLiveData<ResultsState>()
    val resultsState: LiveData<ResultsState> = _resultsState

    var testId: Long? = null
        private set
    var testTitle: String? = null
        private set

    fun loadResults(sessionId: Long) {
        viewModelScope.launch {
            val session = repository.getSessionById(sessionId) ?: return@launch
            val test = repository.getTestById(session.testId) ?: return@launch
            val userAnswers = repository.getAnswersBySession(sessionId)

            testId = test.id
            testTitle = test.title

            val timeSpentMs = if (session.endTime != null && session.startTime > 0) {
                session.endTime - session.startTime
            } else null

            val answerResults = userAnswers.mapIndexed { index, userAnswer ->
                val question = repository.getQuestionById(userAnswer.questionId)
                AnswerResult(
                    questionNumber = index + 1,
                    questionText = question?.questionText ?: "Вопрос ${index + 1}",
                    isCorrect = userAnswer.isCorrect,
                    explanation = question?.explanation
                )
            }

            _resultsState.value = ResultsState(
                score = session.score ?: 0,
                correctAnswers = session.correctAnswers,
                totalQuestions = session.totalQuestions,
                passed = session.passed ?: false,
                timeSpentMs = timeSpentMs,
                answerResults = answerResults
            )
        }
    }
}
