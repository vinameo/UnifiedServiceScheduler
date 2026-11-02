import type { ReactNode } from 'react'
import { Navigate, Outlet, useLocation } from 'react-router'
import { Alert, Button, Center, Loader, Stack, Text, Title } from '@mantine/core'
import { useMe } from './useMe'
import { errorCopy } from '../api/errorCopy'

export type UserRole = 'CUSTOMER' | 'SERVICE_ADVISOR' | 'WORKSHOP_FOREMAN' | 'ADMIN'

interface RequireRoleProps {
  roles: UserRole[]
  signInPath: string
  children?: ReactNode
}

export function RequireRole({ roles, signInPath, children }: RequireRoleProps) {
  const { data: me, isLoading } = useMe()
  const location = useLocation()

  if (isLoading) {
    return (
      <Center mih="50vh" py="xl">
        <Loader role="status" size="lg" color="blue.8" />
      </Center>
    )
  }

  if (!me) {
    const returnTo = encodeURIComponent(location.pathname + location.search)
    return <Navigate to={`${signInPath}?returnTo=${returnTo}`} replace />
  }

  if (!roles.includes(me.role)) {
    return (
      <Center mih="60vh" p="xl">
        <Stack align="center" gap="md" maw={400}>
          <Title order={2} size="h3">
            Forbidden
          </Title>
          <Alert color="red" role="alert" w="100%">
            {errorCopy('FORBIDDEN')}
          </Alert>
          <Text size="sm" c="dimmed" ta="center">
            You do not have permission to view this section.
          </Text>
          <Button component="a" href={signInPath} variant="default" size="sm">
            Sign in with another account
          </Button>
        </Stack>
      </Center>
    )
  }

  return children ? <>{children}</> : <Outlet />
}
