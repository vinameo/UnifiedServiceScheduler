import { Card, Center, Loader, Text, Timeline, Title } from '@mantine/core'
import type { AppointmentEvent } from '../../shared/api/appointments'
import { formatShortDateTime } from '../../shared/format/time'
import { appointmentCopy, historyTypeLabels } from './copy'

export interface AppointmentHistorySectionProps {
  events?: AppointmentEvent[]
  isLoading?: boolean
  timeZone: string
}

export function AppointmentHistorySection({
  events,
  isLoading,
  timeZone,
}: AppointmentHistorySectionProps) {
  return (
    <Card withBorder padding="lg" radius="sm">
      <Title order={4} mb="md">
        {appointmentCopy.history}
      </Title>

      {isLoading ? (
        <Center py="md">
          <Loader size="sm" />
        </Center>
      ) : !events || events.length === 0 ? (
        <Text size="sm" c="dimmed">
          {appointmentCopy.noHistory}
        </Text>
      ) : (
        <Timeline active={events.length - 1} bulletSize={12} lineWidth={2}>
          {events.map((event) => {
            const label = historyTypeLabels[event.type] ?? event.type
            const actor = event.actor?.name ?? appointmentCopy.system
            const time = formatShortDateTime(event.occurredAt, timeZone)

            return (
              <Timeline.Item key={event.id}>
                <Text size="sm">
                  {label} · {actor} · {time}
                </Text>
              </Timeline.Item>
            )
          })}
        </Timeline>
      )}
    </Card>
  )
}
