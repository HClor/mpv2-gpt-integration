package com.lms.testapp.ui.categories

import androidx.lifecycle.LiveData
import androidx.lifecycle.ViewModel
import androidx.lifecycle.asLiveData
import com.lms.testapp.data.models.Category
import com.lms.testapp.data.repository.TestRepository

class CategoryViewModel(
    private val repository: TestRepository
) : ViewModel() {

    val categories: LiveData<List<Category>> = repository.getRootCategories().asLiveData()

    fun getSubCategories(parentId: Long): LiveData<List<Category>> {
        return repository.getSubCategories(parentId).asLiveData()
    }
}
