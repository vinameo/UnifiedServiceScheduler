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
import {
  validateEmail,
  validateFullName,
  validatePassword,
  validatePasswordConfirmation,
  validatePhone,
} from '../../shared/auth/rules'

export function SignUpPage() {
  const { data: me } = useMe()
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const queryClient = useQueryClient()

  const [fullName, setFullName] = useState('')
  const [email, setEmail] = useState('')
  const [phone, setPhone] = useState('')
  const [password, setPassword] = useState('')
  const [passwordConfirmation, setPasswordConfirmation] = useState('')

  const [loading, setLoading] = useState(false)
  const [alertError, setAlertError] = useState<{ message: string; showSignInLink?: boolean } | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})

  // If already customer, redirect to /book
  if (me?.role === 'CUSTOMER') {
    return <Navigate to="/book" replace />
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setAlertError(null)
    setFieldErrors({})

    // Client-side validation
    const errors: Record<string, string> = {}

    const nameCheck = validateFullName(fullName)
    if (!nameCheck.valid && nameCheck.error) {
      errors.fullName = nameCheck.error
    }

    const emailCheck = validateEmail(email)
    if (!emailCheck.valid && emailCheck.error) {
      errors.email = emailCheck.error
    }

    const phoneCheck = validatePhone(phone)
    if (!phoneCheck.valid && phoneCheck.error) {
      errors.phone = phoneCheck.error
    }

    const passwordCheck = validatePassword(password)
    if (!passwordCheck.valid && passwordCheck.error) {
      errors.password = passwordCheck.error
    }

    const confirmCheck = validatePasswordConfirmation(password, passwordConfirmation)
    if (!confirmCheck.valid && confirmCheck.error) {
      errors.passwordConfirmation = confirmCheck.error
    }

    if (Object.keys(errors).length > 0) {
      setFieldErrors(errors)
      return
    }

    setLoading(true)

    try {
      await ensureCsrfCookie()
      const res = await api.post<Me>('/auth/customer/sign-up', {
        fullName,
        email,
        phone,
        password,
        passwordConfirmation,
      })

      queryClient.setQueryData(['me'], res.data)
      const target = safeReturnTo(searchParams.get('returnTo'), '/book')
      navigate(target)
    } catch (err) {
      const problem = toProblem(err)

      if (problem?.code === 'EMAIL_ALREADY_USED') {
        setAlertError({
          message: errorCopy('EMAIL_ALREADY_USED'),
          showSignInLink: true,
        })
      } else if (problem?.code === 'WEAK_PASSWORD') {
        setFieldErrors({
          password: errorCopy('WEAK_PASSWORD'),
        })
      } else if (problem?.code === 'VALIDATION_ERROR' && problem.errors) {
        const mappedErrors: Record<string, string> = {}
        for (const [key, msgs] of Object.entries(problem.errors)) {
          if (Array.isArray(msgs) && msgs.length > 0) {
            mappedErrors[key] = msgs[0]
          }
        }
        setFieldErrors(mappedErrors)
      } else {
        setAlertError({
          message: errorCopy(problem?.code),
        })
      }
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
                Create your account
              </Title>
              <Text c="dimmed" size="sm" mt={4}>
                Book and manage your car service appointments.
              </Text>
            </div>

            {alertError && (
              <Alert color={alertError.showSignInLink ? 'blue' : 'red'} role="alert">
                <Text size="sm">{alertError.message}</Text>
                {alertError.showSignInLink && (
                  <Anchor
                    component={Link}
                    to={`/sign-in?email=${encodeURIComponent(email)}`}
                    size="sm"
                    fw={600}
                    mt={4}
                    display="inline-block"
                  >
                    Sign in
                  </Anchor>
                )}
              </Alert>
            )}

            <form onSubmit={handleSubmit} noValidate>
              <Stack gap="md">
                <TextInput
                  label="Full name"
                  placeholder="Nguyễn Văn Hùng"
                  value={fullName}
                  onChange={(e) => setFullName(e.currentTarget.value)}
                  error={fieldErrors.fullName}
                  required
                  autoComplete="name"
                />

                <TextInput
                  label="Email"
                  placeholder="hung@mail.com"
                  value={email}
                  onChange={(e) => setEmail(e.currentTarget.value)}
                  error={fieldErrors.email}
                  required
                  autoComplete="email"
                />

                <TextInput
                  label="Phone number"
                  placeholder="0903 123 218"
                  value={phone}
                  onChange={(e) => setPhone(e.currentTarget.value)}
                  error={fieldErrors.phone}
                  required
                  autoComplete="tel"
                />

                <PasswordInput
                  label="Password"
                  placeholder="At least 8 characters, with a letter and a number."
                  value={password}
                  onChange={(e) => setPassword(e.currentTarget.value)}
                  error={fieldErrors.password}
                  required
                  autoComplete="new-password"
                />

                <PasswordInput
                  label="Confirm password"
                  placeholder="Confirm your password"
                  value={passwordConfirmation}
                  onChange={(e) => setPasswordConfirmation(e.currentTarget.value)}
                  error={fieldErrors.passwordConfirmation}
                  required
                  autoComplete="new-password"
                />

                <Button type="submit" fullWidth color="blue.8" loading={loading} disabled={loading} mt="xs">
                  Create account
                </Button>
              </Stack>
            </form>

            <Text size="sm" ta="center">
              Already have an account?{' '}
              <Anchor component={Link} to="/sign-in" fw={600} c="blue.8">
                Sign in
              </Anchor>
            </Text>
          </Stack>
        </Card>
      </Center>
    </Box>
  )
}
