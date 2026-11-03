import { useState, type ReactNode } from 'react'
import { Link } from 'react-router'
import {
  Alert,
  Anchor,
  Box,
  Breadcrumbs,
  Button,
  Card,
  Divider,
  Group,
  SimpleGrid,
  Stack,
  Text,
  Title,
} from '@mantine/core'
import { AppointmentStatusBadge } from '../../shared/appointments/AppointmentStatusBadge'
import { isClosed } from '../../shared/appointments/status'
import { formatInZone, formatDateTime } from '../../shared/format/time'
import { maskPhone } from '../../shared/format/phone'
import { bayTypeLabel } from '../../shared/copy/enums'
import type { Appointment } from '../../shared/api/appointments'
import { appointmentCopy } from './copy'

export interface AppointmentDetailsLayoutProps {
  appointment: Appointment
  actions?: ReactNode
  banner?: ReactNode
  history?: ReactNode
}

export function AppointmentDetailsLayout({
  appointment,
  actions,
  banner,
  history,
}: AppointmentDetailsLayoutProps) {
  const [showFullPhone, setShowFullPhone] = useState(false)

  const timeZone = appointment.dealership?.timezone ?? 'Asia/Ho_Chi_Minh'
  const day = formatInZone(appointment.start, timeZone, 'dayLong')
  const range = formatInZone(appointment.start, timeZone, 'range', appointment.end)
  const phone = appointment.customer?.phone ?? ''

  const vehicleSummary = appointment.vehicle
    ? ` · ${appointment.vehicle.make} ${appointment.vehicle.model}`
    : ''
  const title = `${appointment.customer?.fullName ?? ''}${vehicleSummary}`

  const bayType = bayTypeLabel(appointment.bay?.bayType)
  const bayDisplay = appointment.bay
    ? `${appointment.bay.name}${bayType ? ` · ${bayType}` : ''}`
    : '—'

  const vehicleDisplay = appointment.vehicle
    ? `${appointment.vehicle.make} ${appointment.vehicle.model}${appointment.vehicle.year ? ` ${appointment.vehicle.year}` : ''} · ${appointment.vehicle.licencePlate}`
    : '—'

  const closed = isClosed(appointment.status)
  const effectiveBanner =
    banner ??
    (closed ? (
      <Alert color="gray" mb="lg" radius="sm">
        {appointmentCopy.closedBanner}
      </Alert>
    ) : null)

  return (
    <Box maw={1100} mx="auto" py="lg" px="md">
      <Breadcrumbs mb="md">
        <Anchor component={Link} to="/staff/appointments" size="sm">
          {appointmentCopy.breadcrumb}
        </Anchor>
        <Text size="sm" c="dimmed">
          {appointment.reference}
        </Text>
      </Breadcrumbs>

      <Group justify="space-between" align="center" mb="xs">
        <Title order={2} fw={700}>
          {title}
        </Title>
        <AppointmentStatusBadge status={appointment.status} size="lg" />
      </Group>

      <Text size="sm" c="dimmed" mb="lg">
        {day}, {range} · {appointment.serviceType?.name}
      </Text>

      {effectiveBanner}

      {actions && <Box mb="lg">{actions}</Box>}

      <SimpleGrid cols={{ base: 1, md: 2 }} spacing="lg">
        <Card withBorder padding="lg" radius="sm">
          <Title order={4} mb="md">
            {appointmentCopy.infoTitle}
          </Title>

          <Stack gap="sm">
            <Group justify="space-between">
              <Text size="sm" c="dimmed">
                {appointmentCopy.fields.time}
              </Text>
              <Text size="sm" fw={500}>
                {range}, {day}
              </Text>
            </Group>

            <Divider />

            <Group justify="space-between">
              <Text size="sm" c="dimmed">
                {appointmentCopy.fields.service}
              </Text>
              <Text size="sm" fw={500}>
                {appointment.serviceType?.name} · {appointment.serviceType?.durationMinutes} min
              </Text>
            </Group>

            <Divider />

            <Group justify="space-between">
              <Text size="sm" c="dimmed">
                {appointmentCopy.fields.technician}
              </Text>
              <Text size="sm" fw={500}>
                {appointment.technician?.name ?? '—'}
              </Text>
            </Group>

            <Divider />

            <Group justify="space-between">
              <Text size="sm" c="dimmed">
                {appointmentCopy.fields.bay}
              </Text>
              <Text size="sm" fw={500}>
                {bayDisplay}
              </Text>
            </Group>

            <Divider />

            <Group justify="space-between">
              <Text size="sm" c="dimmed">
                {appointmentCopy.fields.dealership}
              </Text>
              <Text size="sm" fw={500}>
                {appointment.dealership?.name}
                {appointment.dealership?.branchName ? ` · ${appointment.dealership.branchName}` : ''}
              </Text>
            </Group>

            {appointment.createdBy && (
              <>
                <Divider />
                <Group justify="space-between">
                  <Text size="sm" c="dimmed">
                    {appointmentCopy.fields.createdBy}
                  </Text>
                  <Text size="sm" fw={500}>
                    {appointment.createdBy.name},{' '}
                    {formatDateTime(appointment.createdAt, timeZone)}
                  </Text>
                </Group>
              </>
            )}
          </Stack>
        </Card>

        <Stack gap="lg">
          <Card withBorder padding="lg" radius="sm">
            <Title order={4} mb="md">
              {appointmentCopy.customerVehicleTitle}
            </Title>

            <Stack gap="sm">
              <Group justify="space-between">
                <Text size="sm" c="dimmed">
                  {appointmentCopy.fields.customer}
                </Text>
                <Text size="sm" fw={500}>
                  {appointment.customer?.fullName}
                </Text>
              </Group>

              <Divider />

              <Group justify="space-between">
                <Text size="sm" c="dimmed">
                  {appointmentCopy.fields.phone}
                </Text>
                <Group gap="xs">
                  {phone ? (
                    showFullPhone ? (
                      <Anchor size="sm" fw={500} href={`tel:${phone}`}>
                        {phone}
                      </Anchor>
                    ) : (
                      <>
                        <Text size="sm" fw={500}>
                          {maskPhone(phone)}
                        </Text>
                        <Button
                          variant="subtle"
                          size="compact-xs"
                          onClick={() => setShowFullPhone(true)}
                        >
                          {appointmentCopy.showNumber}
                        </Button>
                      </>
                    )
                  ) : (
                    <Text size="sm" fw={500}>
                      —
                    </Text>
                  )}
                </Group>
              </Group>

              <Divider />

              <Group justify="space-between">
                <Text size="sm" c="dimmed">
                  {appointmentCopy.fields.vehicle}
                </Text>
                <Text size="sm" fw={500}>
                  {vehicleDisplay}
                </Text>
              </Group>

              {appointment.vehicle?.vin && (
                <>
                  <Divider />
                  <Group justify="space-between">
                    <Text size="sm" c="dimmed">
                      {appointmentCopy.fields.vin}
                    </Text>
                    <Text size="sm" fw={500}>
                      {appointment.vehicle.vin}
                    </Text>
                  </Group>
                </>
              )}
            </Stack>
          </Card>

          {history}
        </Stack>
      </SimpleGrid>
    </Box>
  )
}
