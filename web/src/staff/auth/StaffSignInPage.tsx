import { useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router'
import { useQueryClient } from '@tanstack/react-query'
import {
  Alert,
  Box,
  Button,
  Card,
  Center,
  PasswordInput,
  Stack,
  Text,
  TextInput,
  Title,
  ThemeIcon,
  Group,
} from '@mantine/core'
import { api, ensureCsrfCookie } from '../../shared/api/client'
import { toProblem } from '../../shared/api/problem'
import { errorCopy } from '../../shared/api/errorCopy'
import { safeReturnTo } from '../../shared/auth/returnTo'
import { homePathFor } from '../../shared/auth/homePath'
import type { Me } from '../../shared/auth/useMe'

export function StaffSignInPage() {
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [loading, setLoading] = useState(false)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)

  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const queryClient = useQueryClient()

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setErrorMessage(null)
    setLoading(true)

    try {
      await ensureCsrfCookie()
      const res = await api.post<Me>('/auth/staff/sign-in', { email, password })

      queryClient.setQueryData(['me'], res.data)
      const returnTo = safeReturnTo(searchParams.get('returnTo'), homePathFor(res.data.role))
      navigate(returnTo)
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
        <Card withBorder shadow="sm" radius="md" p="xl" w={{ base: '100%', xs: 400 }} mx="md" bg="white">
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
                Staff sign-in
              </Title>
              <Text c="dimmed" size="sm" mt={4}>
                For dealership staff.
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
                  label="Work email"
                  placeholder="advisor@saigonauto.test"
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

                <Button type="submit" fullWidth color="blue.8" loading={loading} mt="xs">
                  Sign in
                </Button>
              </Stack>
            </form>

            <Text size="xs" c="dimmed" ta="center">
              Staff accounts are created by the admin team.
            </Text>
          </Stack>
        </Card>
      </Center>
    </Box>
  )
}

