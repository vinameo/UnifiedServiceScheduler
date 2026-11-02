import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { screen } from '@testing-library/react'
import axios from 'axios'
import { renderRoute } from './test/render'
import { apiMock } from './test/apiMock'

vi.mock('axios', async (importOriginal) => {
  const actual = await importOriginal<typeof import('axios')>()
  return {
    ...actual,
    default: {
      ...actual.default,
      get: vi.fn(),
    },
  }
})

describe('routes', () => {
  beforeEach(() => {
    vi.mocked(axios.get).mockResolvedValue({
      data: { status: 'ok', checks: { database: 'ok' } },
    })

    apiMock.install()
    apiMock.reset()
    apiMock.get('/me', [
      200,
      {
        id: 1,
        email: 'advisor@saigonauto.test',
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
  })

  afterEach(() => {
    apiMock.restore()
  })

  it('shows the customer app at /', async () => {
    renderRoute('/')

    expect(screen.getByRole('heading', { name: 'Book a service' })).toBeInTheDocument()
    expect(await screen.findByText('System online')).toBeInTheDocument()
  })

  it('shows the staff web at /staff', async () => {
    renderRoute('/staff')

    expect(await screen.findByRole('heading', { name: 'New booking' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Appointments' })).toBeInTheDocument()
  })
})
