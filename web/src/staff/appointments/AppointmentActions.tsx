import { useMemo } from 'react'
import { Link, useNavigate } from 'react-router'
import { Box, Button, Group, Menu, Tooltip } from '@mantine/core'
import type { Appointment } from '../../shared/api/appointments'
import { useAppointmentAction, useExtend } from '../../shared/api/workshop'
import { useMe } from '../../shared/auth/useMe'
import { formatInZone } from '../../shared/format/time'
import { useIdempotencyKey } from '../../shared/idempotency/useIdempotencyKey'
import { appointmentCopy } from './copy'

export interface AppointmentActionsProps {
  appointment: Appointment
  etag: string
  role?: string
  onCancelClick?: () => void
  onRescheduleClick?: () => void
}

export function AppointmentActions({
  appointment,
  etag,
  role: roleProp,
  onCancelClick,
  onRescheduleClick,
}: AppointmentActionsProps) {
  const navigate = useNavigate()
  const { data: me } = useMe()

  const role = roleProp ?? me?.role
  const isAdvisor = role === 'SERVICE_ADVISOR'
  const isForeman = role === 'WORKSHOP_FOREMAN'
  const isAdmin = role === 'ADMIN'

  const timeZone = appointment.dealership?.timezone ?? 'Asia/Ho_Chi_Minh'
  const isBeforeStart = new Date() < new Date(appointment.start)
  const formattedStartTime = formatInZone(appointment.start, timeZone, 'time')

  const checkInInput = useMemo(
    () => ({ id: appointment.id, action: 'check-in', etag }),
    [appointment.id, etag],
  )
  const { key: checkInKey, settle: settleCheckIn } = useIdempotencyKey(checkInInput)

  const startInput = useMemo(
    () => ({ id: appointment.id, action: 'start', etag }),
    [appointment.id, etag],
  )
  const { key: startKey, settle: settleStart } = useIdempotencyKey(startInput)

  const completeInput = useMemo(
    () => ({ id: appointment.id, action: 'complete', etag }),
    [appointment.id, etag],
  )
  const { key: completeKey, settle: settleComplete } = useIdempotencyKey(completeInput)

  const noShowInput = useMemo(
    () => ({ id: appointment.id, action: 'no-show', etag }),
    [appointment.id, etag],
  )
  const { key: noShowKey, settle: settleNoShow } = useIdempotencyKey(noShowInput)

  const extendInput = useMemo(
    () => ({ id: appointment.id, action: 'extend', etag }),
    [appointment.id, etag],
  )
  const { key: extendKey, settle: settleExtend } = useIdempotencyKey(extendInput)

  const checkInMutation = useAppointmentAction(appointment.id, 'check-in')
  const startMutation = useAppointmentAction(appointment.id, 'start')
  const completeMutation = useAppointmentAction(appointment.id, 'complete')
  const noShowMutation = useAppointmentAction(appointment.id, 'no-show')
  const extendMutation = useExtend(appointment.id)

  const renderNoShowButton = () => {
    if (isBeforeStart) {
      return (
        <Tooltip label={appointmentCopy.actions.noShowLater(formattedStartTime)}>
          <Box component="span">
            <Button variant="default" disabled style={{ pointerEvents: 'none' }}>
              {appointmentCopy.actions.noShow}
            </Button>
          </Box>
        </Tooltip>
      )
    }

    return (
      <Button
        variant="default"
        loading={noShowMutation.isPending}
        onClick={() =>
          noShowMutation.mutate(
            { etag, idempotencyKey: noShowKey },
            { onSettled: () => settleNoShow() },
          )
        }
      >
        {appointmentCopy.actions.noShow}
      </Button>
    )
  }

  const renderExtendMenu = () => (
    <Menu shadow="md" width={140}>
      <Menu.Target>
        <Button variant="outline" loading={extendMutation.isPending}>
          {appointmentCopy.actions.extend}
        </Button>
      </Menu.Target>
      <Menu.Dropdown>
        {[15, 30, 45, 60, 90, 120].map((minutes) => (
          <Menu.Item
            key={minutes}
            onClick={() =>
              extendMutation.mutate(
                { minutes, etag, idempotencyKey: extendKey },
                { onSettled: () => settleExtend() },
              )
            }
          >
            {appointmentCopy.actions.extendBy(minutes)}
          </Menu.Item>
        ))}
      </Menu.Dropdown>
    </Menu>
  )

  const actionButtons: React.ReactNode[] = []

  if (appointment.status === 'CONFIRMED') {
    if (isAdvisor) {
      actionButtons.push(
        <Button
          key="check-in"
          color="blue"
          loading={checkInMutation.isPending}
          onClick={() =>
            checkInMutation.mutate(
              { etag, idempotencyKey: checkInKey },
              { onSettled: () => settleCheckIn() },
            )
          }
        >
          {appointmentCopy.actions.checkIn}
        </Button>,
        <Button
          key="reschedule"
          onClick={() => {
            if (onRescheduleClick) {
              onRescheduleClick()
            } else {
              navigate(`/staff/appointments/${appointment.id}/reschedule`)
            }
          }}
        >
          {appointmentCopy.reschedule.button}
        </Button>,
        <Button
          key="cancel"
          variant="outline"
          color="red"
          onClick={onCancelClick}
        >
          {appointmentCopy.cancel.button}
        </Button>,
        <Box key="no-show">{renderNoShowButton()}</Box>,
      )
    } else if (isForeman) {
      actionButtons.push(
        <Button
          key="check-in"
          color="blue"
          loading={checkInMutation.isPending}
          onClick={() =>
            checkInMutation.mutate(
              { etag, idempotencyKey: checkInKey },
              { onSettled: () => settleCheckIn() },
            )
          }
        >
          {appointmentCopy.actions.checkIn}
        </Button>,
        <Box key="no-show">{renderNoShowButton()}</Box>,
      )
    }
  } else if (appointment.status === 'CHECKED_IN') {
    if (isForeman) {
      actionButtons.push(
        <Button
          key="start"
          color="blue"
          loading={startMutation.isPending}
          onClick={() =>
            startMutation.mutate(
              { etag, idempotencyKey: startKey },
              { onSettled: () => settleStart() },
            )
          }
        >
          {appointmentCopy.actions.start}
        </Button>,
        <Box key="extend">{renderExtendMenu()}</Box>,
      )
    }
  } else if (appointment.status === 'IN_PROGRESS') {
    if (isForeman) {
      actionButtons.push(
        <Button
          key="complete"
          color="green"
          loading={completeMutation.isPending}
          onClick={() =>
            completeMutation.mutate(
              { etag, idempotencyKey: completeKey },
              { onSettled: () => settleComplete() },
            )
          }
        >
          {appointmentCopy.actions.complete}
        </Button>,
        <Box key="extend">{renderExtendMenu()}</Box>,
      )
    }
  } else if (appointment.status === 'HELD' && isAdvisor) {
    actionButtons.push(
      <Button
        key="cancel"
        variant="outline"
        color="red"
        onClick={onCancelClick}
      >
        {appointmentCopy.cancel.button}
      </Button>,
    )
  }

  if (isAdmin) {
    actionButtons.push(
      <Button
        key="view-log"
        component={Link}
        to={`/staff/notifications?appointmentId=${appointment.id}`}
        variant="default"
      >
        {appointmentCopy.viewLog}
      </Button>,
    )
  }

  if (actionButtons.length === 0) {
    return null
  }

  return <Group>{actionButtons}</Group>
}
