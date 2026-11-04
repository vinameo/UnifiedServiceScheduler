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
import { notifications } from '@mantine/notifications'
import { useQueryClient } from '@tanstack/react-query'
import axios from 'axios'
import { Link, useNavigate, useParams } from 'react-router'
import { setAppointment, useAppointment } from '../../shared/api/appointmentQuery'
import { useRescheduleAppointment, type Appointment } from '../../shared/api/appointments'
import { useAvailability } from '../../shared/api/availability'
import { useDealership } from '../../shared/api/masterData'
import { AppointmentModifiedAlert } from '../../shared/appointments/AppointmentModifiedAlert'
import { canCustomerChange } from '../../shared/appointments/changeWindow'
import type { AppointmentDiffInput } from '../../shared/appointments/diffAppointment'
import { BookingErrorCard, type BookingProblem } from '../../shared/booking/BookingErrorCard'
import { SlotGrid } from '../../shared/booking/SlotGrid'
import { formatInZone, todayInZone } from '../../shared/format/time'
import { SubmitButton } from '../../shared/idempotency/SubmitButton'
import { useIdempotencyKey } from '../../shared/idempotency/useIdempotencyKey'
import { customerBookingsCopy } from './copy'

export function ChangeTimePage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [now] = useState(() => Date.now())

  const { data, isLoading, isError } = useAppointment(id ?? '')
  const appointment = data?.appointment
  const currentEtag = data?.etag ?? ''
  const timeZone = appointment?.dealership?.timezone ?? 'Asia/Ho_Chi_Minh'

  const minDate = todayInZone(timeZone)
  const defaultDate = appointment?.start
    ? todayInZone(timeZone, new Date(appointment.start))
    : minDate
  const initialDate = defaultDate >= minDate ? defaultDate : minDate

  const [userSelectedDate, setUserSelectedDate] = useState<string | null>(null)
  const [pickedStart, setPickedStart] = useState<string | null>(null)
  const [conflict, setConflict] = useState<{ latest: AppointmentDiffInput; etag: string } | null>(null)
  const [bookingError, setBookingError] = useState<BookingProblem | null>(null)
  const [tooLateError, setTooLateError] = useState<{ phone: string } | null>(null)

  const selectedDate = userSelectedDate ?? initialDate

  const { data: dealership } = useDealership(appointment?.dealership?.id)

  const days = Array.from({ length: 14 }, (_, i) => {
    const d = new Date(`${minDate}T12:00:00Z`)
    d.setUTCDate(d.getUTCDate() + i)
    const isoDate = d.toISOString().slice(0, 10)
    const weekday = d.getUTCDay() === 0 ? 7 : d.getUTCDay()
    const hasOpeningHours = Boolean(dealership?.openingHours && dealership.openingHours.length > 0)
    const dayOpeningHours = dealership?.openingHours?.find((h) => h.weekday === weekday)
    const isOpen = !hasOpeningHours || Boolean(dayOpeningHours)

    return {
      date: isoDate,
      weekday,
      isOpen,
      dayShort: formatInZone(`${isoDate}T00:00:00Z`, timeZone, 'dayShort'),
    }
  }).filter((d) => d.isOpen)

  const effectiveSelectedDate = days.some((d) => d.date === selectedDate)
    ? selectedDate
    : (days[0]?.date ?? selectedDate)

  const {
    data: availability,
    isLoading: isAvailabilityLoading,
    isError: isAvailabilityError,
    error: availabilityErrorObj,
    refetch: refetchAvailability,
  } = useAvailability({
    dealershipId: appointment?.dealership?.id,
    serviceTypeId: appointment?.serviceType?.id,
    date: effectiveSelectedDate,
    excludeAppointmentId: appointment?.id,
  })

  const actionInput = {
    id: appointment?.id ?? '',
    etag: currentEtag,
    newStart: pickedStart ?? '',
    keepTechnician: true,
  }
  const { key: idempotencyKey, reset: resetKey, settle } = useIdempotencyKey(actionInput)
  const rescheduleMutation = useRescheduleAppointment(appointment?.id ?? '')

  if (isLoading) {
    return (
      <Container size={390} py="xl" px="md">
        <Box ta="center" py="xl">
          <Loader size="md" />
        </Box>
      </Container>
    )
  }

  if (isError || !appointment) {
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

  if (appointment.status !== 'CONFIRMED') {
    return (
      <Container size={390} py="xl" px="md">
        <Stack align="flex-start" gap="md">
          <Alert color="red" title="Cannot change time">
            Only confirmed appointments can be changed. Current status is {appointment.status}.
          </Alert>
          <Button component={Link} to={`/my-bookings/${appointment.id}`}>
            {customerBookingsCopy.details.back}
          </Button>
        </Stack>
      </Container>
    )
  }

  if (!canCustomerChange(appointment, now)) {
    return (
      <Container size={390} py="xl" px="md">
        <Stack align="flex-start" gap="md">
          <Alert color="yellow" title="Too late to change time">
            {appointment.dealership?.phone
              ? customerBookingsCopy.reschedule.tooLate(appointment.dealership.phone)
              : "It's less than 2 hours before your appointment. Please call the dealership to change it."}
          </Alert>
          <Button component={Link} to={`/my-bookings/${appointment.id}`}>
            {customerBookingsCopy.details.back}
          </Button>
        </Stack>
      </Container>
    )
  }

  const durationMinutes = appointment.serviceType?.durationMinutes ?? 60
  const serviceName = appointment.serviceType?.name ?? '—'

  const currentDayShort = formatInZone(appointment.start, timeZone, 'dayShort')
  const currentTime = formatInZone(appointment.start, timeZone, 'time')

  const newDayShort = pickedStart ? formatInZone(pickedStart, timeZone, 'dayShort') : null
  const newTime = pickedStart ? formatInZone(pickedStart, timeZone, 'time') : null

  const isClosedDay = availability?.closed ?? false

  const handleConfirm = (e: React.FormEvent) => {
    e.preventDefault()
    if (!pickedStart || rescheduleMutation.isPending || Boolean(conflict)) {
      return
    }

    setBookingError(null)
    setConflict(null)
    setTooLateError(null)

    rescheduleMutation.mutate(
      {
        newStart: pickedStart,
        keepTechnician: true,
        idempotencyKey,
        etag: currentEtag,
      },
      {
        onSuccess: () => {
          resetKey()
          const pickedEnd = new Date(new Date(pickedStart).getTime() + durationMinutes * 60000).toISOString()
          const dayShort = formatInZone(pickedStart, timeZone, 'dayShort')
          const range = formatInZone(pickedStart, timeZone, 'range', pickedEnd)
          const when = `${dayShort}, ${range}`
          notifications.show({
            message: customerBookingsCopy.reschedule.done(when),
            color: 'green',
          })
          navigate(`/my-bookings/${appointment.id}`)
        },
        onError: (err: unknown) => {
          settle(err)
          if (axios.isAxiosError(err) && err.response) {
            const resStatus = err.response.status
            const resData = err.response.data as BookingProblem & {
              extra?: {
                appointment?: AppointmentDiffInput
                dealershipPhone?: string
              }
              appointment?: AppointmentDiffInput
              dealershipPhone?: string
            }

            if (resStatus === 412) {
              const latest = resData.extra?.appointment ?? resData.appointment
              const resEtag = (err.response.headers['etag'] as string) || ''
              if (latest) {
                setConflict({ latest, etag: resEtag })
              }
            } else if (resStatus === 409) {
              setBookingError(resData)
            } else if (resStatus === 422 && resData.code === 'TOO_LATE_TO_RESCHEDULE') {
              const phone =
                resData.extra?.dealershipPhone ??
                resData.dealershipPhone ??
                appointment.dealership?.phone ??
                ''
              setTooLateError({ phone })
            }
          }
        },
      },
    )
  }

  return (
    <Container size={390} py="md" px="md">
      <Stack gap="md">
        {/* Header with back button */}
        <Group align="center" gap="sm">
          <Anchor
            component={Link}
            to={`/my-bookings/${appointment.id}`}
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
              {customerBookingsCopy.reschedule.title}
            </Title>
            <Text size="xs" c="dimmed">
              {serviceName} · {durationMinutes} min
            </Text>
          </Box>
        </Group>

        {/* Live time comparison */}
        <Card withBorder radius="md" p="sm">
          <Group justify="space-between" align="center" wrap="nowrap">
            <Box style={{ flex: 1, minWidth: 0 }}>
              <Text size="xs" c="dimmed">
                {customerBookingsCopy.reschedule.current}
              </Text>
              <Text size="sm" fw={600} truncate>
                {currentDayShort} · {currentTime}
              </Text>
            </Box>
            <Text size="md" c="dimmed" px="xs">
              →
            </Text>
            <Box style={{ flex: 1, minWidth: 0 }} ta="right">
              <Text size="xs" c="dimmed">
                {customerBookingsCopy.reschedule.new}
              </Text>
              <Text size="sm" fw={600} truncate>
                {newDayShort && newTime ? `${newDayShort} · ${newTime}` : '—'}
              </Text>
            </Box>
          </Group>
        </Card>

        {/* 14 day chips */}
        <Box>
          <Text size="sm" fw={600} mb="xs">
            {customerBookingsCopy.reschedule.chooseDay}
          </Text>
          <Group
            gap="xs"
            wrap="nowrap"
            style={{
              overflowX: 'auto',
              paddingBottom: 8,
              WebkitOverflowScrolling: 'touch',
            }}
          >
            {days.map((d) => {
              const isSelected = d.date === effectiveSelectedDate
              return (
                <Button
                  key={d.date}
                  size="xs"
                  variant={isSelected ? 'filled' : 'default'}
                  onClick={() => {
                    setUserSelectedDate(d.date)
                    setPickedStart(null)
                    setBookingError(null)
                  }}
                  style={{ flexShrink: 0 }}
                >
                  {d.dayShort}
                </Button>
              )
            })}
          </Group>
        </Box>

        {/* Conflict Alert (412) */}
        {conflict && (
          <AppointmentModifiedAlert
            before={appointment as unknown as AppointmentDiffInput}
            latest={conflict.latest}
            etag={conflict.etag}
            onReview={(latest, etag) => {
              setConflict(null)
              setAppointment(queryClient, latest as unknown as Appointment, etag)
            }}
          />
        )}

        {/* 409 Booking Error Card */}
        {bookingError && (
          <BookingErrorCard
            error={bookingError}
            variant="customer"
            timeZone={timeZone}
            onPickAlternative={(startIso) => {
              setBookingError(null)
              const altDate = todayInZone(timeZone, new Date(startIso))
              setUserSelectedDate(altDate)
              setPickedStart(startIso)
            }}
            context={{
              serviceName,
              durationMinutes,
              date: effectiveSelectedDate,
              time: pickedStart ? formatInZone(pickedStart, timeZone, 'time') : '',
              start: pickedStart ? formatInZone(pickedStart, timeZone, 'time') : '',
              timeZone,
            }}
          />
        )}

        {/* 422 Too late error */}
        {tooLateError && (
          <Alert color="yellow" title="Too late to change time" radius="md">
            <Text size="sm">
              {customerBookingsCopy.reschedule.tooLate(tooLateError.phone)}
            </Text>
          </Alert>
        )}

        {/* Choose a time */}
        <Box>
          <Group justify="space-between" align="baseline" mb="xs">
            <Text size="sm" fw={600}>
              {customerBookingsCopy.reschedule.chooseTime}
            </Text>
            {availability && !availability.closed && (
              <Text size="xs" c="dimmed">
                {customerBookingsCopy.reschedule.freeSlots(availability.freeCount)}
              </Text>
            )}
          </Group>

          <SlotGrid
            slots={availability?.slots ?? []}
            freeCount={availability?.freeCount}
            closed={isClosedDay}
            loading={isAvailabilityLoading}
            error={isAvailabilityError ? availabilityErrorObj : undefined}
            onRetry={() => refetchAvailability()}
            variant="customer"
            timeZone={timeZone}
            value={pickedStart}
            onChange={(slotIso) => {
              setPickedStart(slotIso)
              setBookingError(null)
            }}
            currentStart={appointment.start}
          />
        </Box>

        {/* Info Alert */}
        <Alert color="blue" radius="md">
          {customerBookingsCopy.reschedule.info}
        </Alert>

        {/* Confirm SubmitButton */}
        <form onSubmit={handleConfirm}>
          <SubmitButton
            type="submit"
            fullWidth
            size="md"
            disabled={!pickedStart || rescheduleMutation.isPending || Boolean(conflict)}
            pending={rescheduleMutation.isPending}
            pendingLabel={customerBookingsCopy.reschedule.submitting}
          >
            {customerBookingsCopy.reschedule.confirm}
          </SubmitButton>
        </form>
      </Stack>
    </Container>
  )
}
