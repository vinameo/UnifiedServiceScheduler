import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { MantineProvider } from '@mantine/core'
import { SlotGrid, type SlotGridProps } from './SlotGrid'
import { renderRoute } from '../../test/render'
import { apiMock } from '../../test/apiMock'

function renderSlotGrid(props: Partial<SlotGridProps> & { onChange?: (iso: string) => void }) {
  const defaultSlots = [
    { start: '2026-11-02T08:00:00+07:00', available: true, reason: null },
    { start: '2026-11-02T08:15:00+07:00', available: false, reason: 'NO_TECHNICIAN_AVAILABLE' },
    { start: '2026-11-02T08:30:00+07:00', available: true, reason: null },
  ]
  const onChange = props.onChange ?? vi.fn()

  return {
    onChange,
    ...render(
      <MantineProvider>
        <SlotGrid
          slots={props.slots ?? defaultSlots}
          value={props.value}
          onChange={onChange}
          freeCount={props.freeCount}
          closed={props.closed}
          variant={props.variant ?? 'staff'}
          timeZone={props.timeZone ?? 'Asia/Ho_Chi_Minh'}
          disabled={props.disabled}
          loading={props.loading}
          error={props.error}
          onRetry={props.onRetry}
        />
      </MantineProvider>,
    ),
  }
}

describe('SlotGrid (TC-06-14)', () => {
  it('TC-06-14 (component): struck-through taken times, aria-disabled, not selectable, free start times count shown', async () => {
    const user = userEvent.setup()
    const { onChange } = renderSlotGrid({})

    // "2 free start times" shown
    expect(screen.getByText('2 free start times')).toBeInTheDocument()
    expect(
      screen.getByText('Struck-through slots have no free bay or technician.'),
    ).toBeInTheDocument()

    const freeBtn = screen.getByRole('button', { name: '08:00' })
    const takenBtn = screen.getByRole('button', { name: '08:15' })
    const freeBtn2 = screen.getByRole('button', { name: '08:30' })

    // Available slot is interactive
    expect(freeBtn).not.toBeDisabled()
    expect(freeBtn).toHaveAttribute('aria-pressed', 'false')

    // Middle slot is struck-through, aria-disabled, not clickable
    expect(takenBtn).toBeDisabled()
    expect(takenBtn).toHaveAttribute('aria-disabled', 'true')
    expect(takenBtn).toHaveStyle({ textDecoration: 'line-through' })
    expect(takenBtn).toHaveAttribute('title', 'No qualified technician free')

    // Click middle slot -> not selectable
    await user.click(takenBtn)
    expect(onChange).not.toHaveBeenCalled()

    // Click free slot -> selectable
    await user.click(freeBtn2)
    expect(onChange).toHaveBeenCalledWith('2026-11-02T08:30:00+07:00')
  })

  it('renders closed message when dealership is closed', () => {
    renderSlotGrid({ closed: true, slots: [] })
    expect(screen.getByText('The dealership is closed on this day.')).toBeInTheDocument()
  })

  it('customer variant displays group free slot counts', () => {
    renderSlotGrid({
      variant: 'customer',
      slots: [
        { start: '2026-11-02T08:00:00+07:00', available: true, reason: null },
        { start: '2026-11-02T08:15:00+07:00', available: false, reason: 'NO_BAY_AVAILABLE' },
        { start: '2026-11-02T13:00:00+07:00', available: true, reason: null },
      ],
    })

    expect(screen.getByText(/Morning\s+1 free slots/)).toBeInTheDocument()
    expect(screen.getByText(/Afternoon\s+1 free slots/)).toBeInTheDocument()
  })
})

