import { screen } from '@testing-library/react'
import axios from 'axios'
import { renderRoute } from './test/render'

vi.mock('axios', async (importOriginal) => {
  const actual = await importOriginal<typeof import('axios')>()
  return { ...actual, default: { ...actual.default, get: vi.fn() } }
})

describe('routes', () => {
  beforeEach(() => {
    vi.mocked(axios.get).mockResolvedValue({ data: { status: 'ok', checks: { database: 'ok' } } })
  })

  it('shows the customer app at /', async () => {
    renderRoute('/')

    expect(screen.getByRole('heading', { name: 'Book a service' })).toBeInTheDocument()
    expect(await screen.findByText('System online')).toBeInTheDocument()
  })

  it('shows the staff web at /staff', () => {
    renderRoute('/staff')

    expect(screen.getByRole('heading', { name: 'New booking' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Appointments' })).toBeInTheDocument()
  })
})
