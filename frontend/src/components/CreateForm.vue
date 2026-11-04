<script setup>
import { ref } from 'vue'

const props = defineProps({ submit: Function })

const title = ref('')
const description = ref('')
const saving = ref(false)
const error = ref('')

async function onSubmit() {
  if (!title.value.trim()) {
    error.value = 'El título es obligatorio.'
    return
  }
  saving.value = true
  error.value = ''
  try {
    await props.submit({ title: title.value.trim(), description: description.value.trim() || null })
    title.value = ''
    description.value = ''
  } catch (e) {
    error.value = e.message
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <form class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5" @submit.prevent="onSubmit">
    <h2 class="mb-3 text-base font-semibold">Nueva solicitud</h2>
    <div class="grid gap-3 md:grid-cols-[1fr_2fr_auto] md:items-start">
      <input
        v-model="title"
        maxlength="255"
        placeholder="Título"
        aria-label="Título"
        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:outline-none"
      />
      <textarea
        v-model="description"
        rows="1"
        maxlength="5000"
        placeholder="Descripción (opcional)"
        aria-label="Descripción"
        class="w-full resize-y rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:outline-none"
      />
      <button
        type="submit"
        :disabled="saving"
        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-60"
      >
        {{ saving ? 'Creando…' : 'Crear' }}
      </button>
    </div>
    <p v-if="error" class="mt-2 text-sm text-rose-600">{{ error }}</p>
  </form>
</template>
