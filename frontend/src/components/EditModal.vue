<script setup>
import { onMounted, onUnmounted, ref } from 'vue'

const props = defineProps({ item: Object, save: Function })
const emit = defineEmits(['close'])

const title = ref(props.item.title)
const description = ref(props.item.description ?? '')
const saving = ref(false)
const error = ref('')
const titleInput = ref(null)

async function onSubmit() {
  if (!title.value.trim()) {
    error.value = 'El título es obligatorio.'
    return
  }
  saving.value = true
  error.value = ''
  try {
    await props.save(props.item.id, { title: title.value.trim(), description: description.value.trim() || null })
    emit('close')
  } catch (e) {
    error.value = e.message
  } finally {
    saving.value = false
  }
}

const onKey = (e) => e.key === 'Escape' && emit('close')
onMounted(() => {
  window.addEventListener('keydown', onKey)
  titleInput.value?.focus()
})
onUnmounted(() => window.removeEventListener('keydown', onKey))
</script>

<template>
  <div class="fixed inset-0 z-40 flex items-end justify-center bg-slate-900/50 p-0 sm:items-center sm:p-4" @click.self="emit('close')">
    <form
      role="dialog"
      aria-modal="true"
      aria-labelledby="edit-title"
      class="w-full max-w-lg rounded-t-2xl bg-white p-5 shadow-xl sm:rounded-2xl"
      @submit.prevent="onSubmit"
    >
      <h2 id="edit-title" class="text-lg font-semibold">Editar solicitud #{{ item.id }}</h2>

      <label class="mt-4 block text-sm font-medium">
        Título
        <input
          ref="titleInput"
          v-model="title"
          maxlength="255"
          class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:outline-none"
        />
      </label>
      <label class="mt-3 block text-sm font-medium">
        Descripción
        <textarea
          v-model="description"
          rows="5"
          maxlength="5000"
          class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:outline-none"
        />
      </label>

      <p v-if="error" class="mt-2 text-sm text-rose-600">{{ error }}</p>

      <div class="mt-5 flex justify-end gap-2">
        <button type="button" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100" @click="emit('close')">
          Cancelar
        </button>
        <button type="submit" :disabled="saving" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-60">
          {{ saving ? 'Guardando…' : 'Guardar' }}
        </button>
      </div>
    </form>
  </div>
</template>
