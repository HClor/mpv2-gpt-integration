package com.lms.testapp.data.models

import androidx.room.Entity
import androidx.room.ForeignKey
import androidx.room.Index
import androidx.room.PrimaryKey

@Entity(
    tableName = "questions",
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
data class Question(
    @PrimaryKey
    val id: Long,
    val testId: Long,
    val questionText: String,
    val questionType: String, // single, multiple, matching, ordering, fill_blank, essay
    val questionImage: String? = null,
    val explanation: String? = null,
    val explanationImage: String? = null,
    val sortOrder: Int = 0,
    val isPublished: Boolean = true
)

enum class QuestionType(val value: String) {
    SINGLE("single"),
    MULTIPLE("multiple"),
    MATCHING("matching"),
    ORDERING("ordering"),
    FILL_BLANK("fill_blank"),
    ESSAY("essay");

    companion object {
        fun fromValue(value: String): QuestionType {
            return entries.find { it.value == value } ?: SINGLE
        }
    }
}
