import { useState } from 'react'
import { useNavigate, useParams } from 'react-router'
import { Box, Button, Center, Loader, Stack, Text, Title } from '@mantine/core'
import { useAppointment } from '../../shared/api/appointmentQuery'
import { useAppointmentHistory } from '../../shared/api/appointments'
import { isClosed } from '../../shared/appointments/status'
import { useMe } from '../../shared/auth/useMe'
import { AppointmentActions } from './AppointmentActions'
import { AppointmentDetailsLayout } from './AppointmentDetailsLayout'
import { AppointmentHistorySection } from './AppointmentHistorySection'
import { CancelAppointmentModal } from './CancelAppointmentModal'
import { ContactRequiredBanner } from './ContactRequiredBanner'
import { appointmentCopy } from './copy'

export function AppointmentDetailsPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const { data: me } = useMe()
  const { data, isLoading, isError } = useAppointment(id ?? '')
  const { data: historyEvents, isLoading: isHistoryLoading } = useAppointmentHistory(id ?? '')
  const [cancelModalOpened, setCancelModalOpened] = useState(false)

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
  const openReschedule = () => navigate(`/staff/appointments/${data.appointment.id}/reschedule`)
  const contactBanner =
    data.appointment.contactRequiredReason && !isClosed(data.appointment.status) ? (
      <ContactRequiredBanner
        appointment={data.appointment}
        canChange={me?.role === 'SERVICE_ADVISOR'}
        onRescheduleClick={openReschedule}
        onCancelClick={() => setCancelModalOpened(true)}
      />
    ) : undefined

  return (
    <>
      <AppointmentDetailsLayout
        appointment={data.appointment}
        banner={contactBanner}
        actions={
          <AppointmentActions
            appointment={data.appointment}
            etag={data.etag}
            onCancelClick={() => setCancelModalOpened(true)}
            onRescheduleClick={openReschedule}
          />
        }
        history={
          <AppointmentHistorySection
            events={historyEvents}
            isLoading={isHistoryLoading}
            timeZone={timeZone}
          />
        }
      />
      <CancelAppointmentModal
        opened={cancelModalOpened}
        onClose={() => setCancelModalOpened(false)}
        appointment={data.appointment}
        etag={data.etag}
      />
    </>
  )
}
