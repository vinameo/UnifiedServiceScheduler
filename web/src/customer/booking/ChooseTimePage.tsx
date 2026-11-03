import {
  Anchor,
  Box,
  Button,
  Card,
  Container,
  Group,
  SimpleGrid,
  Stack,
  Text,
  Title,
} from '@mantine/core'
import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router'
import { useDealership, useServiceTypes } from '../../shared/api/masterData'
import { BookingErrorCard } from '../../shared/booking/BookingErrorCard'
import { formatInZone, getTimezoneOffsetLabel, todayInZone } from '../../shared/format/time'
import { localSlotsForDay, type DayOpeningHours } from '../../shared/format/slots'
import { useBookingDraft } from './BookingDraftContext'

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
  const [selectedDate, setSelectedDate] = useState<string>(draft.date ?? minDate)

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
  const isClosedDay = currentDayInfo ? !currentDayInfo.isOpen : false
  const effectiveHours: DayOpeningHours = currentDayInfo?.openingHours ?? {
    opensAt: '08:00',
    closesAt: '17:00',
  }

  const durationMinutes = service?.durationMinutes ?? 60
  const slots = isClosedDay
    ? []
    : localSlotsForDay(selectedDate, effectiveHours, durationMinutes, 15, timeZone)

  const morningSlots = slots.filter((s) => s.time < '12:00')
  const afternoonSlots = slots.filter((s) => s.time >= '12:00')

  const handleSelectSlot = (slotIso: string) => {
    updateDraft({
      date: selectedDate,
      start: slotIso,
    })
  }

  const handleContinue = () => {
    if (draft.start) {
      navigate('/book/details')
    }
  }

  // Get timezone offset label, e.g. "GMT+7"
  const tzOffsetLabel = getTimezoneOffsetLabel(timeZone)

  // Selected summary text
  let choiceSummary = ''
  if (draft.start) {
    const dayShort = formatInZone(draft.start, timeZone, 'dayShort')
    const time = formatInZone(draft.start, timeZone, 'time')
    choiceSummary = `Your choice ${dayShort}, ${time}`
  }

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
                    setSelectedDate(d.date)
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
        {isClosedDay ? (
          <Card withBorder padding="md">
            <Text c="dimmed" ta="center">
              The dealership is closed on this day.
            </Text>
          </Card>
        ) : (
          <Stack gap="md">
            {morningSlots.length > 0 && (
              <Box>
                <Text size="xs" fw={700} tt="uppercase" c="dimmed" mb="xs">
                  Morning
                </Text>
                <SimpleGrid cols={4} spacing="xs">
                  {morningSlots.map((slot) => {
                    const isSelected = draft.start === slot.iso
                    return (
                      <Button
                        key={slot.iso}
                        size="xs"
                        variant={isSelected ? 'filled' : 'default'}
                        onClick={() => handleSelectSlot(slot.iso)}
                      >
                        {slot.time}
                      </Button>
                    )
                  })}
                </SimpleGrid>
              </Box>
            )}

            {afternoonSlots.length > 0 && (
              <Box>
                <Text size="xs" fw={700} tt="uppercase" c="dimmed" mb="xs">
                  Afternoon
                </Text>
                <SimpleGrid cols={4} spacing="xs">
                  {afternoonSlots.map((slot) => {
                    const isSelected = draft.start === slot.iso
                    return (
                      <Button
                        key={slot.iso}
                        size="xs"
                        variant={isSelected ? 'filled' : 'default'}
                        onClick={() => handleSelectSlot(slot.iso)}
                      >
                        {slot.time}
                      </Button>
                    )
                  })}
                </SimpleGrid>
              </Box>
            )}

            <Text size="xs" c="dimmed">
              Times in dealership time zone ({tzOffsetLabel}).
            </Text>
          </Stack>
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
            <Button onClick={handleContinue} disabled={!draft.start}>
              Continue
            </Button>
          </Group>
        </Container>
      </Box>
    </Container>
  )
}
