import { Alert, Box, Button, Group, Skeleton, Stack, Text } from '@mantine/core'
import { formatInZone } from '../format/time'
import { type AvailabilitySlot, reasonTooltip } from '../api/availability'

export interface SlotGridSlot {
  start: string
  end?: string
  time?: string
  available: boolean
  reason?: string | null
}

export interface SlotGridProps {
  slots: (AvailabilitySlot | SlotGridSlot)[]
  value?: string | null
  onChange: (iso: string) => void
  freeCount?: number
  closed?: boolean
  variant?: 'staff' | 'customer'
  timeZone?: string
  disabled?: boolean
  loading?: boolean
  error?: unknown
  onRetry?: () => void
  renderFullyBooked?: React.ReactNode
}

function resolveSlotTime(slot: AvailabilitySlot | SlotGridSlot, timeZone?: string): string {
  if ('time' in slot && slot.time) {
    return slot.time
  }
  if (timeZone) {
    try {
      return formatInZone(slot.start, timeZone, 'time')
    } catch {
      // Fall through to slice
    }
  }
  return slot.start.slice(11, 16)
}

export function SlotGrid({
  slots,
  value,
  onChange,
  freeCount,
  closed = false,
  variant = 'staff',
  timeZone,
  disabled = false,
  loading = false,
  error,
  onRetry,
  renderFullyBooked,
}: SlotGridProps) {
  if (closed) {
    return (
      <Text size="sm" c="dimmed">
        The dealership is closed on this day.
      </Text>
    )
  }

  if (loading) {
    return (
      <Stack gap="md">
        <Box>
          <Skeleton height={16} width={60} mb="xs" />
          <Group gap="xs">
            {Array.from({ length: 8 }).map((_, i) => (
              <Skeleton key={i} height={36} width={70} radius="sm" />
            ))}
          </Group>
        </Box>
        <Box>
          <Skeleton height={16} width={70} mb="xs" />
          <Group gap="xs">
            {Array.from({ length: 8 }).map((_, i) => (
              <Skeleton key={i} height={36} width={70} radius="sm" />
            ))}
          </Group>
        </Box>
      </Stack>
    )
  }

  if (error) {
    return (
      <Alert color="red" title="Failed to load available times">
        <Group justify="space-between" align="center">
          <Text size="sm">Could not fetch availability.</Text>
          {onRetry && (
            <Button size="xs" variant="outline" color="red" onClick={onRetry}>
              Retry
            </Button>
          )}
        </Group>
      </Alert>
    )
  }

  const effectiveFreeCount = freeCount ?? slots.filter((s) => s.available).length

  if (effectiveFreeCount === 0 && renderFullyBooked) {
    return <>{renderFullyBooked}</>
  }

  if (slots.length === 0) {
    return (
      <Text size="sm" c="dimmed">
        No slots available on this day.
      </Text>
    )
  }

  const enrichedSlots = slots.map((s) => ({
    ...s,
    displayTime: resolveSlotTime(s, timeZone),
  }))

  const morningSlots = enrichedSlots.filter((s) => s.displayTime < '12:00')
  const afternoonSlots = enrichedSlots.filter((s) => s.displayTime >= '12:00')

  const renderSlotsGroup = (groupSlots: typeof enrichedSlots) => (
    <Group gap="xs">
      {groupSlots.map((slot) => {
        const isSelected = value === slot.start
        const isAvailable = slot.available
        const isDisabled = disabled || !isAvailable
        const tooltip = !isAvailable ? reasonTooltip(slot.reason) : undefined

        return (
          <Button
            key={slot.start}
            variant={isSelected ? 'filled' : 'default'}
            color={isSelected ? 'blue' : undefined}
            size="sm"
            disabled={isDisabled}
            aria-pressed={isSelected}
            aria-disabled={isDisabled || undefined}
            title={tooltip}
            td={!isAvailable ? 'line-through' : undefined}
            onClick={() => {
              if (!isDisabled) {
                onChange(slot.start)
              }
            }}
            styles={{
              root: {
                fontWeight: isSelected ? 600 : 400,
                textDecoration: !isAvailable ? 'line-through' : undefined,
              },
            }}
          >
            {slot.displayTime}
          </Button>
        )
      })}
    </Group>
  )

  return (
    <Stack gap="md">
      {variant === 'staff' && (
        <Group justify="space-between" align="baseline">
          <Text size="sm" fw={500}>
            {effectiveFreeCount} free start times
          </Text>
          <Text size="xs" c="dimmed">
            Struck-through slots have no free bay or technician.
          </Text>
        </Group>
      )}

      {morningSlots.length > 0 && (
        <Box>
          <Text size="xs" fw={700} c="dimmed" tt="uppercase" mb="xs">
            {variant === 'customer'
              ? `Morning  ${morningSlots.filter((s) => s.available).length} free slots`
              : 'Morning'}
          </Text>
          {renderSlotsGroup(morningSlots)}
        </Box>
      )}

      {afternoonSlots.length > 0 && (
        <Box>
          <Text size="xs" fw={700} c="dimmed" tt="uppercase" mb="xs">
            {variant === 'customer'
              ? `Afternoon  ${afternoonSlots.filter((s) => s.available).length} free slots`
              : 'Afternoon'}
          </Text>
          {renderSlotsGroup(afternoonSlots)}
        </Box>
      )}

      {variant === 'customer' && (
        <Text size="xs" c="dimmed">
          Times can be taken by someone else until you confirm.
        </Text>
      )}
    </Stack>
  )
}
