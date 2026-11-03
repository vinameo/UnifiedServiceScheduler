import { useState } from 'react'
import axios from 'axios'
import {
  ActionIcon,
  Alert,
  Box,
  Card,
  Grid,
  Group,
  Radio,
  Select,
  Stack,
  Text,
  TextInput,
  Title,
} from '@mantine/core'
import { useNavigate } from 'react-router'
import { useMe } from '../../shared/auth/useMe'
import { useCustomerSearch } from '../../shared/api/customers'
import { useCustomerVehicles } from '../../shared/api/vehicles'
import { useDealership, useServiceTypes } from '../../shared/api/masterData'
import { useCreateAppointment } from '../../shared/api/appointments'
import { useIdempotencyKey } from '../../shared/idempotency/useIdempotencyKey'
import { SubmitButton } from '../../shared/idempotency/SubmitButton'
import { TimeGrid } from '../../shared/booking/TimeGrid'
import { formatInZone } from '../../shared/format/time'
import { localSlotsForDay } from '../../shared/format/slots'
import { maskPhone } from '../../shared/format/phone'

export function NewBookingPage() {
  const navigate = useNavigate()
  const { data: me } = useMe()
  const dealershipId = me?.dealership?.id

  const { data: dealership } = useDealership(dealershipId)
  const timeZone = dealership?.timezone ?? 'Asia/Ho_Chi_Minh'

  const [customerSearchQuery, setCustomerSearchQuery] = useState('')
  const [selectedCustomerId, setSelectedCustomerId] = useState<string | null>(null)
  const [selectedVehicleId, setSelectedVehicleId] = useState<string | null>(null)
  const [selectedServiceTypeId, setSelectedServiceTypeId] = useState<string | null>(null)
  const [selectedDate, setSelectedDate] = useState('2026-11-02')
  const [selectedTimeIso, setSelectedTimeIso] = useState<string | null>(null)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)

  const { data: customerSearchResult } = useCustomerSearch(customerSearchQuery)
  const { data: vehicles = [] } = useCustomerVehicles(selectedCustomerId ?? undefined)
  const { data: serviceTypes = [] } = useServiceTypes(dealershipId)

  const activeServices = serviceTypes.filter((s) => s.active)
  const selectedService = activeServices.find((s) => s.id === selectedServiceTypeId)
  const selectedCustomer = customerSearchResult?.data.find((c) => c.id === selectedCustomerId)
  const selectedVehicle = vehicles.find((v) => v.id === selectedVehicleId)

  // Calculate opening hours for selected date
  const selectedWeekday = new Date(`${selectedDate}T12:00:00Z`).getUTCDay()
  const normalizedWeekday = selectedWeekday === 0 ? 7 : selectedWeekday
  const dayOpeningHours = dealership?.openingHours?.find((h) => h.weekday === normalizedWeekday) ?? {
    opensAt: '08:00',
    closesAt: '17:00',
  }

  const durationMinutes = selectedService?.durationMinutes ?? 60
  const slots = localSlotsForDay(selectedDate, dayOpeningHours, durationMinutes, 15, timeZone)

  const actionInput = {
    dealershipId,
    customerId: selectedCustomerId,
    vehicleId: selectedVehicleId,
    serviceTypeId: selectedServiceTypeId,
    desiredStart: selectedTimeIso,
  }
  const { key: idempotencyKey, reset: resetKey, settle } = useIdempotencyKey(actionInput)

  const createMutation = useCreateAppointment({
    onSuccess: (result) => {
      resetKey()
      navigate(`/staff/bookings/${result.data.id}/confirmed`, { state: result.data })
    },
    onError: (err: unknown) => {
      settle(err)
      let detail = 'Booking failed.'
      if (axios.isAxiosError(err)) {
        const data = err.response?.data as Record<string, unknown> | undefined
        detail = (data?.title as string) ?? (data?.detail as string) ?? detail
      }
      setErrorMessage(detail)
    },
  })

  const handleConfirm = () => {
    if (!selectedCustomerId || !selectedVehicleId || !selectedServiceTypeId || !selectedTimeIso) {
      return
    }

    setErrorMessage(null)
    createMutation.mutate({
      payload: {
        dealershipId,
        customerId: selectedCustomerId,
        vehicleId: selectedVehicleId,
        serviceTypeId: selectedServiceTypeId,
        desiredStart: selectedTimeIso,
      },
      idempotencyKey,
    })
  }

  const customerSelectData = (customerSearchResult?.data ?? []).map((c) => ({
    value: c.id,
    label: `${c.fullName} · ${maskPhone(c.phone)}`,
  }))

  const vehicleSelectData = vehicles.map((v) => ({
    value: v.id,
    label: `${v.make} ${v.model} · ${v.licencePlate}`,
  }))

  const isComplete = Boolean(
    selectedCustomerId && selectedVehicleId && selectedServiceTypeId && selectedTimeIso,
  )

  const formattedDate = formatInZone(`${selectedDate}T00:00:00Z`, timeZone, 'dayLong')

  let summaryTimeDisplay = '—'
  if (selectedTimeIso && selectedService) {
    const endIso = new Date(new Date(selectedTimeIso).getTime() + selectedService.durationMinutes * 60000).toISOString()
    const dayShort = formatInZone(selectedTimeIso, timeZone, 'dayShort')
    const range = formatInZone(selectedTimeIso, timeZone, 'range', endIso)
    summaryTimeDisplay = `${dayShort}, ${range}`
  }

  const handleDateShift = (days: number) => {
    const d = new Date(`${selectedDate}T12:00:00Z`)
    d.setUTCDate(d.getUTCDate() + days)
    setSelectedDate(d.toISOString().slice(0, 10))
    setSelectedTimeIso(null)
  }

  return (
    <Stack gap="xl">
      <Box>
        <Title order={2} fw={700}>
          New booking
        </Title>
        <Text size="lg" fw={600} mt={2}>
          Book a service appointment
        </Text>
        <Text c="dimmed" size="sm" mt={4}>
          The system checks a service bay and a qualified technician together for the full service duration.
        </Text>
      </Box>

      {errorMessage && (
        <Alert color="red" title="Booking error">
          {errorMessage}
        </Alert>
      )}

      <Grid gap="xl">
        <Grid.Col span={{ base: 12, md: 8 }}>
          <Stack gap="lg">
            {/* 1. Customer and vehicle */}
            <Card withBorder padding="md" radius="sm">
              <Title order={4} fw={600} mb="md">
                1. Customer and vehicle
              </Title>
              <Stack gap="md">
                <Select
                  label="Customer"
                  placeholder="Search customer by name, email or phone"
                  searchable
                  clearable
                  data={customerSelectData}
                  value={selectedCustomerId}
                  searchValue={customerSearchQuery}
                  onSearchChange={setCustomerSearchQuery}
                  onChange={(val) => {
                    setSelectedCustomerId(val)
                    setSelectedVehicleId(null)
                  }}
                  nothingFoundMessage={customerSearchQuery.length >= 3 ? 'No customers found' : 'Type at least 3 characters'}
                />

                <Select
                  label="Vehicle"
                  placeholder={selectedCustomerId ? 'Choose a vehicle' : 'Select a customer first'}
                  disabled={!selectedCustomerId}
                  data={vehicleSelectData}
                  value={selectedVehicleId}
                  onChange={setSelectedVehicleId}
                  nothingFoundMessage="No vehicles for this customer"
                />

                <Text size="xs" c="dimmed">
                  Only vehicles owned by the selected customer are shown.
                </Text>
              </Stack>
            </Card>

            {/* 2. Service */}
            <Card withBorder padding="md" radius="sm">
              <Title order={4} fw={600} mb="md">
                2. Service
              </Title>
              <Radio.Group
                value={selectedServiceTypeId ?? ''}
                onChange={(val) => setSelectedServiceTypeId(val)}
              >
                <Stack gap="sm">
                  {activeServices.map((service) => (
                    <Card
                      key={service.id}
                      withBorder
                      padding="sm"
                      radius="sm"
                      style={{ cursor: 'pointer' }}
                      onClick={() => setSelectedServiceTypeId(service.id)}
                    >
                      <Group justify="space-between">
                        <Group>
                          <Radio value={service.id} />
                          <Box>
                            <Text fw={600} size="sm">
                              {service.name}
                            </Text>
                            <Text size="xs" c="dimmed">
                              {service.description ?? `${service.durationMinutes} min`}
                            </Text>
                          </Box>
                        </Group>
                        <Text size="sm" fw={500}>
                          {service.durationMinutes} min
                        </Text>
                      </Group>
                    </Card>
                  ))}
                </Stack>
              </Radio.Group>
            </Card>

            {/* 3. Date and time */}
            <Card withBorder padding="md" radius="sm">
              <Title order={4} fw={600} mb="md">
                3. Date and time
              </Title>
              <Stack gap="md">
                <Group justify="space-between" align="center">
                  <Group gap="xs">
                    <ActionIcon variant="default" onClick={() => handleDateShift(-1)} aria-label="Previous day">
                      ‹
                    </ActionIcon>
                    <Text fw={600}>{formattedDate}</Text>
                    <ActionIcon variant="default" onClick={() => handleDateShift(1)} aria-label="Next day">
                      ›
                    </ActionIcon>
                  </Group>

                  <TextInput
                    type="date"
                    size="xs"
                    value={selectedDate}
                    onChange={(e) => {
                      setSelectedDate(e.currentTarget.value)
                      setSelectedTimeIso(null)
                    }}
                  />
                </Group>

                <TimeGrid
                  slots={slots}
                  value={selectedTimeIso}
                  onChange={setSelectedTimeIso}
                />

                <Text size="xs" c="dimmed">
                  Times in dealership time zone (GMT+7).
                </Text>
              </Stack>
            </Card>
          </Stack>
        </Grid.Col>

        {/* Summary card on right */}
        <Grid.Col span={{ base: 12, md: 4 }}>
          <Card withBorder padding="lg" radius="sm" style={{ position: 'sticky', top: 80 }}>
            <Title order={4} fw={600} mb="lg">
              Summary
            </Title>

            <Stack gap="sm">
              <Group justify="space-between">
                <Text size="sm" c="dimmed">
                  Customer
                </Text>
                <Text size="sm" fw={500}>
                  {selectedCustomer?.fullName ?? '—'}
                </Text>
              </Group>

              <Group justify="space-between">
                <Text size="sm" c="dimmed">
                  Vehicle
                </Text>
                <Text size="sm" fw={500}>
                  {selectedVehicle ? `${selectedVehicle.make} ${selectedVehicle.model} · ${selectedVehicle.licencePlate}` : '—'}
                </Text>
              </Group>

              <Group justify="space-between">
                <Text size="sm" c="dimmed">
                  Service
                </Text>
                <Text size="sm" fw={500}>
                  {selectedService?.name ?? '—'}
                </Text>
              </Group>

              <Group justify="space-between">
                <Text size="sm" c="dimmed">
                  Duration
                </Text>
                <Text size="sm" fw={500}>
                  {selectedService ? `${selectedService.durationMinutes} min` : '—'}
                </Text>
              </Group>

              <Group justify="space-between">
                <Text size="sm" c="dimmed">
                  Time
                </Text>
                <Text size="sm" fw={500}>
                  {summaryTimeDisplay}
                </Text>
              </Group>

              <Box mt="xs">
                <Text size="xs" c="dimmed">
                  Technician, bay
                </Text>
                <Text size="xs" fw={500}>
                  Assigned automatically on confirm
                </Text>
              </Box>

              <Box mt="md">
                <SubmitButton
                  fullWidth
                  pending={createMutation.isPending}
                  disabled={!isComplete}
                  onClick={handleConfirm}
                >
                  {isComplete ? 'Confirm booking' : 'Choose a time to continue'}
                </SubmitButton>
                <Text size="xs" c="dimmed" ta="center" mt="xs">
                  Each booking carries its own idempotency key to prevent double bookings.
                </Text>
              </Box>
            </Stack>
          </Card>
        </Grid.Col>
      </Grid>
    </Stack>
  )
}
