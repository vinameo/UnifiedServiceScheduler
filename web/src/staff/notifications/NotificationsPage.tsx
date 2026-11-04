import { useEffect, useState } from 'react'
import {
  Alert,
  Anchor,
  Badge,
  Box,
  Button,
  Card,
  Grid,
  Group,
  Loader,
  Select,
  SimpleGrid,
  Stack,
  Table,
  Text,
  TextInput,
  Title,
  UnstyledButton,
} from '@mantine/core'
import { useDebouncedValue } from '@mantine/hooks'
import { notifications } from '@mantine/notifications'
import { useQueryClient } from '@tanstack/react-query'
import { Link, useSearchParams } from 'react-router'
import { useMe } from '../../shared/auth/useMe'
import { formatInZone } from '../../shared/format/time'
import { SubmitButton } from '../../shared/idempotency/SubmitButton'
import { toProblem } from '../../shared/api/problem'
import { errorCopy } from '../../shared/api/errorCopy'
import { notificationsCopy as copy } from './copy'
import {
  useOutboxMessages,
  useOutboxSummary,
  useRetryOutboxMessage,
  type OutboxMessageItem,
} from './hooks'

function statusBadgeColor(displayStatus: string): string {
  switch (displayStatus) {
    case 'Pending':
      return 'gray'
    case 'Retrying':
      return 'yellow'
    case 'Failed':
      return 'red'
    case 'Sent':
      return 'green'
    case 'Skipped':
      return 'gray'
    default:
      return 'gray'
  }
}

function channelLabel(channel: string): string {
  if (channel === 'EMAIL') return copy.channel.Email
  if (channel === 'SMS') return copy.channel.SMS
  if (channel === 'DMS') return copy.channel.DMS
  return channel
}

function eventLabel(eventType: string): string {
  const label = (copy.event as Record<string, string>)[eventType]
  return label ?? eventType
}

interface ResendButtonProps {
  messageId: string
  onError: (err: unknown) => void
}

function ResendButton({ messageId, onError }: ResendButtonProps) {
  const retryMutation = useRetryOutboxMessage(messageId)

  const handleResend = () => {
    retryMutation.mutate(
      { id: messageId },
      {
        onSuccess: () => {
          notifications.show({
            message: copy.resent,
            color: 'green',
          })
        },
        onError: (err) => {
          onError(err)
        },
      },
    )
  }

  return (
    <SubmitButton
      size="xs"
      variant="light"
      color="blue"
      pending={retryMutation.isPending}
      pendingLabel={copy.resending}
      onClick={handleResend}
    >
      {copy.resend}
    </SubmitButton>
  )
}

