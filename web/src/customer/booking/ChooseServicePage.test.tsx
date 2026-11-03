import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { renderRoute } from '../../test/render'
import { apiMock } from '../../test/apiMock'

describe('ChooseServicePage (TC-02-34)', () => {
  const d1Id = '0192d111-0000-7000-8000-000000000001'
  const d2Id = '0192d111-0000-7000-8000-000000000002'

  beforeEach(() => {
    sessionStorage.clear()
    apiMock.install()
    apiMock.reset()

    apiMock.get('/me', [
      200,
      {
        id: 10,
        email: 'hung@example.test',
        name: 'Nguyễn Văn Hùng',
        role: 'CUSTOMER',
        dealership_id: null,
        customer: {
          id: '0192d222-0000-7000-8000-000000000002',
          fullName: 'Nguyễn Văn Hùng',
          phone: '0903123218',
        },
      },
    ])
  })

  afterEach(() => {
    sessionStorage.clear()
    apiMock.restore()
  })

  it('TC-02-34: shows Dealership selector when multiple dealerships exist', async () => {
    apiMock.get('/dealerships', [
      200,
      {
        data: [
          {
            id: d1Id,
            code: 'SGA-D7',
            name: 'Saigon Auto',
            branchName: 'District 7',
            timezone: 'Asia/Ho_Chi_Minh',
            phone: '028 3812 3456',
            slotStepMinutes: 15,
          },
          {
            id: d2Id,
            code: 'SGA-BTH',
            name: 'Saigon Auto',
            branchName: 'Bình Thạnh',
            timezone: 'Asia/Ho_Chi_Minh',
            phone: '028 3899 9999',
            slotStepMinutes: 15,
          },
        ],
      },
    ])

    apiMock.get(`/dealerships/${d1Id}/service-types`, [
      200,
      {
        data: [
          {
            id: 'svc-1',
            code: 'OIL_CHANGE',
            name: 'Oil change',
            description: 'Quick engine oil change',
            durationMinutes: 60,
            requiredLevel: 1,
            requiredBayType: 'QUICK_SERVICE',
            active: true,
          },
        ],
      },
    ])

    renderRoute('/book')

    // Assert Dealership select is present
    await waitFor(() => {
      expect(screen.getByText('Dealership')).toBeInTheDocument()
    })
    expect(screen.getByPlaceholderText('Choose a dealership')).toBeInTheDocument()
  })

  it('hides Dealership selector when only one dealership exists', async () => {
    apiMock.get('/dealerships', [
      200,
      {
        data: [
          {
            id: d1Id,
            code: 'SGA-D7',
            name: 'Saigon Auto',
            branchName: 'District 7',
            timezone: 'Asia/Ho_Chi_Minh',
            phone: '028 3812 3456',
            slotStepMinutes: 15,
          },
        ],
      },
    ])

    apiMock.get(`/dealerships/${d1Id}/service-types`, [
      200,
      {
        data: [
          {
            id: 'svc-1',
            code: 'OIL_CHANGE',
            name: 'Oil change',
            description: 'Quick engine oil change',
            durationMinutes: 60,
            requiredLevel: 1,
            requiredBayType: 'QUICK_SERVICE',
            active: true,
          },
        ],
      },
    ])

    const user = userEvent.setup()
    renderRoute('/book')

    await waitFor(() => {
      expect(screen.getByText('Step 1 of 3 · Choose a service')).toBeInTheDocument()
    })

    expect(screen.queryByText('Dealership')).toBeNull()

    const continueBtn = screen.getByRole('button', { name: 'Choose date and time' })
    expect(continueBtn).toBeDisabled()

    // Select service
    const serviceCard = await screen.findByText('Oil change')
    await user.click(serviceCard)

    expect(continueBtn).not.toBeDisabled()
  })
})
