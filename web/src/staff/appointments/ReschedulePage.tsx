import { useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router'
import {
  ActionIcon,
  Alert,
  Anchor,
  Box,
  Breadcrumbs,
  Button,
  Card,
  Center,
  Checkbox,
  Grid,
  Group,
  Loader,
  Stack,
  Text,
  TextInput,
  Title,
} from '@mantine/core'
import { notifications } from '@mantine/notifications'
import axios from 'axios'
import { useAppointment } from '../../shared/api/appointmentQuery'
import { useRescheduleAppointment } from '../../shared/api/appointments'
import { useAvailability } from '../../shared/api/availability'
import { AppointmentModifiedAlert } from '../../shared/appointments/AppointmentModifiedAlert'
import type { AppointmentDiffInput } from '../../shared/appointments/diffAppointment'
import { BookingErrorCard, type BookingProblem } from '../../shared/booking/BookingErrorCard'
import { SlotGrid } from '../../shared/booking/SlotGrid'
import { formatInZone, todayInZone } from '../../shared/format/time'
import { SubmitButton } from '../../shared/idempotency/SubmitButton'
import { useIdempotencyKey } from '../../shared/idempotency/useIdempotencyKey'
import { appointmentCopy } from './copy'

export function ReschedulePage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const { data, isLoading, isError } = useAppointment(id ?? '')

  const appointment = data?.appointment
  const currentEtag = data?.etag ?? ''
  const timeZone = appointment?.dealership?.timezone ?? 'Asia/Ho_Chi_Minh'

  const defaultDate = appointment?.start
    ? todayInZone(timeZone, new Date(appointment.start))
    : todayInZone(timeZone)

  const [selectedDate, setSelectedDate] = useState<string>(defaultDate)
  const [pickedStart, setPickedStart] = useState<string | null>(null)
  const [keepTechnician, setKeepTechnician] = useState<boolean>(true)
  const [conflict, setConflict] = useState<{ latest: AppointmentDiffInput; etag: string } | null>(null)
  const [bookingError, setBookingError] = useState<BookingProblem | null>(null)

  const {
    data: availability,
    isLoading: isAvailabilityLoading,
    isError: isAvailabilityError,
    refetch: refetchAvailability,
  } = useAvailability({
    dealershipId: appointment?.dealership?.id,
    serviceTypeId: appointment?.serviceType?.id,
    date: selectedDate,
    excludeAppointmentId: appointment?.id,
  })

  const actionInput = {
    id: appointment?.id ?? '',
    etag: currentEtag,
    newStart: pickedStart ?? '',
    keepTechnician,
  }
  const { key: idempotencyKey, reset: resetKey, settle } = useIdempotencyKey(actionInput)
  const rescheduleMutation = useRescheduleAppointment(appointment?.id ?? '')

  if (isLoading) {
    return (
      <Center py="xl" mih={300}>
        <Loader size="md" />
      </Center>
    )
  }

  if (isError || !appointment) {
    return (
      <Box maw={640} mx="auto" py="xl" px="md">
        <Stack align="flex-start" gap="md">
          <Title order={3}>{appointmentCopy.notFound}</Title>
          <Text size="sm" c="dimmed">
            The appointment may have been deleted, or you do not have permission to view it.
          </Text>
          <Button onClick={() => navigate('/staff/appointments')}>
            {appointmentCopy.backToList}
          </Button>
        </Stack>
      </Box>
    )
  }

  if (appointment.status !== 'CONFIRMED') {
    return (
      <Box maw={640} mx="auto" py="xl" px="md">
        <Stack align="flex-start" gap="md">
          <Alert color="red" title="Cannot reschedule">
            Only confirmed appointments can be rescheduled. Current status is {appointment.status}.
          </Alert>
          <Button onClick={() => navigate(`/staff/appointments/${appointment.id}`)}>
            {appointmentCopy.viewAppointment}
          </Button>
        </Stack>
      </Box>
    )
  }

  const durationMinutes = appointment.serviceType?.durationMinutes ?? 60
  const pickedEnd = pickedStart
    ? new Date(new Date(pickedStart).getTime() + durationMinutes * 60000).toISOString()
    : null

  const pickedDayShort = pickedStart ? formatInZone(pickedStart, timeZone, 'dayShort') : ''
  const pickedRange = pickedStart && pickedEnd ? formatInZone(pickedStart, timeZone, 'range', pickedEnd) : ''
  const changeToSummary = pickedStart ? `${pickedDayShort}, ${pickedRange}` : ''

  const currentDayShort = formatInZone(appointment.start, timeZone, 'dayShort')
  const currentRange = formatInZone(appointment.start, timeZone, 'range', appointment.end)
  const currentTimeDisplay = `${currentDayShort}, ${currentRange}`

  const customerName = appointment.customer?.fullName ?? '—'
  const vehicleName = appointment.vehicle
    ? `${appointment.vehicle.make} ${appointment.vehicle.model}`
    : '—'
  const serviceName = appointment.serviceType?.name ?? '—'
  const technicianName = appointment.technician?.name ?? 'technician'

  const minDate = todayInZone(timeZone)
  const maxDate = (() => {
    const d = new Date(`${minDate}T12:00:00Z`)
    d.setUTCDate(d.getUTCDate() + 90)
    return d.toISOString().slice(0, 10)
  })()

  const handleDateShift = (deltaDays: number) => {
    const current = new Date(`${selectedDate}T12:00:00Z`)
    current.setUTCDate(current.getUTCDate() + deltaDays)
    const shifted = todayInZone(timeZone, current)
    if (shifted >= minDate && shifted <= maxDate) {
      setSelectedDate(shifted)
      setPickedStart(null)
      setBookingError(null)
    }
  }

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (!pickedStart || rescheduleMutation.isPending) {
      return
    }

    setBookingError(null)
    setConflict(null)

    rescheduleMutation.mutate(
      {
        newStart: pickedStart,
        keepTechnician,
        idempotencyKey,
        etag: currentEtag,
      },
      {
        onSuccess: () => {
          resetKey()
          notifications.show({
            message: appointmentCopy.reschedule.done(changeToSummary),
            color: 'green',
          })
          navigate(`/staff/appointments/${appointment.id}`)
        },
        onError: (err: unknown) => {
          settle(err)
          if (axios.isAxiosError(err) && err.response) {
            const resStatus = err.response.status
            const resData = err.response.data as BookingProblem & {
              extra?: { appointment?: AppointmentDiffInput }
              appointment?: AppointmentDiffInput
            }

            if (resStatus === 412) {
              const latest = resData.extra?.appointment ?? resData.appointment
              const resEtag = (err.response.headers['etag'] as string) || ''
              if (latest) {
                setConflict({ latest, etag: resEtag })
              }
            } else if (resStatus === 409) {
              setBookingError(resData)
            }
          }
        },
      },
    )
  }

  const breadcrumbs = [
    { title: appointmentCopy.breadcrumb, href: '/staff/appointments' },
    { title: appointment.reference, href: `/staff/appointments/${appointment.id}` },
    { title: appointmentCopy.reschedule.button, href: '#' },
  ]

  const formattedDate = formatInZone(`${selectedDate}T12:00:00Z`, timeZone, 'dayLong')

  return (
    <Box maw={960} mx="auto" py="md" px="md">
      <Stack gap="lg">
        <Breadcrumbs>
          {breadcrumbs.map((item, index) =>
            item.href === '#' ? (
              <Text key={index} size="sm" c="dimmed">
                {item.title}
              </Text>
            ) : (
              <Anchor key={index} component={Link} to={item.href} size="sm">
                {item.title}
              </Anchor>
            ),
          )}
        </Breadcrumbs>

        <Box>
          <Title order={2}>{appointmentCopy.reschedule.title}</Title>
          <Text size="sm" c="dimmed" mt={4}>
            {appointmentCopy.reschedule.subtitle(customerName, vehicleName, serviceName, durationMinutes)}
          </Text>
        </Box>

        <Alert color="blue" radius="sm">
          {appointmentCopy.reschedule.info}
        </Alert>

        {conflict && (
          <AppointmentModifiedAlert
            before={appointment as unknown as AppointmentDiffInput}
            latest={conflict.latest}
            etag={conflict.etag}
            onReview={() => {
              setConflict(null)
            }}
          />
        )}

        {bookingError && (
          <BookingErrorCard
            error={bookingError}
            context={{
              serviceName,
              durationMinutes,
              date: selectedDate,
              timeZone,
            }}
            onPickAlternative={(startIso) => {
              setBookingError(null)
              const altDate = todayInZone(timeZone, new Date(startIso))
              setSelectedDate(altDate)
              setPickedStart(startIso)
            }}
          />
        )}

        <form onSubmit={handleSubmit}>
          <Grid gap="md">
            <Grid.Col span={{ base: 12, md: 5 }}>
              <Card withBorder padding="md" radius="sm">
                <Title order={4} fw={600} mb="md">
                  {appointmentCopy.reschedule.current}
                </Title>
                <Stack gap="sm">
                  <Box>
                    <Text size="xs" c="dimmed">
                      {appointmentCopy.fields.time}
                    </Text>
                    <Text size="sm" fw={500}>
                      {currentTimeDisplay}
                    </Text>
                  </Box>
                  <Box>
                    <Text size="xs" c="dimmed">
                      {appointmentCopy.fields.technician}
                    </Text>
                    <Text size="sm" fw={500}>
                      {appointment.technician?.name ?? '—'}
                    </Text>
                  </Box>
                  <Box>
                    <Text size="xs" c="dimmed">
                      {appointmentCopy.fields.bay}
                    </Text>
                    <Text size="sm" fw={500}>
                      {appointment.bay?.name ?? '—'}
                    </Text>
                  </Box>
                </Stack>
              </Card>
            </Grid.Col>

            <Grid.Col span={{ base: 12, md: 7 }}>
              <Card withBorder padding="md" radius="sm">
                <Title order={4} fw={600} mb="md">
                  {appointmentCopy.reschedule.choose}
                </Title>
                <Stack gap="md">
                  <Group justify="space-between" align="center">
                    <Group gap="xs">
                      <ActionIcon
                        variant="default"
                        onClick={() => handleDateShift(-1)}
                        disabled={selectedDate <= minDate}
                        aria-label="Previous day"
                      >
                        ‹
                      </ActionIcon>
                      <Text fw={600}>{formattedDate}</Text>
                      <ActionIcon
                        variant="default"
                        onClick={() => handleDateShift(1)}
                        disabled={selectedDate >= maxDate}
                        aria-label="Next day"
                      >
                        ›
                      </ActionIcon>
                    </Group>

                    <TextInput
                      type="date"
                      size="xs"
                      value={selectedDate}
                      min={minDate}
                      max={maxDate}
                      onChange={(e) => {
                        setSelectedDate(e.currentTarget.value)
                        setPickedStart(null)
                        setBookingError(null)
                      }}
                    />
                  </Group>

                  {appointment.technician && (
                    <Checkbox
                      checked={keepTechnician}
                      onChange={(e) => setKeepTechnician(e.currentTarget.checked)}
                      label={appointmentCopy.reschedule.keepTech(technicianName)}
                    />
                  )}

                  <SlotGrid
                    slots={availability?.slots ?? []}
                    value={pickedStart}
                    onChange={(iso) => {
                      setPickedStart(iso)
                      setBookingError(null)
                    }}
                    freeCount={availability?.freeCount}
                    closed={availability?.closed}
                    variant="staff"
                    timeZone={timeZone}
                    loading={isAvailabilityLoading}
                    error={isAvailabilityError}
                    onRetry={() => refetchAvailability()}
                    currentStart={appointment.start}
                  />

                  {pickedStart && (
                    <Text fw={600} size="sm" c="blue">
                      {appointmentCopy.reschedule.changeTo(changeToSummary)}
                    </Text>
                  )}

                  <Group justify="flex-end" gap="sm" mt="md">
                    <Button
                      variant="default"
                      onClick={() => navigate(`/staff/appointments/${appointment.id}`)}
                    >
                      {appointmentCopy.reschedule.back}
                    </Button>
                    <SubmitButton
                      type="submit"
                      disabled={!pickedStart || rescheduleMutation.isPending}
                      pending={rescheduleMutation.isPending}
                      pendingLabel={appointmentCopy.reschedule.submitting}
                    >
                      {appointmentCopy.reschedule.confirm}
                    </SubmitButton>
                  </Group>
                </Stack>
              </Card>
            </Grid.Col>
          </Grid>
        </form>
      </Stack>
    </Box>
  )
}
