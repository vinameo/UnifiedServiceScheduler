import { Alert, Button, Group, Stack, Text } from '@mantine/core'
import {
  type AppointmentDiffInput,
  diffAppointment,
} from './diffAppointment'

export interface AppointmentModifiedAlertProps {
  before: AppointmentDiffInput
  latest: AppointmentDiffInput
  etag?: string
  onReview: (latest: AppointmentDiffInput, etag: string) => void
}

export function AppointmentModifiedAlert({
  before,
  latest,
  etag,
  onReview,
}: AppointmentModifiedAlertProps) {
  const diffs = diffAppointment(before, latest)
  const resolvedEtag =
    etag ??
    (latest.version !== undefined ? `"${latest.version}"` : '')

  const handleReview = () => {
    onReview(latest, resolvedEtag)
  }

  return (
    <Alert
      color="yellow"
      title="This appointment was changed by someone else"
      radius="md"
      mt="md"
    >
      <Stack gap="xs">
        <Text size="sm">Review the latest details and try again.</Text>
        {diffs.length > 0 && (
          <Stack gap={4} mt="xs">
            {diffs.map((diff) => (
              <Text key={diff.field} size="sm" fw={500}>
                {diff.text}
              </Text>
            ))}
          </Stack>
        )}
        <Group mt="xs">
          <Button size="sm" color="blue" onClick={handleReview}>
            Review and retry
          </Button>
        </Group>
      </Stack>
    </Alert>
  )
}

