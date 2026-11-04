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
import { useNavigate, useParams } from 'react-router'
import { useAppointment } from '../../shared/api/appointmentQuery'
import { useConfirmHold } from '../../shared/api/appointments'
import { toProblem } from '../../shared/api/problem'
import { useAddVehicle, useMyVehicles } from '../../shared/api/vehicles'
import { useMe } from '../../shared/auth/useMe'
import { formatInZone } from '../../shared/format/time'
import { useIdempotencyKey } from '../../shared/idempotency/useIdempotencyKey'
import { useCountdown } from '../../shared/time/useCountdown'
import { useBookingDraft, type NewVehicleDraft } from './BookingDraftContext'

function formatDuration(minutes: number): string {
  if (minutes >= 120 && minutes % 60 === 0) {
    return `${minutes / 60} hours`
  }
  return `${minutes} min`
}

export function HoldPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const { draft, updateDraft, clearDraft } = useBookingDraft()
  const isNavigatingAway = useRef(false)

  const { data: appointmentData, isLoading: isAppointmentLoading, isError: isAppointmentError } = useAppointment(id ?? '')
  const appointment = appointmentData?.appointment
  const etag = appointmentData?.etag ?? ''

  useEffect(() => {
    if (isNavigatingAway.current) return
    if (isAppointmentLoading) return
    if (!id || isAppointmentError || (appointment && appointment.status !== 'HELD')) {
      navigate('/book/time', { replace: true })
    }
  }, [id, appointment, isAppointmentLoading, isAppointmentError, navigate])

  const { data: me } = useMe()
  const { data: vehicles = [] } = useMyVehicles()

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
  const [hasServerExpired, setHasServerExpired] = useState(false)

  const countdown = useCountdown(appointment?.expiresAt)
  const isExpired = hasServerExpired || countdown.expired
  const isUnderOneMinute = !isExpired && countdown.totalSeconds < 60

  // Idempotency keys
  const vehicleActionInput = { mode: 'vehicle', ...newVehicle }
  const { key: vehicleIdempotencyKey, reset: resetVehicleKey } = useIdempotencyKey(vehicleActionInput)

  const confirmActionInput = {
    appointmentId: id,
    vehicleId: currentVehicleMode,
    reminderOptIn,
  }
  const { key: confirmIdempotencyKey, reset: resetConfirmKey } = useIdempotencyKey(confirmActionInput)

  const addVehicleMutation = useAddVehicle()
  const confirmHoldMutation = useConfirmHold(id ?? '')

  const timeZone = appointment?.dealership?.timezone ?? 'Asia/Ho_Chi_Minh'
  const timeLabel = appointment?.start ? formatInZone(appointment.start, timeZone, 'time') : ''

  const handlePickAgain = () => {
    isNavigatingAway.current = true
    updateDraft({ start: null })
    navigate('/book/time')
  }

  const handleConfirm = async () => {
    if (isExpired) return

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

      // 2. Call confirm hold
      await confirmHoldMutation.mutateAsync({
        payload: {
          vehicleId: targetVehicleId,
          reminderOptIn,
        },
        idempotencyKey: confirmIdempotencyKey,
        etag,
      })
      resetConfirmKey()

      // 3. Success
      isNavigatingAway.current = true
      clearDraft()
    } catch (err) {
      const problem = toProblem(err)
      if (problem?.code === 'HOLD_EXPIRED' || problem?.status === 410) {
        setHasServerExpired(true)
        return
      }

      if (problem?.status === 412) {
        const extra = problem.extra as Record<string, unknown> | undefined
        const apptExtra = extra?.appointment as { status?: string } | undefined
        if (apptExtra?.status === 'EXPIRED') {
          setHasServerExpired(true)
          return
        }
      }

      if (problem?.code === 'VEHICLE_ALREADY_BOOKED' || problem?.status === 409) {
        setBookingError(problem.title || 'This vehicle already has an appointment at this time.')
        return
      }

      if (problem?.title) {
        setBookingError(problem.title)
      } else {
        setBookingError('Failed to confirm appointment. Please try again.')
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  // Formatting selection card
  let selectionTimeDisplay = '—'
  if (appointment?.start && appointment?.end) {
    const day = formatInZone(appointment.start, timeZone, 'dayLong')
    const range = formatInZone(appointment.start, timeZone, 'range', appointment.end)
    selectionTimeDisplay = `${day}, ${range}`
  }

  const customerName = appointment?.customer?.fullName ?? me?.customer?.fullName ?? me?.name ?? ''
  const customerPhone = appointment?.customer?.phone ?? me?.customer?.phone ?? ''

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

        {/* Hold Timer Card / Expired Card */}
        {isExpired ? (
          <Card withBorder p="md" bg="var(--mantine-color-red-light)">
            <Stack gap="xs">
              <Title order={4} c="red.9">
                Your hold has expired
              </Title>
              <Text size="sm" c="red.9">
                Someone may have booked the {timeLabel} slot. Pick a time again to continue; the details you entered are kept.
              </Text>
              <Button
                variant="outline"
                color="red"
                onClick={handlePickAgain}
                style={{ alignSelf: 'flex-start' }}
              >
                Pick a time again
              </Button>
            </Stack>
          </Card>
        ) : (
          <Card
            withBorder
            p="md"
            bg={isUnderOneMinute ? 'var(--mantine-color-red-light)' : 'var(--mantine-color-blue-light)'}
          >
            <Group justify="space-between" align="center">
              <Text fw={600} size="sm">
                Slot held for you
              </Text>
              <Text fw={700} size="lg" c={isUnderOneMinute ? 'red.9' : undefined}>
                {countdown.formatted}
              </Text>
            </Group>
            <Text size="xs" c="dimmed" mt={4}>
              Finish within this time to keep the slot.
            </Text>
          </Card>
        )}

        {/* Error Alert */}
        {bookingError && (
          <Alert color="red" title="Booking error">
            {bookingError}
          </Alert>
        )}

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
                {appointment?.serviceType?.name} (
                {appointment?.serviceType ? formatDuration(appointment.serviceType.durationMinutes) : ''})
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
                {appointment?.dealership?.name}{' '}
                {appointment?.dealership?.branchName ? `· ${appointment.dealership.branchName}` : ''}
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
              updateDraft({ vehicleId: val === 'new' ? null : val })
            }}
          >
            <Stack gap="xs">
              {vehicles.map((v) => (
                <Radio
                  key={v.id}
                  value={v.id}
                  disabled={isExpired}
                  label={`${v.make} ${v.model} · ${v.licencePlate}`}
                />
              ))}
              <Radio value="new" disabled={isExpired} label="Add a new vehicle" />
            </Stack>
          </Radio.Group>
        </Box>

        {/* Add new vehicle fields if selected */}
        {currentVehicleMode === 'new' && (
          <Card withBorder padding="md" radius="md">
            <Stack gap="sm">
              <TextInput
                label="Licence plate"
                placeholder="51H-123.45"
                required
                disabled={isExpired}
                value={newVehicle.licencePlate}
                onChange={(e) => {
                  const val = e.currentTarget.value
                  setNewVehicle((prev) => ({ ...prev, licencePlate: val }))
                  updateDraft({ newVehicle: { ...newVehicle, licencePlate: val } })
                }}
                error={plateError}
              />
              <TextInput
                label="Make"
                placeholder="Toyota"
                required
                disabled={isExpired}
                value={newVehicle.make}
                onChange={(e) => {
                  const val = e.currentTarget.value
                  setNewVehicle((prev) => ({ ...prev, make: val }))
                  updateDraft({ newVehicle: { ...newVehicle, make: val } })
                }}
              />
              <TextInput
                label="Model"
                placeholder="Vios"
                required
                disabled={isExpired}
                value={newVehicle.model}
                onChange={(e) => {
                  const val = e.currentTarget.value
                  setNewVehicle((prev) => ({ ...prev, model: val }))
                  updateDraft({ newVehicle: { ...newVehicle, model: val } })
                }}
              />
              <NumberInput
                label="Year"
                placeholder="2021"
                min={1950}
                max={2100}
                disabled={isExpired}
                value={newVehicle.year ?? ''}
                onChange={(val) => {
                  const numVal = typeof val === 'number' ? val : null
                  setNewVehicle((prev) => ({ ...prev, year: numVal }))
                  updateDraft({ newVehicle: { ...newVehicle, year: numVal } })
                }}
              />
            </Stack>
          </Card>
        )}

        {/* Reminder opt-in checkbox */}
        <Checkbox
          label="Text me a reminder 1 day before"
          checked={reminderOptIn}
          disabled={isExpired}
          onChange={(e) => {
            const val = e.currentTarget.checked
            setReminderOptIn(val)
            updateDraft({ reminderOptIn: val })
          }}
        />
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
            disabled={isExpired || isSubmitting}
            loading={isSubmitting}
          >
            {isSubmitting ? 'Confirming…' : 'Confirm appointment'}
          </Button>
        </Container>
      </Box>
    </Container>
  )
}
