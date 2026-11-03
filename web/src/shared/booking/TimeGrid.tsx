import { Box, Button, Group, Stack, Text } from '@mantine/core'
import type { Slot } from '../format/slots'

export interface TimeGridProps {
  slots: Slot[]
  value?: string | null
  onChange: (iso: string) => void
  disabledSlots?: string[]
  disabled?: boolean
}

export function TimeGrid({
  slots,
  value,
  onChange,
  disabledSlots = [],
  disabled = false,
}: TimeGridProps) {
  const morningSlots = slots.filter((s) => s.time < '12:00')
  const afternoonSlots = slots.filter((s) => s.time >= '12:00')

  const renderSlotsGroup = (groupSlots: Slot[]) => (
    <Group gap="xs">
      {groupSlots.map((slot) => {
        const isSelected = value === slot.iso
        const isDisabled = disabled || disabledSlots.includes(slot.iso)

        return (
          <Button
            key={slot.iso}
            variant={isSelected ? 'filled' : 'default'}
            color={isSelected ? 'blue' : undefined}
            size="sm"
            disabled={isDisabled}
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

