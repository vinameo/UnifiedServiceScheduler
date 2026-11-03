import { useEffect, useMemo } from 'react'
import {
  Anchor,
  Button,
  Card,
  Group,
  Select,
  Stack,
  Table,
  Text,
  TextInput,
  Title,
} from '@mantine/core'
import { Link, useSearchParams } from 'react-router'
import { useMe } from '../../shared/auth/useMe'
import { useBays, useTechnicians } from '../../shared/api/masterData'
import { useDealershipAppointments } from '../../shared/api/appointments'
import { AppointmentStatusBadge } from '../../shared/appointments/AppointmentStatusBadge'
import { formatInZone, getTimezoneOffsetLabel, todayInZone } from '../../shared/format/time'
import { appointmentCopy } from './copy'

const STATUS_OPTIONS = [
  { value: '', label: appointmentCopy.list.filters.all },
  { value: 'HELD', label: 'On hold' },
  { value: 'CONFIRMED', label: 'Confirmed' },
  { value: 'CHECKED_IN', label: 'Checked in' },
  { value: 'IN_PROGRESS', label: 'In progress' },
  { value: 'COMPLETED', label: 'Completed' },
  { value: 'CANCELLED', label: 'Cancelled' },
  { value: 'NO_SHOW', label: 'No-show' },
  { value: 'EXPIRED', label: 'Expired' },
]

