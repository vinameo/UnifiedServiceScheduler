import { Box, Group, Text, Tooltip } from '@mantine/core'
import { Link } from 'react-router'
import type { WorkshopBay, WorkshopBlock, WorkshopBoardData } from '../../shared/api/workshop'
import { formatInZone } from '../../shared/format/time'

export interface BayTimelineProps {
  board?: WorkshopBoardData
  bays?: WorkshopBay[]
  bay?: WorkshopBay
  opensAt?: string
  closesAt?: string
  now?: string
  timeZone?: string
}

function getTimeInZoneMinutes(iso: string, timeZone: string): number {
  const date = new Date(iso)
  const parts = new Intl.DateTimeFormat('en-GB', {
    timeZone,
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
  }).formatToParts(date)
  const hour = parseInt(parts.find((p) => p.type === 'hour')?.value ?? '0', 10)
  const minute = parseInt(parts.find((p) => p.type === 'minute')?.value ?? '0', 10)
  return hour * 60 + minute
}

function parseTimeToMinutes(timeStr: string): number {
  const [h, m] = timeStr.split(':').map((s) => parseInt(s, 10))
  return (h ?? 0) * 60 + (m ?? 0)
}

const STATUS_BG: Record<string, string> = {
  HELD: 'var(--mantine-color-yellow-6)',
  CONFIRMED: 'var(--mantine-color-blue-6)',
  CHECKED_IN: 'var(--mantine-color-cyan-6)',
  IN_PROGRESS: 'var(--mantine-color-indigo-6)',
  COMPLETED: 'var(--mantine-color-green-6)',
}

const OVERRUN_HATCHED_BACKGROUND =
  'repeating-linear-gradient(45deg, #fa5252 0px, #fa5252 6px, #c92a2a 6px, #c92a2a 12px)'

