import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MantineProvider } from '@mantine/core'
import { AlternativeChips } from './AlternativeChips'

function renderChips(ui: React.ReactElement) {
  return render(<MantineProvider>{ui}</MantineProvider>)
}

describe('AlternativeChips (WEB-06.4, AC-06.15)', () => {
  const timeZone = 'Asia/Ho_Chi_Minh'

  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date('2026-11-02T08:00:00Z')) // Nov 2, 2026 in GMT+7
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  describe('label formatting', () => {
    it('formats same day as today as "{HH:mm} today"', () => {
      renderChips(
        <AlternativeChips
          alternatives={[{ start: '2026-11-02T10:00:00+07:00', end: '2026-11-02T11:00:00+07:00' }]}
          timeZone={timeZone}
          referenceDate="2026-11-02"
          onPick={vi.fn()}
        />,
      )
      expect(screen.getByRole('button', { name: '10:00 today' })).toBeInTheDocument()
    })

    it('formats same day as reference (when reference is not today) as "{HH:mm}"', () => {
      renderChips(
        <AlternativeChips
          alternatives={[{ start: '2026-11-04T14:30:00+07:00', end: '2026-11-04T15:30:00+07:00' }]}
          timeZone={timeZone}
          referenceDate="2026-11-04"
          onPick={vi.fn()}
        />,
      )
      expect(screen.getByRole('button', { name: '14:30' })).toBeInTheDocument()
    })

    it('formats another day as "{HH:mm} {Ddd D Mmm}"', () => {
      renderChips(
        <AlternativeChips
          alternatives={[{ start: '2026-11-03T08:00:00+07:00', end: '2026-11-03T09:00:00+07:00' }]}
          timeZone={timeZone}
          referenceDate="2026-11-02"
          onPick={vi.fn()}
        />,
      )
      expect(screen.getByRole('button', { name: '08:00 Tue 3 Nov' })).toBeInTheDocument()
    })
  })

  it('renders header and suggestion chips', () => {
    const onPick = vi.fn()
    const alternatives = [
      { start: '2026-11-02T10:00:00+07:00', end: '2026-11-02T11:00:00+07:00' },
      { start: '2026-11-02T11:15:00+07:00', end: '2026-11-02T12:15:00+07:00' },
      { start: '2026-11-03T08:00:00+07:00', end: '2026-11-03T09:00:00+07:00' },
    ]

    renderChips(
      <AlternativeChips
        alternatives={alternatives}
        timeZone={timeZone}
        onPick={onPick}
        referenceDate="2026-11-02"
      />,
    )

    expect(screen.getByText('Nearest available slots')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: '10:00 today' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: '11:15 today' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: '08:00 Tue 3 Nov' })).toBeInTheDocument()
  })

  it('shows empty message when alternatives array is empty', () => {
    renderChips(
      <AlternativeChips
        alternatives={[]}
        timeZone={timeZone}
        onPick={vi.fn()}
      />,
    )

    expect(screen.getByText('Nearest available slots')).toBeInTheDocument()
    expect(screen.getByText('No free time nearby. Pick another day.')).toBeInTheDocument()
  })

  it('calls onPick with startIso when a chip is clicked', async () => {
    const onPick = vi.fn()
    const alternatives = [
      { start: '2026-11-02T10:00:00+07:00', end: '2026-11-02T11:00:00+07:00' },
    ]

    const user = userEvent.setup()
    renderChips(
      <AlternativeChips
        alternatives={alternatives}
        timeZone={timeZone}
        onPick={onPick}
      />,
    )

    const chip = screen.getByRole('button', { name: '10:00 today' })
    await user.click(chip)

    expect(onPick).toHaveBeenCalledTimes(1)
    expect(onPick).toHaveBeenCalledWith('2026-11-02T10:00:00+07:00')
  })
})
