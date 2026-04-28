// Single source of truth for call/job status → color theme.
// Used by UiBadge, table rows, dashboard, etc.

export const STATUS_COLORS = {
  completed:   { fg: '#39FF14', label: 'Completed',  dot: 'var(--color-status-completed)' },
  in_progress: { fg: '#c89bff', label: 'In progress', dot: 'var(--color-status-progress)' },
  voicemail:   { fg: '#ffc14d', label: 'Voicemail',  dot: 'var(--color-status-voicemail)' },
  failed:      { fg: '#ff6b6b', label: 'Failed',     dot: 'var(--color-status-failed)' },
  calling:     { fg: '#7ec3ff', label: 'Calling',    dot: 'var(--color-status-calling)' },
  paid:        { fg: '#7ec3ff', label: 'Paid',       dot: 'var(--color-status-calling)' },
  pending_payment: { fg: '#9aa0a6', label: 'Pending pago', dot: '#9aa0a6' },
  refunded:    { fg: '#ffc14d', label: 'Refunded',   dot: 'var(--color-status-voicemail)' },
  generating_joke:  { fg: '#c89bff', label: 'Generando…', dot: 'var(--color-status-progress)' },
  generating_audio: { fg: '#c89bff', label: 'Audio…',     dot: 'var(--color-status-progress)' },
  queued_for_call:  { fg: '#7ec3ff', label: 'En cola',    dot: 'var(--color-status-calling)' },
}

export function statusOf(key) {
  return STATUS_COLORS[key] || { fg: '#9aa0a6', label: key || '—', dot: '#9aa0a6' }
}
