import { useNavigate, useParams } from 'react-router'
import { Box, Button, Center, Loader, Stack, Text, Title } from '@mantine/core'
import { useAppointment } from '../../shared/api/appointmentQuery'
import { useAppointmentHistory } from '../../shared/api/appointments'
import { AppointmentDetailsLayout } from './AppointmentDetailsLayout'
import { AppointmentHistorySection } from './AppointmentHistorySection'
import { appointmentCopy } from './copy'

export function AppointmentDetailsPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const { data, isLoading, isError } = useAppointment(id ?? '')
  const { data: historyEvents, isLoading: isHistoryLoading } = useAppointmentHistory(id ?? '')

  if (isLoading) {
    return (
      <Center py="xl" mih={300}>
        <Loader size="md" />
      </Center>
    )
  }

  if (isError || !data?.appointment) {
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

  const timeZone = data.appointment.dealership?.timezone ?? 'Asia/Ho_Chi_Minh'

  return (
    <AppointmentDetailsLayout
      appointment={data.appointment}
      history={
        <AppointmentHistorySection
          events={historyEvents}
          isLoading={isHistoryLoading}
          timeZone={timeZone}
        />
      }
    />
  )
}
