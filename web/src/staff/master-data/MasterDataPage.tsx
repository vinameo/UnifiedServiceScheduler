import {
  Alert,
  Anchor,
  Badge,
  Box,
  Button,
  Container,
  Group,
  Modal,
  Skeleton,
  Stack,
  Table,
  Tabs,
  Text,
  Title,
} from '@mantine/core'
import { notifications } from '@mantine/notifications'
import { useMemo, useState } from 'react'
import { Link } from 'react-router'
import { useMe } from '../../shared/auth/useMe'
import {
  useBays,
  useDealership,
  useServiceTypes,
  useTechnicians,
  useSetTechnicianActive,
  type Technician,
  type TechnicianAppointmentPreview,
} from '../../shared/api/masterData'
import { toProblem } from '../../shared/api/problem'
import { formatAppointmentSlot } from '../../shared/format/time'
import { bayTypeLabel, levelLabel } from '../../shared/copy/enums'
import { formatTimezoneOffset, groupWeekdays } from '../../shared/format/weekdays'
import { masterDataCopy as copy } from './copy'

export function MasterDataPage() {
  const { data: me } = useMe()
  const dealershipId = me?.dealership?.id

  const {
    data: dealership,
    isLoading: isLoadingDealership,
    isError: isErrorDealership,
  } = useDealership(dealershipId)
  const {
    data: serviceTypes = [],
    isLoading: isLoadingServices,
    isError: isErrorServices,
  } = useServiceTypes(dealershipId)
  const {
    data: bays = [],
    isLoading: isLoadingBays,
    isError: isErrorBays,
  } = useBays(dealershipId)
  const {
    data: technicians = [],
    isLoading: isLoadingTechnicians,
    isError: isErrorTechnicians,
  } = useTechnicians(dealershipId)

  const [activeTab, setActiveTab] = useState<string | null>('service-types')
  const [technicianToDisable, setTechnicianToDisable] = useState<Technician | null>(null)
  const [blockedInfo, setBlockedInfo] = useState<{
    count: number
    appointments: TechnicianAppointmentPreview[]
  } | null>(null)

  const setTechnicianActiveMutation = useSetTechnicianActive(dealershipId)

  const handleOpenDisableModal = (tech: Technician) => {
    setBlockedInfo(null)
    setTechnicianToDisable(tech)
  }

  const handleCloseModal = () => {
    setTechnicianToDisable(null)
    setBlockedInfo(null)
  }

  const handleConfirmDisable = async () => {
    if (!technicianToDisable) return
    try {
      await setTechnicianActiveMutation.mutateAsync({
        technicianId: technicianToDisable.id,
        active: false,
      })
      notifications.show({
        message: copy.disable.notification(technicianToDisable.name),
        color: 'green',
      })
      handleCloseModal()
    } catch (err: unknown) {
      const problem = toProblem(err)
      if (problem?.code === 'TECHNICIAN_HAS_BOOKINGS') {
        const count = typeof problem.count === 'number' ? problem.count : 0
        const appointments = Array.isArray(problem.appointments)
          ? (problem.appointments as TechnicianAppointmentPreview[])
          : []
        setBlockedInfo({ count, appointments })
      }
    }
  }

  const handleEnableTechnician = async (tech: Technician) => {
    try {
      await setTechnicianActiveMutation.mutateAsync({
        technicianId: tech.id,
        active: true,
      })
      notifications.show({
        message: copy.enable.notification(tech.name),
        color: 'green',
      })
    } catch {
      // ignore
    }
  }

  const openingHours = dealership?.openingHours
  const openingHoursGroups = useMemo(() => {
    if (!openingHours) return []
    return groupWeekdays(openingHours, { format: 'long', includeClosed: true })
  }, [openingHours])

  const isAdmin = me?.role === 'ADMIN'
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

          {/* Bays tab panel */}
          <Tabs.Panel value="bays" pt="md">
            {isLoadingBays ? (
              <Skeleton height={200} radius="md" />
            ) : isErrorBays ? (
              <Alert color="red" title="Error">
                Failed to load bays.
              </Alert>
            ) : bays.length === 0 ? (
              <Text c="dimmed" py="xl" ta="center">
                {copy.empty}
              </Text>
            ) : (
              <Table.ScrollContainer minWidth={600}>
                <Table striped highlightOnHover withTableBorder withColumnBorders>
                  <Table.Thead>
                    <Table.Tr>
                      <Table.Th>{copy.columns.bayCode}</Table.Th>
                      <Table.Th>{copy.columns.bayName}</Table.Th>
                      <Table.Th>{copy.columns.bayType}</Table.Th>
                      <Table.Th>{copy.columns.supportedServices}</Table.Th>
                      <Table.Th>{copy.columns.status}</Table.Th>
                    </Table.Tr>
                  </Table.Thead>
                  <Table.Tbody>
                    {bays.map((bay) => {
                      const supportedServiceNames = (bay.supportedServiceTypeIds ?? [])
                        .map((id) => serviceTypes.find((s) => s.id === id)?.name)
                        .filter((name): name is string => Boolean(name))
                        .join(', ')

                      return (
                        <Table.Tr key={bay.id}>
                          <Table.Td>
                            <Text fw={600} size="sm">
                              {bay.code}
                            </Text>
                          </Table.Td>
                          <Table.Td>{bay.name}</Table.Td>
                          <Table.Td>{bayTypeLabel(bay.bayType)}</Table.Td>
                          <Table.Td>{supportedServiceNames || '—'}</Table.Td>
                          <Table.Td>
                            <Badge
                              color={bay.active ? 'green' : 'gray'}
                              variant="light"
                              size="sm"
                            >
                              {bay.active ? copy.status.active : copy.status.inactive}
                            </Badge>
                          </Table.Td>
                        </Table.Tr>
                      )
                    })}
                  </Table.Tbody>
                </Table>
              </Table.ScrollContainer>
            )}
          </Tabs.Panel>

          {/* Technicians tab panel */}
          <Tabs.Panel value="technicians" pt="md">
            {isLoadingTechnicians ? (
              <Skeleton height={200} radius="md" />
            ) : isErrorTechnicians ? (
              <Alert color="red" title="Error">
                Failed to load technicians.
              </Alert>
            ) : technicians.length === 0 ? (
              <Text c="dimmed" py="xl" ta="center">
                {copy.empty}
              </Text>
            ) : (
              <Table.ScrollContainer minWidth={600}>
                <Table striped highlightOnHover withTableBorder withColumnBorders>
                  <Table.Thead>
                    <Table.Tr>
                      <Table.Th>{copy.columns.technician}</Table.Th>
                      <Table.Th>{copy.columns.level}</Table.Th>
                      <Table.Th>{copy.columns.status}</Table.Th>
                      {isAdmin && <Table.Th>{copy.columns.action}</Table.Th>}
                    </Table.Tr>
                  </Table.Thead>
                  <Table.Tbody>
                    {technicians.map((tech) => (
                      <Table.Tr key={tech.id}>
                        <Table.Td>
                          <Text fw={600} size="sm">
                            {tech.name}
                          </Text>
                        </Table.Td>
                        <Table.Td>{levelLabel(tech.level)}</Table.Td>
                        <Table.Td>
                          <Badge
                            color={tech.active ? 'green' : 'gray'}
                            variant="light"
                            size="sm"
                          >
                            {tech.active ? copy.status.working : copy.status.notTaking}
                          </Badge>
                        </Table.Td>
                        {isAdmin && (
                          <Table.Td>
                            <Button
                              size="xs"
                              variant="light"
                              color={tech.active ? 'red' : 'blue'}
                              loading={
                                setTechnicianActiveMutation.isPending &&
                                setTechnicianActiveMutation.variables?.technicianId === tech.id
                              }
                              onClick={() => {
                                if (tech.active) {
                                  handleOpenDisableModal(tech)
                                } else {
                                  handleEnableTechnician(tech)
                                }
                              }}
                            >
                              {tech.active ? copy.disable.label : copy.enable.label}
                            </Button>
                          </Table.Td>
                        )}
                      </Table.Tr>
                    ))}
                  </Table.Tbody>
                </Table>
              </Table.ScrollContainer>
            )}
          </Tabs.Panel>

          {/* Opening hours tab panel */}
          <Tabs.Panel value="opening-hours" pt="md">
            {isLoadingDealership ? (
              <Skeleton height={200} radius="md" />
            ) : isErrorDealership ? (
              <Alert color="red" title="Error">
                Failed to load opening hours.
              </Alert>
            ) : openingHoursGroups.length === 0 ? (
              <Text c="dimmed" py="xl" ta="center">
                {copy.empty}
              </Text>
            ) : (
              <Group align="flex-start" gap="xl" wrap="wrap">
                <Box style={{ flex: '1 1 360px', minWidth: 320 }}>
                  <Table.ScrollContainer minWidth={320}>
                    <Table striped highlightOnHover withTableBorder withColumnBorders>
                      <Table.Thead>
                        <Table.Tr>
                          <Table.Th>{copy.columns.date}</Table.Th>
                          <Table.Th>{copy.columns.openingHours}</Table.Th>
                        </Table.Tr>
                      </Table.Thead>
                      <Table.Tbody>
                        {openingHoursGroups.map((group) => (
                          <Table.Tr key={group.days} aria-label={`${group.days} · ${group.hours}`}>
                            <Table.Td>
                              <span
                                style={{
                                  position: 'absolute',
                                  width: 1,
                                  height: 1,
                                  padding: 0,
                                  margin: -1,
                                  overflow: 'hidden',
                                  clip: 'rect(0, 0, 0, 0)',
                                  whiteSpace: 'nowrap',
                                  border: 0,
                                }}
                              >
                                {group.days} · {group.hours}
                              </span>
                              <Text size="sm">{group.days}</Text>
                            </Table.Td>
                            <Table.Td>
                              {group.isClosed ? (
                                <Badge color="gray" variant="light" size="sm">
                                  {copy.closed}
                                </Badge>
                              ) : (
                                <Text size="sm">{group.hours}</Text>
                              )}
                            </Table.Td>
                          </Table.Tr>
                        ))}
                      </Table.Tbody>
                    </Table>
                  </Table.ScrollContainer>
                </Box>
                <Stack style={{ flex: '1 1 280px', minWidth: 260 }} gap="md">
                  <Box>
                    <Text size="xs" c="dimmed">
                      {copy.timezone}
                    </Text>
                    <Text size="sm" fw={600}>
                      {dealership?.timezone ?? 'Asia/Ho_Chi_Minh'} (
                      {formatTimezoneOffset(dealership?.timezone ?? 'Asia/Ho_Chi_Minh')})
                    </Text>
                  </Box>
                  <Box>
                    <Text size="xs" c="dimmed">
                      {copy.slotStep}
                    </Text>
                    <Text size="sm" fw={600}>
                      {dealership?.slotStepMinutes ?? 15} min
                    </Text>
                  </Box>
                  <Text size="xs" c="dimmed">
                    {copy.fitRule}
                  </Text>
                </Stack>
              </Group>
            )}
          </Tabs.Panel>
        </Tabs>
      </Stack>

      <Modal
        opened={technicianToDisable !== null}
        onClose={handleCloseModal}
        title={technicianToDisable ? copy.disable.title(technicianToDisable.name) : ''}
        centered
        size={blockedInfo ? 'lg' : 'md'}
        transitionProps={{ duration: 0 }}
      >
        {technicianToDisable && (
          <Stack gap="md">
            {blockedInfo ? (
              <>
                <Text size="sm">
                  {copy.disable.blocked(technicianToDisable.name, blockedInfo.count)}
                </Text>

                <Table.ScrollContainer minWidth={400} maxHeight={280}>
                  <Table striped highlightOnHover withTableBorder>
                    <Table.Thead>
                      <Table.Tr>
                        <Table.Th>{copy.columns.reference}</Table.Th>
                        <Table.Th>{copy.columns.time}</Table.Th>
                        <Table.Th>{copy.columns.customer}</Table.Th>
                      </Table.Tr>
                    </Table.Thead>
                    <Table.Tbody>
                      {blockedInfo.appointments.map((apt) => (
                        <Table.Tr key={apt.id}>
                          <Table.Td>
                            <Anchor
                              component={Link}
                              to={`/staff/appointments/${apt.id}`}
                              size="sm"
                              fw={600}
                            >
                              {apt.reference}
                            </Anchor>
                          </Table.Td>
                          <Table.Td>
                            <Text size="sm">
                              {formatAppointmentSlot(
                                apt.start,
                                apt.end,
                                dealership?.timezone ?? 'Asia/Ho_Chi_Minh',
                              )}
                            </Text>
                          </Table.Td>
                          <Table.Td>
                            <Text size="sm">{apt.customerName}</Text>
                          </Table.Td>
                        </Table.Tr>
                      ))}
                    </Table.Tbody>
                  </Table>
                </Table.ScrollContainer>

                {blockedInfo.count > blockedInfo.appointments.length && (
                  <Text size="sm" c="dimmed">
                    {copy.disable.more(blockedInfo.count - blockedInfo.appointments.length)}
                  </Text>
                )}

                <Group justify="flex-end" mt="md">
                  <Button onClick={handleCloseModal}>{copy.actions.close}</Button>
                </Group>
              </>
            ) : (
              <>
                <Text size="sm">{copy.disable.body(technicianToDisable.name)}</Text>
                <Group justify="flex-end" mt="md">
                  <Button variant="default" onClick={handleCloseModal}>
                    {copy.actions.cancel}
                  </Button>
                  <Button
                    color="red"
                    loading={setTechnicianActiveMutation.isPending}
                    onClick={handleConfirmDisable}
                  >
                    {copy.disable.confirm}
                  </Button>
                </Group>
              </>
            )}
          </Stack>
        )}
      </Modal>
    </Container>
  )
}