export function NotificationsPage() {
  const { data: me } = useMe()
  const timeZone = me?.dealership?.timezone ?? 'Asia/Ho_Chi_Minh'
  const queryClient = useQueryClient()

  const [searchParams, setSearchParams] = useSearchParams()
  const statusParam = searchParams.get('status') ?? ''
  const channelParam = searchParams.get('channel') ?? ''
  const eventTypeParam = searchParams.get('eventType') ?? ''
  const appointmentIdParam = searchParams.get('appointmentId') ?? ''

  const [prevAppointmentIdParam, setPrevAppointmentIdParam] = useState(appointmentIdParam)
  const [appointmentIdInput, setAppointmentIdInput] = useState(appointmentIdParam)
  const [debouncedAppointmentId] = useDebouncedValue(appointmentIdInput, 300)
  const [retryAlert, setRetryAlert] = useState<string | null>(null)

  if (prevAppointmentIdParam !== appointmentIdParam) {
    setPrevAppointmentIdParam(appointmentIdParam)
    setAppointmentIdInput(appointmentIdParam)
  }

  useEffect(() => {
    if (debouncedAppointmentId !== appointmentIdParam) {
      const next = new URLSearchParams(searchParams)
      if (debouncedAppointmentId.trim()) {
        next.set('appointmentId', debouncedAppointmentId.trim())
      } else {
        next.delete('appointmentId')
      }
      setSearchParams(next)
    }
  }, [debouncedAppointmentId, appointmentIdParam, searchParams, setSearchParams])

  const { data: summary, isLoading: isSummaryLoading } = useOutboxSummary()

  const {
    data: messagesPages,
    isLoading: isMessagesLoading,
    isFetchingNextPage,
    hasNextPage,
    fetchNextPage,
  } = useOutboxMessages({
    status: statusParam || undefined,
    channel: channelParam || undefined,
    eventType: eventTypeParam || undefined,
    appointmentId: appointmentIdParam || undefined,
  })

  const messages: OutboxMessageItem[] = messagesPages
    ? messagesPages.pages.flatMap((p) => p.data)
    : []

  const handleFilterChange = (key: string, value: string | null) => {
    const next = new URLSearchParams(searchParams)
    if (value && value !== '') {
      next.set(key, value)
    } else {
      next.delete(key)
    }
    setSearchParams(next)
  }

  const handleStatCardClick = (statusValue: string) => {
    handleFilterChange('status', statusValue)
  }

  const handleRefresh = () => {
    void queryClient.invalidateQueries({ queryKey: ['outbox', 'summary'] })
    void queryClient.invalidateQueries({ queryKey: ['outbox', 'messages'] })
  }

  const handleRetryError = (err: unknown) => {
    const problem = toProblem(err)
    if (problem?.code === 'OUTBOX_NOT_RETRYABLE') {
      setRetryAlert(copy.outboxNotRetryable)
      handleRefresh()
    } else {
      setRetryAlert(errorCopy(problem?.code))
    }
  }

  return (
    <Stack gap="xl">
      {/* Title & Subtitle */}
      <Box>
        <Title order={2}>{copy.title}</Title>
        <Text c="dimmed" size="sm" mt={4}>
          {copy.subtitle}
        </Text>
      </Box>

      {/* Info Banner */}
      <Alert color="blue" radius="md" title={copy.infoTitle}>
        <Text size="sm">{copy.infoBody}</Text>
      </Alert>

      {/* Retry Error Alert */}
      {retryAlert && (
        <Alert
          color="red"
          radius="md"
          title="Error"
          withCloseButton
          onClose={() => setRetryAlert(null)}
        >
          <Group justify="space-between" align="center">
            <Text size="sm">{retryAlert}</Text>
            <Button variant="white" color="red" size="xs" onClick={handleRefresh}>
              Refresh
            </Button>
          </Group>
        </Alert>
      )}

      {/* Stat Cards */}
      <SimpleGrid cols={{ base: 1, sm: 2, md: 4 }} spacing="md">
        {/* Pending */}
        <UnstyledButton
          aria-label={copy.stats.pending}
          onClick={() => handleStatCardClick('PENDING')}
        >
          <Card
            withBorder
            padding="md"
            radius="md"
            style={{
              borderColor: statusParam === 'PENDING' ? 'var(--mantine-color-blue-6)' : undefined,
            }}
          >
            <Text size="xs" c="dimmed" fw={500}>
              {copy.stats.pending}
            </Text>
            <Text fz={26} fw={700} style={{ fontVariantNumeric: 'tabular-nums' }} mt={4}>
              {isSummaryLoading && !summary ? '—' : (summary?.pending ?? 0)}
            </Text>
          </Card>
        </UnstyledButton>

        {/* Retrying */}
        <UnstyledButton
          aria-label={copy.stats.retrying}
          onClick={() => handleStatCardClick('RETRYING')}
        >
          <Card
            withBorder
            padding="md"
            radius="md"
            style={{
              borderColor: statusParam === 'RETRYING' ? 'var(--mantine-color-yellow-6)' : undefined,
            }}
          >
            <Text size="xs" c="dimmed" fw={500}>
              {copy.stats.retrying}
            </Text>
            <Text fz={26} fw={700} style={{ fontVariantNumeric: 'tabular-nums' }} mt={4}>
              {isSummaryLoading && !summary ? '—' : (summary?.retrying ?? 0)}
            </Text>
          </Card>
        </UnstyledButton>

        {/* Failed, needs action */}
        <UnstyledButton
          aria-label={copy.stats.failed}
          onClick={() => handleStatCardClick('FAILED')}
        >
          <Card
            withBorder
            padding="md"
            radius="md"
            style={{
              borderColor: statusParam === 'FAILED' ? 'var(--mantine-color-red-6)' : undefined,
            }}
          >
            <Text size="xs" c="dimmed" fw={500}>
              {copy.stats.failed}
            </Text>
            <Text
              fz={26}
              fw={700}
              c={(summary?.failed ?? 0) > 0 ? 'red.7' : undefined}
              style={{ fontVariantNumeric: 'tabular-nums' }}
              mt={4}
            >
              {isSummaryLoading && !summary ? '—' : (summary?.failed ?? 0)}
            </Text>
          </Card>
        </UnstyledButton>

        {/* Sent today */}
        <UnstyledButton
          aria-label={copy.stats.sentToday}
          onClick={() => handleStatCardClick('SENT')}
        >
          <Card
            withBorder
            padding="md"
            radius="md"
            style={{
              borderColor: statusParam === 'SENT' ? 'var(--mantine-color-green-6)' : undefined,
            }}
          >
            <Text size="xs" c="dimmed" fw={500}>
              {copy.stats.sentToday}
            </Text>
            <Text fz={26} fw={700} style={{ fontVariantNumeric: 'tabular-nums' }} mt={4}>
              {isSummaryLoading && !summary ? '—' : (summary?.sentToday ?? 0)}
            </Text>
          </Card>
        </UnstyledButton>
      </SimpleGrid>

      {/* Filter Controls */}
      <Card withBorder padding="md" radius="md">
        <Grid gap="md" align="flex-end">
          <Grid.Col span={{ base: 12, sm: 6, md: 3 }}>
            <Select
              label={copy.filters.status}
              placeholder={copy.filters.all}
              clearable
              value={statusParam}
              onChange={(val) => handleFilterChange('status', val)}
              data={[
                { value: '', label: copy.filters.all },
                { value: 'PENDING', label: copy.status.Pending },
                { value: 'RETRYING', label: copy.status.Retrying },
                { value: 'FAILED', label: copy.status.Failed },
                { value: 'SENT', label: copy.status.Sent },
                { value: 'SKIPPED', label: copy.status.Skipped },
              ]}
            />
          </Grid.Col>

          <Grid.Col span={{ base: 12, sm: 6, md: 3 }}>
            <Select
              label={copy.filters.channel}
              placeholder={copy.filters.allChannels}
              clearable
              value={channelParam}
              onChange={(val) => handleFilterChange('channel', val)}
              data={[
                { value: '', label: copy.filters.allChannels },
                { value: 'SMS', label: copy.channel.SMS },
                { value: 'EMAIL', label: copy.channel.Email },
                { value: 'DMS', label: copy.channel.DMS },
              ]}
            />
          </Grid.Col>

          <Grid.Col span={{ base: 12, sm: 6, md: 3 }}>
            <Select
              label={copy.filters.eventType}
              placeholder={copy.filters.allTypes}
              clearable
              value={eventTypeParam}
              onChange={(val) => handleFilterChange('eventType', val)}
              data={[
                { value: '', label: copy.filters.allTypes },
                { value: 'AppointmentConfirmed', label: copy.event.AppointmentConfirmed },
                { value: 'AppointmentCancelled', label: copy.event.AppointmentCancelled },
                { value: 'AppointmentRescheduled', label: copy.event.AppointmentRescheduled },
                { value: 'AppointmentReminder', label: copy.event.AppointmentReminder },
                { value: 'AppointmentReassigned', label: copy.event.AppointmentReassigned },
              ]}
            />
          </Grid.Col>

          <Grid.Col span={{ base: 12, sm: 6, md: 3 }}>
            <TextInput
              label={copy.filters.appointmentId}
              placeholder="e.g. 0192d222-0000"
              value={appointmentIdInput}
              onChange={(e) => setAppointmentIdInput(e.currentTarget.value)}
            />
          </Grid.Col>
        </Grid>
      </Card>

      {/* Events Table */}
      <Card withBorder padding={0} radius="md">
        <Table.ScrollContainer minWidth={800}>
          <Table highlightOnHover verticalSpacing="sm" horizontalSpacing="md">
            <Table.Thead>
              <Table.Tr>
                <Table.Th>{copy.columns.time}</Table.Th>
                <Table.Th>{copy.columns.event}</Table.Th>
                <Table.Th>{copy.columns.appointment}</Table.Th>
                <Table.Th>{copy.columns.channel}</Table.Th>
                <Table.Th>{copy.columns.attempts}</Table.Th>
                <Table.Th>{copy.columns.status}</Table.Th>
                <Table.Th>{copy.columns.actions}</Table.Th>
              </Table.Tr>
            </Table.Thead>
            <Table.Tbody>
              {isMessagesLoading ? (
                <Table.Tr>
                  <Table.Td colSpan={7} ta="center" py="xl">
                    <Loader size="sm" />
                  </Table.Td>
                </Table.Tr>
              ) : messages.length === 0 ? (
                <Table.Tr>
                  <Table.Td colSpan={7} ta="center" py="xl">
                    <Text c="dimmed">{copy.empty}</Text>
                  </Table.Td>
                </Table.Tr>
              ) : (
                messages.map((row) => {
                  const isFailed = row.status === 'FAILED' || row.displayStatus === 'Failed'
                  const isRetrying = row.status === 'RETRYING' || row.displayStatus === 'Retrying'
                  const showErrorNote = (isFailed || isRetrying) && row.lastError

                  return (
                    <Table.Tr key={row.id}>
                      {/* Time */}
                      <Table.Td
                        style={{
                          fontVariantNumeric: 'tabular-nums',
                          whiteSpace: 'nowrap',
                        }}
                      >
                        {formatInZone(row.createdAt, timeZone, 'timeWithSeconds')}
                      </Table.Td>

                      {/* Event */}
                      <Table.Td>
                        <Text size="sm">{eventLabel(row.eventType)}</Text>
                      </Table.Td>

                      {/* Appointment */}
                      <Table.Td>
                        {row.appointment ? (
                          <Anchor
                            component={Link}
                            to={`/staff/appointments/${row.appointment.id}`}
                            size="sm"
                          >
                            {row.appointment.reference} · {row.appointment.customerName}
                          </Anchor>
                        ) : (
                          <Text size="sm" c="dimmed">
                            —
                          </Text>
                        )}
                      </Table.Td>

                      {/* Channel */}
                      <Table.Td>
                        <Text size="sm">{channelLabel(row.channel)}</Text>
                      </Table.Td>

                      {/* Attempts */}
                      <Table.Td style={{ fontVariantNumeric: 'tabular-nums' }}>
                        <Text size="sm">{row.attempts}/5</Text>
                      </Table.Td>

                      {/* Status */}
                      <Table.Td>
                        <Badge
                          color={statusBadgeColor(row.displayStatus)}
                          variant="light"
                        >
                          {(copy.status as Record<string, string>)[row.displayStatus] ??
                            row.displayStatus}
                        </Badge>
                        {showErrorNote && (
                          <Text size="xs" c="dimmed" mt={2} maw={260}>
                            {row.lastError}
                          </Text>
                        )}
                      </Table.Td>

                      {/* Actions */}
                      <Table.Td>
                        {isFailed ? (
                          <ResendButton messageId={row.id} onError={handleRetryError} />
                        ) : null}
                      </Table.Td>
                    </Table.Tr>
                  )
                })
              )}
            </Table.Tbody>
          </Table>
        </Table.ScrollContainer>
      </Card>

      {/* Pagination Load More */}
      {hasNextPage && (
        <Group justify="center" pb="lg">
          <Button
            variant="default"
            size="xs"
            onClick={() => void fetchNextPage()}
            loading={isFetchingNextPage}
          >
            Load more
          </Button>
        </Group>
      )}
    </Stack>
  )
}
