import { MantineProvider } from '@mantine/core'
import { Notifications } from '@mantine/notifications'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { useEffect, useState, type ReactNode } from 'react'
import { theme } from './shared/theme'
import { setQueryCacheClearer } from './shared/api/client'

export function AppProviders({ children }: { children: ReactNode }) {
  const [queryClient] = useState(
    () =>
      new QueryClient({
        defaultOptions: {
          // Never retry a write; retry a read once.
          queries: { retry: 1, refetchOnWindowFocus: true },
          mutations: { retry: false },
        },
      }),
  )

  useEffect(() => {
    setQueryCacheClearer(() => queryClient.clear())
  }, [queryClient])

  return (
    <MantineProvider theme={theme}>
      <Notifications position="top-right" />
      <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
    </MantineProvider>
  )
}
