import { AxiosError, AxiosHeaders } from 'axios'
import { toProblem } from './problem'

function axiosErrorWith(data: unknown) {
  return new AxiosError('failed', 'ERR_BAD_RESPONSE', undefined, undefined, {
    data,
    status: 409,
    statusText: 'Conflict',
    headers: {},
    config: { headers: new AxiosHeaders() },
  })
}

describe('toProblem', () => {
  it('reads a problem+json body', () => {
    const problem = toProblem(axiosErrorWith({ type: '/problems/no-availability', title: 'Full', status: 409, code: 'NO_AVAILABILITY' }))

    expect(problem?.code).toBe('NO_AVAILABILITY')
  })

  it('returns null for other errors', () => {
    expect(toProblem(new Error('network'))).toBeNull()
    expect(toProblem(axiosErrorWith('<html>'))).toBeNull()
  })
})