export function AppointmentListPage() {
  const { data: me } = useMe()
  const [searchParams, setSearchParams] = useSearchParams()

  const isAdvisor = me?.role === 'SERVICE_ADVISOR'
  const dealershipId = me?.dealership?.id
  const timeZone = me?.dealership?.timezone ?? 'Asia/Ho_Chi_Minh'

  const defaultDate = todayInZone(timeZone)
  const dateParam = searchParams.get('date')
  const effectiveDate = dateParam || defaultDate

  const technicianId = searchParams.get('technicianId') || undefined
  const bayId = searchParams.get('bayId') || undefined
  const status = searchParams.get('status') || undefined

  useEffect(() => {
    if (!dateParam && effectiveDate) {
      const next = new URLSearchParams(searchParams)
      next.set('date', effectiveDate)
      setSearchParams(next, { replace: true })
    }
  }, [dateParam, effectiveDate, searchParams, setSearchParams])

  const { data: technicians = [] } = useTechnicians(dealershipId)
  const { data: bays = [] } = useBays(dealershipId)

  const {
    data,
    fetchNextPage,
    hasNextPage,
    isFetchingNextPage,
  } = useDealershipAppointments({
    dealershipId,
    date: effectiveDate,
    technicianId,
    bayId,
    status,
  })

  const appointments = useMemo(
    () => data?.pages.flatMap((page) => page.data) ?? [],
    [data],
  )
  const total = data?.pages[0]?.meta?.total ?? appointments.length

  const updateFilter = (key: string, value: string | null) => {
    const next = new URLSearchParams(searchParams)
    if (!value) {
      next.delete(key)
    } else {
      next.set(key, value)
    }
    setSearchParams(next)
  }

  const dayLong = formatInZone(`${effectiveDate}T12:00:00Z`, timeZone, 'dayLong')
  const offset = getTimezoneOffsetLabel(timeZone, new Date(`${effectiveDate}T12:00:00Z`))
  const subtitle = appointmentCopy.list.subtitle(dayLong, offset)

  return (
    <Stack gap="md">
      <Group justify="space-between" align="flex-start">
        <Stack gap={4}>
          <Title order={2}>{appointmentCopy.list.title}</Title>
          <Text c="dimmed" size="sm">
            {subtitle}
          </Text>
        </Stack>
        {isAdvisor && (
          <Button component={Link} to="/staff" variant="filled">
            {appointmentCopy.list.newBooking}
          </Button>
        )}
      </Group>

      <Card withBorder p="sm" radius="md">
        <Group gap="md" align="flex-end">
          <TextInput
            type="date"
            label={appointmentCopy.list.filters.date}
            value={effectiveDate}
            onChange={(e) => updateFilter('date', e.currentTarget.value)}
          />
          <Select
            label={appointmentCopy.list.filters.technician}
            placeholder={appointmentCopy.list.filters.all}
            value={technicianId ?? ''}
            data={[
              { value: '', label: appointmentCopy.list.filters.all },
              ...technicians.map((t) => ({ value: t.id, label: t.name })),
            ]}
            onChange={(val) => updateFilter('technicianId', val)}
          />
          <Select
            label={appointmentCopy.list.filters.bay}
            placeholder={appointmentCopy.list.filters.all}
            value={bayId ?? ''}
            data={[
              { value: '', label: appointmentCopy.list.filters.all },
              ...bays.map((b) => ({ value: b.id, label: b.name })),
            ]}
            onChange={(val) => updateFilter('bayId', val)}
          />
          <Select
            label={appointmentCopy.list.filters.status}
            placeholder={appointmentCopy.list.filters.all}
            value={status ?? ''}
            data={STATUS_OPTIONS}
            onChange={(val) => updateFilter('status', val)}
          />
        </Group>
      </Card>

      <Card withBorder radius="md" p={0}>
        <Table verticalSpacing="sm" horizontalSpacing="md">
          <Table.Thead>
            <Table.Tr>
              <Table.Th>{appointmentCopy.list.columns.time}</Table.Th>
              <Table.Th>{appointmentCopy.list.columns.customerAndVehicle}</Table.Th>
              <Table.Th>{appointmentCopy.list.columns.service}</Table.Th>
              <Table.Th>{appointmentCopy.list.columns.technician}</Table.Th>
              <Table.Th>{appointmentCopy.list.columns.bay}</Table.Th>
              <Table.Th>{appointmentCopy.list.columns.status}</Table.Th>
              <Table.Th>{appointmentCopy.list.columns.actions}</Table.Th>
            </Table.Tr>
          </Table.Thead>
          <Table.Tbody>
            {appointments.length === 0 ? (
              <Table.Tr>
                <Table.Td colSpan={7}>
                  <Text c="dimmed" ta="center" py="xl">
                    {appointmentCopy.list.empty}
                  </Text>
                </Table.Td>
              </Table.Tr>
            ) : (
              appointments.map((apt) => (
                <Table.Tr key={apt.id}>
                  <Table.Td>
                    <Text size="sm" fw={500}>
                      {formatInZone(apt.start, timeZone, 'range', apt.end)}
                    </Text>
                  </Table.Td>
                  <Table.Td>
                    <Text size="sm" fw={600}>
                      {apt.customer.fullName}
                    </Text>
                    {apt.vehicle && (
                      <Text size="xs" c="dimmed">
                        {apt.vehicle.make} {apt.vehicle.model} · {apt.vehicle.licencePlate}
                      </Text>
                    )}
                  </Table.Td>
                  <Table.Td>
                    <Text size="sm">{apt.serviceType.name}</Text>
                  </Table.Td>
                  <Table.Td>
                    <Text size="sm">{apt.technician ? apt.technician.name : '—'}</Text>
                  </Table.Td>
                  <Table.Td>
                    <Text size="sm">{apt.bay ? apt.bay.name : '—'}</Text>
                  </Table.Td>
                  <Table.Td>
                    <AppointmentStatusBadge status={apt.status} />
                  </Table.Td>
                  <Table.Td>
                    <Anchor component={Link} to={`/staff/appointments/${apt.id}`} size="sm">
                      {appointmentCopy.list.view}
                    </Anchor>
                  </Table.Td>
                </Table.Tr>
              ))
            )}
          </Table.Tbody>
        </Table>
      </Card>

      {appointments.length > 0 && (
        <Group justify="space-between" px="xs">
          <Text size="sm" c="dimmed">
            {appointmentCopy.list.footer(appointments.length, total)}
          </Text>
          {hasNextPage && (
            <Button
              variant="default"
              size="xs"
              onClick={() => fetchNextPage()}
              loading={isFetchingNextPage}
            >
              {appointmentCopy.list.loadMore}
            </Button>
          )}
        </Group>
      )}
    </Stack>
  )
}
