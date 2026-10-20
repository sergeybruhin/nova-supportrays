export const STATUS_LABELS = {
  new: 'Новый',
  open: 'В работе',
  pending: 'Ожидает ответа',
  on_hold: 'Приостановлен',
  resolved: 'Решён',
  closed: 'Закрыт',
}

export const PRIORITY_LABELS = {
  low: 'Низкий',
  normal: 'Обычный',
  high: 'Высокий',
  urgent: 'Срочный',
}

const dateTime = new Intl.DateTimeFormat('ru', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
const date = new Intl.DateTimeFormat('ru', { day: '2-digit', month: '2-digit', year: 'numeric' })
const money = new Intl.NumberFormat('ru', { style: 'currency', currency: 'RUB', maximumFractionDigits: 2 })
const number = new Intl.NumberFormat('ru')

export function formatTimestamp(value) {
  if (!value) return '—'
  const parsed = new Date(value)
  return Number.isNaN(parsed.getTime()) ? '—' : dateTime.format(parsed)
}

/** Ticket custom fields are self-describing: `{key, label, value, type}`. */
export function formatCustomField(field) {
  if (field.value === null || field.value === undefined || field.value === '') return '—'
  if (field.type === 'money' && !Number.isNaN(Number(field.value))) return money.format(Number(field.value))
  if (field.type === 'number' && !Number.isNaN(Number(field.value))) return number.format(Number(field.value))
  if (field.type === 'date') {
    const parsed = new Date(field.value)
    return Number.isNaN(parsed.getTime()) ? String(field.value) : date.format(parsed)
  }
  return String(field.value)
}

/** Links come from integrations; only plain web links are rendered. */
export function isSafeUrl(url) {
  try {
    return ['http:', 'https:'].includes(new URL(url).protocol)
  } catch {
    return false
  }
}

export function isActive(ticket) {
  return ticket && !['resolved', 'closed'].includes(ticket.status)
}

export function isBreached(ticket) {
  return Boolean(ticket?.sla?.first_response_breached_at || ticket?.sla?.resolution_breached_at)
}

export function formatSize(bytes) {
  if (!bytes && bytes !== 0) return ''
  if (bytes < 1024) return `${bytes} Б`
  if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} КБ`
  return `${(bytes / 1024 / 1024).toFixed(1).replace('.', ',')} МБ`
}
