import { AppShell, Avatar, Box, Button, Group, NavLink, Stack, Text, ThemeIcon, Title } from '@mantine/core'
import { NavLink as RouterNavLink, Outlet, useNavigate } from 'react-router'
import { useMe } from '../shared/auth/useMe'
import { useAuthActions } from '../shared/auth/useAuthActions'

const ROLE_LABELS: Record<string, string> = {
  CUSTOMER: 'Customer',
  SERVICE_ADVISOR: 'Service advisor',
  WORKSHOP_FOREMAN: 'Workshop foreman',
  ADMIN: 'Admin',
}

function getInitials(name: string | undefined): string {
  if (!name) return 'ST'
  const parts = name.trim().split(/\s+/)
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase()
  return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
}

export function StaffLayout() {
  const { data: me } = useMe()
  const { signOut } = useAuthActions()
  const navigate = useNavigate()

  const handleSignOut = async () => {
    await signOut()
    navigate('/staff/sign-in')
  }

  const role = me?.role
  const isAdvisor = role === 'SERVICE_ADVISOR'
  const isAdmin = role === 'ADMIN'
  const isForeman = role === 'WORKSHOP_FOREMAN'
  const isStaff = isAdvisor || isForeman || isAdmin

  const dealershipDisplay = me?.dealership
    ? `${me.dealership.name}${me.dealership.branchName ? ` · ${me.dealership.branchName}` : ''}`
    : 'Saigon Auto · District 7'

  return (
    <AppShell header={{ height: 60 }} navbar={{ width: 240, breakpoint: 'sm' }} padding="lg">
      <AppShell.Header>
        <Group h="100%" px="lg" justify="space-between">
          <Group gap="sm">
            <ThemeIcon size={32} radius="md" color="blue.8">
              <Text fw={700} size="sm">
                S
              </Text>
            </ThemeIcon>
            <Title order={4} fw={700}>
              Service Scheduler
            </Title>
          </Group>

          <Group gap="md">
            <Button variant="default" size="sm" radius="sm">
              {dealershipDisplay}
            </Button>

            <Group gap="xs">
              <Avatar radius="xl" size={36} color="blue" bg="blue.0" c="blue.8">
                {getInitials(me?.name)}
              </Avatar>
              <Box style={{ lineHeight: 1.2 }}>
                <Text size="sm" fw={600}>
                  {me?.name ?? 'Staff User'}
                </Text>
                <Text size="xs" c="dimmed">
                  {role ? ROLE_LABELS[role] ?? role : 'Staff'}
                </Text>
              </Box>
            </Group>

            <Button variant="default" size="xs" onClick={handleSignOut}>
              Sign out
            </Button>
          </Group>
        </Group>
      </AppShell.Header>

      <AppShell.Navbar p="sm">
        <Stack gap="xs">
          <Box>
            <Text size="xs" fw={700} c="dimmed" tt="uppercase" px="xs" py={4}>
              Operations
            </Text>
            {isAdvisor && (
              <NavLink component={RouterNavLink} to="/staff" end label="New booking" />
            )}
            {isStaff && (
              <NavLink component={RouterNavLink} to="/staff/appointments" label="Appointments" />
            )}
            {isStaff && (
              <NavLink component={RouterNavLink} to="/staff/workshop" label="Workshop board" />
            )}
          </Box>

          {isAdmin && (
            <Box mt="sm">
              <Text size="xs" fw={700} c="dimmed" tt="uppercase" px="xs" py={4}>
                Administration
              </Text>
              <NavLink component={RouterNavLink} to="/staff/master-data" label="Master data" />
              <NavLink component={RouterNavLink} to="/staff/notifications" label="Notifications" />
              <NavLink component={RouterNavLink} to="/staff/monitoring" label="Monitoring" />
            </Box>
          )}
        </Stack>
      </AppShell.Navbar>

      <AppShell.Main>
        <Outlet />
      </AppShell.Main>
    </AppShell>
  )
}
