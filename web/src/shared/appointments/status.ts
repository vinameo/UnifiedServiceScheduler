export const CLOSED_STATUSES = ['COMPLETED', 'CANCELLED', 'NO_SHOW', 'EXPIRED'] as const

export function isClosed(status: string): boolean {
  return CLOSED_STATUSES.includes(status as (typeof CLOSED_STATUSES)[number])
}
