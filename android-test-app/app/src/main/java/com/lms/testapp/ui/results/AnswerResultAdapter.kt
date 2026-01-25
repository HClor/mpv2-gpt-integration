package com.lms.testapp.ui.results

import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import androidx.recyclerview.widget.RecyclerView
import com.lms.testapp.R
import com.lms.testapp.databinding.ItemAnswerResultBinding

class AnswerResultAdapter(
    private val results: List<AnswerResult>
) : RecyclerView.Adapter<AnswerResultAdapter.AnswerResultViewHolder>() {

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): AnswerResultViewHolder {
        val binding = ItemAnswerResultBinding.inflate(
            LayoutInflater.from(parent.context),
            parent,
            false
        )
        return AnswerResultViewHolder(binding)
    }

    override fun onBindViewHolder(holder: AnswerResultViewHolder, position: Int) {
        holder.bind(results[position])
    }

    override fun getItemCount() = results.size

    inner class AnswerResultViewHolder(
        private val binding: ItemAnswerResultBinding
    ) : RecyclerView.ViewHolder(binding.root) {

        fun bind(result: AnswerResult) {
            binding.questionNumber.text = "${result.questionNumber}."
            binding.questionText.text = result.questionText

            when (result.isCorrect) {
                true -> {
                    binding.resultIcon.setImageResource(R.drawable.ic_check)
                    binding.root.setBackgroundResource(R.drawable.answer_correct)
                }
                false -> {
                    binding.resultIcon.setImageResource(R.drawable.ic_close)
                    binding.root.setBackgroundResource(R.drawable.answer_incorrect)
                }
                null -> {
                    // Essay or not graded
                    binding.resultIcon.setImageResource(R.drawable.ic_timer)
                    binding.root.setBackgroundResource(R.drawable.answer_background)
                }
            }

            if (!result.explanation.isNullOrBlank()) {
                binding.explanationText.visibility = View.VISIBLE
                binding.explanationText.text = result.explanation
            } else {
                binding.explanationText.visibility = View.GONE
            }
        }
    }
}
