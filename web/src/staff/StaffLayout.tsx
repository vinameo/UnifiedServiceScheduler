import { AppShell, Group, NavLink, Title } from '@mantine/core'
import { NavLink as RouterNavLink, Outlet } from 'react-router'
import { HealthBadge } from '../shared/HealthBadge'

const links = [
  { to: '/staff', label: 'New booking', end: true },
  { to: '/staff/appointments', label: 'Appointments' },
  { to: '/staff/workshop', label: 'Workshop board' },
  { to: '/staff/master-data', label: 'Master data' },
]

// Staff web: desktop, designed for 1440 px (docs/designs/Admin).
export function StaffLayout() {
  return (
    <AppShell header={{ height: 60 }} navbar={{ width: 240, breakpoint: 'sm' }} padding="lg">
      <AppShell.Header>
        <Group h="100%" px="lg" justify="space-between">
          <Title order={4}>Service Scheduler</Title>
          <HealthBadge />
        </Group>
      </AppShell.Header>
      <AppShell.Navbar p="sm">
        {links.map((link) => (
          <NavLink key={link.to} component={RouterNavLink} to={link.to} end={link.end} label={link.label} />
        ))}
      </AppShell.Navbar>
      <AppShell.Main>
        <Outlet />
      </AppShell.Main>
    </AppShell>
  )
}
