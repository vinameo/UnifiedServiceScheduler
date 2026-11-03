import { Alert, Button, Group, Stack, Text } from '@mantine/core'
import type React from 'react'
import { IDEMPOTENCY_COPY, shortenKey } from './copy'

export interface IdempotentRetryPanelProps {
  idempotencyKey: string
  reason: 'no-response' | 'in-progress'
  variant?: 'staff' | 'customer'
  onRetry: () => void
  isRetrying?: boolean
  secondaryAction?: React.ReactNode
  title?: string
}

export function IdempotentRetryPanel({
  idempotencyKey,
  reason,
  variant = 'customer',
  onRetry,
  isRetrying = false,
  secondaryAction,
  title,
}: IdempotentRetryPanelProps) {
  const isInProgress = reason === 'in-progress'
  const displayTitle =
    title ?? (isInProgress ? 'Request in progress' : IDEMPOTENCY_COPY.retryTitle)

  const bodyText = isInProgress
    ? IDEMPOTENCY_COPY.inProgressBody
    : variant === 'staff'
      ? IDEMPOTENCY_COPY.retryBodyStaff(shortenKey(idempotencyKey))
      : IDEMPOTENCY_COPY.retryBodyCustomer

  return (
    <Alert
      color={isInProgress ? 'blue' : 'yellow'}
      title={displayTitle}
      radius="md"
      mt="md"
    >
      <Stack gap="sm">
        <Text size="sm">{bodyText}</Text>
        <Group gap="sm" mt="xs">
          <Button
            size="sm"
            color="blue"
            onClick={onRetry}
            loading={isRetrying}
            disabled={isRetrying}
          >
            {IDEMPOTENCY_COPY.retryButton}
          </Button>
          {secondaryAction}
        </Group>
      </Stack>
    </Alert>
  )
}
