<script setup>
import { computed, onMounted, ref } from 'vue'
import { api } from './api'
import { STATUS_STYLE } from './statusUi'
import SummaryPanel from './components/SummaryPanel.vue'
import CreateForm from './components/CreateForm.vue'
import ApplicationCard from './components/ApplicationCard.vue'
import EditModal from './components/EditModal.vue'

const statuses = ref([])
const items = ref([])
const loading = ref(true)
const loadError = ref('')
const filter = ref('all')
const busyId = ref(null)
const editing = ref(null)
const toast = ref(null)

const summary = ref(null)
const summaryLoading = ref(false)
const summaryStale = ref(false)

const counts = computed(() =>
  Object.fromEntries(statuses.value.map((s) => [s.code, items.value.filter((i) => i.status === s.code).length])),
)
const visibleStatuses = computed(() =>
  filter.value === 'all' ? statuses.value : statuses.value.filter((s) => s.code === filter.value),
)
const byStatus = (code) => items.value.filter((i) => i.status === code)

function notify(message, type = 'error') {
  toast.value = { message, type }
  clearTimeout(notify.t)
  notify.t = setTimeout(() => (toast.value = null), 4500)
}

function upsert(item) {
  const idx = items.value.findIndex((i) => i.id === item.id)
  if (idx === -1) items.value.unshift(item)
  else items.value.splice(idx, 1, item)
  summaryStale.value = true
}

async function loadBoard() {
  loading.value = true
  loadError.value = ''
  try {
    const [st, list] = await Promise.all([api.statuses(), api.list()])
    statuses.value = st
    items.value = list
  } catch (e) {
    loadError.value = e.message
  } finally {
    loading.value = false
  }
}

// El resumen se pide aparte y nunca bloquea el listado.
async function loadSummary(refresh = false) {
  summaryLoading.value = true
  try {
    summary.value = await api.summary(refresh)
    summaryStale.value = false
  } catch {
    summary.value = { summary: null, fallback: true, message: 'El servicio de analítica IA no está disponible en este momento.' }
  } finally {
    summaryLoading.value = false
  }
}

async function create(payload) {
  const item = await api.create(payload)
  upsert(item)
  notify('Solicitud creada', 'ok')
}

async function save(id, payload) {
  upsert(await api.update(id, payload))
  notify('Cambios guardados', 'ok')
}

async function transition(item, to) {
  busyId.value = item.id
  try {
    upsert(await api.transition(item.id, to))
  } catch (e) {
    notify(e.message)
    // Si el estado cambió en otra pestaña, resincronizamos.
    if (e.status === 400) loadBoard()
  } finally {
    busyId.value = null
  }
}

onMounted(() => {
  loadBoard()
  loadSummary()
})
</script>

<template>
  <div class="mx-auto max-w-[1400px] px-4 py-6 sm:px-6 lg:py-8">
    <header class="mb-6 flex flex-wrap items-end justify-between gap-2">
      <div>
        <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">Tablero de solicitudes</h1>
        <p class="text-sm text-slate-500">Crea, edita y mueve solicitudes a través de su flujo de estados.</p>
      </div>
      <p class="text-sm text-slate-500">{{ items.length }} solicitudes</p>
    </header>

    <SummaryPanel :loading="summaryLoading" :data="summary" :stale="summaryStale" @refresh="loadSummary(true)" />

    <div class="mt-6">
      <CreateForm :submit="create" />
    </div>

    <nav class="mt-6 -mx-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:px-0" aria-label="Filtrar por estado">
      <button
        type="button"
        class="shrink-0 rounded-full px-3 py-1.5 text-sm font-medium ring-1"
        :class="filter === 'all' ? 'bg-slate-900 text-white ring-slate-900' : 'bg-white ring-slate-300 hover:bg-slate-100'"
        @click="filter = 'all'"
      >
        Todas <span class="opacity-70">{{ items.length }}</span>
      </button>
      <button
        v-for="s in statuses"
        :key="s.code"
        type="button"
        class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-medium ring-1"
        :class="filter === s.code ? 'bg-slate-900 text-white ring-slate-900' : 'bg-white ring-slate-300 hover:bg-slate-100'"
        @click="filter = s.code"
      >
        <span class="h-2 w-2 rounded-full" :class="STATUS_STYLE[s.code]?.dot" />
        {{ s.name }} <span class="opacity-70">{{ counts[s.code] }}</span>
      </button>
    </nav>

    <div v-if="loading" class="mt-10 flex justify-center text-slate-500">
      <span class="h-6 w-6 animate-spin rounded-full border-2 border-slate-300 border-t-indigo-600" />
    </div>

    <div v-else-if="loadError" class="mt-6 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
      {{ loadError }}
      <button class="ml-2 font-semibold underline" @click="loadBoard">Reintentar</button>
    </div>

    <div
      v-else
      class="mt-4 grid gap-4"
      :class="filter === 'all' ? 'sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5' : 'grid-cols-1'"
    >
      <section
        v-for="s in visibleStatuses"
        :key="s.code"
        class="flex min-w-0 flex-col rounded-2xl border border-t-4 border-slate-200 bg-slate-100/70 p-3"
        :class="STATUS_STYLE[s.code]?.col"
      >
        <h2 class="mb-3 flex items-center justify-between px-1 text-sm font-semibold">
          {{ s.name }}
          <span class="rounded-full bg-white px-2 py-0.5 text-xs text-slate-600 ring-1 ring-slate-200">{{ counts[s.code] }}</span>
        </h2>
        <div class="grid gap-3" :class="{ 'md:grid-cols-2 xl:grid-cols-3': filter !== 'all' }">
          <ApplicationCard
            v-for="item in byStatus(s.code)"
            :key="item.id"
            :item="item"
            :busy="busyId === item.id"
            @edit="editing = $event"
            @transition="transition"
          />
        </div>
        <p v-if="!counts[s.code]" class="px-1 py-6 text-center text-sm text-slate-400">Sin solicitudes</p>
      </section>
    </div>

    <EditModal v-if="editing" :item="editing" :save="save" @close="editing = null" />

    <Transition
      enter-from-class="translate-y-4 opacity-0"
      leave-to-class="translate-y-4 opacity-0"
      enter-active-class="transition"
      leave-active-class="transition"
    >
      <div
        v-if="toast"
        role="status"
        class="fixed inset-x-4 bottom-4 z-50 mx-auto max-w-md rounded-xl px-4 py-3 text-sm font-medium text-white shadow-lg"
        :class="toast.type === 'ok' ? 'bg-emerald-600' : 'bg-rose-600'"
      >
        {{ toast.message }}
      </div>
    </Transition>
  </div>
</template>
