import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { api, setQueryCacheClearer, setUnauthenticatedHandler } from './client'
import { apiMock } from '../../test/apiMock'

describe('api client 401 interceptor (WEB-00.3)', () => {
  beforeEach(() => {
    apiMock.install()
    apiMock.reset()
  })

  afterEach(() => {
    apiMock.restore()
    setUnauthenticatedHandler(() => {})
    setQueryCacheClearer(() => {})
  })

  it('redirects to /sign-in and clears cache on 401 for generic API route', async () => {
    const unauthenticatedHandler = vi.fn()
    const clearCache = vi.fn()

    setUnauthenticatedHandler(unauthenticatedHandler)
    setQueryCacheClearer(clearCache)

    apiMock.get('/appointments', apiMock.replyProblem(401, 'UNAUTHENTICATED', 'Please sign in.'))

    await expect(api.get('/appointments')).rejects.toThrow()

    expect(clearCache).toHaveBeenCalledTimes(1)
    expect(unauthenticatedHandler).toHaveBeenCalledTimes(1)
    expect(unauthenticatedHandler).toHaveBeenCalledWith(expect.stringContaining('/sign-in?returnTo='))
  })

  it('does not redirect on 401 for /me', async () => {
    const unauthenticatedHandler = vi.fn()
    const clearCache = vi.fn()

    setUnauthenticatedHandler(unauthenticatedHandler)
    setQueryCacheClearer(clearCache)

    apiMock.get('/me', apiMock.replyProblem(401, 'UNAUTHENTICATED', 'Please sign in.'))

    await expect(api.get('/me')).rejects.toThrow()

    expect(clearCache).not.toHaveBeenCalled()
    expect(unauthenticatedHandler).not.toHaveBeenCalled()
  })

  it('does not redirect on 401 for /auth/* routes', async () => {
    const unauthenticatedHandler = vi.fn()
    const clearCache = vi.fn()

    setUnauthenticatedHandler(unauthenticatedHandler)
    setQueryCacheClearer(clearCache)

    apiMock.post('/auth/customer/sign-in', apiMock.replyProblem(401, 'INVALID_CREDENTIALS', 'Email or password is incorrect.'))

    await expect(api.post('/auth/customer/sign-in', {})).rejects.toThrow()

    expect(clearCache).not.toHaveBeenCalled()
    expect(unauthenticatedHandler).not.toHaveBeenCalled()
  })
})

