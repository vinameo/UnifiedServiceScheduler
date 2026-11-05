import { errorCopy } from '../../shared/api/errorCopy'
import { toProblem } from '../../shared/api/problem'
import { statusMeta } from '../../shared/appointments/status'
import { modifiedConflict, type ModifiedConflict } from '../../shared/appointments/modifiedConflict'
import { appointmentCopy } from './copy'

export type ActionError =
  | { kind: 'modified'; conflict: ModifiedConflict }
  | { kind: 'message'; message: string }

/**
 * What to show after a failed check-in, start, complete, no-show or extend (F12): a 412 becomes
 * the "changed by someone else" alert, every other answer one sentence chosen by its `code`.
 */
export function describeActionError(error: unknown): ActionError {
  const conflict = modifiedConflict(error)
  if (conflict) return { kind: 'modified', conflict }

  const problem = toProblem(error)
  // Of these actions only extend answers 409 NO_AVAILABILITY: a neighbour could not be moved
  if (problem?.code === 'NO_AVAILABILITY') {
    return { kind: 'message', message: appointmentCopy.actions.extendFailed }
  }

  const currentStatus = typeof problem?.currentStatus === 'string' ? problem.currentStatus : undefined
  return {
    kind: 'message',
    message: errorCopy(problem?.code, {
      status: currentStatus ? statusMeta(currentStatus).label.toLowerCase() : undefined,
    }),
  }
}
