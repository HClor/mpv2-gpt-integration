package com.lms.testapp.data.database

import android.content.Context
import androidx.room.Database
import androidx.room.Room
import androidx.room.RoomDatabase
import com.lms.testapp.data.models.*

@Database(
    entities = [
        Category::class,
        Test::class,
        Question::class,
        Answer::class,
        MatchingPair::class,
        OrderingItem::class,
        FillBlank::class,
        FillBlankAnswer::class,
        TestSession::class,
        UserAnswer::class
    ],
    version = 1,
    exportSchema = false
)
abstract class TestDatabase : RoomDatabase() {

    abstract fun categoryDao(): CategoryDao
    abstract fun testDao(): TestDao
    abstract fun questionDao(): QuestionDao
    abstract fun answerDao(): AnswerDao
    abstract fun matchingPairDao(): MatchingPairDao
    abstract fun orderingItemDao(): OrderingItemDao
    abstract fun fillBlankDao(): FillBlankDao
    abstract fun testSessionDao(): TestSessionDao
    abstract fun userAnswerDao(): UserAnswerDao

    companion object {
        @Volatile
        private var INSTANCE: TestDatabase? = null

        fun getDatabase(context: Context): TestDatabase {
            return INSTANCE ?: synchronized(this) {
                val instance = Room.databaseBuilder(
                    context.applicationContext,
                    TestDatabase::class.java,
                    "lms_test_database"
                )
                    .fallbackToDestructiveMigration()
                    .build()
                INSTANCE = instance
                instance
            }
        }
    }
}
