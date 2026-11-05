import { useState } from 'react'
import {
  Alert,
  Anchor,
  Badge,
  Box,
  Button,
  Card,
  Container,
  Group,
  Loader,
  Stack,
  Table,
  Text,
  Title,
} from '@mantine/core'
import { Link } from 'react-router'
import { useQueryClient } from '@tanstack/react-query'
import { useMe } from '../../shared/auth/useMe'
import { errorCopy } from '../../shared/api/errorCopy'
import { toProblem } from '../../shared/api/problem'
import { useExtend, useWorkshopBoard, type WorkshopAlert } from '../../shared/api/workshop'
import { AppointmentModifiedAlert } from '../../shared/appointments/AppointmentModifiedAlert'
import { statusMeta } from '../../shared/appointments/status'
import type { AppointmentDiffInput } from '../../shared/appointments/diffAppointment'
import { useIdempotencyKey } from '../../shared/idempotency/useIdempotencyKey'
import { formatInZone } from '../../shared/format/time'
import { describeActionError } from '../appointments/actionError'
import { contactReasonLabel } from '../appointments/copy'
import { BayTimeline, OVERRUN_HATCHED_BACKGROUND } from './BayTimeline'
import { workshopCopy } from './copy'

// Legend order follows `board.legend`; the last entry, Overrun, is drawn hatched
const LEGEND_STATUSES = ['COMPLETED', 'IN_PROGRESS', 'CHECKED_IN', 'CONFIRMED', 'HELD'] as const

interface OverrunAlertCardProps {
  alert: WorkshopAlert
  isForeman: boolean
  timeZone: string
  onConflict: (conflict: {
    before: AppointmentDiffInput
    latest: AppointmentDiffInput
    etag: string
  }) => void
}

function OverrunAlertCard({
  alert,
  isForeman,
  timeZone,
  onConflict,
}: OverrunAlertCardProps) {
  const etag = `"${alert.version}"`
  const extendInput = { id: alert.appointmentId, action: 'extend', etag }
  const { key: idempotencyKey, settle: settleExtend } = useIdempotencyKey(extendInput)
  const extendMutation = useExtend(alert.appointmentId)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)

  const handleExtend = () => {
    setErrorMessage(null)
    extendMutation.mutate(
      {
        minutes: 30,
        etag,
        idempotencyKey,
      },
      {
        onSuccess: () => settleExtend(200),
        onError: (err: unknown) => {
          settleExtend(err)
          const actionError = describeActionError(err)
          if (actionError.kind === 'modified') {
            onConflict({
              before: {
                id: alert.appointmentId,
                version: alert.version,
                status: 'IN_PROGRESS',
              },
              latest: actionError.conflict.latest,
              etag: actionError.conflict.etag,
            })
            return
          }
          setErrorMessage(actionError.message)
        },
      },
    )
  }

  const customer = alert.customerName ?? ''
  const service = alert.serviceName ?? ''
  const technician = alert.technicianName ?? ''

  let bodyText = workshopCopy.board.overrunBodyNone(customer, service, technician)
  if (alert.nextBooking) {
    const nextTime = formatInZone(alert.nextBooking.start, timeZone, 'time')
    if (alert.nextBooking.status === 'CANCELLED') {
      bodyText = workshopCopy.board.overrunBodyFree(customer, service, technician, nextTime)
    } else {
      bodyText = workshopCopy.board.overrunBodyNext(customer, service, technician, nextTime)
    }
  }

  return (
    <Card withBorder padding="md" radius="md">
      <Group justify="space-between" align="center" wrap="nowrap">
        <Stack gap={4}>
          <Text fw={600} c="red.7">
            {workshopCopy.board.overrunTitle(alert.bayName, alert.minutesOver)}
          </Text>
          <Text size="sm">{bodyText}</Text>
          {errorMessage && (
            <Text size="sm" c="red.7" role="alert">
              {errorMessage}
            </Text>
          )}
        </Stack>

        {isForeman && (
          <Button
            color="red"
            variant="light"
            loading={extendMutation.isPending}
            onClick={handleExtend}
          >
            {workshopCopy.board.extend30}
          </Button>
        )}
      </Group>
    </Card>
  )
}

