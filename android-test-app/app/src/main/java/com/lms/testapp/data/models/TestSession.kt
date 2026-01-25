package com.lms.testapp.data.models

import androidx.room.Entity
import androidx.room.ForeignKey
import androidx.room.Index
import androidx.room.PrimaryKey

@Entity(
    tableName = "test_sessions",
    foreignKeys = [
        ForeignKey(
            entity = Test::class,
            parentColumns = ["id"],
            childColumns = ["testId"],
            onDelete = ForeignKey.CASCADE
        )
    ],
    indices = [Index("testId")]
)
data class TestSession(
    @PrimaryKey(autoGenerate = true)
    val id: Long = 0,
    val testId: Long,
    val startTime: Long = System.currentTimeMillis(),
    val endTime: Long? = null,
    val score: Int? = null, // percentage
    val correctAnswers: Int = 0,
    val totalQuestions: Int = 0,
    val passed: Boolean? = null,
    val status: String = "in_progress" // in_progress, completed, abandoned
)

@Entity(
    tableName = "user_answers",
    foreignKeys = [
        ForeignKey(
            entity = TestSession::class,
            parentColumns = ["id"],
            childColumns = ["sessionId"],
            onDelete = ForeignKey.CASCADE
        ),
        ForeignKey(
            entity = Question::class,
            parentColumns = ["id"],
            childColumns = ["questionId"],
            onDelete = ForeignKey.CASCADE
        )
    ],
    indices = [Index("sessionId"), Index("questionId")]
)
data class UserAnswer(
    @PrimaryKey(autoGenerate = true)
    val id: Long = 0,
    val sessionId: Long,
    val questionId: Long,
    val answerId: Long? = null, // for single choice
    val userAnswerText: String? = null, // for fill_blank, essay
    val answerData: String? = null, // JSON for complex answers (multiple, matching, ordering)
    val isCorrect: Boolean? = null,
    val answeredAt: Long = System.currentTimeMillis()
)
