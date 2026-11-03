import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { renderRoute } from '../../test/render'
import { apiMock } from '../../test/apiMock'

describe('SignInPage (TC-00-21)', () => {
  beforeEach(() => {
    apiMock.install()
    apiMock.reset()
    apiMock.get('/sanctum/csrf-cookie', [204])
    apiMock.get('/me', apiMock.replyProblem(401, 'UNAUTHENTICATED', 'Please sign in.'))
  })

  afterEach(() => {
    apiMock.restore()
  })

  it('renders customer sign-in elements and pre-fills email from query string', () => {
    renderRoute('/sign-in?email=hung%40mail.com')

    expect(screen.getByRole('heading', { name: 'Sign in' })).toBeInTheDocument()
    expect(screen.getByPlaceholderText('hung@mail.com')).toHaveValue('hung@mail.com')
    expect(screen.getByPlaceholderText(/your password/i)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Sign in' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Create an account' })).toBeInTheDocument()
  })

  it('shows error alert on invalid credentials, clears password and keeps email (TC-00-21)', async () => {
    const user = userEvent.setup()
    apiMock.post(
      '/auth/customer/sign-in',
      apiMock.replyProblem(401, 'INVALID_CREDENTIALS', 'Email or password is incorrect.')
    )

    renderRoute('/sign-in')

    const emailInput = screen.getByPlaceholderText('hung@mail.com')
    const passwordInput = screen.getByPlaceholderText(/your password/i)
    const submitBtn = screen.getByRole('button', { name: 'Sign in' })

    await user.type(emailInput, 'hung@mail.com')
    await user.type(passwordInput, 'wrongpassword')
    await user.click(submitBtn)

    await waitFor(() => {
      const alert = screen.getByRole('alert')
      expect(alert).toHaveTextContent('Email or password is incorrect.')
    })

    expect(emailInput).toHaveValue('hung@mail.com')
    expect(passwordInput).toHaveValue('')
  })
})
