import { describe, it, expect } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router'
import { AppProviders } from '../../AppProviders'
import type { WorkshopBay, WorkshopBoardData } from '../../shared/api/workshop'
import { BayTimeline } from './BayTimeline'

describe('BayTimeline (TC-12-24, WEB-12.5)', () => {
  const timeZone = 'Asia/Ho_Chi_Minh'
  const opensAt = '08:00'
  const closesAt = '17:00'
  const now = '2026-11-02T10:50:00+07:00'

  const mockBays: WorkshopBay[] = [
    {
      id: 'bay-1',
      code: 'K1',
      name: 'Bay 1 Quick service',
      bayType: 'QUICK',
      blocks: [
        {
          appointmentId: 'apt-0900',
          version: 1,
          reference: 'BK-0900',
          status: 'CONFIRMED',
          start: '2026-11-02T09:00:00+07:00',
          end: '2026-11-02T10:00:00+07:00',
          customerName: 'Nguyễn Văn An',
          serviceName: 'Oil change',
          technicianName: 'Trần Minh An',
          overrunMinutes: 0,
        },
        {
          appointmentId: 'apt-cancelled',
          version: 1,
          reference: 'BK-CANCELLED',
          status: 'CANCELLED',
          start: '2026-11-02T11:00:00+07:00',
          end: '2026-11-02T12:00:00+07:00',
          customerName: 'Lê Văn Cancelled',
          serviceName: 'Brake check',
          technicianName: 'Trần Minh An',
          overrunMinutes: 0,
        },
      ],
    },
    {
      id: 'bay-3',
      code: 'K3',
      name: 'Bay 3 Standard',
      bayType: 'STANDARD',
      blocks: [
        {
          appointmentId: 'apt-overrun',
          version: 2,
          reference: 'BK-OVERRUN',
          status: 'IN_PROGRESS',
          start: '2026-11-02T08:30:00+07:00',
          end: '2026-11-02T10:00:00+07:00',
          customerName: 'Phạm Quốc Bảo',
          serviceName: '20,000 km service',
          technicianName: 'Võ Thị Chi',
          overrunMinutes: 50,
        },
      ],
    },
  ]

  const mockBoardData: WorkshopBoardData = {
    date: '2026-11-02',
    now,
    opensAt,
    closesAt,
    bays: mockBays,
    displaced: [],
    technicians: [],
    alerts: [],
  }

  function renderBayTimeline(props: Partial<Parameters<typeof BayTimeline>[0]> = {}) {
    return render(
      <AppProviders>
        <MemoryRouter>
          <BayTimeline
            bays={mockBays}
            opensAt={opensAt}
            closesAt={closesAt}
            now={now}
            timeZone={timeZone}
            {...props}
          />
        </MemoryRouter>
      </AppProviders>,
    )
  }

  it('TC-12-24: renders blocks placed at hour column, overrun part hatched, now-line at 10:50, cancelled blocks absent', () => {
    renderBayTimeline()

    // 1. Column 09:00 exists and has its calculated position
    const col09 = screen.getByTestId('hour-column-09')
    expect(col09).toBeInTheDocument()

    // 2. The block 09:00–10:00 is placed at the 09:00 column
    const block0900 = screen.getByText('Trần Minh An').closest('[data-testid="timeline-block"]') as HTMLElement
    expect(block0900).toBeInTheDocument()
    expect(block0900.style.left).toBe(col09.style.left)
    expect(block0900.getAttribute('href')).toBe('/staff/appointments/apt-0900')

    // 3. The overrun block has an overrun part hatched with diagonal stripes CSS
    const overrunPart = screen.getByTestId('overrun-part')
    expect(overrunPart).toBeInTheDocument()
    expect(overrunPart.style.background).toMatch(/repeating-linear-gradient/)

    const overrunBlock = overrunPart.closest('[data-testid="timeline-block"]') as HTMLElement
    expect(overrunBlock).toBeInTheDocument()
    expect(overrunBlock.getAttribute('href')).toBe('/staff/appointments/apt-overrun')

    // 4. The now-line is rendered at 10:50
    const nowLine = screen.getByTestId('now-line')
    expect(nowLine).toBeInTheDocument()
    expect(nowLine.getAttribute('data-now-time')).toBe('10:50')
    // Timeline 08:00 (480m) to 17:00 (1020m) -> 540 min. 10:50 is 650m -> offset 170m -> 170/540 * 100 = 31.4815%
    expect(parseFloat(nowLine.style.left)).toBeCloseTo(31.48, 1)

    // 5. Cancelled blocks are absent from bay blocks
    expect(screen.queryByText('BK-CANCELLED')).not.toBeInTheDocument()
    expect(screen.queryByText('Lê Văn Cancelled')).not.toBeInTheDocument()
  })

  it('renders successfully when passed board object directly', () => {
    render(
      <AppProviders>
        <MemoryRouter>
          <BayTimeline board={mockBoardData} timeZone={timeZone} />
        </MemoryRouter>
      </AppProviders>,
    )

    expect(screen.getByTestId('bay-timeline')).toBeInTheDocument()
    expect(screen.getByText('Trần Minh An')).toBeInTheDocument()
    expect(screen.getByText('Võ Thị Chi')).toBeInTheDocument()
    expect(screen.getByTestId('now-line')).toBeInTheDocument()
  })
})
