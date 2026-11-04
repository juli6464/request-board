// Solo presentación: colores y verbos. Qué transiciones existen lo decide el backend
// (campo `allowed_transitions` de cada solicitud).

export const STATUS_STYLE = {
  created: { dot: 'bg-slate-400', badge: 'bg-slate-100 text-slate-700 ring-slate-300', col: 'border-t-slate-400' },
  in_review: { dot: 'bg-amber-400', badge: 'bg-amber-50 text-amber-800 ring-amber-300', col: 'border-t-amber-400' },
  in_progress: { dot: 'bg-sky-500', badge: 'bg-sky-50 text-sky-800 ring-sky-300', col: 'border-t-sky-500' },
  paused: { dot: 'bg-rose-500', badge: 'bg-rose-50 text-rose-800 ring-rose-300', col: 'border-t-rose-500' },
  done: { dot: 'bg-emerald-500', badge: 'bg-emerald-50 text-emerald-800 ring-emerald-300', col: 'border-t-emerald-500' },
}

export function actionLabel(from, to) {
  if (to === 'paused') return 'Pausar'
  if (to === 'done') return 'Terminar'
  if (to === 'in_review') return from === 'paused' ? 'Volver a revisión' : 'Enviar a revisión'
  if (to === 'in_progress') return from === 'paused' ? 'Reanudar' : 'Iniciar trabajo'
  return to
}

export function actionStyle(to) {
  if (to === 'paused') return 'border-rose-300 text-rose-700 hover:bg-rose-50'
  if (to === 'done') return 'border-emerald-600 bg-emerald-600 text-white hover:bg-emerald-700'
  return 'border-slate-300 text-slate-700 hover:bg-slate-100'
}

export function formatDate(iso) {
  if (!iso) return ''
  return new Date(iso).toLocaleDateString('es', { day: '2-digit', month: 'short', year: 'numeric' })
}
