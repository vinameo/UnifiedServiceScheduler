import {
  Alert,
  Badge,
  Box,
  Container,
  Group,
  Skeleton,
  Stack,
  Table,
  Tabs,
  Text,
  Title,
} from '@mantine/core'
import { useState } from 'react'
import { useMe } from '../../shared/auth/useMe'
import {
  useBays,
  useDealership,
  useServiceTypes,
  useTechnicians,
} from '../../shared/api/masterData'
import { bayTypeLabel, levelLabel } from '../../shared/copy/enums'
import { masterDataCopy as copy } from './copy'

export function MasterDataPage() {
  const { data: me } = useMe()
  const dealershipId = me?.dealership?.id

  const { data: dealership } = useDealership(dealershipId)
  const {
    data: serviceTypes = [],
    isLoading: isLoadingServices,
    isError: isErrorServices,
  } = useServiceTypes(dealershipId)
  const { data: bays = [] } = useBays(dealershipId)
  const { data: technicians = [] } = useTechnicians(dealershipId)

  const [activeTab, setActiveTab] = useState<string | null>('service-types')

  const dealershipName = dealership?.name ?? me?.dealership?.name ?? 'Saigon Auto'
  const branchName = dealership?.branchName ?? me?.dealership?.branchName ?? 'District 7'

  return (
    <Container size="xl" py="lg">
      <Stack gap="lg">
        {/* Header */}
        <Group justify="space-between" align="flex-start">
          <Box>
            <Title order={2} fw={700}>
              {copy.title}
            </Title>
            <Text c="dimmed" size="sm" mt={4}>
              {copy.subtitle}
            </Text>
            <Text size="xs" c="dimmed" mt={2}>
              {copy.branch(dealershipName, branchName)}
            </Text>
          </Box>
          <Group gap="xs" align="center">
            <Badge color="gray" variant="light" size="lg">
              {copy.readOnly}
            </Badge>
          </Group>
        </Group>

        {/* Read-only Seed Info Alert */}
        <Alert
          color="blue"
          variant="light"
          title={copy.seedTitle}
          radius="md"
        >
          <Text size="sm">{copy.seedBody}</Text>
          <Text size="xs" fw={600} mt={6} c="blue.8">
            {copy.onlyStatus}
          </Text>
        </Alert>

        {/* Tabs */}
        <Tabs value={activeTab} onChange={setActiveTab} variant="outline" radius="md">
          <Tabs.List>
            <Tabs.Tab
              value="service-types"
              rightSection={
                <Badge size="xs" variant="light" color="blue">
                  {serviceTypes.length}
                </Badge>
              }
            >
              {copy.tabs.serviceTypes}
            </Tabs.Tab>
            <Tabs.Tab
              value="bays"
              rightSection={
                <Badge size="xs" variant="light" color="gray">
                  {bays.length}
                </Badge>
              }
            >
              {copy.tabs.bays}
            </Tabs.Tab>
            <Tabs.Tab
              value="technicians"
              rightSection={
                <Badge size="xs" variant="light" color="gray">
                  {technicians.length}
                </Badge>
              }
            >
              {copy.tabs.technicians}
            </Tabs.Tab>
            <Tabs.Tab value="opening-hours">
              {copy.tabs.openingHours}
            </Tabs.Tab>
          </Tabs.List>

          {/* Service types tab panel */}
          <Tabs.Panel value="service-types" pt="md">
            {isLoadingServices ? (
              <Skeleton height={200} radius="md" />
            ) : isErrorServices ? (
              <Alert color="red" title="Error">
                Failed to load service types.
              </Alert>
            ) : serviceTypes.length === 0 ? (
              <Text c="dimmed" py="xl" ta="center">
                {copy.empty}
              </Text>
            ) : (
              <Table.ScrollContainer minWidth={600}>
                <Table striped highlightOnHover withTableBorder withColumnBorders>
                  <Table.Thead>
                    <Table.Tr>
                      <Table.Th>{copy.columns.code}</Table.Th>
                      <Table.Th>{copy.columns.serviceName}</Table.Th>
                      <Table.Th>{copy.columns.duration}</Table.Th>
                      <Table.Th>{copy.columns.bayTypeRequired}</Table.Th>
                      <Table.Th>{copy.columns.levelRequired}</Table.Th>
                      <Table.Th>{copy.columns.status}</Table.Th>
                    </Table.Tr>
                  </Table.Thead>
                  <Table.Tbody>
                    {serviceTypes.map((service) => (
                      <Table.Tr key={service.id}>
                        <Table.Td>
                          <Text fw={600} size="sm">
                            {service.code}
                          </Text>
                        </Table.Td>
                        <Table.Td>{service.name}</Table.Td>
                        <Table.Td>{service.durationMinutes} min</Table.Td>
                        <Table.Td>{bayTypeLabel(service.requiredBayType)}</Table.Td>
                        <Table.Td>{levelLabel(service.requiredLevel)}</Table.Td>
                        <Table.Td>
                          <Badge
                            color={service.active ? 'green' : 'gray'}
                            variant="light"
                            size="sm"
                          >
                            {service.active ? copy.status.active : copy.status.inactive}
                          </Badge>
                        </Table.Td>
                      </Table.Tr>
                    ))}
                  </Table.Tbody>
                </Table>
              </Table.ScrollContainer>
            )}
          </Tabs.Panel>

          {/* Bays tab panel (placeholder for Ticket 07) */}
          <Tabs.Panel value="bays" pt="md">
            <Text c="dimmed" py="md">
              {copy.tabs.bays} view will be loaded here.
            </Text>
          </Tabs.Panel>

          {/* Technicians tab panel (placeholder for Ticket 07 & 10) */}
          <Tabs.Panel value="technicians" pt="md">
            <Text c="dimmed" py="md">
              {copy.tabs.technicians} view will be loaded here.
            </Text>
          </Tabs.Panel>

          {/* Opening hours tab panel (placeholder for Ticket 08) */}
          <Tabs.Panel value="opening-hours" pt="md">
            <Text c="dimmed" py="md">
              {copy.tabs.openingHours} view will be loaded here.
            </Text>
          </Tabs.Panel>
        </Tabs>
      </Stack>
    </Container>
  )
}
