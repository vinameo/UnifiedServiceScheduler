import { render, screen } from '@testing-library/react'
import { describe, it, expect } from 'vitest'
import { MantineProvider } from '@mantine/core'
import { AppointmentStatusBadge } from './AppointmentStatusBadge'

function renderBadge(status: string) {
  return render(
    <MantineProvider>
      <AppointmentStatusBadge status={status} />
    </MantineProvider>,
  )
}

describe('AppointmentStatusBadge', () => {
  it('renders CONFIRMED with Confirmed label', () => {
    renderBadge('CONFIRMED')
    expect(screen.getByText('Confirmed')).toBeInTheDocument()
  })

  it('renders HELD with On hold label', () => {
    renderBadge('HELD')
    expect(screen.getByText('On hold')).toBeInTheDocument()
  })

  it('renders CHECKED_IN with Checked in label', () => {
    renderBadge('CHECKED_IN')
    expect(screen.getByText('Checked in')).toBeInTheDocument()
  })

  it('renders IN_PROGRESS with In progress label', () => {
    renderBadge('IN_PROGRESS')
    expect(screen.getByText('In progress')).toBeInTheDocument()
  })

  it('renders COMPLETED with Completed label', () => {
    renderBadge('COMPLETED')
    expect(screen.getByText('Completed')).toBeInTheDocument()
  })

  it('renders CANCELLED with Cancelled label', () => {
    renderBadge('CANCELLED')
    expect(screen.getByText('Cancelled')).toBeInTheDocument()
  })

  it('renders NO_SHOW with No-show label', () => {
    renderBadge('NO_SHOW')
    expect(screen.getByText('No-show')).toBeInTheDocument()
  })

  it('renders EXPIRED with Expired label', () => {
    renderBadge('EXPIRED')
    expect(screen.getByText('Expired')).toBeInTheDocument()
  })
})

