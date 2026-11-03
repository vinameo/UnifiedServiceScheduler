import React from 'react'
import { Alert, Box, Stack, Text } from '@mantine/core'
import { noAvailabilityReasonCopy } from '../api/errorCopy'

export interface BookingProblem {
  status?: number
  code?: string
  title?: string
  detail?: string
  opensAt?: string | null
  closesAt?: string | null
  latestStart?: string | null
  maxDate?: string | null
  reason?: string
  existingAppointment?: {
    id?: string
    reference?: string
    start?: string
    end?: string
  } | null
  [key: string]: unknown
}

export interface BookingErrorCardContext {
  serviceName?: string
  durationMinutes?: number
  start?: string
  end?: string
  time?: string
  bayTypeLabel?: string
  licencePlate?: string
  customerName?: string
}

export interface BookingErrorCardProps {
  error: BookingProblem | null | undefined
  context?: BookingErrorCardContext
  actions?: React.ReactNode
}

export function BookingErrorCard({ error, context = {}, actions }: BookingErrorCardProps) {
  if (!error) {
    return null
  }

  // VEHICLE_NOT_OWNED_BY_CUSTOMER is rendered field-level, not as a card
  if (error.code === 'VEHICLE_NOT_OWNED_BY_CUSTOMER') {
    return null
  }

  let heading = (error.title as string) ?? 'Booking error'
  let body = (error.detail as string) ?? 'Something went wrong. Please try again.'

  if (error.code === 'NO_AVAILABILITY') {
    const copy = noAvailabilityReasonCopy(error.reason, {
      time: context.time ?? context.start,
      start: context.start,
      end: context.end,
      bayTypeLabel: context.bayTypeLabel,
    })
    heading = copy.heading
    body = copy.body
  } else if (error.code === 'OUTSIDE_OPENING_HOURS') {
    heading = 'The service would end after closing time'
    if (error.opensAt === null) {
      body = 'The dealership is closed on this day.'
    } else {
      const serviceName = context.serviceName ?? 'service'
      const duration = context.durationMinutes ?? 60
      const start = context.start ?? ''
      const end = context.end ?? ''
      const closesAt = error.closesAt ? error.closesAt.slice(0, 5) : 'closing'
      const latest = error.latestStart ?? ''
      body = `A ${serviceName} takes ${duration} minutes. Starting at ${start} it would end at ${end}, after the ${closesAt} closing time. The latest start that day is ${latest}.`
    }
  } else if (error.code === 'VEHICLE_ALREADY_BOOKED') {
    const plate = context.licencePlate ? `${context.licencePlate} ` : ''
    const range = context.start ? `at ${context.start} ` : ''
    heading = `Vehicle ${plate}already has a booking ${range}that day`.trim()
    body = 'A vehicle cannot be in two bays at once. You can reschedule the existing booking instead of creating a new one.'
  } else if (error.code === 'START_IN_PAST') {
    heading = 'This time has already passed'
    body = 'This time has already passed. Pick another time.'
  } else if (error.code === 'START_TOO_FAR_AHEAD') {
    heading = 'Date is too far ahead'
    body = `You can book up to 90 days ahead${error.maxDate ? ` (up to ${error.maxDate})` : ''}.`
  }

  return (
    <Alert
      color="red"
      title={heading}
      role="alert"
      radius="sm"
    >
      <Stack gap="xs">
        <Text size="sm">{body}</Text>
        {actions && <Box mt="xs">{actions}</Box>}
      </Stack>
    </Alert>
  )
}

