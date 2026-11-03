import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { api } from '../shared/api/client'
import { toProblem } from '../shared/api/problem'
import { apiMock } from './apiMock'

describe('apiMock', () => {
  beforeEach(() => {
    apiMock.install()
    apiMock.reset()
  })

  afterEach(() => {
    apiMock.restore()
  })

  it('mocks successful GET requests', async () => {
    apiMock.get('/me', [200, { user: { name: 'Alice' } }])

    const res = await api.get('/me')
    expect(res.status).toBe(200)
    expect(res.data).toEqual({ user: { name: 'Alice' } })
  })

  it('mocks problem+json error responses', async () => {
    apiMock.post('/auth/sign-in', apiMock.replyProblem(401, 'INVALID_CREDENTIALS', 'Email or password is incorrect.'))

    try {
      await api.post('/auth/sign-in', { email: 'test@example.com' })
      expect.unreachable('Should have thrown')
    } catch (err) {
      const problem = toProblem(err)
      expect(problem?.status).toBe(401)
      expect(problem?.code).toBe('INVALID_CREDENTIALS')
    }
  })
})