export function BayTimeline({
  board,
  bays: propBays,
  bay: propBay,
  opensAt: propOpensAt,
  closesAt: propClosesAt,
  now: propNow,
  timeZone: propTimeZone,
}: BayTimelineProps) {
  const bays = propBays ?? (propBay ? [propBay] : (board?.bays ?? []))
  const opensAt = propOpensAt ?? board?.opensAt ?? '08:00'
  const closesAt = propClosesAt ?? board?.closesAt ?? '17:00'
  const now = propNow ?? board?.now ?? new Date().toISOString()
  const timeZone = propTimeZone ?? 'Asia/Ho_Chi_Minh'

  const startTimelineMinutes = parseTimeToMinutes(opensAt)
  const endTimelineMinutes = parseTimeToMinutes(closesAt)
  const totalTimelineMinutes = Math.max(1, endTimelineMinutes - startTimelineMinutes)

  const openHour = Math.floor(startTimelineMinutes / 60)
  const closeHour = Math.ceil(endTimelineMinutes / 60)

  const hourColumns: { hour: number; label: string; leftPercent: number; widthPercent: number }[] = []
  for (let h = openHour; h < closeHour; h++) {
    const hourStartMin = h * 60
    const hourEndMin = (h + 1) * 60
    const colStart = Math.max(startTimelineMinutes, hourStartMin)
    const colEnd = Math.min(endTimelineMinutes, hourEndMin)
    const leftPercent = ((colStart - startTimelineMinutes) / totalTimelineMinutes) * 100
    const widthPercent = ((colEnd - colStart) / totalTimelineMinutes) * 100
    hourColumns.push({
      hour: h,
      label: String(h).padStart(2, '0'),
      leftPercent,
      widthPercent,
    })
  }

  const nowMinutes = getTimeInZoneMinutes(now, timeZone)
  const nowInTimeline = nowMinutes >= startTimelineMinutes && nowMinutes <= endTimelineMinutes
  const nowLeftPercent = ((nowMinutes - startTimelineMinutes) / totalTimelineMinutes) * 100

  return (
    <Box
      data-testid="bay-timeline"
      style={{
        overflowX: 'auto',
        borderRadius: 8,
        border: '1px solid var(--mantine-color-default-border)',
        backgroundColor: 'var(--mantine-color-body)',
      }}
    >
      <Box style={{ minWidth: 720 }}>
        {/* Timeline Header Row with Hours */}
        <Group gap={0} wrap="nowrap" style={{ borderBottom: '1px solid var(--mantine-color-default-border)' }}>
          <Box
            style={{
              width: 160,
              flexShrink: 0,
              padding: '8px 12px',
              borderRight: '1px solid var(--mantine-color-default-border)',
              backgroundColor: 'var(--mantine-color-gray-0)',
            }}
          >
            <Text size="xs" fw={700} c="dimmed">
              BAY
            </Text>
          </Box>
          <Box style={{ position: 'relative', flex: 1, height: 32 }}>
            {hourColumns.map((col) => (
              <Box
                key={col.hour}
                data-testid={`hour-column-${col.label}`}
                data-hour={col.label}
                style={{
                  position: 'absolute',
                  left: `${col.leftPercent}%`,
                  width: `${col.widthPercent}%`,
                  top: 0,
                  bottom: 0,
                  borderLeft: '1px solid var(--mantine-color-default-border)',
                  display: 'flex',
                  alignItems: 'center',
                  paddingLeft: 6,
                  backgroundColor: 'var(--mantine-color-gray-0)',
                }}
              >
                <Text size="xs" fw={600} c="dimmed">
                  {col.label}
                </Text>
              </Box>
            ))}
          </Box>
        </Group>

        {/* Bays rows container */}
        <Box style={{ position: 'relative' }}>
          {/* Vertical Now-Line stretching over all bay rows */}
          {nowInTimeline && (
            <Box
              data-testid="now-line"
              data-now-time={formatInZone(now, timeZone, 'time')}
              style={{
                position: 'absolute',
                top: 0,
                bottom: 0,
                left: `${nowLeftPercent}%`,
                width: 2,
                backgroundColor: 'var(--mantine-color-red-6)',
                zIndex: 10,
                pointerEvents: 'none',
              }}
              title={`Now: ${formatInZone(now, timeZone, 'time')}`}
            />
          )}

          {bays.map((bay, idx) => {
            // Cancelled and no-show appointments are absent (WEB-12.5, TC-12-24)
            const visibleBlocks: WorkshopBlock[] = (bay.blocks ?? []).filter(
              (b) => b.status !== 'CANCELLED' && b.status !== 'NO_SHOW' && b.status !== 'EXPIRED',
            )

            return (
              <Group
                key={bay.id || idx}
                gap={0}
                wrap="nowrap"
                style={{
                  borderBottom:
                    idx < bays.length - 1 ? '1px solid var(--mantine-color-default-border)' : undefined,
                  minHeight: 52,
                }}
              >
                {/* Bay label */}
                <Box
                  style={{
                    width: 160,
                    flexShrink: 0,
                    padding: '8px 12px',
                    borderRight: '1px solid var(--mantine-color-default-border)',
                    display: 'flex',
                    flexDirection: 'column',
                    justifyContent: 'center',
                  }}
                >
                  <Text size="sm" fw={600}>
                    {bay.name}
                  </Text>
                  {bay.bayType && (
                    <Text size="xs" c="dimmed">
                      {bay.bayType}
                    </Text>
                  )}
                </Box>

                {/* Timeline track */}
                <Box
                  data-testid={`bay-track-${bay.code || bay.name}`}
                  style={{ position: 'relative', flex: 1, minHeight: 52 }}
                >
                  {/* Grid hour background lines */}
                  {hourColumns.map((col) => (
                    <Box
                      key={col.hour}
                      style={{
                        position: 'absolute',
                        left: `${col.leftPercent}%`,
                        width: `${col.widthPercent}%`,
                        top: 0,
                        bottom: 0,
                        borderLeft: '1px solid var(--mantine-color-default-border)',
                        pointerEvents: 'none',
                      }}
                    />
                  ))}

                  {/* Render blocks */}
                  {visibleBlocks.map((block) => {
                    const startMin = getTimeInZoneMinutes(block.start, timeZone)
                    const endMin = getTimeInZoneMinutes(block.end, timeZone)
                    const scheduledDuration = Math.max(1, endMin - startMin)

                    const isOverrun =
                      (block.overrunMinutes !== undefined && block.overrunMinutes > 0) ||
                      (block.status === 'IN_PROGRESS' && nowMinutes > endMin)

                    const overrunMinutes =
                      block.overrunMinutes !== undefined && block.overrunMinutes > 0
                        ? block.overrunMinutes
                        : isOverrun
                          ? Math.max(0, nowMinutes - endMin)
                          : 0

                    const totalBlockMinutes = scheduledDuration + (isOverrun ? overrunMinutes : 0)

                    const blockLeftPercent =
                      ((startMin - startTimelineMinutes) / totalTimelineMinutes) * 100
                    const blockWidthPercent = (totalBlockMinutes / totalTimelineMinutes) * 100

                    const scheduledWidthPercent = isOverrun
                      ? (scheduledDuration / totalBlockMinutes) * 100
                      : 100
                    const overrunWidthPercent = isOverrun
                      ? (overrunMinutes / totalBlockMinutes) * 100
                      : 0

                    const label = block.technicianName || block.reference || 'Booking'
                    const tooltipText = `${block.customerName || 'Customer'} · ${block.serviceName || 'Service'} · ${block.technicianName || 'Technician'} (${formatInZone(block.start, timeZone, 'time')}–${formatInZone(block.end, timeZone, 'time')})`

                    return (
                      <Tooltip key={block.appointmentId} label={tooltipText} withArrow>
                        <Box
                          component={Link}
                          to={`/staff/appointments/${block.appointmentId}`}
                          data-testid="timeline-block"
                          data-reference={block.reference}
                          data-status={block.status}
                          data-appointment-id={block.appointmentId}
                          style={{
                            position: 'absolute',
                            left: `${blockLeftPercent}%`,
                            width: `${blockWidthPercent}%`,
                            top: 6,
                            bottom: 6,
                            borderRadius: 4,
                            overflow: 'hidden',
                            display: 'flex',
                            textDecoration: 'none',
                            boxShadow: '0 1px 3px rgba(0, 0, 0, 0.1)',
                            zIndex: 2,
                            cursor: 'pointer',
                          }}
                        >
                          {/* Scheduled block segment */}
                          <Box
                            style={{
                              width: `${scheduledWidthPercent}%`,
                              backgroundColor: STATUS_BG[block.status] ?? 'var(--mantine-color-blue-6)',
                              color: '#ffffff',
                              padding: '2px 6px',
                              display: 'flex',
                              alignItems: 'center',
                              overflow: 'hidden',
                              whiteSpace: 'nowrap',
                              textOverflow: 'ellipsis',
                            }}
                          >
                            <Text size="xs" fw={600} c="white" truncate>
                              {label}
                            </Text>
                          </Box>

                          {/* Overrun segment: hatched red (diagonal stripes) */}
                          {isOverrun && (
                            <Box
                              data-testid="overrun-part"
                              data-overrun="true"
                              style={{
                                width: `${overrunWidthPercent}%`,
                                background: OVERRUN_HATCHED_BACKGROUND,
                                height: '100%',
                                flexShrink: 0,
                              }}
                              title={`Overrun: ${overrunMinutes} min`}
                            />
                          )}
                        </Box>
                      </Tooltip>
                    )
                  })}
                </Box>
              </Group>
            )
          })}
        </Box>
      </Box>
    </Box>
  )
}
