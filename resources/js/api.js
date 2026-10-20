const BASE = '/nova-vendor/supportrays'

/**
 * Thrown when the user's SupportRays key is missing, expired or revoked: the
 * page swaps itself for the connect screen.
 */
export class NotConnectedError extends Error {}

function unwrap(error) {
  const response = error.response
  if (response && response.status === 409 && response.data?.code === 'not_connected') {
    throw new NotConnectedError(response.data.message)
  }
  throw error
}

/** Ticket work, relayed to the SupportRays manager API with the user's key. */
export function sr(method, path, { params, data, idempotencyKey } = {}) {
  return Nova.request()
    .request({
      method,
      url: `${BASE}/api/${path.replace(/^\//, '')}`,
      params,
      data,
      headers: idempotencyKey ? { 'Idempotency-Key': idempotencyKey } : {},
    })
    .then(response => response.data)
    .catch(unwrap)
}

/** The host app's own cards and links for a ticket's sidebar. */
export function ticketContext(uuid) {
  return Nova.request()
    .get(`${BASE}/context/${uuid}`)
    .then(response => response.data)
    .catch(unwrap)
}

/** A browser link that downloads an attachment through the proxy. */
export function attachmentUrl(uuid) {
  return `${BASE}/api/attachments/${uuid}`
}

export const connection = {
  show: () => Nova.request().get(`${BASE}/connection`).then(r => r.data),
  requestCode: () => Nova.request().post(`${BASE}/connection/code`).then(r => r.data),
  verify: code => Nova.request().post(`${BASE}/connection`, { code }).then(r => r.data),
  disconnect: () => Nova.request().delete(`${BASE}/connection`).then(r => r.data),
}

/** The first validation message, else the API's message, else the fallback. */
export function errorMessage(error, fallback) {
  const data = error?.response?.data || {}
  const first = Object.values(data.errors || {})[0]
  return (Array.isArray(first) ? first[0] : first) || data.message || fallback
}

export function newIdempotencyKey() {
  return window.crypto?.randomUUID ? window.crypto.randomUUID() : `${Date.now()}-${Math.random().toString(16).slice(2)}`
}
