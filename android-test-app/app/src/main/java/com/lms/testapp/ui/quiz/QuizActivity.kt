package com.lms.testapp.ui.quiz

import android.content.Intent
import android.os.Bundle
import android.os.CountDownTimer
import android.view.View
import android.widget.Toast
import androidx.activity.OnBackPressedCallback
import androidx.activity.viewModels
import androidx.appcompat.app.AlertDialog
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.recyclerview.widget.LinearLayoutManager
import com.lms.testapp.R
import com.lms.testapp.TestApplication
import com.lms.testapp.data.models.QuestionType
import com.lms.testapp.databinding.ActivityQuizBinding
import com.lms.testapp.ui.results.ResultsActivity

class QuizActivity : AppCompatActivity() {

    companion object {
        const val EXTRA_TEST_ID = "test_id"
        const val EXTRA_TEST_TITLE = "test_title"
    }

    private lateinit var binding: ActivityQuizBinding
    private var timer: CountDownTimer? = null
    private var answerAdapter: AnswerAdapter? = null

    private val viewModel: QuizViewModel by viewModels {
        object : ViewModelProvider.Factory {
            override fun <T : ViewModel> create(modelClass: Class<T>): T {
                val repository = (application as TestApplication).repository
                @Suppress("UNCHECKED_CAST")
                return QuizViewModel(repository) as T
            }
        }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityQuizBinding.inflate(layoutInflater)
        setContentView(binding.root)

        val testId = intent.getLongExtra(EXTRA_TEST_ID, 0)
        val testTitle = intent.getStringExtra(EXTRA_TEST_TITLE) ?: getString(R.string.tests_title)

        setupToolbar(testTitle)
        setupButtons()
        setupBackPressHandler()
        observeData()

        viewModel.startTest(testId)
    }

    private fun setupToolbar(testTitle: String) {
        setSupportActionBar(binding.toolbar)
        supportActionBar?.apply {
            title = testTitle
            setDisplayHomeAsUpEnabled(true)
        }
        binding.toolbar.setNavigationOnClickListener { confirmExit() }
    }

    private fun setupButtons() {
        binding.nextButton.setOnClickListener {
            if (viewModel.saveCurrentAnswer()) {
                viewModel.nextQuestion()
            } else {
                Toast.makeText(this, R.string.no_answer_selected, Toast.LENGTH_SHORT).show()
            }
        }

        binding.prevButton.setOnClickListener {
            viewModel.saveCurrentAnswer()
            viewModel.previousQuestion()
        }

        binding.finishButton.setOnClickListener {
            confirmFinish()
        }
    }

    private fun setupBackPressHandler() {
        onBackPressedDispatcher.addCallback(this, object : OnBackPressedCallback(true) {
            override fun handleOnBackPressed() {
                confirmExit()
            }
        })
    }

    private fun observeData() {
        viewModel.quizState.observe(this) { state ->
            updateUI(state)
        }

        viewModel.testCompleted.observe(this) { completed ->
            if (completed) {
                openResults()
            }
        }
    }

    private fun updateUI(state: QuizState) {
        // Update progress
        binding.progressText.text = getString(
            R.string.question_number,
            state.currentQuestionIndex + 1,
            state.totalQuestions
        )
        binding.progressBar.max = state.totalQuestions
        binding.progressBar.progress = state.currentQuestionIndex + 1

        // Update question
        state.currentQuestion?.let { question ->
            binding.questionText.text = question.questionText

            // Show question type hint
            binding.questionTypeHint.text = when (QuestionType.fromValue(question.questionType)) {
                QuestionType.SINGLE -> getString(R.string.type_single)
                QuestionType.MULTIPLE -> getString(R.string.type_multiple)
                QuestionType.MATCHING -> getString(R.string.type_matching)
                QuestionType.ORDERING -> getString(R.string.type_ordering)
                QuestionType.FILL_BLANK -> getString(R.string.type_fill_blank)
                QuestionType.ESSAY -> getString(R.string.type_essay)
            }

            // Setup answers based on question type
            setupAnswersForQuestion(state)
        }

        // Update navigation buttons
        binding.prevButton.visibility = if (state.currentQuestionIndex > 0) View.VISIBLE else View.INVISIBLE
        binding.nextButton.visibility = if (state.currentQuestionIndex < state.totalQuestions - 1) View.VISIBLE else View.GONE
        binding.finishButton.visibility = if (state.currentQuestionIndex == state.totalQuestions - 1) View.VISIBLE else View.GONE

        // Update timer
        state.remainingTimeMs?.let { startTimer(it) } ?: hideTimer()
    }

