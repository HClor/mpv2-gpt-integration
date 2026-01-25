package com.lms.testapp.ui.quiz

import android.view.LayoutInflater
import android.view.ViewGroup
import androidx.recyclerview.widget.DiffUtil
import androidx.recyclerview.widget.ListAdapter
import androidx.recyclerview.widget.RecyclerView
import com.lms.testapp.R
import com.lms.testapp.data.models.Answer
import com.lms.testapp.databinding.ItemAnswerBinding

class AnswerAdapter(
    private val isMultipleChoice: Boolean,
    private val onAnswerSelected: (Long, Boolean) -> Unit
) : ListAdapter<Answer, AnswerAdapter.AnswerViewHolder>(AnswerDiffCallback()) {

    private var selectedAnswerIds = mutableSetOf<Long>()

    fun setSelectedAnswers(ids: Set<Long>) {
        selectedAnswerIds = ids.toMutableSet()
        notifyDataSetChanged()
    }

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): AnswerViewHolder {
        val binding = ItemAnswerBinding.inflate(
            LayoutInflater.from(parent.context),
            parent,
            false
        )
        return AnswerViewHolder(binding)
    }

    override fun onBindViewHolder(holder: AnswerViewHolder, position: Int) {
        holder.bind(getItem(position))
    }

    inner class AnswerViewHolder(
        private val binding: ItemAnswerBinding
    ) : RecyclerView.ViewHolder(binding.root) {

        fun bind(answer: Answer) {
            val isSelected = selectedAnswerIds.contains(answer.id)

            binding.answerText.text = answer.answerText

            // Show radio or checkbox icon
            binding.answerIcon.setImageResource(
                if (isMultipleChoice) {
                    if (isSelected) R.drawable.ic_checkbox_checked else R.drawable.ic_checkbox_unchecked
                } else {
                    if (isSelected) R.drawable.ic_radio_checked else R.drawable.ic_radio_unchecked
                }
            )

            // Update background
            binding.root.isSelected = isSelected
            binding.root.setBackgroundResource(
                if (isSelected) R.drawable.answer_background else R.drawable.answer_background
            )

            binding.root.setOnClickListener {
                val newSelected = !isSelected

                if (isMultipleChoice) {
                    if (newSelected) {
                        selectedAnswerIds.add(answer.id)
                    } else {
                        selectedAnswerIds.remove(answer.id)
                    }
                } else {
                    selectedAnswerIds.clear()
                    if (newSelected) {
                        selectedAnswerIds.add(answer.id)
                    }
                }

                onAnswerSelected(answer.id, newSelected)
                notifyDataSetChanged()
            }
        }
    }

    private class AnswerDiffCallback : DiffUtil.ItemCallback<Answer>() {
        override fun areItemsTheSame(oldItem: Answer, newItem: Answer): Boolean {
            return oldItem.id == newItem.id
        }

        override fun areContentsTheSame(oldItem: Answer, newItem: Answer): Boolean {
            return oldItem == newItem
        }
    }
}
