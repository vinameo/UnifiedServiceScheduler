export function shortenKey(key: string): string {
  if (key.length <= 8) return key
  return `${key.slice(0, 4)}…${key.slice(-4)}`
}

export const IDEMPOTENCY_COPY = {
  retryTitle: 'No response from the server yet',
  retryBodyStaff: (key: string) =>
    `It is safe to retry. The request reuses key ${key}, so if the first attempt succeeded the system returns that same appointment instead of creating a second one.`,
  retryBodyCustomer: "No response yet. It's safe to try again: you won't be booked twice.",
  retryButton: 'Retry',
  inProgressBody: 'Your request is still being processed. Wait a moment, then check again.',
  submittingConfirm: 'Confirming…',
  doubleSubmitNote: (key: string) =>
    `Each booking carries its own request key (${key}). On a flaky network, pressing again is safe and never creates a duplicate.`,
} as const
