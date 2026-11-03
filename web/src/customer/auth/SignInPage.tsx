import { useState } from 'react'
import { Link, Navigate, useNavigate, useSearchParams } from 'react-router'
import { useQueryClient } from '@tanstack/react-query'
import {
  Alert,
  Anchor,
  Box,
  Button,
  Card,
  Center,
  Group,
  PasswordInput,
  Stack,
  Text,
  TextInput,
  ThemeIcon,
  Title,
} from '@mantine/core'
import { api, ensureCsrfCookie } from '../../shared/api/client'
import { toProblem } from '../../shared/api/problem'
import { errorCopy } from '../../shared/api/errorCopy'
import { safeReturnTo } from '../../shared/auth/returnTo'
import { useMe, type Me } from '../../shared/auth/useMe'

export function SignInPage() {
  const { data: me } = useMe()
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const queryClient = useQueryClient()

  const [email, setEmail] = useState(() => searchParams.get('email') ?? '')
  const [password, setPassword] = useState('')
  const [loading, setLoading] = useState(false)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)

  if (me?.role === 'CUSTOMER') {
    return <Navigate to="/book" replace />
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setErrorMessage(null)
    setLoading(true)

    try {
      await ensureCsrfCookie()
      const res = await api.post<Me>('/auth/customer/sign-in', { email, password })

      queryClient.setQueryData(['me'], res.data)
      const target = safeReturnTo(searchParams.get('returnTo'), '/book')
      navigate(target)
    } catch (err) {
      const problem = toProblem(err)
      setPassword('')
      setErrorMessage(errorCopy(problem?.code))
    } finally {
      setLoading(false)
    }
  }

  return (
    <Box bg="gray.0" mih="100vh" py="xl">
      <Center mih="100vh">
        <Card withBorder shadow="sm" radius="md" p="xl" w={{ base: '100%', xs: 390 }} mx="md" bg="white">
          <Stack gap="lg">
            <Group gap="sm">
              <ThemeIcon size={32} radius="md" color="blue.8">
                <Text fw={700} size="sm">
                  S
                </Text>
              </ThemeIcon>
              <Text fw={700} size="md">
                Service Scheduler
              </Text>
            </Group>

            <div>
              <Title order={2} size="h3" fw={700}>
                Sign in
              </Title>
              <Text c="dimmed" size="sm" mt={4}>
                Book and manage your car service appointments.
              </Text>
            </div>

            {errorMessage && (
              <Alert color="red" role="alert">
                {errorMessage}
              </Alert>
            )}

            <form onSubmit={handleSubmit}>
              <Stack gap="md">
                <TextInput
                  label="Email"
                  placeholder="hung@mail.com"
                  value={email}
                  onChange={(e) => setEmail(e.currentTarget.value)}
                  required
                  autoComplete="email"
                />

                <PasswordInput
                  label="Password"
                  placeholder="Your password"
                  value={password}
                  onChange={(e) => setPassword(e.currentTarget.value)}
                  required
                  autoComplete="current-password"
                />

                <Button type="submit" fullWidth color="blue.8" loading={loading} disabled={loading} mt="xs">
                  Sign in
                </Button>
              </Stack>
            </form>

            <Text size="sm" ta="center">
              New here?{' '}
              <Anchor component={Link} to="/sign-up" fw={600} c="blue.8">
                Create an account
              </Anchor>
            </Text>
          </Stack>
        </Card>
      </Center>
    </Box>
  )
}
