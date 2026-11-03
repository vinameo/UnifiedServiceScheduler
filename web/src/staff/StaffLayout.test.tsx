import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { screen, waitFor } from '@testing-library/react'
import { renderRoute } from '../test/render'
import { apiMock } from '../test/apiMock'

describe('StaffLayout', () => {
  beforeEach(() => {
    apiMock.install()
    apiMock.reset()
  })

  afterEach(() => {
    apiMock.restore()
  })

  it('TC-00-25: advisor sees operations but not monitoring or administration menu', async () => {
    apiMock.get('/me', [
      200,
      {
        id: 10,
        email: 'minhanh@saigonauto.test',
        name: 'Minh Anh',
        role: 'SERVICE_ADVISOR',
        dealership: {
          id: 'dealership-1',
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
      expect(screen.getByText('Minh Anh')).toBeInTheDocument()
      expect(screen.getByText('Service advisor')).toBeInTheDocument()
      expect(screen.getByText(/Saigon Auto · District 7/i)).toBeInTheDocument()
    })

    expect(screen.getByRole('link', { name: 'New booking' })).toBeInTheDocument()
    const appointmentsLink = screen.getByRole('link', { name: 'Appointments' })
    expect(appointmentsLink).toBeInTheDocument()
    expect(appointmentsLink).toHaveAttribute('href', '/staff/appointments')
    expect(screen.getByRole('link', { name: 'Workshop board' })).toBeInTheDocument()

    expect(screen.queryByRole('link', { name: 'Monitoring' })).not.toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Master data' })).not.toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Notifications' })).not.toBeInTheDocument()
  })

  it('TC-00-25: admin sees administration items: Master data, Notifications, Monitoring', async () => {
    apiMock.get('/me', [
      200,
      {
        id: 1,
        email: 'admin@saigonauto.test',
        name: 'Admin User',
        role: 'ADMIN',
        dealership: {
          id: 'dealership-1',
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
      expect(screen.getByText('Admin User')).toBeInTheDocument()
      expect(screen.getByText('Admin')).toBeInTheDocument()
    })

    expect(screen.getByRole('link', { name: 'Master data' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Notifications' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Monitoring' })).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'New booking' })).not.toBeInTheDocument()
  })
})

