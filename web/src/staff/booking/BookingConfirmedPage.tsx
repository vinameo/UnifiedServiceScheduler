import { Box, Button, Card, Divider, Group, Stack, Text, Title } from '@mantine/core'
import { useLocation, useNavigate } from 'react-router'
import { AppointmentStatusBadge } from '../../shared/appointments/AppointmentStatusBadge'
import { formatInZone } from '../../shared/format/time'
import { maskPhone } from '../../shared/format/phone'
import type { Appointment } from '../../shared/api/appointments'

export function BookingConfirmedPage() {
  const navigate = useNavigate()
  const location = useLocation()
  const appointment = location.state as Appointment | undefined

  if (!appointment) {
    return (
      <Box p="lg">
        <Text>No appointment details found.</Text>
        <Button mt="md" onClick={() => navigate('/staff')}>
          Back to New booking
        </Button>
      </Box>
    )
  }

  const timeZone = appointment.dealership?.timezone ?? 'Asia/Ho_Chi_Minh'
  const day = formatInZone(appointment.start, timeZone, 'dayLong')
  const time = formatInZone(appointment.start, timeZone, 'range', appointment.end)
  const phone = appointment.customer?.phone ? maskPhone(appointment.customer.phone) : '—'

  return (
    <Box maw={640} mx="auto" py="xl">
      <Card withBorder padding="xl" radius="sm">
        <Group justify="space-between" align="center" mb="lg">
          <Title order={3} fw={700}>
            Appointment confirmed
          </Title>
          <AppointmentStatusBadge status={appointment.status} size="lg" />
        </Group>

        <Text size="sm" c="dimmed" mb="lg">
          Appointment ID <Text span fw={600} c="dark">{appointment.reference}</Text>. The bay and technician are reserved for the full service duration.
        </Text>

        <Divider my="md" />

        <Stack gap="sm">
          <Group justify="space-between">
            <Text size="sm" c="dimmed">Time</Text>
            <Text size="sm" fw={600}>{day}, {time}</Text>
          </Group>

          <Group justify="space-between">
            <Text size="sm" c="dimmed">Service</Text>
            <Text size="sm" fw={500}>{appointment.serviceType?.name}</Text>
          </Group>

          <Group justify="space-between">
            <Text size="sm" c="dimmed">Technician</Text>
            <Text size="sm" fw={500}>{appointment.technician?.name ?? 'Assigned'}</Text>
          </Group>

          <Group justify="space-between">
            <Text size="sm" c="dimmed">Bay</Text>
            <Text size="sm" fw={500}>{appointment.bay?.code ? `${appointment.bay.code} · ${appointment.bay.name}` : 'Assigned'}</Text>
          </Group>

          <Group justify="space-between">
            <Text size="sm" c="dimmed">Customer</Text>
            <Text size="sm" fw={500}>{appointment.customer?.fullName}</Text>
          </Group>

          <Group justify="space-between">
            <Text size="sm" c="dimmed">Vehicle</Text>
            <Text size="sm" fw={500}>{appointment.vehicle ? `${appointment.vehicle.make} ${appointment.vehicle.model} · ${appointment.vehicle.licencePlate}` : '—'}</Text>
          </Group>
        </Stack>

        <Divider my="md" />

        <Text size="xs" c="dimmed" mb="lg">
          A confirmation SMS has been queued for {phone}.
        </Text>

        <Button fullWidth onClick={() => navigate('/staff')}>
          Book another
        </Button>
      </Card>
    </Box>
  )
}
