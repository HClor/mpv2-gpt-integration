package com.lms.testapp

import android.app.Application
import com.lms.testapp.data.database.TestDatabase
import com.lms.testapp.data.repository.TestRepository
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.SupervisorJob

class TestApplication : Application() {

    private val applicationScope = CoroutineScope(SupervisorJob())

    val database by lazy { TestDatabase.getDatabase(this) }

    val repository by lazy {
        TestRepository(
            database.categoryDao(),
            database.testDao(),
            database.questionDao(),
            database.answerDao(),
            database.matchingPairDao(),
            database.orderingItemDao(),
            database.fillBlankDao(),
            database.testSessionDao(),
            database.userAnswerDao()
        )
    }
}
