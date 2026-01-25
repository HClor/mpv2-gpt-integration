package com.lms.testapp.ui

import android.content.Intent
import android.os.Bundle
import android.view.View
import androidx.activity.viewModels
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.lifecycleScope
import androidx.recyclerview.widget.LinearLayoutManager
import com.lms.testapp.TestApplication
import com.lms.testapp.data.models.Category
import com.lms.testapp.databinding.ActivityMainBinding
import com.lms.testapp.ui.categories.CategoryAdapter
import com.lms.testapp.ui.categories.CategoryViewModel
import com.lms.testapp.ui.tests.TestListActivity
import com.lms.testapp.utils.DemoDataLoader
import kotlinx.coroutines.launch

class MainActivity : AppCompatActivity() {

    private lateinit var binding: ActivityMainBinding
    private lateinit var adapter: CategoryAdapter

    private val viewModel: CategoryViewModel by viewModels {
        object : ViewModelProvider.Factory {
            override fun <T : ViewModel> create(modelClass: Class<T>): T {
                val repository = (application as TestApplication).repository
                @Suppress("UNCHECKED_CAST")
                return CategoryViewModel(repository) as T
            }
        }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityMainBinding.inflate(layoutInflater)
        setContentView(binding.root)

        setupToolbar()
        setupRecyclerView()
        observeData()
        loadDemoDataIfNeeded()
    }

    private fun setupToolbar() {
        setSupportActionBar(binding.toolbar)
        supportActionBar?.title = getString(com.lms.testapp.R.string.categories_title)
    }

    private fun setupRecyclerView() {
        adapter = CategoryAdapter { category ->
            openCategory(category)
        }
        binding.recyclerView.apply {
            layoutManager = LinearLayoutManager(this@MainActivity)
            adapter = this@MainActivity.adapter
        }
    }

    private fun observeData() {
        viewModel.categories.observe(this) { categories ->
            adapter.submitList(categories)
            binding.emptyView.visibility = if (categories.isEmpty()) View.VISIBLE else View.GONE
            binding.recyclerView.visibility = if (categories.isEmpty()) View.GONE else View.VISIBLE
        }
    }

    private fun loadDemoDataIfNeeded() {
        lifecycleScope.launch {
            val repository = (application as TestApplication).repository
            DemoDataLoader.loadDemoDataIfNeeded(repository)
        }
    }

    private fun openCategory(category: Category) {
        val intent = Intent(this, TestListActivity::class.java).apply {
            putExtra(TestListActivity.EXTRA_CATEGORY_ID, category.id)
            putExtra(TestListActivity.EXTRA_CATEGORY_NAME, category.name)
        }
        startActivity(intent)
    }
}
