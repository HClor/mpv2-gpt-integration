package com.lms.testapp.data.models

import androidx.room.Entity
import androidx.room.ForeignKey
import androidx.room.Index
import androidx.room.PrimaryKey

@Entity(
    tableName = "answers",
    foreignKeys = [
        ForeignKey(
            entity = Question::class,
            parentColumns = ["id"],
            childColumns = ["questionId"],
            onDelete = ForeignKey.CASCADE
        )
    ],
    indices = [Index("questionId")]
)
data class Answer(
    @PrimaryKey
    val id: Long,
    val questionId: Long,
    val answerText: String,
    val answerImage: String? = null,
    val isCorrect: Boolean = false,
    val sortOrder: Int = 0
)
