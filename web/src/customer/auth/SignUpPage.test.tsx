import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { renderRoute } from '../../test/render'
import { apiMock } from '../../test/apiMock'

describe('SignUpPage (TC-00-22)', () => {
  beforeEach(() => {
    apiMock.install()
    apiMock.reset()
    apiMock.get('/sanctum/csrf-cookie', [204])
    apiMock.get('/me', apiMock.replyProblem(401, 'UNAUTHENTICATED', 'Please sign in.'))
  })

  afterEach(() => {
    apiMock.restore()
  })

  it('renders sign-up page elements', () => {
    renderRoute('/sign-up')

    expect(screen.getByRole('heading', { name: 'Create your account' })).toBeInTheDocument()
    expect(screen.getByPlaceholderText('Nguyễn Văn Hùng')).toBeInTheDocument()
    expect(screen.getByPlaceholderText('hung@mail.com')).toBeInTheDocument()
    expect(screen.getByPlaceholderText('0903 123 218')).toBeInTheDocument()
    expect(
      screen.getByPlaceholderText('At least 8 characters, with a letter and a number.')
    ).toBeInTheDocument()
    expect(screen.getByPlaceholderText('Confirm your password')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Create account' })).toBeInTheDocument()
    expect(screen.getByText('Already have an account?')).toBeInTheDocument()
  })

  it('handles 400 (errors.phone), 422 WEAK_PASSWORD, and 409 in turn (TC-00-22)', async () => {
    const user = userEvent.setup()

    // 1. First attempt: API returns 400 with errors.phone
    apiMock.post(
      '/auth/customer/sign-up',
      apiMock.replyProblem(400, 'VALIDATION_ERROR', 'The request is not valid.', {
        errors: { phone: ['Enter a valid phone number.'] },
      })
    )

    renderRoute('/sign-up')

    const nameInput = screen.getByPlaceholderText('Nguyễn Văn Hùng')
    const emailInput = screen.getByPlaceholderText('hung@mail.com')
    const phoneInput = screen.getByPlaceholderText('0903 123 218')
    const passwordInput = screen.getByPlaceholderText(
      'At least 8 characters, with a letter and a number.'
    )
    const confirmInput = screen.getByPlaceholderText('Confirm your password')
    const submitBtn = screen.getByRole('button', { name: 'Create account' })

    await user.type(nameInput, 'Nguyễn Văn Hùng')
    await user.type(emailInput, 'hung@mail.com')
    await user.type(phoneInput, '0903123218')
    await user.type(passwordInput, 'validPass123')
    await user.type(confirmInput, 'validPass123')
    await user.click(submitBtn)

    await waitFor(() => {
      expect(screen.getByText('Enter a valid phone number.')).toBeInTheDocument()
    })

    // 2. Second attempt: API returns 422 WEAK_PASSWORD
    apiMock.reset()
    apiMock.get('/sanctum/csrf-cookie', [204])
    apiMock.get('/me', apiMock.replyProblem(401, 'UNAUTHENTICATED', 'Please sign in.'))
    apiMock.post(
      '/auth/customer/sign-up',
      apiMock.replyProblem(
        422,
        'WEAK_PASSWORD',
        'Password must be at least 8 characters, with a letter and a number.'
      )
    )

    await user.click(submitBtn)

    await waitFor(() => {
      expect(
        screen.getByText('Password must be at least 8 characters, with a letter and a number.')
      ).toBeInTheDocument()
    })

    // 3. Third attempt: API returns 409 EMAIL_ALREADY_USED
    apiMock.reset()
    apiMock.get('/sanctum/csrf-cookie', [204])
    apiMock.get('/me', apiMock.replyProblem(401, 'UNAUTHENTICATED', 'Please sign in.'))
    apiMock.post(
      '/auth/customer/sign-up',
      apiMock.replyProblem(
        409,
        'EMAIL_ALREADY_USED',
        'An account with this email already exists. Sign in instead.'
      )
    )

    await user.click(submitBtn)

    await waitFor(() => {
      const alert = screen.getByRole('alert')
      expect(alert).toHaveTextContent(
        'An account with this email already exists. Sign in instead.'
      )
      const signInLink = within(alert).getByRole('link', { name: 'Sign in' })
      expect(signInLink).toHaveAttribute(
        'href',
        `/sign-in?email=${encodeURIComponent('hung@mail.com')}`
      )
    })
  })
})
