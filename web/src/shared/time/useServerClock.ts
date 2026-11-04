let serverOffsetMs = 0

/**
 * Updates the recorded server clock offset from an API response Date header or timestamp.
 * offsetMs = Date.parse(response Date header) - Date.now() (D-10.11, WEB-10.1).
 */
export function recordServerDate(dateHeader: string | number | Date): void {
  const serverTime =
    typeof dateHeader === 'number'
      ? dateHeader
      : dateHeader instanceof Date
        ? dateHeader.getTime()
        : Date.parse(dateHeader)

  if (!Number.isNaN(serverTime)) {
    serverOffsetMs = serverTime - Date.now()
  }
}

/**
 * Returns the current server offset in milliseconds.
 */
export function getServerOffsetMs(): number {
  return serverOffsetMs
}

/**
 * Returns the current server time adjusted by the recorded offset (now + offsetMs).
 */
export function getServerNow(): Date {
  return new Date(Date.now() + serverOffsetMs)
}

/**
 * Resets the server clock offset to 0 (for test isolation).
 */
export function resetServerClock(): void {
  serverOffsetMs = 0
}

/**
 * Hook providing server-synchronized now() and offsetMs.
 */
export function useServerClock() {
  return {
    now: getServerNow,
    offsetMs: serverOffsetMs,
  }
}
