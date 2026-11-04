<script setup>
import { STATUS_STYLE, actionLabel, actionStyle, formatDate } from '../statusUi'

defineProps({
  item: Object,
  busy: Boolean,
  showBadge: Boolean,
})
defineEmits(['edit', 'transition'])
</script>

<template>
  <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:shadow-md" :class="{ 'opacity-60': busy }">
    <div class="flex items-start justify-between gap-2">
      <h3 class="font-semibold leading-snug break-words">{{ item.title }}</h3>
      <button
        type="button"
        class="shrink-0 rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
        :aria-label="`Editar ${item.title}`"
        @click="$emit('edit', item)"
      >
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M12 20h9M16.5 3.5a2.1 2.1 0 1 1 3 3L7 19l-4 1 1-4Z" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </button>
    </div>

    <p v-if="item.description" class="mt-1 line-clamp-3 text-sm text-slate-600 break-words">{{ item.description }}</p>

    <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-slate-500">
      <span
        v-if="showBadge"
        class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 font-medium ring-1"
        :class="STATUS_STYLE[item.status]?.badge"
      >
        <span class="h-1.5 w-1.5 rounded-full" :class="STATUS_STYLE[item.status]?.dot" />
        {{ item.status_label }}
      </span>
      <span>#{{ item.id }} · {{ formatDate(item.created_at) }}</span>
    </div>

    <div v-if="item.allowed_transitions.length" class="mt-3 flex flex-wrap gap-2">
      <button
        v-for="to in item.allowed_transitions"
        :key="to"
        type="button"
        :disabled="busy"
        class="rounded-lg border px-2.5 py-1 text-xs font-semibold disabled:cursor-not-allowed"
        :class="actionStyle(to)"
        @click="$emit('transition', item, to)"
      >
        {{ actionLabel(item.status, to) }}
      </button>
    </div>
    <p v-else class="mt-3 text-xs font-medium text-emerald-700">✓ Finalizada</p>
  </article>
</template>
