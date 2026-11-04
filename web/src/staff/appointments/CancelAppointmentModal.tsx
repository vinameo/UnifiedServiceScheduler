import { useState } from 'react'
import { Alert, Button, Group, Modal, Select, Stack, Text, Textarea } from '@mantine/core'
import { notifications } from '@mantine/notifications'
import axios from 'axios'
import type { Appointment } from '../../shared/api/appointments'
import { useCancelAppointment } from '../../shared/api/appointments'
import { formatInZone } from '../../shared/format/time'
import { useIdempotencyKey } from '../../shared/idempotency/useIdempotencyKey'
import { SubmitButton } from '../../shared/idempotency/SubmitButton'
import { AppointmentModifiedAlert } from '../../shared/appointments/AppointmentModifiedAlert'
import type { AppointmentDiffInput } from '../../shared/appointments/diffAppointment'
import { appointmentCopy } from './copy'

export interface CancelAppointmentModalProps {
  opened: boolean
  onClose: () => void
  appointment: Appointment
  etag: string
}

const STAFF_CANCEL_REASONS = [
  { value: 'CUSTOMER_REQUEST', label: appointmentCopy.cancel.reasons.CUSTOMER_REQUEST },
  { value: 'CUSTOMER_BUSY', label: appointmentCopy.cancel.reasons.CUSTOMER_BUSY },
  { value: 'DONE_ELSEWHERE', label: appointmentCopy.cancel.reasons.DONE_ELSEWHERE },
  { value: 'DEALERSHIP_ISSUE', label: appointmentCopy.cancel.reasons.DEALERSHIP_ISSUE },
  { value: 'OTHER', label: appointmentCopy.cancel.reasons.OTHER },
]

export function CancelAppointmentModal({
  opened,
  onClose,
  appointment,
  etag,
}: CancelAppointmentModalProps) {
  const [reason, setReason] = useState<string | null>(null)
  const [note, setNote] = useState('')
  const [conflict, setConflict] = useState<{ latest: AppointmentDiffInput; etag: string } | null>(null)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)

  const timeZone = appointment.dealership?.timezone ?? 'Asia/Ho_Chi_Minh'
  const bayDisplay = appointment.bay?.name ?? '—'
  const technicianDisplay = appointment.technician?.name ?? '—'
  const range = formatInZone(appointment.start, timeZone, 'range', appointment.end)

  const actionInput = {
    id: appointment.id,
    etag,
    reason,
    note: note ? note.trim() : null,
  }
  const { key: idempotencyKey, reset: resetKey, settle } = useIdempotencyKey(actionInput)
  const cancelMutation = useCancelAppointment(appointment.id)

  const handleClose = () => {
    setReason(null)
    setNote('')
    setConflict(null)
    setErrorMessage(null)
    onClose()
  }

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (!reason || cancelMutation.isPending) {
      return
    }

    setErrorMessage(null)
    setConflict(null)

    cancelMutation.mutate(
      {
        reason,
        note: note ? note.trim() : null,
        idempotencyKey,
        etag,
      },
      {
        onSuccess: () => {
          resetKey()
          notifications.show({
            message: appointmentCopy.cancel.done,
            color: 'green',
          })
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
    <Modal
      opened={opened}
      onClose={handleClose}
      title={<Text fw={600}>{appointmentCopy.cancel.title}</Text>}
      radius="sm"
    >
      <form onSubmit={handleSubmit}>
        <Stack gap="md">
          <Text size="sm">
            {appointmentCopy.cancel.body(bayDisplay, technicianDisplay, range)}
          </Text>

          {conflict && (
            <AppointmentModifiedAlert
              before={appointment as unknown as AppointmentDiffInput}
              latest={conflict.latest}
              etag={conflict.etag}
              onReview={(latest) => {
                const updated = latest as unknown as Appointment
                if (updated.status && !['CONFIRMED', 'HELD'].includes(updated.status)) {
                  handleClose()
                } else {
                  setConflict(null)
                }
              }}
            />
          )}

          {errorMessage && (
            <Alert color="red" radius="sm">
              {errorMessage}
            </Alert>
          )}

          <Select
            label={appointmentCopy.cancel.reason}
            placeholder={appointmentCopy.cancel.reason}
            data={STAFF_CANCEL_REASONS}
            value={reason}
            onChange={setReason}
            required
            comboboxProps={{ withinPortal: false }}
          />

          <Textarea
            label={appointmentCopy.cancel.note}
            value={note}
            onChange={(e) => setNote(e.currentTarget.value)}
            maxLength={500}
            rows={3}
          />

          <Group justify="flex-end" gap="sm" mt="md">
            <Button variant="default" onClick={handleClose}>
              {appointmentCopy.cancel.keep}
            </Button>
            <SubmitButton
              color="red"
              disabled={!reason}
              pending={cancelMutation.isPending}
              pendingLabel={appointmentCopy.cancel.submitting}
            >
              {appointmentCopy.cancel.confirm}
            </SubmitButton>
          </Group>
        </Stack>
      </form>
    </Modal>
  )
}
