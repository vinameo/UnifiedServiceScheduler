import { Anchor, Box, Container, Group, Text } from '@mantine/core'
import { Link, Outlet } from 'react-router'
import { BookingDraftProvider } from './BookingDraftContext'

export function CustomerBookingLayout() {
  return (
    <BookingDraftProvider>
      <Box
        component="header"
        style={{
          borderBottom: '1px solid var(--mantine-color-default-border)',
          backgroundColor: 'var(--mantine-color-body)',
        }}
      >
        <Container size={390} py="xs" px="md">
          <Group justify="space-between" align="center">
            <Anchor
              component={Link}
              to="/book"
              underline="never"
              c="inherit"
            >
              <Text fw={700} size="sm">
                Service Scheduler
              </Text>
            </Anchor>
            <Anchor
              component={Link}
              to="/my-bookings"
              size="sm"
              fw={600}
            >
              My bookings
            </Anchor>
          </Group>
        </Container>
      </Box>
      <Outlet />
    </BookingDraftProvider>
  )
}

