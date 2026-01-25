package com.lms.testapp.ui.quiz

import android.text.Editable
import android.text.TextWatcher
import android.view.LayoutInflater
import android.view.ViewGroup
import androidx.recyclerview.widget.RecyclerView
import com.lms.testapp.databinding.ItemFillBlankBinding

class FillBlankAdapter(
    private val blankCount: Int,
    private val currentAnswers: Map<Int, String>,
    private val onAnswerChanged: (Int, String) -> Unit
) : RecyclerView.Adapter<FillBlankAdapter.FillBlankViewHolder>() {

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): FillBlankViewHolder {
        val binding = ItemFillBlankBinding.inflate(
            LayoutInflater.from(parent.context),
            parent,
            false
        )
        return FillBlankViewHolder(binding)
    }

    override fun onBindViewHolder(holder: FillBlankViewHolder, position: Int) {
        holder.bind(position + 1)
    }

    override fun getItemCount() = blankCount

    inner class FillBlankViewHolder(
        private val binding: ItemFillBlankBinding
    ) : RecyclerView.ViewHolder(binding.root) {

        private var textWatcher: TextWatcher? = null

        fun bind(blankNumber: Int) {
            // Remove previous watcher
            textWatcher?.let { binding.blankInput.removeTextChangedListener(it) }

            binding.blankLabel.text = "Пропуск $blankNumber:"
            binding.blankInput.setText(currentAnswers[blankNumber] ?: "")

            textWatcher = object : TextWatcher {
                override fun beforeTextChanged(s: CharSequence?, start: Int, count: Int, after: Int) {}
                override fun onTextChanged(s: CharSequence?, start: Int, before: Int, count: Int) {}
                override fun afterTextChanged(s: Editable?) {
                    onAnswerChanged(blankNumber, s?.toString() ?: "")
                }
            }
            binding.blankInput.addTextChangedListener(textWatcher)
        }
    }
}
