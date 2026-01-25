package com.lms.testapp.data.models

import androidx.room.Entity
import androidx.room.ForeignKey
import androidx.room.Index
import androidx.room.PrimaryKey

@Entity(
    tableName = "fill_blanks",
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
data class FillBlank(
    @PrimaryKey
    val id: Long,
    val questionId: Long,
    val templateText: String, // "Столица России - {{1}}, а Франции - {{2}}."
    val caseSensitive: Boolean = false
)

@Entity(
    tableName = "fill_blank_answers",
    foreignKeys = [
        ForeignKey(
            entity = FillBlank::class,
            parentColumns = ["id"],
            childColumns = ["fillBlankId"],
            onDelete = ForeignKey.CASCADE
        )
    ],
    indices = [Index("fillBlankId")]
)
data class FillBlankAnswer(
    @PrimaryKey
    val id: Long,
    val fillBlankId: Long,
    val blankNumber: Int,
    val correctAnswer: String,
    val alternativeAnswers: String? = null // JSON array of alternative answers
)
