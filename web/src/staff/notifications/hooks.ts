import { useInfiniteQuery, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '../../shared/api/client'
import { useIdempotencyKey } from '../../shared/idempotency/useIdempotencyKey'

export interface OutboxSummary {
  pending: number
  retrying: number
  failed: number
  sentToday: number
}

export interface OutboxAppointment {
  id: string
  reference: string
  customerName: string
}

export interface OutboxMessageItem {
  id: string
  eventId: string
  eventType: string
  channel: string
  status: string
  displayStatus: string
  attempts: number
  nextAttemptAt: string | null
  lastError: string | null
  sentAt: string | null
  createdAt: string
  appointment: OutboxAppointment | null
}

export interface OutboxMessagesResponse {
  data: OutboxMessageItem[]
  meta: {
    nextCursor: string | null
    limit: number
    total: number
  }
}

export interface OutboxFilters {
  status?: string
  channel?: string
  eventType?: string
  appointmentId?: string
  limit?: number
}

export function useOutboxSummary() {
  return useQuery({
    queryKey: ['outbox', 'summary'],
    queryFn: async () => {
      const response = await api.get<{ data: OutboxSummary }>('/outbox/summary')
      return response.data.data
    },
    refetchInterval: 15_000,
  })
}

export function useOutboxMessages(filters: OutboxFilters) {
  return useInfiniteQuery({
    queryKey: ['outbox', 'messages', filters],
    initialPageParam: null as string | null,
    queryFn: async ({ pageParam }) => {
      const params: Record<string, unknown> = {}
      if (filters.status) params.status = filters.status
      if (filters.channel) params.channel = filters.channel
      if (filters.eventType) params.eventType = filters.eventType
      if (filters.appointmentId) params.appointmentId = filters.appointmentId
      if (filters.limit) params.limit = filters.limit
      if (pageParam) params.cursor = pageParam

      const response = await api.get<OutboxMessagesResponse>('/outbox', { params })
      return response.data
    },
    getNextPageParam: (lastPage) => lastPage.meta.nextCursor ?? undefined,
  })
}

export function useRetryOutboxMessage(messageId?: string) {
  const queryClient = useQueryClient()
  const { key, reset, settle } = useIdempotencyKey(messageId ? { id: messageId } : undefined)

  const mutation = useMutation({
    mutationFn: async (variables?: { id?: string; idempotencyKey?: string } | string) => {
      const targetId = typeof variables === 'string' ? variables : variables?.id ?? messageId
      if (!targetId) {
        throw new Error('Message ID is required for retry')
      }
      const targetKey =
        typeof variables === 'object' && variables?.idempotencyKey
          ? variables.idempotencyKey
          : key

      const response = await api.post<{ data: OutboxMessageItem }>(
        `/outbox/${targetId}/retry`,
        {},
        {
          headers: {
            'Idempotency-Key': targetKey,
          },
        },
      )
      return response.data.data
    },
    onSuccess: () => {
      reset()
      void queryClient.invalidateQueries({ queryKey: ['outbox', 'summary'] })
      void queryClient.invalidateQueries({ queryKey: ['outbox', 'messages'] })
    },
    onError: (err: unknown) => {
      settle(err)
    },
  })

  return {
    ...mutation,
    idempotencyKey: key,
    resetKey: reset,
    settleKey: settle,
  }
}