describe('TC-06-14 on New booking page', () => {
  const dealershipId = '0192d111-0000-7000-8000-000000000001'
  const customerId = '0192d222-0000-7000-8000-000000000002'
  const vehicleId = '0192d333-0000-7000-8000-000000000003'
  const serviceTypeId = '0192d444-0000-7000-8000-000000000004'

  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date('2026-11-02T08:00:00Z'))
    sessionStorage.clear()
    apiMock.install()
    apiMock.reset()
  })

  afterEach(() => {
    vi.useRealTimers()
    sessionStorage.clear()
    apiMock.restore()
  })

  it('TC-06-14 (integration): given Fake API: 3 slots, middle false; when Render New booking, click middle slot; then middle slot struck through, aria-disabled, not selectable; "2 free start times" shown', async () => {
    apiMock.get('/me', [
      200,
      {
        id: 2,
        email: 'advisor@saigonauto.test',
        name: 'Trần Thị Cố Vấn',
        role: 'SERVICE_ADVISOR',
        dealership_id: dealershipId,
        dealership: {
          id: dealershipId,
          code: 'SGA-D7',
          name: 'Saigon Auto',
          branchName: 'District 7',
          timezone: 'Asia/Ho_Chi_Minh',
          phone: '028 3812 3456',
        },
      },
    ])

    apiMock.get(`/dealerships/${dealershipId}`, [
      200,
      {
        data: {
          id: dealershipId,
          code: 'SGA-D7',
          name: 'Saigon Auto',
          branchName: 'District 7',
          timezone: 'Asia/Ho_Chi_Minh',
          phone: '028 3812 3456',
          slotStepMinutes: 15,
          openingHours: [{ weekday: 1, opensAt: '08:00', closesAt: '17:00' }],
        },
      },
    ])

    apiMock.get(`/dealerships/${dealershipId}/service-types`, [
      200,
      {
        data: [
          {
            id: serviceTypeId,
            code: 'OIL_CHANGE',
            name: 'Oil change',
            durationMinutes: 60,
            requiredLevel: 1,
            requiredBayType: 'QUICK_SERVICE',
            active: true,
          },
        ],
      },
    ])

    apiMock.get('/customers', [
      200,
      {
        data: [
          {
            id: customerId,
            fullName: 'Nguyễn Văn Hùng',
            phone: '0903123218',
            email: 'hung@example.test',
            vehicleCount: 1,
          },
        ],
        meta: { total: 1 },
      },
    ])

    apiMock.get(`/customers/${customerId}/vehicles`, [
      200,
      {
        data: [
          {
            id: vehicleId,
            licencePlate: '51H-123.45',
            make: 'Toyota',
            model: 'Vios',
            year: 2021,
          },
        ],
      },
    ])

    apiMock.get(new RegExp(`/dealerships/${dealershipId}/availability`), [
      200,
      {
        data: {
          date: '2026-11-02',
          timezone: 'Asia/Ho_Chi_Minh',
          closed: false,
          opensAt: '08:00',
          closesAt: '17:00',
          stepMinutes: 15,
          durationMinutes: 60,
          freeCount: 2,
          slots: [
            {
              start: '2026-11-02T08:00:00+07:00',
              end: '2026-11-02T09:00:00+07:00',
              available: true,
              reason: null,
            },
            {
              start: '2026-11-02T08:15:00+07:00',
              end: '2026-11-02T09:15:00+07:00',
              available: false,
              reason: 'NO_TECHNICIAN_AVAILABLE',
            },
            {
              start: '2026-11-02T08:30:00+07:00',
              end: '2026-11-02T09:30:00+07:00',
              available: true,
              reason: null,
            },
          ],
        },
      },
    ])

    const user = userEvent.setup()
    renderRoute('/staff')

    await waitFor(() => {
      expect(screen.getByText('Book a service appointment')).toBeInTheDocument()
    })

    // 1. Customer
    const customerInput = screen.getByPlaceholderText('Search customer by name, email or phone')
    await user.type(customerInput, 'Nguyễn')
    const customerOption = await screen.findByText('Nguyễn Văn Hùng · 0903 *** 218')
    await user.click(customerOption)

    // 2. Vehicle
    const vehicleInput = await screen.findByPlaceholderText('Choose a vehicle')
    await user.click(vehicleInput)
    const vehicleOption = await screen.findByText('Toyota Vios · 51H-123.45')
    await user.click(vehicleOption)

    // 3. Service
    const serviceRadio = await screen.findByText('Oil change')
    await user.click(serviceRadio)

    // 4. Assert "2 free start times" shown
    await screen.findByText('2 free start times')
    expect(
      screen.getByText('Struck-through slots have no free bay or technician.'),
    ).toBeInTheDocument()

    const middleSlotBtn = screen.getByRole('button', { name: '08:15' })
    expect(middleSlotBtn).toBeDisabled()
    expect(middleSlotBtn).toHaveAttribute('aria-disabled', 'true')
    expect(middleSlotBtn).toHaveStyle({ textDecoration: 'line-through' })

    // Click middle slot -> not selectable
    await user.click(middleSlotBtn)
    const confirmBtn = screen.getByRole('button', { name: 'Choose a time to continue' })
    expect(confirmBtn).toBeDisabled()

    // Click available slot -> selected
    const freeSlotBtn = screen.getByRole('button', { name: '08:00' })
    await user.click(freeSlotBtn)
    expect(screen.getByRole('button', { name: 'Confirm booking' })).not.toBeDisabled()
  })
})
