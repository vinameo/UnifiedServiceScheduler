import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from './client'

export interface Vehicle {
  id: string
  licencePlate: string
  make: string
  model: string
  year?: number | null
  vin?: string | null
}

export interface CreateVehiclePayload {
  licencePlate: string
  make: string
  model: string
  year?: number | null
  vin?: string | null
}

export function useCustomerVehicles(customerId: string | undefined) {
  return useQuery({
    queryKey: ['customer-vehicles', customerId],
    queryFn: async () => {
      if (!customerId) return []
      const response = await api.get<{ data: Vehicle[] }>(`/customers/${customerId}/vehicles`)
      return response.data.data
    },
    enabled: Boolean(customerId),
  })
}

export function useMyVehicles() {
  return useQuery({
    queryKey: ['my-vehicles'],
    queryFn: async () => {
      const response = await api.get<{ data: Vehicle[] }>('/me/vehicles')
      return response.data.data
    },
  })
}

export function useAddVehicle() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async ({
      payload,
      idempotencyKey,
    }: {
      payload: CreateVehiclePayload
      idempotencyKey: string
    }) => {
      const response = await api.post<{ data: Vehicle }>('/me/vehicles', payload, {
        headers: {
          'Idempotency-Key': idempotencyKey,
        },
      })
      return response.data.data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['my-vehicles'] })
    },
  })
}
