import {
  Anchor,
  Box,
  Button,
  Card,
  Container,
  Group,
  Radio,
  Select,
  Stack,
  Text,
  Title,
} from '@mantine/core'
import { useEffect } from 'react'
import { useNavigate } from 'react-router'
import { useDealerships, useServiceTypes, type ServiceType } from '../../shared/api/masterData'
import { useBookingDraft } from './BookingDraftContext'

function formatDuration(minutes: number): string {
  if (minutes >= 120 && minutes % 60 === 0) {
    return `${minutes / 60} hours`
  }
  return `${minutes} min`
}

export function ChooseServicePage() {
  const navigate = useNavigate()
  const { draft, updateDraft } = useBookingDraft()
  const { data: dealerships = [] } = useDealerships()

  // Initialize or fallback dealership
  const selectedDealershipId =
    draft.dealershipId ?? (dealerships.length > 0 ? dealerships[0].id : null)

  useEffect(() => {
    if (!draft.dealershipId && dealerships.length > 0) {
      updateDraft({ dealershipId: dealerships[0].id })
    }
  }, [dealerships, draft.dealershipId, updateDraft])

  const selectedDealership = dealerships.find((d) => d.id === selectedDealershipId)
  const { data: serviceTypes = [] } = useServiceTypes(selectedDealershipId ?? undefined)
  const activeServices = serviceTypes.filter((s) => s.active)
  const selectedService = activeServices.find((s) => s.id === draft.serviceTypeId)

  const handleSelectDealership = (val: string | null) => {
    if (val && val !== selectedDealershipId) {
      updateDraft({
        dealershipId: val,
        serviceTypeId: null,
        date: null,
        start: null,
      })
    }
  }

  const handleSelectService = (serviceId: string) => {
    updateDraft({
      serviceTypeId: serviceId,
      date: null,
      start: null,
    })
  }

  const handleContinue = () => {
    if (draft.dealershipId && draft.serviceTypeId) {
      navigate('/book/time')
    }
  }

  const branchDisplay = selectedDealership?.branchName
    ? `${selectedDealership.branchName} branch`
    : `${selectedDealership?.name ?? ''} branch`

  return (
    <Container size={390} py="md" pb={100}>
      <Stack gap="lg">
        {/* Header */}
        <Box>
          <Text size="xs" c="dimmed" tt="uppercase" fw={600}>
            {branchDisplay}
          </Text>
          <Title order={2} mt={4}>
            Book a service
          </Title>
          <Text size="sm" c="dimmed" mt={2}>
            Step 1 of 3 · Choose a service
          </Text>
        </Box>

        {/* Dealership selector when > 1 */}
        {dealerships.length > 1 && (
          <Select
            label="Dealership"
            placeholder="Choose a dealership"
            value={selectedDealershipId}
            data={dealerships.map((d) => ({
              value: d.id,
              label: d.branchName ? `${d.name} (${d.branchName})` : d.name,
            }))}
            onChange={handleSelectDealership}
          />
        )}

        {/* Services List */}
        <Radio.Group
          value={draft.serviceTypeId ?? ''}
          onChange={handleSelectService}
        >
          <Stack gap="sm">
            {activeServices.map((service: ServiceType) => {
              const durationText = formatDuration(service.durationMinutes)
              const isSelected = service.id === draft.serviceTypeId
              return (
                <Card
                  key={service.id}
                  padding="md"
                  radius="md"
                  withBorder
                  style={{
                    cursor: 'pointer',
                    borderColor: isSelected ? 'var(--mantine-color-blue-filled)' : undefined,
                  }}
                  onClick={() => handleSelectService(service.id)}
                >
                  <Group align="flex-start" wrap="nowrap">
                    <Radio value={service.id} mt={2} />
                    <Box style={{ flex: 1 }}>
                      <Text fw={600}>{service.name}</Text>
                      <Text size="sm" c="dimmed" mt={2}>
                        About {durationText}
                        {service.description ? ` · ${service.description}` : ''}
                      </Text>
                    </Box>
                  </Group>
                </Card>
              )
            })}
          </Stack>
        </Radio.Group>

        {/* Can't find note */}
        {selectedDealership?.phone && (
          <Text size="sm" c="dimmed">
            Can&apos;t find what you need? Call{' '}
            <Anchor href={`tel:${selectedDealership.phone}`}>
              {selectedDealership.phone}
            </Anchor>{' '}
            and a service advisor will book it for you.
          </Text>
        )}
      </Stack>

      {/* Sticky Bottom Footer */}
      <Box
        style={{
          position: 'fixed',
          bottom: 0,
          left: 0,
          right: 0,
          backgroundColor: 'var(--mantine-color-body)',
          borderTop: '1px solid var(--mantine-color-default-border)',
          padding: '12px 16px',
          zIndex: 100,
        }}
      >
        <Container size={390} p={0}>
          <Group justify="space-between" align="center">
            <Box style={{ flex: 1, minWidth: 0 }}>
              {selectedService ? (
                <Text size="sm" fw={500} truncate>
                  Selected {selectedService.name}
                </Text>
              ) : (
                <Text size="sm" c="dimmed">
                  Select a service to continue
                </Text>
              )}
            </Box>
            <Button
              onClick={handleContinue}
              disabled={!draft.dealershipId || !draft.serviceTypeId}
            >
              Choose date and time
            </Button>
          </Group>
        </Container>
      </Box>
    </Container>
  )
}

