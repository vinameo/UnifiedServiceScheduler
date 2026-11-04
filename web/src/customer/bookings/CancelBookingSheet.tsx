import { useState } from 'react'
import {
  Alert,
  Anchor,
  Button,
  Drawer,
  Group,
  Radio,
  Stack,
  Text,
  Textarea,
} from '@mantine/core'
import axios from 'axios'
import type { Appointment } from '../../shared/api/appointments'
import { useCancelAppointment } from '../../shared/api/appointments'
import { formatInZone } from '../../shared/format/time'
import { useIdempotencyKey } from '../../shared/idempotency/useIdempotencyKey'
import { SubmitButton } from '../../shared/idempotency/SubmitButton'
import { AppointmentModifiedAlert } from '../../shared/appointments/AppointmentModifiedAlert'
import type { AppointmentDiffInput } from '../../shared/appointments/diffAppointment'
import { customerBookingsCopy } from './copy'

export interface CancelBookingSheetProps {
  opened: boolean
  onClose: () => void
  appointment: Appointment
  etag: string
}

export function CancelBookingSheet({
  opened,
  onClose,
  appointment,
  etag,
}: CancelBookingSheetProps) {
  const [reason, setReason] = useState<string | null>(null)
  const [note, setNote] = useState('')
  const [conflict, setConflict] = useState<{ latest: AppointmentDiffInput; etag: string } | null>(null)
  const [tooLateError, setTooLateError] = useState(false)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)

  const timeZone = appointment.dealership?.timezone ?? 'Asia/Ho_Chi_Minh'
  const time = formatInZone(appointment.start, timeZone, 'time')
  const day = formatInZone(appointment.start, timeZone, 'dayShort')
  const dealershipPhone = appointment.dealership?.phone ?? ''

  const actionInput = {
    id: appointment.id,
    etag,
    reason,
    note: reason === 'OTHER' && note ? note.trim() : null,
  }
  const { key: idempotencyKey, reset: resetKey, settle } = useIdempotencyKey(actionInput)
  const cancelMutation = useCancelAppointment(appointment.id)

  const handleClose = () => {
    setReason(null)
    setNote('')
    setConflict(null)
    setTooLateError(false)
    setErrorMessage(null)
    onClose()
  }

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (cancelMutation.isPending) {
      return
    }

    setErrorMessage(null)
    setConflict(null)
    setTooLateError(false)

    cancelMutation.mutate(
      {
        reason: reason || null,
        note: reason === 'OTHER' && note ? note.trim() : null,
        idempotencyKey,
        etag,
      },
      {
        onSuccess: () => {
          resetKey()
          handleClose()
        },
        onError: (err: unknown) => {
          settle(err)
          if (axios.isAxiosError(err) && err.response) {
            const data = err.response.data as {
              code?: string
              detail?: string
              extra?: { appointment?: AppointmentDiffInput }
              appointment?: AppointmentDiffInput
            }

            if (err.response.status === 412) {
              const latest = data.extra?.appointment ?? data.appointment
              const resEtag = (err.response.headers['etag'] as string) || ''
              if (latest) {
                setConflict({ latest, etag: resEtag })
                return
              }
            }

            if (data.code === 'TOO_LATE_TO_CANCEL') {
              setTooLateError(true)
              return
            }

            if (data.code === 'INVALID_STATUS_TRANSITION') {
              setErrorMessage(data.detail ?? 'This appointment can no longer be cancelled.')
              return
            }

            setErrorMessage(data.detail ?? 'Failed to cancel appointment.')
          } else {
            setErrorMessage('An unexpected error occurred.')
          }
        },
      },
    )
  }

  return (
    <Drawer
      opened={opened}
      onClose={handleClose}
      position="bottom"
      title={
        <Text component="span" fw={700} size="md">
          {customerBookingsCopy.cancel.title(time, day)}
        </Text>
      }
      radius="md"
      size="auto"
      transitionProps={{ duration: 0 }}
    >
      <form onSubmit={handleSubmit}>
        <Stack gap="md" pb="md">
          <Text size="sm" c="dimmed">
            {customerBookingsCopy.cancel.body}
          </Text>

          {conflict && (
            <AppointmentModifiedAlert
              before={appointment as unknown as AppointmentDiffInput}
              latest={conflict.latest}
              etag={conflict.etag}
              onReview={(latest) => {
                const updated = latest as unknown as Appointment
                if (updated.status && updated.status !== 'CONFIRMED') {
                  handleClose()
                } else {
                  setConflict(null)
                }
              }}
            />
          )}

          {tooLateError && (
            <Alert color="red" radius="sm">
              <Text size="sm">
                It's less than 2 hours before your appointment. Please call{' '}
                {dealershipPhone ? (
                  <Anchor href={`tel:${dealershipPhone.replace(/\s+/g, '')}`} fw={600}>
                    {dealershipPhone}
                  </Anchor>
                ) : null}{' '}
                to cancel.
              </Text>
            </Alert>
          )}

          {errorMessage && (
            <Alert color="red" radius="sm">
              {errorMessage}
            </Alert>
          )}

          <Radio.Group
            label={customerBookingsCopy.cancel.reason}
            value={reason ?? ''}
            onChange={(val) => setReason(val || null)}
          >
            <Stack gap="xs" mt="xs">
              <Radio
                value="SOMETHING_CAME_UP"
                label={customerBookingsCopy.cancel.reasons.SOMETHING_CAME_UP}
              />
              <Radio
                value="DONE_ELSEWHERE"
                label={customerBookingsCopy.cancel.reasons.DONE_ELSEWHERE}
              />
              <Radio
                value="OTHER"
                label={customerBookingsCopy.cancel.reasons.OTHER}
              />
            </Stack>
          </Radio.Group>

          {reason === 'OTHER' && (
            <Textarea
              label={customerBookingsCopy.cancel.otherNote}
              value={note}
              onChange={(e) => setNote(e.currentTarget.value)}
              maxLength={500}
              rows={3}
            />
          )}

          <Group justify="space-between" gap="sm" mt="md">
            <Button variant="default" onClick={handleClose}>
              {customerBookingsCopy.cancel.keep}
            </Button>
            <SubmitButton
              color="red"
              pending={cancelMutation.isPending}
              pendingLabel={customerBookingsCopy.cancel.submitting}
            >
              {customerBookingsCopy.cancel.confirm}
            </SubmitButton>
          </Group>
        </Stack>
      </form>
    </Drawer>
  )
}
