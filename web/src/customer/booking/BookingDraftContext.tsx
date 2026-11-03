import { createContext, useContext, useState, type ReactNode } from 'react'
import type { BookingProblem } from '../../shared/booking/BookingErrorCard'

export interface NewVehicleDraft {
  licencePlate: string
  make: string
  model: string
  year?: number | null
}

export interface BookingDraft {
  dealershipId?: string | null
  serviceTypeId?: string | null
  date?: string | null
  start?: string | null // ISO with offset
  vehicleId?: string | null
  newVehicle?: NewVehicleDraft | null
  reminderOptIn?: boolean
}

export interface BookingDraftContextValue {
  draft: BookingDraft
  updateDraft: (patch: Partial<BookingDraft>) => void
  clearDraft: () => void
  availabilityError: BookingProblem | null
  setAvailabilityError: (err: BookingProblem | null) => void
}

const STORAGE_KEY = 'customer_booking_draft'

function loadSavedDraft(): BookingDraft {
  try {
    const raw = sessionStorage.getItem(STORAGE_KEY)
    if (!raw) return {}
    return JSON.parse(raw) as BookingDraft
  } catch {
    return {}
  }
}

function persistDraft(draft: BookingDraft): void {
  try {
    sessionStorage.setItem(STORAGE_KEY, JSON.stringify(draft))
  } catch {
    // sessionStorage disabled or unavailable
  }
}

const BookingDraftContext = createContext<BookingDraftContextValue | null>(null)

export function BookingDraftProvider({ children }: { children: ReactNode }) {
  const [draft, setDraftState] = useState<BookingDraft>(() => loadSavedDraft())
  const [availabilityError, setAvailabilityError] = useState<BookingProblem | null>(null)

  const updateDraft = (patch: Partial<BookingDraft>) => {
    setDraftState((prev) => {
      const next = { ...prev, ...patch }
      persistDraft(next)
      return next
    })
  }

  const clearDraft = () => {
    try {
      sessionStorage.removeItem(STORAGE_KEY)
    } catch {
      // ignore
    }
    setDraftState({})
    setAvailabilityError(null)
  }

  return (
    <BookingDraftContext.Provider
      value={{
        draft,
        updateDraft,
        clearDraft,
        availabilityError,
        setAvailabilityError,
      }}
    >
      {children}
    </BookingDraftContext.Provider>
  )
}

// eslint-disable-next-line react-refresh/only-export-components
export function useBookingDraft(): BookingDraftContextValue {
  const ctx = useContext(BookingDraftContext)
  if (!ctx) {
    throw new Error('useBookingDraft must be used within a BookingDraftProvider')
  }
  return ctx
}
