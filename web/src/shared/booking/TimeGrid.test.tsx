import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, it, expect, vi } from 'vitest'
import { MantineProvider } from '@mantine/core'
import { TimeGrid, type TimeGridProps } from './TimeGrid'

function renderTimeGrid(props: Partial<TimeGridProps> & { onChange?: (iso: string) => void }) {
  const defaultSlots = [
    { time: '08:00', iso: '2026-11-02T08:00:00+07:00' },
    { time: '09:00', iso: '2026-11-02T09:00:00+07:00' },
    { time: '13:00', iso: '2026-11-02T13:00:00+07:00' },
  ]
  const onChange = props.onChange ?? vi.fn()

  return {
    onChange,
    ...render(
      <MantineProvider>
        <TimeGrid
          slots={props.slots ?? defaultSlots}
          value={props.value}
          onChange={onChange}
          disabledSlots={props.disabledSlots}
          disabled={props.disabled}
          chipState={props.chipState}
          chipStates={props.chipStates}
        />
      </MantineProvider>,
    ),
  }
}

describe('TimeGrid', () => {
  it('renders empty message when slots list is empty', () => {
    renderTimeGrid({ slots: [] })
    expect(screen.getByText('No slots available on this day.')).toBeInTheDocument()
  })

  it('groups morning and afternoon slots under headers', () => {
    renderTimeGrid({})
    expect(screen.getByText('Morning')).toBeInTheDocument()
    expect(screen.getByText('Afternoon')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: '08:00' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: '13:00' })).toBeInTheDocument()
  })

  it('backwards-compatible: selects value and disables disabledSlots', async () => {
    const user = userEvent.setup()
    const { onChange } = renderTimeGrid({
      value: '2026-11-02T09:00:00+07:00',
      disabledSlots: ['2026-11-02T08:00:00+07:00'],
    })

    const selectedBtn = screen.getByRole('button', { name: '09:00' })
    const disabledBtn = screen.getByRole('button', { name: '08:00' })
    const freeBtn = screen.getByRole('button', { name: '13:00' })

    expect(selectedBtn).toHaveAttribute('aria-pressed', 'true')
    expect(selectedBtn).not.toBeDisabled()

    expect(disabledBtn).toBeDisabled()
    expect(disabledBtn).toHaveAttribute('aria-disabled', 'true')

    expect(freeBtn).not.toBeDisabled()
    expect(freeBtn).toHaveAttribute('aria-pressed', 'false')

    await user.click(freeBtn)
    expect(onChange).toHaveBeenCalledWith('2026-11-02T13:00:00+07:00')
  })

  it('takes chip state directly from slot object (selected, taken, free)', async () => {
    const user = userEvent.setup()
    const { onChange } = renderTimeGrid({
      slots: [
        { time: '08:00', iso: '2026-11-02T08:00:00+07:00', state: 'taken', reason: 'NO_TECHNICIAN_AVAILABLE' },
        { time: '09:00', iso: '2026-11-02T09:00:00+07:00', state: 'selected' },
        { time: '13:00', iso: '2026-11-02T13:00:00+07:00', state: 'free' },
      ],
    })

    const takenBtn = screen.getByRole('button', { name: '08:00' })
    const selectedBtn = screen.getByRole('button', { name: '09:00' })
    const freeBtn = screen.getByRole('button', { name: '13:00' })

    expect(takenBtn).toBeDisabled()
    expect(takenBtn).toHaveAttribute('title', 'NO_TECHNICIAN_AVAILABLE')

    expect(selectedBtn).toHaveAttribute('aria-pressed', 'true')
    expect(selectedBtn).not.toBeDisabled()

    expect(freeBtn).not.toBeDisabled()
    await user.click(freeBtn)
    expect(onChange).toHaveBeenCalledWith('2026-11-02T13:00:00+07:00')
  })

  it('takes chip state from chipStates dictionary prop', () => {
    renderTimeGrid({
      chipStates: {
        '2026-11-02T08:00:00+07:00': 'taken',
        '2026-11-02T09:00:00+07:00': 'free',
        '2026-11-02T13:00:00+07:00': 'selected',
      },
    })

    expect(screen.getByRole('button', { name: '08:00' })).toBeDisabled()
    expect(screen.getByRole('button', { name: '09:00' })).not.toBeDisabled()
    expect(screen.getByRole('button', { name: '13:00' })).toHaveAttribute('aria-pressed', 'true')
  })

  it('takes chip state from chipState function prop', () => {
    renderTimeGrid({
      chipState: (slot) => (slot.time === '08:00' ? 'selected' : 'free'),
    })

    expect(screen.getByRole('button', { name: '08:00' })).toHaveAttribute('aria-pressed', 'true')
    expect(screen.getByRole('button', { name: '09:00' })).toHaveAttribute('aria-pressed', 'false')
  })

  it('disables all slots when disabled prop is true', () => {
    renderTimeGrid({ disabled: true })

    expect(screen.getByRole('button', { name: '08:00' })).toBeDisabled()
    expect(screen.getByRole('button', { name: '09:00' })).toBeDisabled()
    expect(screen.getByRole('button', { name: '13:00' })).toBeDisabled()
  })
})
