import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { useState } from 'react'
import { describe, expect, it, vi } from 'vitest'
import { AppProviders } from '../../AppProviders'
import { SubmitButton } from './SubmitButton'

describe('SubmitButton (TC-04-37, WEB-04.3)', () => {
  it('TC-04-37: blocks double submit when clicked twice quickly, shows Confirming… and is disabled', async () => {
    const user = userEvent.setup()
    const onSubmit = vi.fn()

    function TestForm() {
      const [pending, setPending] = useState(false)

      const handleSubmit = () => {
        onSubmit()
        setPending(true)
        // Simulate hanging API call
      }

      return (
        <SubmitButton pending={pending} onClick={handleSubmit}>
          Book appointment
        </SubmitButton>
      )
    }

    render(
      <AppProviders>
        <TestForm />
      </AppProviders>,
    )

    const button = screen.getByRole('button', { name: 'Book appointment' })
    expect(button).toBeInTheDocument()
    expect(button).not.toBeDisabled()

    // Click submit twice quickly
    await user.click(button)
    await user.click(button)

    // Exactly 1 request sent
    expect(onSubmit).toHaveBeenCalledTimes(1)

    // Button shows "Confirming…" and is disabled
    expect(screen.getByRole('button')).toHaveTextContent('Confirming…')
    expect(screen.getByRole('button')).toBeDisabled()
  })
})

