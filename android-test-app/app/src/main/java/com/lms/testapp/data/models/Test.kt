package com.lms.testapp.data.models

import androidx.room.Entity
import androidx.room.ForeignKey
import androidx.room.Index
import androidx.room.PrimaryKey

@Entity(
    tableName = "tests",
    foreignKeys = [
        ForeignKey(
            entity = Category::class,
            parentColumns = ["id"],
            childColumns = ["categoryId"],
            onDelete = ForeignKey.CASCADE
        )
    ],
    indices = [Index("categoryId")]
)
data class Test(
    @PrimaryKey
    val id: Long,
    val categoryId: Long,
    val title: String,
    val description: String? = null,
    val mode: String = "training", // training, exam
    val timeLimit: Int? = null, // in minutes, null = no limit
    val passScore: Int = 70, // percentage
    val questionsPerSession: Int? = null, // null = all questions
    val randomizeQuestions: Boolean = true,
    val randomizeAnswers: Boolean = true,
    val isActive: Boolean = true
)

enum class TestMode(val value: String) {
    TRAINING("training"),
    EXAM("exam")
}
