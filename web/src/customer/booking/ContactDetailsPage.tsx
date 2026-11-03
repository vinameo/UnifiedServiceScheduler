import {
  Alert,
  Box,
  Button,
  Card,
  Checkbox,
  Container,
  Group,
  NumberInput,
  Radio,
  Stack,
  Text,
  TextInput,
  Title,
} from '@mantine/core'
import { useEffect, useRef, useState } from 'react'
import { useNavigate } from 'react-router'
import { useCreateAppointment } from '../../shared/api/appointments'
import { useDealership, useServiceTypes } from '../../shared/api/masterData'
import { toProblem } from '../../shared/api/problem'
import { useAddVehicle, useMyVehicles, type Vehicle } from '../../shared/api/vehicles'
import { useMe } from '../../shared/auth/useMe'
import { formatInZone } from '../../shared/format/time'
import { useIdempotencyKey } from '../../shared/idempotency/useIdempotencyKey'
import { useBookingDraft, type NewVehicleDraft } from './BookingDraftContext'

function formatDuration(minutes: number): string {
  if (minutes >= 120 && minutes % 60 === 0) {
    return `${minutes / 60} hours`
  }
  return `${minutes} min`
}

export function ContactDetailsPage() {
  const navigate = useNavigate()
  const { draft, updateDraft, clearDraft, setAvailabilityError } = useBookingDraft()
  const isNavigatingAway = useRef(false)

  useEffect(() => {
    if (isNavigatingAway.current) return
    if (!draft.dealershipId || !draft.serviceTypeId) {
      navigate('/book', { replace: true })
    } else if (!draft.start) {
      navigate('/book/time', { replace: true })
    }
  }, [draft.dealershipId, draft.serviceTypeId, draft.start, navigate])

  const { data: me } = useMe()
  const { data: dealership } = useDealership(draft.dealershipId ?? undefined)
  const { data: serviceTypes = [] } = useServiceTypes(draft.dealershipId ?? undefined)
  const { data: vehicles = [] } = useMyVehicles()

  const service = serviceTypes.find((s) => s.id === draft.serviceTypeId)
  const timeZone = dealership?.timezone ?? 'Asia/Ho_Chi_Minh'

  const [selectedVehicleMode, setSelectedVehicleMode] = useState<string | null>(draft.vehicleId ?? null)
  const currentVehicleMode = selectedVehicleMode ?? (vehicles.length > 0 ? vehicles[0].id : 'new')

  const [newVehicle, setNewVehicle] = useState<NewVehicleDraft>(() => ({
    licencePlate: draft.newVehicle?.licencePlate ?? '',
    make: draft.newVehicle?.make ?? '',
    model: draft.newVehicle?.model ?? '',
    year: draft.newVehicle?.year ?? null,
  }))

  const [reminderOptIn, setReminderOptIn] = useState<boolean>(draft.reminderOptIn ?? true)
  const [plateError, setPlateError] = useState<string | null>(null)
  const [bookingError, setBookingError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  // Idempotency keys
  const vehicleActionInput = { mode: 'vehicle', ...newVehicle }
  const { key: vehicleIdempotencyKey, reset: resetVehicleKey } = useIdempotencyKey(vehicleActionInput)

  const appointmentActionInput = {
    dealershipId: draft.dealershipId,
    serviceTypeId: draft.serviceTypeId,
    start: draft.start,
    vehicleId: currentVehicleMode,
    reminderOptIn,
  }
  const { key: appointmentIdempotencyKey, reset: resetAppointmentKey } = useIdempotencyKey(appointmentActionInput)

  const addVehicleMutation = useAddVehicle()
  const createAppointmentMutation = useCreateAppointment()

  const handleConfirm = async () => {
    setPlateError(null)
    setBookingError(null)
    setIsSubmitting(true)

    try {
      let targetVehicleId = currentVehicleMode

      // 1. If adding a new vehicle, call useAddVehicle first
      if (currentVehicleMode === 'new') {
        if (!newVehicle.licencePlate.trim() || !newVehicle.make.trim() || !newVehicle.model.trim()) {
          setPlateError('Please fill in licence plate, make and model.')
          setIsSubmitting(false)
          return
        }

        try {
          const created = await addVehicleMutation.mutateAsync({
            payload: {
              licencePlate: newVehicle.licencePlate.trim(),
              make: newVehicle.make.trim(),
              model: newVehicle.model.trim(),
              year: newVehicle.year ?? null,
            },
            idempotencyKey: vehicleIdempotencyKey,
          })
          resetVehicleKey()
          targetVehicleId = created.id
          updateDraft({ vehicleId: created.id, newVehicle: null })
          setSelectedVehicleMode(created.id)
        } catch (err) {
          const problem = toProblem(err)
          if (problem?.code === 'PLATE_ALREADY_REGISTERED') {
            setPlateError(problem.title || 'A vehicle with this licence plate is already registered.')
          } else {
            setPlateError(problem?.title ?? 'Failed to add vehicle.')
          }
          setIsSubmitting(false)
          return
        }
      }

      // 2. Call create appointment
      const result = await createAppointmentMutation.mutateAsync({
        payload: {
          dealershipId: draft.dealershipId!,
          vehicleId: targetVehicleId,
          serviceTypeId: draft.serviceTypeId!,
          desiredStart: draft.start!,
          reminderOptIn,
        },
        idempotencyKey: appointmentIdempotencyKey,
      })
      resetAppointmentKey()

      const appointment = result.data

      // 3. Success
      isNavigatingAway.current = true
      clearDraft()
      navigate(`/book/confirmed/${appointment.id}`, { state: appointment })
    } catch (err) {
      const problem = toProblem(err)
      if (problem?.code === 'NO_AVAILABILITY' || problem?.code === 'START_IN_PAST') {
        isNavigatingAway.current = true
        setAvailabilityError(problem)
        updateDraft({ start: null })
        navigate('/book/time')
        return
      }
      if (problem?.code === 'VEHICLE_ALREADY_BOOKED') {
        setBookingError('This vehicle is already booked for an appointment during this time.')
      } else {
        setBookingError(problem?.title ?? 'Failed to confirm appointment. Please try again.')
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  // Formatting selection card
  let selectionTimeDisplay = '—'
  if (draft.start && service) {
    const endIso = new Date(new Date(draft.start).getTime() + service.durationMinutes * 60000).toISOString()
    const day = formatInZone(draft.start, timeZone, 'dayLong')
    const range = formatInZone(draft.start, timeZone, 'range', endIso)
    selectionTimeDisplay = `${day}, ${range}`
  }

  const customerName = me?.customer?.fullName ?? me?.name ?? ''
  const customerPhone = me?.customer?.phone ?? ''

  return (
    <Container size={390} py="md" pb={100}>
      <Stack gap="lg">
        {/* Header */}
        <Box>
          <Title order={2}>Book a service</Title>
          <Text size="sm" c="dimmed" mt={2}>
            Step 3 of 3 · Contact details
          </Text>
        </Box>

        {/* Your selection Card */}
        <Card withBorder padding="md" radius="md">
          <Text size="xs" fw={700} tt="uppercase" c="dimmed" mb="xs">
            Your selection
          </Text>
          <Stack gap="xs">
            <Group justify="space-between">
              <Text size="sm" c="dimmed">
                Service
              </Text>
              <Text size="sm" fw={500}>
                {service?.name} ({service ? formatDuration(service.durationMinutes) : ''})
              </Text>
            </Group>
            <Group justify="space-between">
              <Text size="sm" c="dimmed">
                Time
              </Text>
              <Text size="sm" fw={500}>
                {selectionTimeDisplay}
              </Text>
            </Group>
            <Group justify="space-between">
              <Text size="sm" c="dimmed">
                Dealership
              </Text>
              <Text size="sm" fw={500}>
                {dealership?.name} {dealership?.branchName ? `· ${dealership.branchName}` : ''}
              </Text>
            </Group>
          </Stack>
        </Card>

        {/* Read-only contact info */}
        <Stack gap="sm">
          <TextInput
            label="Full name"
            value={customerName}
            readOnly
            disabled
          />
          <TextInput
            label="Phone number"
            value={customerPhone}
            readOnly
            disabled
          />
        </Stack>

        {/* Vehicle selection */}
        <Box>
          <Text size="sm" fw={500} mb="xs">
            Vehicle
          </Text>
          <Radio.Group
            value={currentVehicleMode}
            onChange={(val) => {
              setSelectedVehicleMode(val)
              setPlateError(null)
              if (val !== 'new') {
                updateDraft({ vehicleId: val })
              }
            }}
          >
            <Stack gap="xs">
              {vehicles.map((v: Vehicle) => (
                <Card
                  key={v.id}
                  padding="xs"
                  withBorder
                  radius="md"
                  style={{
                    cursor: 'pointer',
                    borderColor:
                      currentVehicleMode === v.id ? 'var(--mantine-color-blue-filled)' : undefined,
                  }}
                  onClick={() => {
                    setSelectedVehicleMode(v.id)
                    updateDraft({ vehicleId: v.id })
                  }}
                >
                  <Group wrap="nowrap">
                    <Radio value={v.id} />
                    <Text size="sm">
                      {v.make} {v.model} · {v.licencePlate}
                    </Text>
                  </Group>
                </Card>
              ))}

              <Card
                padding="xs"
                withBorder
                radius="md"
                style={{
                  cursor: 'pointer',
                  borderColor:
                    currentVehicleMode === 'new' ? 'var(--mantine-color-blue-filled)' : undefined,
                }}
                onClick={() => setSelectedVehicleMode('new')}
              >
                <Group wrap="nowrap">
                  <Radio value="new" />
                  <Text size="sm">Add a new vehicle</Text>
                </Group>
              </Card>
            </Stack>
          </Radio.Group>
        </Box>

        {/* Inline new vehicle form */}
        {currentVehicleMode === 'new' && (
          <Card withBorder padding="md" radius="md">
            <Stack gap="xs">
              <TextInput
                label="Licence plate"
                placeholder="e.g. 51G-999.99"
                required
                value={newVehicle.licencePlate}
                error={plateError}
                onChange={(e) => {
                  const val = e.currentTarget.value
                  setPlateError(null)
                  setNewVehicle((prev) => ({ ...prev, licencePlate: val }))
                }}
              />
              <TextInput
                label="Make"
                placeholder="e.g. Toyota"
                required
                value={newVehicle.make}
                onChange={(e) => {
                  const val = e.currentTarget.value
                  setNewVehicle((prev) => ({ ...prev, make: val }))
                }}
              />
              <TextInput
                label="Model"
                placeholder="e.g. Vios"
                required
                value={newVehicle.model}
                onChange={(e) => {
                  const val = e.currentTarget.value
                  setNewVehicle((prev) => ({ ...prev, model: val }))
                }}
              />
              <NumberInput
                label="Year (optional)"
                placeholder="e.g. 2021"
                min={1950}
                max={new Date().getFullYear() + 1}
                value={newVehicle.year ?? ''}
                onChange={(val) =>
                  setNewVehicle((prev) => ({
                    ...prev,
                    year: typeof val === 'number' ? val : null,
                  }))
                }
              />
            </Stack>
          </Card>
        )}

        {/* Reminder checkbox */}
        <Checkbox
          label="Text me a reminder 1 day before"
          checked={reminderOptIn}
          onChange={(e) => {
            setReminderOptIn(e.currentTarget.checked)
            updateDraft({ reminderOptIn: e.currentTarget.checked })
          }}
        />

        {/* Booking Error Alert if any */}
        {bookingError && (
          <Alert color="red" title="Booking error">
            {bookingError}
          </Alert>
        )}
      </Stack>

      {/* Sticky Bottom Footer */}
      <Box
        style={{
          position: 'fixed',
          bottom: 0,
          left: 0,
          right: 0,
          backgroundColor: 'var(--mantine-color-body)',
          borderTop: '1px solid var(--mantine-color-default-border)',
          padding: '12px 16px',
          zIndex: 100,
        }}
      >
        <Container size={390} p={0}>
          <Button
            fullWidth
            onClick={handleConfirm}
            loading={isSubmitting}
            disabled={isSubmitting}
          >
            Confirm appointment
          </Button>
        </Container>
      </Box>
    </Container>
  )
}
