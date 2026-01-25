package com.lms.testapp.ui.tests

import androidx.lifecycle.LiveData
import androidx.lifecycle.ViewModel
import androidx.lifecycle.asLiveData
import androidx.lifecycle.viewModelScope
import com.lms.testapp.data.models.Test
import com.lms.testapp.data.repository.TestRepository
import kotlinx.coroutines.launch
import kotlinx.coroutines.runBlocking

class TestListViewModel(
    private val repository: TestRepository
) : ViewModel() {

    fun getTestsByCategory(categoryId: Long): LiveData<List<Test>> {
        return repository.getTestsByCategory(categoryId).asLiveData()
    }

    fun getQuestionCount(testId: Long): Int {
        return runBlocking {
            repository.getQuestionCount(testId)
        }
    }
}
