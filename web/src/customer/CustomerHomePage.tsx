import { Button, Container, Stack, Text, Title } from '@mantine/core'
import { Link } from 'react-router'
import { HealthBadge } from '../shared/HealthBadge'

// Customer app: mobile-first, designed for 390 px (docs/designs/Customer).
export function CustomerHomePage() {
  return (
    <Container size={390} py="xl">
      <Stack gap="md">
        <HealthBadge />
        <Title order={2}>Book a service</Title>
        <Text c="dimmed">Choose a service, pick a time and we hold it for you while you confirm.</Text>
        <Button size="md" fullWidth disabled>
          Start booking
        </Button>
        <Button component={Link} to="/staff" variant="subtle">
          Staff sign-in
        </Button>
      </Stack>
    </Container>
  )
}
