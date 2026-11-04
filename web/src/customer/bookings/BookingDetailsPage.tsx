import { useState } from 'react'
import {
  Alert,
  Anchor,
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
import { Link, useParams } from 'react-router'
import { useAppointment } from '../../shared/api/appointmentQuery'
import { AppointmentStatusBadge } from '../../shared/appointments/AppointmentStatusBadge'
import { canCustomerChange } from '../../shared/appointments/changeWindow'
import { formatAppointmentSlot } from '../../shared/format/time'
import { CancelBookingSheet } from './CancelBookingSheet'
import { customerBookingsCopy } from './copy'

export function BookingDetailsPage() {
  const { id } = useParams<{ id: string }>()
  const { data, isLoading, isError } = useAppointment(id ?? '')
  const [cancelSheetOpened, setCancelSheetOpened] = useState(false)
  const [now] = useState(() => Date.now())

  if (isLoading) {
    return (
      <Container size={390} py="xl" px="md">
        <Box ta="center" py="xl">
          <Loader size="md" />
        </Box>
      </Container>
    )
  }

  if (isError || !data?.appointment) {
    return (
      <Container size={390} py="xl" px="md">
        <Stack align="center" gap="md">
          <Title order={3} ta="center">
            {customerBookingsCopy.notFound}
          </Title>
          <Button component={Link} to="/my-bookings">
            {customerBookingsCopy.backToBookings}
          </Button>
        </Stack>
      </Container>
    )
  }

  const appointment = data.appointment
  const timeZone = appointment.dealership?.timezone ?? 'Asia/Ho_Chi_Minh'
  const slotDisplay = formatAppointmentSlot(appointment.start, appointment.end, timeZone)
  const vehicleDisplay = appointment.vehicle
    ? `${appointment.vehicle.make} ${appointment.vehicle.model} · ${appointment.vehicle.licencePlate}`
    : '—'
  const dealershipDisplay = appointment.dealership
    ? `${appointment.dealership.name}${appointment.dealership.branchName ? ` · ${appointment.dealership.branchName}` : ''}`
    : ''

  const canChange = canCustomerChange(appointment, now)

  return (
    <Container size={390} py="md" px="md">
      <Stack gap="md">
        {/* Header with back button */}
        <Group align="center" gap="sm">
          <Anchor
            component={Link}
            to="/my-bookings"
            aria-label={customerBookingsCopy.details.back}
            c="dimmed"
            style={{ display: 'inline-flex', alignItems: 'center' }}
          >
            <svg
              width="22"
              height="22"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              strokeWidth="2"
              strokeLinecap="round"
              strokeLinejoin="round"
              aria-hidden="true"
            >
              <path d="M15 18l-6-6 6-6" />
            </svg>
          </Anchor>
          <Box style={{ lineHeight: 1.3 }}>
            <Title order={3} fw={700} size="h4">
              {customerBookingsCopy.details.title}
            </Title>
            <Text
              size="xs"
              c="dimmed"
              style={{ fontVariantNumeric: 'tabular-nums' }}
            >
              {appointment.reference}
            </Text>
          </Box>
        </Group>

        {/* Cancellation banner if cancelled */}
        {appointment.status === 'CANCELLED' && (
          <Alert
            color="gray"
            radius="md"
            title={
              appointment.cancellation?.byRole === 'SERVICE_ADVISOR'
                ? undefined
                : customerBookingsCopy.cancel.doneTitle
            }
          >
            {appointment.cancellation?.byRole === 'SERVICE_ADVISOR' ? (
              <Text size="sm">
                This appointment was cancelled by the dealership.{' '}
                {appointment.dealership?.phone ? (
                  <>
                    Call{' '}
                    <Anchor
                      href={`tel:${appointment.dealership.phone.replace(/\s+/g, '')}`}
                      fw={600}
                    >
                      {appointment.dealership.phone}
                    </Anchor>{' '}
                    if you have questions.
                  </>
                ) : (
                  'Call the dealership if you have questions.'
                )}
              </Text>
            ) : (
              <Text size="sm">{customerBookingsCopy.cancel.doneBody}</Text>
            )}
          </Alert>
        )}

        {/* Slot and Service card */}
        <Card withBorder radius="md" p="md">
          <Stack gap="xs">
            <Group justify="space-between" align="center">
              <Text fw={700} size="md">
                {slotDisplay}
              </Text>
              <AppointmentStatusBadge status={appointment.status} />
            </Group>
            <Text size="sm" c="dimmed">
              {customerBookingsCopy.details.about(
                appointment.serviceType.name,
                appointment.serviceType.durationMinutes,
              )}
            </Text>
          </Stack>
        </Card>

        {/* Appointment summary details card */}
        <Card withBorder radius="md" p="md">
          <Stack gap={0}>
            {/* Vehicle */}
            <Group
              justify="space-between"
              align="flex-start"
              py="xs"
              style={{ borderBottom: '1px solid var(--mantine-color-gray-2)' }}
            >
              <Text size="sm" c="dimmed">
                {customerBookingsCopy.details.vehicle}
              </Text>
              <Text size="sm" fw={500} ta="right">
                {vehicleDisplay}
              </Text>
            </Group>

            {/* Booked by */}
            <Group
              justify="space-between"
              align="flex-start"
              py="xs"
              style={{
                borderBottom: appointment.reminderOptIn
                  ? '1px solid var(--mantine-color-gray-2)'
                  : '1px solid var(--mantine-color-gray-2)',
              }}
            >
              <Text size="sm" c="dimmed">
                Booked by
              </Text>
              <Text size="sm" fw={500} ta="right">
                {appointment.customer.fullName}
              </Text>
            </Group>

            {/* Reminder (if opted in) */}
            {appointment.reminderOptIn && (
              <Group
                justify="space-between"
                align="flex-start"
                py="xs"
                style={{ borderBottom: '1px solid var(--mantine-color-gray-2)' }}
              >
                <Text size="sm" c="dimmed">
                  Reminder
                </Text>
                <Text size="sm" fw={500} ta="right">
                  {customerBookingsCopy.details.reminder}
                </Text>
              </Group>
            )}

            {/* Location */}
            <Group justify="space-between" align="flex-start" py="xs">
              <Text size="sm" c="dimmed">
                {customerBookingsCopy.details.location}
              </Text>
              <Box ta="right">
                <Text size="sm" fw={500}>
                  {dealershipDisplay}
                </Text>
                {appointment.dealership?.phone && (
                  <Anchor
                    href={`tel:${appointment.dealership.phone.replace(/\s+/g, '')}`}
                    size="sm"
                    fw={600}
                  >
                    {appointment.dealership.phone}
                  </Anchor>
                )}
              </Box>
            </Group>
          </Stack>
        </Card>

        {/* Actions area + New booking button */}
        <Stack gap="sm" mt="xs">
          {appointment.status === 'CONFIRMED' && (
            canChange ? (
              <>
                <Button
                  component={Link}
                  to={`/my-bookings/${appointment.id}/change`}
                  fullWidth
                  size="md"
                >
                  {customerBookingsCopy.reschedule.button}
                </Button>
                <Button
                  variant="outline"
                  color="red"
                  fullWidth
                  size="md"
                  onClick={() => setCancelSheetOpened(true)}
                >
                  {customerBookingsCopy.cancel.button}
                </Button>
              </>
            ) : (
              <Text size="sm" c="dimmed" ta="center">
                It's less than 2 hours before your appointment. Please call{' '}
                {appointment.dealership?.phone ? (
                  <Anchor
                    href={`tel:${appointment.dealership.phone.replace(/\s+/g, '')}`}
                    fw={600}
                  >
                    {appointment.dealership.phone}
                  </Anchor>
                ) : null}{' '}
                to change or cancel.
              </Text>
            )
          )}

          <Button
            component={Link}
            to="/book"
            variant={appointment.status === 'CONFIRMED' && canChange ? 'default' : 'filled'}
            fullWidth
            size="md"
          >
            {customerBookingsCopy.details.newBooking}
          </Button>
        </Stack>
      </Stack>

      <CancelBookingSheet
        opened={cancelSheetOpened}
        onClose={() => setCancelSheetOpened(false)}
        appointment={data.appointment}
        etag={data.etag}
      />
    </Container>
  )
}
