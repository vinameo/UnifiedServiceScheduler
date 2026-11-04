import { useEffect, useState } from 'react'
import { getServerNow } from './useServerClock'

export interface CountdownState {
  formatted: string
  expired: boolean
  totalSeconds: number
  minutes: number
  seconds: number
}

export function useCountdown(expiresAt: string | null | undefined): CountdownState {
  const [now, setNow] = useState(() => getServerNow())

  useEffect(() => {
    if (!expiresAt) return

    const interval = setInterval(() => {
      setNow(getServerNow())
    }, 1000)

    return () => clearInterval(interval)
  }, [expiresAt])

  if (!expiresAt) {
    return { formatted: '00:00', expired: false, totalSeconds: 0, minutes: 0, seconds: 0 }
  }

  const diffMs = new Date(expiresAt).getTime() - now.getTime()
  if (diffMs <= 0) {
    return { formatted: '00:00', expired: true, totalSeconds: 0, minutes: 0, seconds: 0 }
  }

  const totalSeconds = Math.ceil(diffMs / 1000)
  const minutes = Math.floor(totalSeconds / 60)
  const seconds = totalSeconds % 60
  const formatted = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`

  return {
    formatted,
    expired: false,
    totalSeconds,
    minutes,
    seconds,
  }
}
