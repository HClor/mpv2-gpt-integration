package com.lms.testapp.ui.quiz

import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.AdapterView
import android.widget.ArrayAdapter
import androidx.recyclerview.widget.RecyclerView
import com.lms.testapp.data.models.MatchingPair
import com.lms.testapp.databinding.ItemMatchingBinding

class MatchingAdapter(
    private val pairs: List<MatchingPair>,
    private val onPairMatched: (Long, Long) -> Unit
) : RecyclerView.Adapter<MatchingAdapter.MatchingViewHolder>() {

    private val leftItems = pairs.map { it.id to it.leftItem }
    private val rightItems = pairs.shuffled().map { it.id to it.rightItem }
    private val selectedPairs = mutableMapOf<Long, Long>()

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): MatchingViewHolder {
        val binding = ItemMatchingBinding.inflate(
            LayoutInflater.from(parent.context),
            parent,
            false
        )
        return MatchingViewHolder(binding)
    }

    override fun onBindViewHolder(holder: MatchingViewHolder, position: Int) {
        holder.bind(leftItems[position])
    }

    override fun getItemCount() = leftItems.size

    inner class MatchingViewHolder(
        private val binding: ItemMatchingBinding
    ) : RecyclerView.ViewHolder(binding.root) {

        fun bind(leftItem: Pair<Long, String>) {
            binding.leftItemText.text = leftItem.second

            // Setup spinner for right items
            val rightTexts = listOf("Выберите...") + rightItems.map { it.second }
            val adapter = ArrayAdapter(
                binding.root.context,
                android.R.layout.simple_spinner_item,
                rightTexts
            )
            adapter.setDropDownViewResource(android.R.layout.simple_spinner_dropdown_item)
            binding.rightItemSpinner.adapter = adapter

            // Restore selection if any
            selectedPairs[leftItem.first]?.let { selectedRightId ->
                val rightIndex = rightItems.indexOfFirst { it.first == selectedRightId }
                if (rightIndex >= 0) {
                    binding.rightItemSpinner.setSelection(rightIndex + 1)
                }
            }

            binding.rightItemSpinner.onItemSelectedListener = object : AdapterView.OnItemSelectedListener {
                override fun onItemSelected(parent: AdapterView<*>?, view: View?, position: Int, id: Long) {
                    if (position > 0) {
                        val rightId = rightItems[position - 1].first
                        selectedPairs[leftItem.first] = rightId
                        onPairMatched(leftItem.first, rightId)
                    }
                }

                override fun onNothingSelected(parent: AdapterView<*>?) {}
            }
        }
    }
}
