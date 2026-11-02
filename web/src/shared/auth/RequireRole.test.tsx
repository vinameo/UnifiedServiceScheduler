import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { screen, waitFor } from '@testing-library/react'
import { renderRoute } from '../../test/render'
import { apiMock } from '../../test/apiMock'

describe('RequireRole', () => {
  beforeEach(() => {
    apiMock.install()
    apiMock.reset()
  })

  afterEach(() => {
    apiMock.restore()
  })

  it('TC-00-24: redirects unauthenticated users to staff sign-in with returnTo', async () => {
    apiMock.get('/me', apiMock.replyProblem(401, 'UNAUTHENTICATED', 'Please sign in.'))

    renderRoute('/staff/appointments')

    await waitFor(() => {
      expect(screen.getByRole('heading', { name: 'Staff sign-in' })).toBeInTheDocument()
    })
  })

  it('renders children when authenticated with authorized role', async () => {
    apiMock.get('/me', [
      200,
      {
        id: 1,
        email: 'advisor@saigonauto.test',
        name: 'Minh Anh',
        role: 'SERVICE_ADVISOR',
        dealership: {
          id: '0193-dealership',
          code: 'SGA-D7',
          name: 'Saigon Auto',
          branchName: 'District 7',
          timezone: 'Asia/Ho_Chi_Minh',
          phone: '028 3812 3456',
        },
        customer: null,
      },
    ])

    renderRoute('/staff')

    await waitFor(() => {
      expect(screen.getByRole('heading', { name: 'New booking' })).toBeInTheDocument()
    })
  })

  it('shows forbidden message when user has wrong role', async () => {
    apiMock.get('/me', [
      200,
      {
        id: 2,
        email: 'customer@example.test',
        name: 'Customer Hung',
        role: 'CUSTOMER',
        dealership: null,
        customer: { id: 'cust-1', fullName: 'Customer Hung', phone: '0903123218' },
      },
    ])

    renderRoute('/staff')

    await waitFor(() => {
      expect(screen.getByRole('alert')).toHaveTextContent("You don't have permission to view this page.")
    })
  })
})
