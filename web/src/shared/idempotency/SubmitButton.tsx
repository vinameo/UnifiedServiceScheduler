import { Button, type ButtonProps } from '@mantine/core'
import type React from 'react'
import { IDEMPOTENCY_COPY } from './copy'

export interface SubmitButtonProps extends Omit<ButtonProps, 'onClick'> {
  pending?: boolean
  pendingLabel?: string
  onClick?: (event: React.MouseEvent<HTMLButtonElement>) => void
  children: React.ReactNode
  type?: 'submit' | 'button' | 'reset'
}

export function SubmitButton({
  pending = false,
  pendingLabel = IDEMPOTENCY_COPY.submittingConfirm,
  disabled = false,
  onClick,
  children,
  type = 'submit',
  ...props
}: SubmitButtonProps) {
  const handleClick = (event: React.MouseEvent<HTMLButtonElement>) => {
    if (pending || disabled) {
      event.preventDefault()
      return
    }
    onClick?.(event)
  }

  return (
    <Button
      type={type}
      loading={pending}
      disabled={disabled || pending}
      onClick={handleClick}
      {...props}
    >
      {pending ? pendingLabel : children}
    </Button>
  )
}

