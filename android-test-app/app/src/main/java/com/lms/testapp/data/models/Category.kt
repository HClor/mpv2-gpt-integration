package com.lms.testapp.data.models

import androidx.room.Entity
import androidx.room.PrimaryKey

@Entity(tableName = "categories")
data class Category(
    @PrimaryKey
    val id: Long,
    val parentId: Long? = null,
    val name: String,
    val description: String? = null,
    val icon: String? = null,
    val sortOrder: Int = 0
)
