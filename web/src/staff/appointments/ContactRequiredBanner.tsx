import { Alert, Anchor, Button, Group, Stack, Text } from '@mantine/core'
import type { Appointment } from '../../shared/api/appointments'
import { appointmentCopy, contactReasonLabel } from './copy'

export interface ContactRequiredBannerProps {
  appointment: Appointment
  /** Advisors get Reschedule and Cancel (WEB §B3). */
  canChange: boolean
  onRescheduleClick: () => void
  onCancelClick: () => void
}

/** WEB §B3: a booking flagged by `contactRequiredReason` (D-12.8) asks staff to call the customer. */
export function ContactRequiredBanner({
  appointment,
  canChange,
  onRescheduleClick,
  onCancelClick,
}: ContactRequiredBannerProps) {
  const reason = appointment.contactRequiredReason
  if (!reason) return null

  const phone = appointment.customer?.phone ?? ''

  return (
    <Alert color="yellow" mb="lg" radius="sm" title={appointmentCopy.contact.banner(contactReasonLabel(reason))}>
      <Stack gap="xs">
        {reason === 'OVERRUN' && <Text size="sm">{appointmentCopy.contact.displacedNote}</Text>}
        {phone && (
          <Anchor size="sm" fw={500} href={`tel:${phone}`}>
            {phone}
          </Anchor>
        )}
        {canChange && (
          <Group gap="sm">
            <Button size="xs" onClick={onRescheduleClick}>
              {appointmentCopy.reschedule.button}
            </Button>
            <Button size="xs" variant="outline" color="red" onClick={onCancelClick}>
              {appointmentCopy.cancel.button}
            </Button>
          </Group>
        )}
      </Stack>
    </Alert>
  )
}
