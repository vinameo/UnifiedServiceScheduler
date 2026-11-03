export const BAY_TYPE_LABELS: Record<string, string> = {
  QUICK: 'Quick service',
  STANDARD: 'Standard service',
  EV: 'EV',
}

export function bayTypeLabel(type: string | undefined): string {
  if (!type) return ''
  return BAY_TYPE_LABELS[type] ?? type
}

export function levelLabel(level: number | undefined): string {
  if (level === undefined || level === null) return ''
  return `Level ${level}`
}
