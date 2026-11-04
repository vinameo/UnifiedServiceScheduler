export function formatAge(seconds: number): string {
  if (seconds <= 0) return '0 s'
  if (seconds < 60) return `${seconds} s`
  if (seconds < 3600) return `${Math.floor(seconds / 60)} min`
  if (seconds < 86400) return `${Math.floor(seconds / 3600)} h`
  return `${Math.floor(seconds / 86400)} d`
}

export function formatPercent(value: number, total: number): string {
  if (total <= 0) return '0.0'
  return ((value / total) * 100).toFixed(1)
}
