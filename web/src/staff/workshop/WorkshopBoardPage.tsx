import { useState } from 'react'
import axios from 'axios'
import {
  Alert,
  Box,
  Button,
  Card,
  Container,
  Group,
  Loader,
  Stack,
  Text,
  Title,
} from '@mantine/core'
import { useQueryClient } from '@tanstack/react-query'
import { useMe } from '../../shared/auth/useMe'
import { useExtend, useWorkshopBoard, type WorkshopAlert } from '../../shared/api/workshop'
import { AppointmentModifiedAlert } from '../../shared/appointments/AppointmentModifiedAlert'
import type { AppointmentDiffInput } from '../../shared/appointments/diffAppointment'
import { useIdempotencyKey } from '../../shared/idempotency/useIdempotencyKey'
import { formatInZone } from '../../shared/format/time'
import { workshopCopy } from './copy'

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

  const handleExtend = () => {
    extendMutation.mutate(
      {
        minutes: 30,
        etag,
        idempotencyKey,
      },
      {
        onSettled: () => settleExtend(),
        onError: (err: unknown) => {
          if (axios.isAxiosError(err) && err.response?.status === 412) {
            const data = err.response.data as {
              appointment?: AppointmentDiffInput
              extra?: { appointment?: AppointmentDiffInput }
            }
            const latest = data.extra?.appointment ?? data.appointment
            const resEtag = (err.response.headers['etag'] as string) || ''
            if (latest) {
              onConflict({
                before: {
                  id: alert.appointmentId,
                  version: alert.version,
                  status: 'IN_PROGRESS',
                },
                latest,
                etag: resEtag,
              })
            }
          }
        },
      },
    )
  }

  const customer = alert.customerName ?? 'Customer'
  const service = alert.serviceName ?? 'Service'
  const technician = alert.technicianName ?? 'Technician'

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
        <Alert color="red" title="Error">
          Failed to load workshop board.
        </Alert>
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
      </Stack>
    </Container>
  )
}
