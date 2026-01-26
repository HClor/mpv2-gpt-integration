package com.lms.testapp.ui.tests

import android.view.LayoutInflater
import android.view.ViewGroup
import androidx.recyclerview.widget.DiffUtil
import androidx.recyclerview.widget.ListAdapter
import androidx.recyclerview.widget.RecyclerView
import com.lms.testapp.R
import com.lms.testapp.data.models.Test
import com.lms.testapp.databinding.ItemTestBinding

class TestAdapter(
    private val onTestClick: (Test) -> Unit,
    private val getQuestionCount: (Long) -> Int
) : ListAdapter<Test, TestAdapter.TestViewHolder>(TestDiffCallback()) {

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): TestViewHolder {
        val binding = ItemTestBinding.inflate(
            LayoutInflater.from(parent.context),
            parent,
            false
        )
        return TestViewHolder(binding)
    }

    override fun onBindViewHolder(holder: TestViewHolder, position: Int) {
        holder.bind(getItem(position))
    }

    inner class TestViewHolder(
        private val binding: ItemTestBinding
    ) : RecyclerView.ViewHolder(binding.root) {

        fun bind(test: Test) {
            val context = binding.root.context
            val questionCount = getQuestionCount(test.id)

            binding.testTitle.text = test.title
            binding.testDescription.text = test.description ?: ""
            binding.questionCount.text = context.getString(R.string.test_questions, questionCount)

            if (test.timeLimit != null) {
                binding.timeLimit.text = context.getString(R.string.test_time_limit, test.timeLimit)
            } else {
                binding.timeLimit.text = ""
            }

            binding.passScore.text = context.getString(R.string.test_pass_score, test.passScore)

            binding.modeChip.text = if (test.mode == "exam") {
                context.getString(R.string.exam_mode)
            } else {
                context.getString(R.string.training_mode)
            }

            binding.root.setOnClickListener {
                onTestClick(test)
            }

            binding.startButton.setOnClickListener {
                onTestClick(test)
            }
        }
    }

    private class TestDiffCallback : DiffUtil.ItemCallback<Test>() {
        override fun areItemsTheSame(oldItem: Test, newItem: Test): Boolean {
            return oldItem.id == newItem.id
        }

        override fun areContentsTheSame(oldItem: Test, newItem: Test): Boolean {
            return oldItem == newItem
        }
    }
}
