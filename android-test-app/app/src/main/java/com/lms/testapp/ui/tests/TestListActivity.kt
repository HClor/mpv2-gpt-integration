package com.lms.testapp.ui.tests

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
import com.lms.testapp.data.models.Test
import com.lms.testapp.databinding.ActivityTestListBinding
import com.lms.testapp.ui.quiz.QuizActivity

class TestListActivity : AppCompatActivity() {

    companion object {
        const val EXTRA_CATEGORY_ID = "category_id"
        const val EXTRA_CATEGORY_NAME = "category_name"
    }

    private lateinit var binding: ActivityTestListBinding
    private lateinit var adapter: TestAdapter
    private var categoryId: Long = 0

    private val viewModel: TestListViewModel by viewModels {
        object : ViewModelProvider.Factory {
            override fun <T : ViewModel> create(modelClass: Class<T>): T {
                val repository = (application as TestApplication).repository
                @Suppress("UNCHECKED_CAST")
                return TestListViewModel(repository) as T
            }
        }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityTestListBinding.inflate(layoutInflater)
        setContentView(binding.root)

        categoryId = intent.getLongExtra(EXTRA_CATEGORY_ID, 0)
        val categoryName = intent.getStringExtra(EXTRA_CATEGORY_NAME) ?: getString(R.string.tests_title)

        setupToolbar(categoryName)
        setupRecyclerView()
        observeData()
    }

    private fun setupToolbar(categoryName: String) {
        setSupportActionBar(binding.toolbar)
        supportActionBar?.apply {
            title = categoryName
            setDisplayHomeAsUpEnabled(true)
        }
        binding.toolbar.setNavigationOnClickListener { onBackPressedDispatcher.onBackPressed() }
    }

    private fun setupRecyclerView() {
        adapter = TestAdapter(
            onTestClick = { test -> openTest(test) },
            getQuestionCount = { testId -> viewModel.getQuestionCount(testId) }
        )
        binding.recyclerView.apply {
            layoutManager = LinearLayoutManager(this@TestListActivity)
            adapter = this@TestListActivity.adapter
        }
    }

    private fun observeData() {
        viewModel.getTestsByCategory(categoryId).observe(this) { tests ->
            adapter.submitList(tests)
            binding.emptyView.visibility = if (tests.isEmpty()) View.VISIBLE else View.GONE
            binding.recyclerView.visibility = if (tests.isEmpty()) View.GONE else View.VISIBLE
        }
    }

    private fun openTest(test: Test) {
        val intent = Intent(this, QuizActivity::class.java).apply {
            putExtra(QuizActivity.EXTRA_TEST_ID, test.id)
            putExtra(QuizActivity.EXTRA_TEST_TITLE, test.title)
        }
        startActivity(intent)
    }
}