export function WorkshopBoardPage() {
  const queryClient = useQueryClient()
  const { data: me } = useMe()
  const isForeman = me?.role === 'WORKSHOP_FOREMAN'
  const timeZone = me?.dealership?.timezone ?? 'Asia/Ho_Chi_Minh'

  const { data: board, isLoading, error, refetch } = useWorkshopBoard()

  const [conflict, setConflict] = useState<{
    before: AppointmentDiffInput
    latest: AppointmentDiffInput
    etag: string
  } | null>(null)

  if (isLoading) {
    return (
      <Container size="xl" py="lg">
        <Group justify="center" py="xl">
          <Loader size="lg" />
        </Group>
      </Container>
    )
  }

  if (error || !board) {
    return (
      <Container size="xl" py="lg">
        <Alert color="red">{errorCopy(toProblem(error)?.code)}</Alert>
      </Container>
    )
  }

  const timeStr = formatInZone(board.now, timeZone, 'time')
  const dayLongStr = formatInZone(board.now, timeZone, 'dayLong')

  return (
    <Container size="xl" py="lg">
      <Stack gap="lg">
        <Group justify="space-between" align="flex-start">
          <Box>
            <Title order={2}>{workshopCopy.board.title}</Title>
            <Text c="dimmed" size="sm">
              {workshopCopy.board.updated(dayLongStr, timeStr)}
            </Text>
          </Box>
        </Group>

        {conflict && (
          <AppointmentModifiedAlert
            before={conflict.before}
            latest={conflict.latest}
            etag={conflict.etag}
            onReview={() => {
              setConflict(null)
              refetch()
            }}
          />
        )}

        {/* Overrun Alerts */}
        {board.alerts && board.alerts.length > 0 && (
          <Stack gap="md">
            {board.alerts.map((alert) => (
              <OverrunAlertCard
                key={alert.appointmentId}
                alert={alert}
                isForeman={isForeman}
                timeZone={timeZone}
                onConflict={(c) => {
                  setConflict(c)
                  queryClient.invalidateQueries({ queryKey: ['workshopBoard'] })
                }}
              />
            ))}
          </Stack>
        )}

        {/* Schedule by Bay */}
        <Card withBorder padding="md" radius="md">
          <Stack gap="md">
            <Group justify="space-between" align="baseline">
              <Title order={3}>{workshopCopy.board.byBay}</Title>
              <Text size="xs" c="dimmed">
                {workshopCopy.board.caption}
              </Text>
            </Group>

            <BayTimeline board={board} timeZone={timeZone} />

            {/* Legend */}
            <Group gap="xs" wrap="wrap">
              <Text size="xs" fw={600} c="dimmed">
                {workshopCopy.board.legendLabel}
              </Text>
              {LEGEND_STATUSES.map((status) => (
                <Badge key={status} color={statusMeta(status).color} variant="filled" size="sm">
                  {statusMeta(status).label}
                </Badge>
              ))}
              <Badge
                variant="filled"
                size="sm"
                style={{ background: OVERRUN_HATCHED_BACKGROUND }}
              >
                {workshopCopy.board.overrun}
              </Badge>
            </Group>
          </Stack>
        </Card>

        {/* Displaced Bookings (Needs customer contact) */}
        {board.displaced && board.displaced.length > 0 && (
          <Card withBorder padding="md" radius="md">
            <Stack gap="sm">
              <Title order={4} c="yellow.8">
                {workshopCopy.board.displaced}
              </Title>
              <Table>
                <Table.Thead>
                  <Table.Tr>
                    <Table.Th>{workshopCopy.board.displacedColumns.booking}</Table.Th>
                    <Table.Th>{workshopCopy.board.displacedColumns.time}</Table.Th>
                    <Table.Th>{workshopCopy.board.displacedColumns.reason}</Table.Th>
                  </Table.Tr>
                </Table.Thead>
                <Table.Tbody>
                  {board.displaced.map((item) => (
                    <Table.Tr key={item.appointmentId}>
                      <Table.Td>
                        <Anchor
                          component={Link}
                          to={`/staff/appointments/${item.appointmentId}`}
                          fw={500}
                        >
                          {item.reference}
                        </Anchor>
                      </Table.Td>
                      <Table.Td>
                        {formatInZone(item.start, timeZone, 'range', item.end)}
                      </Table.Td>
                      <Table.Td>
                        <Badge color="yellow" variant="light">
                          {contactReasonLabel(item.reason)}
                        </Badge>
                      </Table.Td>
                    </Table.Tr>
                  ))}
                </Table.Tbody>
              </Table>
            </Stack>
          </Card>
        )}

        {/* Technicians Table */}
        {board.technicians && board.technicians.length > 0 && (
          <Card withBorder padding="md" radius="md">
            <Stack gap="sm">
              <Title order={4}>{workshopCopy.board.technicians}</Title>
              <Table>
                <Table.Thead>
                  <Table.Tr>
                    <Table.Th>{workshopCopy.board.technicianColumns.technician}</Table.Th>
                    <Table.Th>{workshopCopy.board.technicianColumns.level}</Table.Th>
                    <Table.Th>{workshopCopy.board.technicianColumns.booked}</Table.Th>
                    <Table.Th>{workshopCopy.board.technicianColumns.status}</Table.Th>
                  </Table.Tr>
                </Table.Thead>
                <Table.Tbody>
                  {board.technicians.map((tech) => (
                    <Table.Tr key={tech.id}>
                      <Table.Td>
                        <Text fw={500}>{tech.name}</Text>
                      </Table.Td>
                      <Table.Td>{workshopCopy.board.level(tech.level)}</Table.Td>
                      <Table.Td>{workshopCopy.board.bookedMinutes(tech.bookedMinutes)}</Table.Td>
                      <Table.Td>
                        {tech.status === 'OVERRUN' ? (
                          <Badge color="red" variant="filled">
                            {workshopCopy.board.overrun}
                          </Badge>
                        ) : (
                          <Badge color="green" variant="light">
                            {workshopCopy.board.working}
                          </Badge>
                        )}
                      </Table.Td>
                    </Table.Tr>
                  ))}
                </Table.Tbody>
              </Table>
            </Stack>
          </Card>
        )}
      </Stack>
    </Container>
  )
}
