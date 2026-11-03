import { useQueryClient } from '@tanstack/react-query'
import { api, ensureCsrfCookie } from '../api/client'
import type { Me } from './useMe'

export function useAuthActions() {
  const queryClient = useQueryClient()

  const signOut = async () => {
    try {
      await api.post('/auth/sign-out')
    } catch {
      // Ignore network errors on sign out
    } finally {
      queryClient.clear()
    }
  }

  const setMe = (me: Me) => {
    queryClient.setQueryData(['me'], me)
  }

  return {
    ensureCsrfCookie,
    signOut,
    setMe,
  }
}

