import { useState } from 'react'
import {
  Alert,
  Badge,
  Box,
  Button,
  Card,
  Code,
  CopyButton,
  Group,
  Loader,
  SegmentedControl,
  SimpleGrid,
  Stack,
  Table,
  Text,
  TextInput,
  Title,
  Tooltip,
  UnstyledButton,
} from '@mantine/core'
import { useDebouncedValue } from '@mantine/hooks'
import { Link, useSearchParams } from 'react-router'
import { useMe } from '../../shared/auth/useMe'
import { HealthBadge } from '../../shared/HealthBadge'
import { formatInZone } from '../../shared/format/time'
import {
  useMonitoringErrors,
  useMonitoringSummary,
  type MonitoringRange,
} from '../../shared/api/monitoring'
import { monitoringCopy as copy } from './copy'
import { formatAge, formatPercent } from './format'

export function MonitoringPage() {
  const { data: me } = useMe()
  const timeZone = me?.dealership?.timezone ?? 'Asia/Ho_Chi_Minh'

  const [searchParams, setSearchParams] = useSearchParams()
  const rawRange = searchParams.get('range')
  const range: MonitoringRange = rawRange === '24h' || rawRange === '7d' ? rawRange : '1h'

  const [searchInput, setSearchInput] = useState('')
  const [debouncedSearch] = useDebouncedValue(searchInput, 300)
  const isSearching = debouncedSearch.trim().length >= 8

  const {
    data: summary,
    isLoading: isSummaryLoading,
    isError: isSummaryError,
    refetch,
  } = useMonitoringSummary(range)

  const {
    data: searchResults,
    isLoading: isSearchLoading,
  } = useMonitoringErrors(debouncedSearch)

  const handleRangeChange = (val: string) => {
    const next = new URLSearchParams(searchParams)
    next.set('range', val)
    setSearchParams(next)
  }

  const errorsList = isSearching ? (searchResults ?? []) : (summary?.recentErrors ?? [])
  const isTableLoading = isSearching && isSearchLoading

  return (
    <Stack gap="xl">
      {/* Top Header */}
      <Group justify="space-between" align="flex-end" wrap="wrap">
        <Box>
          <Title order={2}>{copy.title}</Title>
          <Text c="dimmed" size="sm" mt={4}>
            {copy.subtitle}
          </Text>
        </Box>
        <SegmentedControl
          aria-label="Time range"
          value={range}
          onChange={handleRangeChange}
          data={[
            { label: copy.range['1h'], value: '1h' },
            { label: copy.range['24h'], value: '24h' },
            { label: copy.range['7d'], value: '7d' },
          ]}
        />
      </Group>

      {isSummaryError && (
        <Alert color="red" title="Error" radius="md">
          <Group justify="space-between" align="center">
            <Text size="sm">Failed to load monitoring summary.</Text>
            <Button variant="white" color="red" size="xs" onClick={() => void refetch()}>
              Retry
            </Button>
          </Group>
        </Alert>
      )}

      {isSummaryLoading && !summary ? (
        <Box ta="center" py="xl">
          <Loader size="md" />
        </Box>
      ) : summary ? (
        <>
          {/* Health Cards */}
          <SimpleGrid cols={{ base: 1, sm: 3 }} spacing="md">
            {/* Application */}
            <Card withBorder padding="md" radius="md">
              <Group justify="space-between" align="center" mb="xs">
                <Text fw={600}>{copy.health.app}</Text>
                <HealthBadge />
              </Group>
              <Text size="sm" c="dimmed">
                {copy.health.appBody(summary.health.app.bookingP95Ms)}
              </Text>
            </Card>

            {/* Database */}
            <Card withBorder padding="md" radius="md">
              <Group justify="space-between" align="center" mb="xs">
                <Text fw={600}>{copy.health.db}</Text>
                <Badge
                  color={summary.health.database.status === 'UP' ? 'green' : 'red'}
                  variant="light"
                >
                  {summary.health.database.status}
                </Badge>
              </Group>
              <Text size="sm" c="dimmed">
                {copy.health.dbBody(
                  summary.health.database.connectionsUsed,
                  summary.health.database.connectionsMax,
                )}
              </Text>
            </Card>

            {/* Outbox Pending */}
            <Card withBorder padding="md" radius="md">
              <Group justify="space-between" align="center" mb="xs">
                <Text fw={600}>{copy.health.outbox}</Text>
                <Button
                  component={Link}
                  to="/staff/notifications?status=PENDING"
                  size="xs"
                  variant="light"
                  color="orange"
                >
                  {copy.health.view}
                </Button>
              </Group>
              <Text size="sm" c="dimmed">
                {copy.health.outboxBody(
                  summary.health.outbox.pending,
                  formatAge(summary.health.outbox.oldestPendingAgeSeconds),
                )}
              </Text>
            </Card>
          </SimpleGrid>

          {/* Bookings Section */}
          <Box>
            <Title order={3} mb="md">
              {copy.bookings.title(copy.range[range])}
            </Title>
            <SimpleGrid cols={{ base: 1, sm: 2, md: 3, lg: 4 }} spacing="md">
              {/* Total requests */}
              <Card withBorder padding="md" radius="md">
                <Text size="xs" c="dimmed" fw={500}>
                  {copy.bookings.total}
                </Text>
                <Text fz={26} fw={700} style={{ fontVariantNumeric: 'tabular-nums' }} mt={4}>
                  {summary.bookings.total.toLocaleString('en-GB')}
                </Text>
                <Text size="xs" c="dimmed" mt={2}>
                  {copy.bookings.totalNote}
                </Text>
              </Card>

              {/* Confirmed */}
              <Card withBorder padding="md" radius="md">
                <Text size="xs" c="dimmed" fw={500}>
                  {copy.bookings.confirmed}
                </Text>
                <Text fz={26} fw={700} style={{ fontVariantNumeric: 'tabular-nums' }} mt={4}>
                  {summary.bookings.confirmed.toLocaleString('en-GB')}
                </Text>
                <Text size="xs" c="dimmed" mt={2}>
                  {copy.bookings.ofRequests(formatPercent(summary.bookings.confirmed, summary.bookings.total))}
                </Text>
              </Card>

              {/* No availability 409 */}
              <Card withBorder padding="md" radius="md">
                <Text size="xs" c="dimmed" fw={500}>
                  {copy.bookings.noAvailability}
                </Text>
                <Text fz={26} fw={700} style={{ fontVariantNumeric: 'tabular-nums' }} mt={4}>
                  {summary.bookings.noAvailability.toLocaleString('en-GB')}
                </Text>
                <Text size="xs" c="dimmed" mt={2}>
                  {copy.bookings.noAvailabilityNote(formatPercent(summary.bookings.noAvailability, summary.bookings.total))}
                </Text>
              </Card>

              {/* System errors 5xx */}
              <Card withBorder padding="md" radius="md">
                <Text size="xs" c="dimmed" fw={500}>
                  {copy.bookings.errors}
                </Text>
                <Text
                  fz={26}
                  fw={700}
                  c={summary.bookings.error > 0 ? 'red.7' : undefined}
                  style={{ fontVariantNumeric: 'tabular-nums' }}
                  mt={4}
                >
                  {summary.bookings.error.toLocaleString('en-GB')}
                </Text>
                <Text size="xs" c="dimmed" mt={2}>
                  {copy.bookings.ofRequests(formatPercent(summary.bookings.error, summary.bookings.total))}
                </Text>
              </Card>

              {/* p95 response time */}
              <Card withBorder padding="md" radius="md">
                <Text size="xs" c="dimmed" fw={500}>
                  {copy.bookings.p95}
                </Text>
                <Text
                  fz={26}
                  fw={700}
                  c={summary.bookings.p95Ms >= 300 ? 'red.7' : undefined}
                  style={{ fontVariantNumeric: 'tabular-nums' }}
                  mt={4}
                >
                  {summary.bookings.p95Ms} ms
                </Text>
                <Text size="xs" c="dimmed" mt={2}>
                  {copy.bookings.p95Note}
                </Text>
              </Card>

              {/* Allocation retries */}
              <Card withBorder padding="md" radius="md">
                <Text size="xs" c="dimmed" fw={500}>
                  {copy.bookings.retries}
                </Text>
                <Text fz={26} fw={700} style={{ fontVariantNumeric: 'tabular-nums' }} mt={4}>
                  {summary.bookings.allocationRetries.toLocaleString('en-GB')}
                </Text>
                <Text size="xs" c="dimmed" mt={2}>
                  {copy.bookings.retriesNote}
                </Text>
              </Card>

              {/* Idempotent replays */}
              <Card withBorder padding="md" radius="md">
                <Text size="xs" c="dimmed" fw={500}>
                  {copy.bookings.replays}
                </Text>
                <Text fz={26} fw={700} style={{ fontVariantNumeric: 'tabular-nums' }} mt={4}>
                  {summary.bookings.idempotentReplays.toLocaleString('en-GB')}
                </Text>
                <Text size="xs" c="dimmed" mt={2}>
                  {copy.bookings.replaysNote}
                </Text>
              </Card>
            </SimpleGrid>
          </Box>

          {/* Recent Errors Section */}
          <Card withBorder padding={0} radius="md">
            <Group justify="space-between" align="flex-end" p="md" wrap="wrap">
              <Title order={3}>{copy.errors.title}</Title>
              <Box style={{ flex: '0 1 360px', minWidth: 260 }}>
                <TextInput
                  label={copy.errors.search}
                  placeholder="e.g. 7f3c9a1e"
                  value={searchInput}
                  onChange={(e) => setSearchInput(e.currentTarget.value)}
                />
              </Box>
            </Group>

            <Table.ScrollContainer minWidth={800}>
              <Table highlightOnHover verticalSpacing="sm" horizontalSpacing="md">
                <Table.Thead>
                  <Table.Tr>
                    {copy.errors.columns.map((col) => (
                      <Table.Th key={col}>{col}</Table.Th>
                    ))}
                  </Table.Tr>
                </Table.Thead>
                <Table.Tbody>
                  {isTableLoading ? (
                    <Table.Tr>
                      <Table.Td colSpan={6} ta="center" py="xl">
                        <Loader size="sm" />
                      </Table.Td>
                    </Table.Tr>
                  ) : errorsList.length === 0 ? (
                    <Table.Tr>
                      <Table.Td colSpan={6} ta="center" py="xl">
                        <Text c="dimmed">
                          {isSearching ? copy.errors.notFound : copy.errors.empty}
                        </Text>
                      </Table.Td>
                    </Table.Tr>
                  ) : (
                    errorsList.map((row) => {
                      const is5xx = row.status !== null && row.status >= 500 && row.status < 600

                      return (
                        <Table.Tr
                          key={row.id}
                          style={{
                            backgroundColor: is5xx ? 'var(--mantine-color-red-0)' : undefined,
                          }}
                        >
                          <Table.Td style={{ fontVariantNumeric: 'tabular-nums', whiteSpace: 'nowrap' }}>
                            {formatInZone(row.occurredAt, timeZone, 'timeWithSeconds')}
                          </Table.Td>
                          <Table.Td>
                            <Code>{row.endpoint}</Code>
                          </Table.Td>
                          <Table.Td>
                            <Code>{row.code}</Code>
                          </Table.Td>
                          <Table.Td>
                            <Badge color={is5xx ? 'red' : 'gray'} variant="light">
                              {row.status ?? '—'}
                            </Badge>
                          </Table.Td>
                          <Table.Td>
                            <CopyButton value={row.correlationId} timeout={2000}>
                              {({ copied, copy: handleCopy }) => (
                                <Tooltip
                                  label={copied ? copy.errors.copied : `Copy ${row.correlationId}`}
                                  withArrow
                                  position="top"
                                >
                                  <UnstyledButton
                                    onClick={handleCopy}
                                    aria-label={copied ? copy.errors.copied : `Copy ${row.correlationId.slice(0, 8)}`}
                                  >
                                    <Code style={{ cursor: 'pointer' }}>
                                      {row.correlationId.slice(0, 8)}
                                    </Code>
                                  </UnstyledButton>
                                </Tooltip>
                              )}
                            </CopyButton>
                          </Table.Td>
                          <Table.Td>
                            <Text size="sm" c="dimmed">
                              {row.details}
                            </Text>
                          </Table.Td>
                        </Table.Tr>
                      )
                    })
                  )}
                </Table.Tbody>
              </Table>
            </Table.ScrollContainer>
          </Card>
        </>
      ) : null}
    </Stack>
  )
}
