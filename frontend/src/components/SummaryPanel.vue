<script setup>
defineProps({
  loading: Boolean,
  data: Object, // { summary, fallback, message, generated_at?, cached? }
  stale: Boolean,
})
defineEmits(['refresh'])
</script>

<template>
  <section
    class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-600 to-violet-700 p-5 text-white shadow-lg sm:p-6"
    aria-live="polite"
  >
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <p class="text-xs font-semibold uppercase tracking-wider text-indigo-200">Resumen con IA</p>
        <h2 class="text-lg font-semibold">¿Cómo va el tablero?</h2>
      </div>
      <button
        type="button"
        class="inline-flex items-center gap-2 rounded-lg bg-white/15 px-3 py-1.5 text-sm font-medium hover:bg-white/25 disabled:opacity-60"
        :disabled="loading"
        @click="$emit('refresh')"
      >
        <svg class="h-4 w-4" :class="{ 'animate-spin': loading }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M21 12a9 9 0 1 1-3-6.7M21 3v6h-6" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        {{ loading ? 'Generando…' : 'Regenerar' }}
      </button>
    </div>

    <div class="mt-4 min-h-[3.5rem]">
      <div v-if="loading" class="flex items-center gap-3 text-indigo-100">
        <span class="h-5 w-5 animate-spin rounded-full border-2 border-white/30 border-t-white" />
        Analizando las solicitudes…
      </div>

      <div v-else-if="data?.fallback" class="flex items-start gap-3 rounded-xl bg-white/10 p-3 text-sm">
        <span aria-hidden="true">⚠️</span>
        <div>
          <p class="font-medium">{{ data.message }}</p>
          <p class="text-indigo-200">El tablero sigue funcionando con normalidad. Puedes reintentar en un momento.</p>
        </div>
      </div>

      <p v-else-if="data?.summary" class="text-base leading-relaxed whitespace-pre-line">{{ data.summary }}</p>
    </div>

    <p v-if="!loading && data && !data.fallback" class="mt-3 text-xs text-indigo-200">
      Generado {{ new Date(data.generated_at).toLocaleString('es') }}<span v-if="data.cached"> · desde caché</span>
      <span v-if="stale" class="ml-2 rounded bg-amber-300/90 px-1.5 py-0.5 font-medium text-amber-950">
        Hay cambios desde este resumen
      </span>
    </p>
  </section>
</template>
