package com.lms.testapp.ui.results

import android.content.Intent
import android.os.Bundle
import android.view.View
import androidx.activity.viewModels
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.recyclerview.widget.LinearLayoutManager
import com.lms.testapp.R
import com.lms.testapp.TestApplication
import com.lms.testapp.databinding.ActivityResultsBinding
import com.lms.testapp.ui.MainActivity
import com.lms.testapp.ui.quiz.QuizActivity

class ResultsActivity : AppCompatActivity() {

    companion object {
        const val EXTRA_SESSION_ID = "session_id"
    }

    private lateinit var binding: ActivityResultsBinding

    private val viewModel: ResultsViewModel by viewModels {
        object : ViewModelProvider.Factory {
            override fun <T : ViewModel> create(modelClass: Class<T>): T {
                val repository = (application as TestApplication).repository
                @Suppress("UNCHECKED_CAST")
                return ResultsViewModel(repository) as T
            }
        }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityResultsBinding.inflate(layoutInflater)
        setContentView(binding.root)

        val sessionId = intent.getLongExtra(EXTRA_SESSION_ID, 0)

        setupToolbar()
        setupButtons()
        observeData()

        viewModel.loadResults(sessionId)
    }

    private fun setupToolbar() {
        setSupportActionBar(binding.toolbar)
        supportActionBar?.apply {
            title = getString(R.string.results_title)
            setDisplayHomeAsUpEnabled(true)
        }
        binding.toolbar.setNavigationOnClickListener { goToMain() }
    }

    private fun setupButtons() {
        binding.retryButton.setOnClickListener {
            viewModel.testId?.let { testId ->
                val intent = Intent(this, QuizActivity::class.java).apply {
                    putExtra(QuizActivity.EXTRA_TEST_ID, testId)
                    putExtra(QuizActivity.EXTRA_TEST_TITLE, viewModel.testTitle)
                }
                startActivity(intent)
                finish()
            }
        }

        binding.backButton.setOnClickListener {
            goToMain()
        }

        binding.viewAnswersButton.setOnClickListener {
            binding.answersSection.visibility = if (binding.answersSection.visibility == View.VISIBLE) {
                View.GONE
            } else {
                View.VISIBLE
            }
        }
    }

    private fun observeData() {
        viewModel.resultsState.observe(this) { state ->
            // Score
            binding.scoreText.text = getString(R.string.score, state.score)
            binding.correctAnswersText.text = getString(
                R.string.correct_answers,
                state.correctAnswers,
                state.totalQuestions
            )

            // Pass/Fail
            if (state.passed) {
                binding.resultIcon.setImageResource(R.drawable.ic_check)
                binding.resultStatus.text = getString(R.string.test_passed)
                binding.resultStatus.setTextColor(getColor(R.color.success))
            } else {
                binding.resultIcon.setImageResource(R.drawable.ic_close)
                binding.resultStatus.text = getString(R.string.test_failed)
                binding.resultStatus.setTextColor(getColor(R.color.error))
            }

            // Time spent
            val timeSpent = state.timeSpentMs?.let { ms ->
                val minutes = ms / 60000
                val seconds = (ms % 60000) / 1000
                String.format("%02d:%02d", minutes, seconds)
            } ?: "N/A"
            binding.timeSpentText.text = getString(R.string.time_spent, timeSpent)

            // Progress bar
            binding.scoreProgressBar.max = 100
            binding.scoreProgressBar.progress = state.score

            // Answers list
            if (state.answerResults.isNotEmpty()) {
                val adapter = AnswerResultAdapter(state.answerResults)
                binding.answersRecyclerView.layoutManager = LinearLayoutManager(this)
                binding.answersRecyclerView.adapter = adapter
            }
        }
    }

    private fun goToMain() {
        val intent = Intent(this, MainActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP
        }
        startActivity(intent)
        finish()
    }

    @Deprecated("Deprecated in Java")
    override fun onBackPressed() {
        goToMain()
    }
}
