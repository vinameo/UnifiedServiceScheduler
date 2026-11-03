import { Outlet } from 'react-router'
import { BookingDraftProvider } from './BookingDraftContext'

export function CustomerBookingLayout() {
  return (
    <BookingDraftProvider>
      <Outlet />
    </BookingDraftProvider>
  )
}

