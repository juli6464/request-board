const BASE = import.meta.env.VITE_API_BASE ?? '/api/v1'

export class ApiError extends Error {
  constructor(message, status, body) {
    super(message)
    this.status = status
    this.body = body
  }
}

async function request(path, { method = 'GET', body } = {}) {
  let res
  try {
    res = await fetch(`${BASE}${path}`, {
      method,
      headers: { Accept: 'application/json', ...(body ? { 'Content-Type': 'application/json' } : {}) },
      body: body ? JSON.stringify(body) : undefined,
    })
  } catch {
    throw new ApiError('No se pudo conectar con la API.', 0, null)
  }

  const data = await res.json().catch(() => null)

  if (!res.ok) {
    const firstValidation = data?.errors ? Object.values(data.errors)[0]?.[0] : null
    throw new ApiError(firstValidation ?? data?.message ?? `Error ${res.status}`, res.status, data)
  }
  return data
}

export const api = {
  statuses: () => request('/statuses').then((r) => r.data),
  list: () => request('/applications').then((r) => r.data),
  create: (payload) => request('/applications', { method: 'POST', body: payload }).then((r) => r.data),
  update: (id, payload) => request(`/applications/${id}`, { method: 'PUT', body: payload }).then((r) => r.data),
  transition: (id, target_status) =>
    request(`/applications/${id}/transition`, { method: 'POST', body: { target_status } }).then((r) => r.data),
  summary: (refresh = false) => request(`/applications/summary/llm${refresh ? '?refresh=1' : ''}`),
}
