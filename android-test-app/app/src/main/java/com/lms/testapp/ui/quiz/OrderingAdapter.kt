package com.lms.testapp.ui.quiz

import android.annotation.SuppressLint
import android.view.LayoutInflater
import android.view.MotionEvent
import android.view.ViewGroup
import androidx.recyclerview.widget.ItemTouchHelper
import androidx.recyclerview.widget.RecyclerView
import com.lms.testapp.data.models.OrderingItem
import com.lms.testapp.databinding.ItemOrderingBinding
import java.util.Collections

class OrderingAdapter(
    private val items: MutableList<OrderingItem>,
    private val onOrderChanged: (List<Long>) -> Unit
) : RecyclerView.Adapter<OrderingAdapter.OrderingViewHolder>() {

    private var touchHelper: ItemTouchHelper? = null

    fun setTouchHelper(helper: ItemTouchHelper) {
        touchHelper = helper
    }

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): OrderingViewHolder {
        val binding = ItemOrderingBinding.inflate(
            LayoutInflater.from(parent.context),
            parent,
            false
        )
        return OrderingViewHolder(binding)
    }

    override fun onBindViewHolder(holder: OrderingViewHolder, position: Int) {
        holder.bind(items[position], position)
    }

    override fun getItemCount() = items.size

    fun onItemMove(fromPosition: Int, toPosition: Int) {
        Collections.swap(items, fromPosition, toPosition)
        notifyItemMoved(fromPosition, toPosition)
        onOrderChanged(items.map { it.id })
    }

    inner class OrderingViewHolder(
        private val binding: ItemOrderingBinding
    ) : RecyclerView.ViewHolder(binding.root) {

        @SuppressLint("ClickableViewAccessibility")
        fun bind(item: OrderingItem, position: Int) {
            binding.positionNumber.text = "${position + 1}"
            binding.itemText.text = item.itemText

            binding.dragHandle.setOnTouchListener { _, event ->
                if (event.actionMasked == MotionEvent.ACTION_DOWN) {
                    touchHelper?.startDrag(this)
                }
                false
            }
        }
    }

    class OrderingTouchCallback(
        private val adapter: OrderingAdapter
    ) : ItemTouchHelper.SimpleCallback(
        ItemTouchHelper.UP or ItemTouchHelper.DOWN,
        0
    ) {
        override fun onMove(
            recyclerView: RecyclerView,
            viewHolder: RecyclerView.ViewHolder,
            target: RecyclerView.ViewHolder
        ): Boolean {
            adapter.onItemMove(viewHolder.adapterPosition, target.adapterPosition)
            return true
        }

        override fun onSwiped(viewHolder: RecyclerView.ViewHolder, direction: Int) {}

        override fun isLongPressDragEnabled() = false
    }
}
