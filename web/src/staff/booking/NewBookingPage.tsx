import { useEffect, useState } from 'react'
import axios from 'axios'
import {
  ActionIcon,
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
import { useAvailability, invalidateAvailability } from '../../shared/api/availability'
import { useQueryClient } from '@tanstack/react-query'
import { useIdempotencyKey } from '../../shared/idempotency/useIdempotencyKey'
import { SubmitButton } from '../../shared/idempotency/SubmitButton'
import { SlotGrid } from '../../shared/booking/SlotGrid'
import { BookingErrorCard, type BookingProblem } from '../../shared/booking/BookingErrorCard'
import { formatInZone, todayInZone } from '../../shared/format/time'
import { localSlotsForDay } from '../../shared/format/slots'
import { maskPhone } from '../../shared/format/phone'

const STAFF_DRAFT_KEY = 'staff_new_booking_draft'

function loadSavedDraft(): {
  selectedCustomerId?: string | null
  selectedVehicleId?: string | null
  selectedServiceTypeId?: string | null
  selectedDate?: string
  selectedTimeIso?: string | null
} | null {
  try {
    const raw = sessionStorage.getItem(STAFF_DRAFT_KEY)
    if (!raw) return null
    return JSON.parse(raw)
  } catch {
    return null
  }
}

function saveDraft(draft: {
  selectedCustomerId: string | null
  selectedVehicleId: string | null
  selectedServiceTypeId: string | null
  selectedDate: string
  selectedTimeIso: string | null
}) {
  try {
    sessionStorage.setItem(STAFF_DRAFT_KEY, JSON.stringify(draft))
  } catch {
    // Ignore sessionStorage errors (e.g. private mode)
  }
}

function bayTypeLabel(bayType?: string): string {
  switch (bayType) {
    case 'QUICK_SERVICE':
      return 'Quick service'
    case 'STANDARD':
      return 'Standard'
    case 'EV':
      return 'EV'
    case 'WASH':
      return 'Wash'
    default:
      return bayType ?? ''
  }
}

export function NewBookingPage() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { data: me } = useMe()
  const dealershipId = me?.dealership?.id

  const { data: dealership } = useDealership(dealershipId)
  const timeZone = dealership?.timezone ?? 'Asia/Ho_Chi_Minh'

  const minDate = todayInZone(timeZone)
  const maxDate = (() => {
    const d = new Date(`${minDate}T12:00:00Z`)
    d.setUTCDate(d.getUTCDate() + 90)
    return d.toISOString().slice(0, 10)
  })()

  const initialDraft = loadSavedDraft()
  const defaultDate = initialDraft?.selectedDate && initialDraft.selectedDate >= minDate
    ? initialDraft.selectedDate
    : minDate

  const [customerSearchQuery, setCustomerSearchQuery] = useState('')
  const [selectedCustomerId, setSelectedCustomerId] = useState<string | null>(initialDraft?.selectedCustomerId ?? null)
  const [selectedVehicleId, setSelectedVehicleId] = useState<string | null>(initialDraft?.selectedVehicleId ?? null)
  const [selectedServiceTypeId, setSelectedServiceTypeId] = useState<string | null>(initialDraft?.selectedServiceTypeId ?? null)
  const [selectedDate, setSelectedDate] = useState(defaultDate)
  const [selectedTimeIso, setSelectedTimeIso] = useState<string | null>(initialDraft?.selectedTimeIso ?? null)
  const [currentError, setCurrentError] = useState<BookingProblem | null>(null)
  const [vehicleError, setVehicleError] = useState<string | null>(null)

  const effectiveSelectedDate = selectedDate < minDate ? minDate : selectedDate

  const {
    data: availability,
    isLoading: isAvailabilityLoading,
    isError: isAvailabilityError,
    refetch: refetchAvailability,
  } = useAvailability({
    dealershipId,
    serviceTypeId: selectedServiceTypeId,
    date: effectiveSelectedDate,
  })

  const isTimeStillAvailable = !selectedTimeIso || !availability?.slots
    ? true
    : availability.slots.some((s) => s.start === selectedTimeIso && s.available)
  const effectiveTimeIso = isTimeStillAvailable ? selectedTimeIso : null

  useEffect(() => {
    saveDraft({
      selectedCustomerId,
      selectedVehicleId,
      selectedServiceTypeId,
      selectedDate: effectiveSelectedDate,
      selectedTimeIso: effectiveTimeIso,
    })
  }, [selectedCustomerId, selectedVehicleId, selectedServiceTypeId, effectiveSelectedDate, effectiveTimeIso])

  const { data: customerSearchResult } = useCustomerSearch(customerSearchQuery)
  const { data: vehicles = [] } = useCustomerVehicles(selectedCustomerId ?? undefined)
  const { data: serviceTypes = [] } = useServiceTypes(dealershipId)

  const activeServices = serviceTypes.filter((s) => s.active)
  const selectedService = activeServices.find((s) => s.id === selectedServiceTypeId)
  const selectedCustomer = customerSearchResult?.data.find((c) => c.id === selectedCustomerId)
  const selectedVehicle = vehicles.find((v) => v.id === selectedVehicleId)

  // Calculate opening hours for selected date
  const selectedWeekday = new Date(`${effectiveSelectedDate}T12:00:00Z`).getUTCDay()
  const normalizedWeekday = selectedWeekday === 0 ? 7 : selectedWeekday
  const hasOpeningHours = Boolean(dealership?.openingHours && dealership.openingHours.length > 0)
  const dayOpeningHours = dealership?.openingHours?.find((h) => h.weekday === normalizedWeekday)
  const isClosedDay = hasOpeningHours && !dayOpeningHours
  const effectiveOpeningHours = dayOpeningHours ?? {
    opensAt: '08:00',
    closesAt: '17:00',
  }

  const durationMinutes = selectedService?.durationMinutes ?? 60
  const slots = isClosedDay
    ? []
    : localSlotsForDay(effectiveSelectedDate, effectiveOpeningHours, durationMinutes, 15, timeZone)

  const actionInput = {
    dealershipId,
    customerId: selectedCustomerId,
    vehicleId: selectedVehicleId,
    serviceTypeId: selectedServiceTypeId,
    desiredStart: effectiveTimeIso,
  }
  const { key: idempotencyKey, reset: resetKey, settle } = useIdempotencyKey(actionInput)

  const createMutation = useCreateAppointment({
    onSuccess: (result) => {
      invalidateAvailability(queryClient)
      resetKey()
      try {
        sessionStorage.removeItem(STAFF_DRAFT_KEY)
      } catch {
        // Ignore
      }
      navigate(`/staff/bookings/${result.data.id}/confirmed`, { state: result.data })
    },
    onError: (err: unknown) => {
      invalidateAvailability(queryClient)
      settle(err)
      if (axios.isAxiosError(err)) {
        const problem = err.response?.data as BookingProblem | undefined
        if (problem?.code === 'VEHICLE_NOT_OWNED_BY_CUSTOMER') {
          const customerName = selectedCustomer?.fullName ?? 'the selected customer'
          setVehicleError(`This vehicle does not belong to ${customerName}. Choose another vehicle or change the customer.`)
          setCurrentError(null)
          return
        }
        setCurrentError(problem ?? { title: 'Booking error', detail: 'Booking failed.' })
        return
      }
      setCurrentError({ title: 'Booking error', detail: 'Booking failed.' })
    },
  })

  const handlePickAlternative = (startIso: string) => {
    const slotDate = todayInZone(timeZone, new Date(startIso))
    if (slotDate !== effectiveSelectedDate) {
      setSelectedDate(slotDate)
    }
    setSelectedTimeIso(startIso)
    setCurrentError(null)
  }

  const handleConfirm = () => {
    if (!selectedCustomerId || !selectedVehicleId || !selectedServiceTypeId || !effectiveTimeIso) {
      return
    }

    setCurrentError(null)
    setVehicleError(null)
    createMutation.mutate({
      payload: {
        dealershipId,
        customerId: selectedCustomerId,
        vehicleId: selectedVehicleId,
        serviceTypeId: selectedServiceTypeId,
        desiredStart: effectiveTimeIso,
      },
      idempotencyKey,
    })
  }

  const customerSelectData = (customerSearchResult?.data ?? []).map((c) => ({
    value: c.id,
    label: `${c.fullName} · ${maskPhone(c.phone)}`,
    disabled: false,
  }))

  if (customerSearchResult && customerSearchResult.meta.total > 20) {
    customerSelectData.push({
      value: '__more__',
      label: `Showing 20 of ${customerSearchResult.meta.total} matches. Type more to narrow the search.`,
      disabled: true,
    })
  }

  const vehicleSelectData = vehicles.map((v) => ({
    value: v.id,
    label: `${v.make} ${v.model} · ${v.licencePlate}`,
  }))

  const isComplete = Boolean(
    selectedCustomerId && selectedVehicleId && selectedServiceTypeId && effectiveTimeIso,
  )

  const formattedDate = formatInZone(`${effectiveSelectedDate}T00:00:00Z`, timeZone, 'dayLong')

  let summaryTimeDisplay = '—'
  let selectedStartFormatted = ''
  let selectedEndFormatted = ''
  if (effectiveTimeIso && selectedService) {
    const endIso = new Date(new Date(effectiveTimeIso).getTime() + selectedService.durationMinutes * 60000).toISOString()
    const dayShort = formatInZone(effectiveTimeIso, timeZone, 'dayShort')
    const range = formatInZone(effectiveTimeIso, timeZone, 'range', endIso)
    summaryTimeDisplay = `${dayShort}, ${range}`
    selectedStartFormatted = formatInZone(effectiveTimeIso, timeZone, 'time')
    selectedEndFormatted = formatInZone(endIso, timeZone, 'time')
  }

  const handleDateShift = (days: number) => {
    const d = new Date(`${effectiveSelectedDate}T12:00:00Z`)
    d.setUTCDate(d.getUTCDate() + days)
    const nextDate = d.toISOString().slice(0, 10)
    if (nextDate < minDate || nextDate > maxDate) {
      return
    }
    setSelectedDate(nextDate)
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
                    setVehicleError(null)
                    setCurrentError(null)
                  }}
                  nothingFoundMessage={customerSearchQuery.length >= 3 ? 'No customers found' : 'Type at least 3 characters'}
                />

                <Select
                  label="Vehicle"
                  placeholder={selectedCustomerId ? 'Choose a vehicle' : 'Select a customer first'}
                  disabled={!selectedCustomerId}
                  data={vehicleSelectData}
                  value={selectedVehicleId}
                  error={vehicleError}
                  onChange={(val) => {
                    setSelectedVehicleId(val)
                    setVehicleError(null)
                    setCurrentError(null)
                  }}
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
                    <ActionIcon
                      variant="default"
                      onClick={() => handleDateShift(-1)}
                      disabled={effectiveSelectedDate <= minDate}
                      aria-label="Previous day"
                    >
                      ‹
                    </ActionIcon>
                    <Text fw={600}>{formattedDate}</Text>
                    <ActionIcon
                      variant="default"
                      onClick={() => handleDateShift(1)}
                      disabled={effectiveSelectedDate >= maxDate}
                      aria-label="Next day"
                    >
                      ›
                    </ActionIcon>
                  </Group>

                  <TextInput
                    type="date"
                    size="xs"
                    value={effectiveSelectedDate}
                    min={minDate}
                    max={maxDate}
                    onChange={(e) => {
                      setSelectedDate(e.currentTarget.value)
                      setSelectedTimeIso(null)
                    }}
                  />
                </Group>

                {!selectedServiceTypeId ? (
                  <Text size="sm" c="dimmed">
                    Choose a service first to view available times.
                  </Text>
                ) : (
                  <SlotGrid
                    slots={
                      availability?.slots ??
                      (isAvailabilityLoading
                        ? []
                        : slots.map((s) => ({
                            start: s.iso,
                            time: s.time,
                            available: true,
                            reason: null,
                          })))
                    }
                    freeCount={availability?.freeCount}
                    closed={availability ? availability.closed : isClosedDay}
                    loading={isAvailabilityLoading}
                    error={isAvailabilityError}
                    onRetry={() => refetchAvailability()}
                    variant="staff"
                    timeZone={timeZone}
                    value={effectiveTimeIso}
                    onChange={setSelectedTimeIso}
                  />
                )}

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
                {currentError && (
                  <Box mb="md">
                    <BookingErrorCard
                      error={currentError}
                      variant="staff"
                      timeZone={timeZone}
                      onPickAlternative={handlePickAlternative}
                      context={{
                        serviceName: selectedService?.name,
                        durationMinutes: selectedService?.durationMinutes,
                        start: selectedStartFormatted,
                        end: selectedEndFormatted,
                        bayTypeLabel: bayTypeLabel(selectedService?.requiredBayType),
                        licencePlate: selectedVehicle?.licencePlate,
                        customerName: selectedCustomer?.fullName,
                        referenceDate: effectiveSelectedDate,
                      }}
                    />
                  </Box>
                )}

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
