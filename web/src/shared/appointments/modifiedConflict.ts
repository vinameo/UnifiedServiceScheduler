import { isAxiosError } from 'axios'
import type { AppointmentDiffInput } from './diffAppointment'

export interface ModifiedConflict {
  latest: AppointmentDiffInput
  etag: string
}

/** The latest appointment sent with a 412 APPOINTMENT_MODIFIED answer; null for any other error. */
export function modifiedConflict(error: unknown): ModifiedConflict | null {
  if (!isAxiosError(error) || error.response?.status !== 412) return null

  const data = error.response.data as {
    appointment?: AppointmentDiffInput
    extra?: { appointment?: AppointmentDiffInput }
  } | null
  const latest = data?.extra?.appointment ?? data?.appointment
  if (!latest) return null

  const rawEtag: unknown = error.response.headers['etag']
  return { latest, etag: typeof rawEtag === 'string' ? rawEtag : '' }
}
