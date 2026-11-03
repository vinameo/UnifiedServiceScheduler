import { Alert, Button, Group, Stack, Text } from '@mantine/core'
import type React from 'react'
import { Link, useInRouterContext } from 'react-router'
import { IDEMPOTENCY_COPY, shortenKey } from './copy'

export interface IdempotentRetryPanelProps {
  idempotencyKey: string
  reason: 'no-response' | 'in-progress'
  variant?: 'staff' | 'customer'
  date?: string
  checkListUrl?: string
  onRetry: () => void
  isRetrying?: boolean
  secondaryAction?: React.ReactNode
  title?: string
}

function CheckListButton({ url }: { url: string }) {
  const inRouter = useInRouterContext()
  if (inRouter) {
    return (
      <Button
        component={Link}
        to={url}
        size="sm"
        variant="default"
      >
        {IDEMPOTENCY_COPY.checkList}
      </Button>
    )
  }
  return (
    <Button
      component="a"
      href={url}
      size="sm"
      variant="default"
    >
      {IDEMPOTENCY_COPY.checkList}
    </Button>
  )
}

export function IdempotentRetryPanel({
  idempotencyKey,
  reason,
  variant = 'customer',
  date,
  checkListUrl,
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

  const listUrl =
    checkListUrl ??
    (variant === 'staff'
      ? date
        ? `/staff/appointments?date=${date}`
        : '/staff/appointments'
      : '/my-bookings')

  const effectiveSecondaryAction =
    secondaryAction !== undefined ? secondaryAction : <CheckListButton url={listUrl} />

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
          {effectiveSecondaryAction}
        </Group>
      </Stack>
    </Alert>
  )
}
