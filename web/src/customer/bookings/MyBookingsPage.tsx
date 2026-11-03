import { useMemo } from 'react'
import {
  Anchor,
  Box,
  Button,
  Card,
  Container,
  Group,
  Loader,
  SegmentedControl,
  Stack,
  Text,
  Title,
} from '@mantine/core'
import { Link, useNavigate, useSearchParams } from 'react-router'
import { useMe } from '../../shared/auth/useMe'
import { useAuthActions } from '../../shared/auth/useAuthActions'
import {
  useMyAppointments,
  type AppointmentScope,
} from '../../shared/api/appointments'
import { AppointmentStatusBadge } from '../../shared/appointments/AppointmentStatusBadge'
import { formatAppointmentSlot } from '../../shared/format/time'
import { customerBookingsCopy } from './copy'

export function MyBookingsPage() {
  const { data: me } = useMe()
  const { signOut } = useAuthActions()
  const navigate = useNavigate()
  const [searchParams, setSearchParams] = useSearchParams()

  const tabParam = searchParams.get('tab')
  const activeTab: AppointmentScope = tabParam === 'past' ? 'past' : 'upcoming'

  const {
    data,
    isLoading,
    fetchNextPage,
    hasNextPage,
    isFetchingNextPage,
  } = useMyAppointments(activeTab)

  const appointments = useMemo(
    () => data?.pages.flatMap((page) => page.data) ?? [],
    [data],
  )

  const handleTabChange = (value: string) => {
    const next = new URLSearchParams(searchParams)
    if (value === 'past') {
      next.set('tab', 'past')
    } else {
      next.delete('tab')
    }
    setSearchParams(next)
  }

  const handleSignOut = async () => {
    await signOut()
    navigate('/sign-in')
  }

  return (
    <Container size={390} py="md" px="md">
      <Stack gap="md">
        {/* Header */}
        <Group justify="space-between" align="flex-start">
          <Stack gap={2}>
            <Title order={2}>{customerBookingsCopy.my.title}</Title>
            {me?.email && (
              <Text size="xs" c="dimmed">
                {customerBookingsCopy.my.signedInAs(me.email)}
              </Text>
            )}
          </Stack>
          <Button variant="subtle" size="xs" onClick={handleSignOut}>
            {customerBookingsCopy.my.signOut}
          </Button>
        </Group>

        {/* Tab switcher */}
        <SegmentedControl
          fullWidth
          value={activeTab}
          onChange={handleTabChange}
          data={[
            { label: customerBookingsCopy.my.upcoming, value: 'upcoming' },
            { label: customerBookingsCopy.my.past, value: 'past' },
          ]}
        />

        {/* Content */}
        {isLoading ? (
          <Box ta="center" py="xl">
            <Loader size="md" />
          </Box>
        ) : appointments.length === 0 ? (
          activeTab === 'upcoming' ? (
            <Stack align="center" gap="md" py="xl">
              <Text c="dimmed" ta="center">
                {customerBookingsCopy.my.emptyUpcoming}
              </Text>
              <Button component={Link} to="/book">
                {customerBookingsCopy.my.newBooking}
              </Button>
            </Stack>
          ) : (
            <Stack align="center" gap="md" py="xl">
              <Text c="dimmed" ta="center">
                {customerBookingsCopy.my.emptyPast}
              </Text>
            </Stack>
          )
        ) : (
          <Stack gap="sm">
            {appointments.map((apt) => {
              const timeZone = apt.dealership?.timezone ?? 'Asia/Ho_Chi_Minh'
              const slotDisplay = formatAppointmentSlot(apt.start, apt.end, timeZone)
              const vehicleDisplay = apt.vehicle
                ? `${apt.vehicle.make} ${apt.vehicle.model}`
                : ''
              const dealershipDisplay = apt.dealership
                ? `${apt.dealership.name}${apt.dealership.branchName ? ` ${apt.dealership.branchName}` : ''}`
                : ''
              const metaLine = [vehicleDisplay, dealershipDisplay].filter(Boolean).join(' · ')

              return (
                <Card key={apt.id} withBorder radius="md" p="md">
                  <Stack gap="xs">
                    <Group justify="space-between" align="center">
                      <Text fw={700} size="md">
                        {slotDisplay}
                      </Text>
                      <AppointmentStatusBadge status={apt.status} />
                    </Group>

                    <Box>
                      <Text fw={500} size="sm">
                        {apt.serviceType.name}
                      </Text>
                      {metaLine && (
                        <Text size="xs" c="dimmed">
                          {metaLine}
                        </Text>
                      )}
                    </Box>

                    <Group justify="space-between" align="center" mt={4}>
                      <Text
                        size="xs"
                        c="dimmed"
                        style={{ fontVariantNumeric: 'tabular-nums' }}
                      >
                        {apt.reference}
                      </Text>
                      <Anchor
                        component={Link}
                        to={`/my-bookings/${apt.id}`}
                        size="sm"
                        fw={600}
                      >
                        {customerBookingsCopy.my.viewDetails}
                      </Anchor>
                    </Group>
                  </Stack>
                </Card>
              )
            })}

            {hasNextPage && (
              <Button
                variant="default"
                size="sm"
                fullWidth
                onClick={() => fetchNextPage()}
                loading={isFetchingNextPage}
                mt="xs"
              >
                {customerBookingsCopy.my.loadMore}
              </Button>
            )}

            {activeTab === 'upcoming' && (
              <Button
                component={Link}
                to="/book"
                variant="default"
                size="md"
                fullWidth
                mt="sm"
              >
                {customerBookingsCopy.my.newBooking}
              </Button>
            )}
          </Stack>
        )}
      </Stack>
    </Container>
  )
}
