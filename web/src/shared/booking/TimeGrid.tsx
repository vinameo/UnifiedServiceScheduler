import { Box, Button, Group, Stack, Text } from '@mantine/core'
import type { Slot } from '../format/slots'

export type TimeGridChipState = 'free' | 'taken' | 'selected'
export type ChipState = TimeGridChipState

export interface TimeGridSlot extends Slot {
  state?: TimeGridChipState
  reason?: string | null
}

export interface TimeGridProps {
  slots: (Slot | TimeGridSlot)[]
  value?: string | null
  onChange: (iso: string) => void
  disabledSlots?: string[]
  disabled?: boolean
  chipState?: (slot: Slot | TimeGridSlot) => TimeGridChipState
  chipStates?: Record<string, TimeGridChipState>
}

function resolveChipState(
  slot: Slot | TimeGridSlot,
  props: {
    value?: string | null
    disabledSlots?: string[]
    disabled?: boolean
    chipState?: (slot: Slot | TimeGridSlot) => TimeGridChipState
    chipStates?: Record<string, TimeGridChipState>
  },
): TimeGridChipState {
  if (props.chipState) {
    return props.chipState(slot)
  }
  if (props.chipStates && slot.iso in props.chipStates) {
    return props.chipStates[slot.iso]
  }
  if ('state' in slot && slot.state !== undefined) {
    return slot.state
  }
  if (props.value === slot.iso) {
    return 'selected'
  }
  if (props.disabled || (props.disabledSlots && props.disabledSlots.includes(slot.iso))) {
    return 'taken'
  }
  return 'free'
}

export function TimeGrid({
  slots,
  value,
  onChange,
  disabledSlots = [],
  disabled = false,
  chipState,
  chipStates,
}: TimeGridProps) {
  const morningSlots = slots.filter((s) => s.time < '12:00')
  const afternoonSlots = slots.filter((s) => s.time >= '12:00')

  const renderSlotsGroup = (groupSlots: (Slot | TimeGridSlot)[]) => (
    <Group gap="xs">
      {groupSlots.map((slot) => {
        const state = resolveChipState(slot, {
          value,
          disabledSlots,
          disabled,
          chipState,
          chipStates,
        })
        const isSelected = state === 'selected'
        const isTaken = state === 'taken'
        const isDisabled = disabled || isTaken
        const reason = 'reason' in slot ? slot.reason : undefined

        return (
          <Button
            key={slot.iso}
            variant={isSelected ? 'filled' : 'default'}
            color={isSelected ? 'blue' : undefined}
            size="sm"
            disabled={isDisabled}
            aria-pressed={isSelected}
            aria-disabled={isDisabled || undefined}
            title={reason ?? undefined}
            onClick={() => onChange(slot.iso)}
            styles={{
              root: {
                fontWeight: isSelected ? 600 : 400,
              },
            }}
          >
            {slot.time}
          </Button>
        )
      })}
    </Group>
  )

  if (slots.length === 0) {
    return (
      <Text size="sm" c="dimmed">
        No slots available on this day.
      </Text>
    )
  }

  return (
    <Stack gap="md">
      {morningSlots.length > 0 && (
        <Box>
          <Text size="xs" fw={700} c="dimmed" tt="uppercase" mb="xs">
            Morning
          </Text>
          {renderSlotsGroup(morningSlots)}
        </Box>
      )}

      {afternoonSlots.length > 0 && (
        <Box>
          <Text size="xs" fw={700} c="dimmed" tt="uppercase" mb="xs">
            Afternoon
          </Text>
          {renderSlotsGroup(afternoonSlots)}
        </Box>
      )}
    </Stack>
  )
}