    private fun setupAnswersForQuestion(state: QuizState) {
        val question = state.currentQuestion ?: return
        val questionType = QuestionType.fromValue(question.questionType)

        // Hide all answer containers first
        binding.answersRecyclerView.visibility = View.GONE
        binding.matchingContainer.visibility = View.GONE
        binding.orderingContainer.visibility = View.GONE
        binding.fillBlankContainer.visibility = View.GONE
        binding.essayContainer.visibility = View.GONE

        when (questionType) {
            QuestionType.SINGLE, QuestionType.MULTIPLE -> {
                binding.answersRecyclerView.visibility = View.VISIBLE
                setupSingleMultipleAnswers(state, questionType == QuestionType.MULTIPLE)
            }
            QuestionType.MATCHING -> {
                binding.matchingContainer.visibility = View.VISIBLE
                setupMatchingAnswers(state)
            }
            QuestionType.ORDERING -> {
                binding.orderingContainer.visibility = View.VISIBLE
                setupOrderingAnswers(state)
            }
            QuestionType.FILL_BLANK -> {
                binding.fillBlankContainer.visibility = View.VISIBLE
                setupFillBlankAnswers(state)
            }
            QuestionType.ESSAY -> {
                binding.essayContainer.visibility = View.VISIBLE
                setupEssayAnswer(state)
            }
        }
    }

    private fun setupSingleMultipleAnswers(state: QuizState, isMultiple: Boolean) {
        answerAdapter = AnswerAdapter(
            isMultipleChoice = isMultiple,
            onAnswerSelected = { answerId, isSelected ->
                viewModel.selectAnswer(answerId, isSelected)
            }
        )
        binding.answersRecyclerView.layoutManager = LinearLayoutManager(this)
        binding.answersRecyclerView.adapter = answerAdapter

        answerAdapter?.submitList(state.currentAnswers)
        answerAdapter?.setSelectedAnswers(state.selectedAnswerIds)
    }

    private fun setupMatchingAnswers(state: QuizState) {
        val matchingAdapter = MatchingAdapter(
            pairs = state.matchingPairs,
            onPairMatched = { leftId, rightId ->
                viewModel.setMatchingPair(leftId, rightId)
            }
        )
        binding.matchingRecyclerView.layoutManager = LinearLayoutManager(this)
        binding.matchingRecyclerView.adapter = matchingAdapter
    }

    private fun setupOrderingAnswers(state: QuizState) {
        val orderingAdapter = OrderingAdapter(
            items = state.orderingItems.toMutableList(),
            onOrderChanged = { newOrder ->
                viewModel.setOrderingSequence(newOrder)
            }
        )
        binding.orderingRecyclerView.layoutManager = LinearLayoutManager(this)
        binding.orderingRecyclerView.adapter = orderingAdapter
    }

    private fun setupFillBlankAnswers(state: QuizState) {
        state.fillBlankTemplate?.let { template ->
            binding.fillBlankText.text = template.replace(Regex("\\{\\{\\d+\\}\\}"), "______")
        }

        val fillBlankAdapter = FillBlankAdapter(
            blankCount = state.fillBlankCount,
            currentAnswers = state.fillBlankUserAnswers,
            onAnswerChanged = { blankNumber, answer ->
                viewModel.setFillBlankAnswer(blankNumber, answer)
            }
        )
        binding.fillBlankInputsRecyclerView.layoutManager = LinearLayoutManager(this)
        binding.fillBlankInputsRecyclerView.adapter = fillBlankAdapter
    }

    private fun setupEssayAnswer(state: QuizState) {
        binding.essayInput.setText(state.essayUserAnswer ?: "")
        binding.essayInput.setOnFocusChangeListener { _, hasFocus ->
            if (!hasFocus) {
                viewModel.setEssayAnswer(binding.essayInput.text.toString())
            }
        }
    }

    private fun startTimer(remainingMs: Long) {
        timer?.cancel()
        binding.timerText.visibility = View.VISIBLE

        timer = object : CountDownTimer(remainingMs, 1000) {
            override fun onTick(millisUntilFinished: Long) {
                val minutes = millisUntilFinished / 60000
                val seconds = (millisUntilFinished % 60000) / 1000
                binding.timerText.text = getString(
                    R.string.time_remaining,
                    String.format("%02d:%02d", minutes, seconds)
                )
                viewModel.updateRemainingTime(millisUntilFinished)
            }

            override fun onFinish() {
                viewModel.finishTest()
            }
        }.start()
    }

    private fun hideTimer() {
        binding.timerText.visibility = View.GONE
        timer?.cancel()
    }

    private fun confirmFinish() {
        AlertDialog.Builder(this)
            .setTitle(R.string.confirm_finish_title)
            .setMessage(R.string.confirm_finish_message)
            .setPositiveButton(R.string.confirm) { _, _ ->
                viewModel.saveCurrentAnswer()
                viewModel.finishTest()
            }
            .setNegativeButton(R.string.cancel, null)
            .show()
    }

    private fun confirmExit() {
        AlertDialog.Builder(this)
            .setTitle(R.string.confirm_finish_title)
            .setMessage(R.string.confirm_finish_message)
            .setPositiveButton(R.string.confirm) { _, _ ->
                viewModel.abandonTest()
                finish()
            }
            .setNegativeButton(R.string.cancel, null)
            .show()
    }

    private fun openResults() {
        val intent = Intent(this, ResultsActivity::class.java).apply {
            putExtra(ResultsActivity.EXTRA_SESSION_ID, viewModel.sessionId)
        }
        startActivity(intent)
        finish()
    }

    override fun onDestroy() {
        super.onDestroy()
        timer?.cancel()
    }
}
