import {
  Box,
  Button,
  Card,
  Container,
  Group,
  Stack,
  Text,
  ThemeIcon,
  Title,
} from '@mantine/core'
import { Link, useLocation } from 'react-router'
import type { Appointment } from '../../shared/api/appointments'
import { maskPhone } from '../../shared/format/phone'
import { formatInZone } from '../../shared/format/time'

export function CustomerBookingConfirmedPage() {
  const location = useLocation()
  const rawState = location.state as { data?: Appointment } | Appointment | undefined
  const appointment = (rawState && 'data' in rawState && rawState.data ? rawState.data : rawState) as Appointment | undefined

  if (!appointment) {
    return (
      <Container size={390} py="xl">
        <Stack gap="md" align="center">
          <Title order={3}>Appointment not found</Title>
          <Button component={Link} to="/book">
            Book a service
          </Button>
        </Stack>
      </Container>
    )
  }

  const timeZone = appointment.dealership.timezone ?? 'Asia/Ho_Chi_Minh'
  const dayDisplay = formatInZone(appointment.start, timeZone, 'dayLong')
  const startTime = formatInZone(appointment.start, timeZone, 'time')
  const endTime = formatInZone(appointment.end, timeZone, 'time')
  const timeRange = `${startTime}–${endTime}`
  const maskedPhone = maskPhone(appointment.customer.phone)

  return (
    <Container size={390} py="xl">
      <Stack gap="lg" align="center">
        {/* Check icon */}
        <ThemeIcon size={64} radius="xl" color="green">
          <svg
            width="32"
            height="32"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="3"
            strokeLinecap="round"
            strokeLinejoin="round"
          >
            <polyline points="20 6 9 17 4 12" />
          </svg>
        </ThemeIcon>

        <Box ta="center">
          <Title order={2}>Booking confirmed</Title>
          {maskedPhone && (
            <Text size="sm" c="dimmed" mt={4}>
              We&apos;ll send a confirmation SMS to {maskedPhone}.
            </Text>
          )}
        </Box>

        {/* Details Card */}
        <Card withBorder padding="md" radius="md" style={{ width: '100%' }}>
          <Stack gap="xs">
            <Group justify="space-between">
              <Text size="sm" c="dimmed">
                Appointment ID
              </Text>
              <Text size="sm" fw={600}>
                {appointment.reference}
              </Text>
            </Group>

            <Group justify="space-between" align="flex-start">
              <Text size="sm" c="dimmed">
                Time
              </Text>
              <Box ta="right">
                <Text size="sm" fw={600}>
                  {dayDisplay}
                </Text>
                <Text size="sm" c="dimmed">
                  {timeRange}
                </Text>
              </Box>
            </Group>

            <Group justify="space-between">
              <Text size="sm" c="dimmed">
                Service
              </Text>
              <Text size="sm" fw={600}>
                {appointment.serviceType.name}
              </Text>
            </Group>

            <Group justify="space-between">
              <Text size="sm" c="dimmed">
                Vehicle
              </Text>
              <Text size="sm" fw={600}>
                {appointment.vehicle
                  ? `${appointment.vehicle.make} ${appointment.vehicle.model} · ${appointment.vehicle.licencePlate}`
                  : '—'}
              </Text>
            </Group>

            <Group justify="space-between">
              <Text size="sm" c="dimmed">
                Location
              </Text>
              <Text size="sm" fw={600}>
                {appointment.dealership.name}
                {appointment.dealership.branchName ? ` · ${appointment.dealership.branchName}` : ''}
              </Text>
            </Group>
          </Stack>
        </Card>

        {/* Arrival note */}
        <Text size="xs" c="dimmed" ta="center">
          Please arrive 10 minutes early. You can change or cancel it under My bookings.
        </Text>

        <Button component={Link} to="/book" fullWidth mt="sm">
          Book another service
        </Button>
      </Stack>
    </Container>
  )
}
