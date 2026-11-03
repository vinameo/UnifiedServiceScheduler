import {
  Anchor,
  Box,
  Button,
  Container,
  Group,
  Stack,
  Text,
  Title,
} from '@mantine/core'
import { useEffect } from 'react'
import { Link, useNavigate } from 'react-router'
import { useAvailability } from '../../shared/api/availability'
import { useDealership, useServiceTypes } from '../../shared/api/masterData'
import { BookingErrorCard } from '../../shared/booking/BookingErrorCard'
import { SlotGrid } from '../../shared/booking/SlotGrid'
import { formatInZone, getTimezoneOffsetLabel, todayInZone } from '../../shared/format/time'
import { useBookingDraft } from './BookingDraftContext'
import { FullyBookedCard } from './FullyBookedCard'

function formatDuration(minutes: number): string {
  if (minutes >= 120 && minutes % 60 === 0) {
    return `${minutes / 60} hours`
  }
  return `${minutes} min`
}

export function ChooseTimePage() {
  const navigate = useNavigate()
  const { draft, updateDraft, availabilityError } = useBookingDraft()

  useEffect(() => {
    if (!draft.dealershipId || !draft.serviceTypeId) {
      navigate('/book', { replace: true })
    }
  }, [draft.dealershipId, draft.serviceTypeId, navigate])

  const { data: dealership } = useDealership(draft.dealershipId ?? undefined)
  const { data: serviceTypes = [] } = useServiceTypes(draft.dealershipId ?? undefined)
  const service = serviceTypes.find((s) => s.id === draft.serviceTypeId)
  const timeZone = dealership?.timezone ?? 'Asia/Ho_Chi_Minh'

  const minDate = todayInZone(timeZone)
  const selectedDate = draft.date ?? minDate

  const {
    data: availability,
    isLoading: isAvailabilityLoading,
    isError: isAvailabilityError,
    error: availabilityErrorObj,
    refetch: refetchAvailability,
  } = useAvailability({
    dealershipId: draft.dealershipId,
    serviceTypeId: draft.serviceTypeId,
    date: selectedDate,
  })

  // Calculate 14 days starting from today in dealership timezone
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
      openingHours: dayOpeningHours ?? { opensAt: '08:00', closesAt: '17:00' },
      dayShort: formatInZone(`${isoDate}T00:00:00Z`, timeZone, 'dayShort'),
    }
  })

  const currentDayInfo = days.find((d) => d.date === selectedDate) ?? days[0]
  const isClosedDay = availability ? availability.closed : (currentDayInfo ? !currentDayInfo.isOpen : false)

  const isTimeStillAvailable =
    !draft.start || !availability?.slots || availability.date !== selectedDate
      ? true
      : availability.slots.some((s) => s.start === draft.start && s.available)
  const effectiveStart = isTimeStillAvailable ? draft.start : null

  // Drop selected time if date/service changes and it is no longer available
  useEffect(() => {
    if (!availability || availability.date !== selectedDate || !draft.start) {
      return
    }
    const slot = availability.slots.find((s) => s.start === draft.start)
    if (!slot || !slot.available) {
      updateDraft({ start: null })
    }
  }, [availability, selectedDate, draft.start, updateDraft])

  const handleSelectSlot = (slotIso: string) => {
    updateDraft({
      date: selectedDate,
      start: slotIso,
    })
  }

  const handlePickNextAvailable = (slotIso: string) => {
    const slotDate = todayInZone(timeZone, new Date(slotIso))
    updateDraft({
      date: slotDate,
      start: slotIso,
    })
  }

  const handleContinue = () => {
    if (effectiveStart) {
      navigate('/book/details')
    }
  }

  // Get timezone offset label, e.g. "GMT+7"
  const tzOffsetLabel = getTimezoneOffsetLabel(timeZone)

  // Selected summary text
  let choiceSummary = ''
  if (effectiveStart) {
    const dayShort = formatInZone(effectiveStart, timeZone, 'dayShort')
    const time = formatInZone(effectiveStart, timeZone, 'time')
    choiceSummary = `Your choice ${dayShort}, ${time}`
  }

  const isFullyBooked =
    !isClosedDay &&
    !isAvailabilityLoading &&
    !isAvailabilityError &&
    Boolean(availability && availability.freeCount === 0)

  return (
    <Container size={390} py="md" pb={100}>
      <Stack gap="lg">
        {/* Header */}
        <Box>
          <Title order={2}>Book a service</Title>
          <Text size="sm" c="dimmed" mt={2}>
            Step 2 of 3 · Choose date and time
          </Text>
          {service && (
            <Group gap="xs" mt="xs">
              <Text size="sm">
                Service <strong>{service.name}</strong> · {formatDuration(service.durationMinutes)}
              </Text>
              <Anchor component={Link} to="/book" size="sm">
                Change
              </Anchor>
            </Group>
          )}
        </Box>

        {/* Availability Error if redirected from Step 3 */}
        {availabilityError && (
          <BookingErrorCard
            error={availabilityError}
            variant="customer"
            timeZone={timeZone}
            onPickAlternative={(altIso) => {
              const slotDate = todayInZone(timeZone, new Date(altIso))
              updateDraft({
                date: slotDate,
                start: altIso,
              })
            }}
            context={{
              time: draft.start ? formatInZone(draft.start, timeZone, 'time') : '',
              start: draft.start ? formatInZone(draft.start, timeZone, 'time') : '',
              bayTypeLabel: service?.requiredBayType ?? 'service',
            }}
          />
        )}

        {/* 14-day Horizontal Scroll / Chips */}
        <Box>
          <Text size="xs" fw={700} tt="uppercase" c="dimmed" mb="xs">
            {formatInZone(`${selectedDate}T12:00:00Z`, timeZone, 'dayLong')}
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
              const isSelected = d.date === selectedDate
              return (
                <Button
                  key={d.date}
                  size="xs"
                  variant={isSelected ? 'filled' : 'default'}
                  disabled={!d.isOpen}
                  onClick={() => {
                    updateDraft({ date: d.date, start: null })
                  }}
                  style={{ flexShrink: 0 }}
                >
                  {d.dayShort}
                </Button>
              )
            })}
          </Group>
        </Box>

        {/* Time slots */}
        <SlotGrid
          slots={availability?.slots ?? []}
          freeCount={availability?.freeCount}
          closed={isClosedDay}
          loading={isAvailabilityLoading}
          error={isAvailabilityError ? availabilityErrorObj : undefined}
          onRetry={() => refetchAvailability()}
          variant="customer"
          timeZone={timeZone}
          value={effectiveStart}
          onChange={handleSelectSlot}
          renderFullyBooked={
            isFullyBooked && draft.dealershipId && draft.serviceTypeId ? (
              <FullyBookedCard
                dealershipId={draft.dealershipId}
                serviceTypeId={draft.serviceTypeId}
                serviceName={service?.name ?? ''}
                phone={dealership?.phone}
                timeZone={timeZone}
                date={selectedDate}
                onPick={handlePickNextAvailable}
              />
            ) : undefined
          }
        />

        <Text size="xs" c="dimmed">
          Times in dealership time zone ({tzOffsetLabel}).
        </Text>
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
          <Group justify="space-between" align="center">
            <Box style={{ flex: 1, minWidth: 0 }}>
              {choiceSummary ? (
                <Text size="sm" fw={500} truncate>
                  {choiceSummary}
                </Text>
              ) : (
                <Text size="sm" c="dimmed">
                  Choose a time to continue
                </Text>
              )}
            </Box>
            <Button onClick={handleContinue} disabled={!effectiveStart}>
              Continue
            </Button>
          </Group>
        </Container>
      </Box>
    </Container>
  )
}
