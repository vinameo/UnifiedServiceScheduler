import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { renderRoute } from '../../test/render'
import { apiMock } from '../../test/apiMock'

describe('StaffSignInPage', () => {
  beforeEach(() => {
    apiMock.install()
    apiMock.reset()
    apiMock.get('/sanctum/csrf-cookie', [204])
  })

  afterEach(() => {
    apiMock.restore()
  })

  it('renders staff sign-in elements', async () => {
    renderRoute('/staff/sign-in')

    expect(screen.getByRole('heading', { name: 'Staff sign-in' })).toBeInTheDocument()
    expect(screen.getByPlaceholderText(/advisor@saigonauto.test/i)).toBeInTheDocument()
    expect(screen.getByPlaceholderText(/your password/i)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /sign in/i })).toBeInTheDocument()
    expect(screen.getByText(/staff accounts are created by the admin team/i)).toBeInTheDocument()
  })

  it('shows error alert on invalid credentials and clears password', async () => {
    const user = userEvent.setup()
    apiMock.post(
      '/auth/staff/sign-in',
      apiMock.replyProblem(401, 'INVALID_CREDENTIALS', 'Email or password is incorrect.')
    )

    renderRoute('/staff/sign-in')

    const emailInput = screen.getByPlaceholderText(/advisor@saigonauto.test/i)
    const passwordInput = screen.getByPlaceholderText(/your password/i)
    const submitBtn = screen.getByRole('button', { name: /sign in/i })

    await user.type(emailInput, 'wrong@saigonauto.test')
    await user.type(passwordInput, 'badpassword')
    await user.click(submitBtn)

    await waitFor(() => {
      const alert = screen.getByRole('alert')
      expect(alert).toHaveTextContent('Email or password is incorrect.')
    })

    expect(emailInput).toHaveValue('wrong@saigonauto.test')
    expect(passwordInput).toHaveValue('')
  })
})
