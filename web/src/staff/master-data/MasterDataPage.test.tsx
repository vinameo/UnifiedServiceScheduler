import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { renderRoute } from '../../test/render'
import { apiMock } from '../../test/apiMock'

describe('MasterDataPage', () => {
  const dealershipId = '0192d111-0000-7000-8000-000000000001'

  beforeEach(() => {
    apiMock.install()
    apiMock.reset()
  })

  afterEach(() => {
    apiMock.restore()
  })

  function setupStandardMocks(options?: {
    technicians?: () => Array<{ id: string; name: string; level: number; active: boolean }>
  }) {
    apiMock.get('/me', [
      200,
      {
        id: 1,
        email: 'admin@saigonauto.test',
        name: 'Nguyễn Văn Quản Lý',
        role: 'ADMIN',
        dealership: {
          id: dealershipId,
          code: 'SGA-D7',
          name: 'Saigon Auto',
          branchName: 'District 7',
          timezone: 'Asia/Ho_Chi_Minh',
          phone: '028 3812 3456',
        },
        customer: null,
      },
    ])

    apiMock.get(`/dealerships/${dealershipId}`, [
      200,
      {
        data: {
          id: dealershipId,
          code: 'SGA-D7',
          name: 'Saigon Auto',
          branchName: 'District 7',
          timezone: 'Asia/Ho_Chi_Minh',
          phone: '028 3812 3456',
          slotStepMinutes: 15,
          openingHours: [
            { weekday: 1, opensAt: '08:00', closesAt: '17:00' },
            { weekday: 2, opensAt: '08:00', closesAt: '17:00' },
            { weekday: 3, opensAt: '08:00', closesAt: '17:00' },
            { weekday: 4, opensAt: '08:00', closesAt: '17:00' },
            { weekday: 5, opensAt: '08:00', closesAt: '17:00' },
            { weekday: 6, opensAt: '08:00', closesAt: '12:00' },
          ],
        },
      },
    ])

    apiMock.get(`/dealerships/${dealershipId}/service-types`, [
      200,
      {
        data: [
          {
            id: 'srv-1',
            code: 'OIL_CHANGE',
            name: 'Oil change',
            description: 'Standard engine oil and filter replacement',
            durationMinutes: 60,
            requiredLevel: 1,
            requiredBayType: 'QUICK',
            active: true,
          },
          {
            id: 'srv-2',
            code: 'MAINT_20K',
            name: '20,000 km maintenance',
            description: 'Periodic inspection and maintenance',
            durationMinutes: 120,
            requiredLevel: 3,
            requiredBayType: 'STANDARD',
            active: true,
          },
          {
            id: 'srv-3',
            code: 'EV_BATTERY',
            name: 'EV battery health check',
            description: 'Diagnostic and cooling check for EV pack',
            durationMinutes: 90,
            requiredLevel: 5,
            requiredBayType: 'EV',
            active: true,
          },
        ],
      },
    ])

    apiMock.get(`/dealerships/${dealershipId}/bays`, [
      200,
      {
        data: [
          { id: 'bay-1', code: 'K1', name: 'Bay 1', bayType: 'QUICK', active: true, supportedServiceTypeIds: ['srv-1'] },
          { id: 'bay-2', code: 'K2', name: 'Bay 2', bayType: 'QUICK', active: true, supportedServiceTypeIds: ['srv-1'] },
          { id: 'bay-3', code: 'K3', name: 'Bay 3', bayType: 'STANDARD', active: true, supportedServiceTypeIds: ['srv-2'] },
          { id: 'bay-4', code: 'KEV', name: 'Bay EV', bayType: 'EV', active: true, supportedServiceTypeIds: ['srv-3'] },
        ],
      },
    ])

    if (options?.technicians) {
      apiMock.get(`/dealerships/${dealershipId}/technicians`, () => [
        200,
        { data: options.technicians!() },
      ])
    } else {
      apiMock.get(`/dealerships/${dealershipId}/technicians`, [
        200,
        {
          data: [
            { id: 'tech-1', name: 'Trần Minh An', level: 2, active: true },
            { id: 'tech-2', name: 'Lý Hoàng Dũng', level: 3, active: true },
            { id: 'tech-3', name: 'Võ Thị Chi', level: 4, active: true },
            { id: 'tech-4', name: 'Ngô Bảo Em', level: 6, active: true },
            { id: 'tech-5', name: 'Huỳnh Tấn Phát', level: 1, active: false },
          ],
        },
      ])
    }
  }

  it('TC-01-11: renders 4 tabs with counts 3/4/5, read-only badges and seed callout without holiday text', async () => {
    setupStandardMocks()

    renderRoute('/staff/master-data')

    // 1. Heading and branch info
    await waitFor(() => {
      expect(screen.getByRole('heading', { name: 'Master data' })).toBeInTheDocument()
    })
    expect(
      screen.getByText('Services, bays, technicians and opening hours that allocation relies on.'),
    ).toBeInTheDocument()
    expect(screen.getByText('Saigon Auto · District 7 branch.')).toBeInTheDocument()

    // 2. Read only badge & notices
    expect(screen.getByText('Read only')).toBeInTheDocument()
    expect(screen.getByText('Only technician status can be changed here.')).toBeInTheDocument()
    expect(screen.getByText('This version loads this data from seed files')).toBeInTheDocument()
    expect(
      screen.getByText(
        /This screen is for viewing and checking only\. To add or change data, edit the seed file and run the seeder\./,
      ),
    ).toBeInTheDocument()

    // 3. No "holiday" text anywhere on the page
    expect(screen.queryByText(/holiday/i)).not.toBeInTheDocument()

    // 4. 4 Tabs with counts 3 / 4 / 5 and Opening hours
    const serviceTab = screen.getByRole('tab', { name: /Service types/i })
    const baysTab = screen.getByRole('tab', { name: /Bays/i })
    const techTab = screen.getByRole('tab', { name: /Technicians/i })
    const openingTab = screen.getByRole('tab', { name: /Opening hours/i })

    expect(serviceTab).toBeInTheDocument()
    expect(baysTab).toBeInTheDocument()
    expect(techTab).toBeInTheDocument()
    expect(openingTab).toBeInTheDocument()

    // Assert tab counts
    await waitFor(() => {
      expect(serviceTab).toHaveTextContent('3')
      expect(baysTab).toHaveTextContent('4')
      expect(techTab).toHaveTextContent('5')
    })

    // 5. Service types table rows
    expect(screen.getByText('OIL_CHANGE')).toBeInTheDocument()
    expect(screen.getByText('Oil change')).toBeInTheDocument()
    expect(screen.getByText('60 min')).toBeInTheDocument()
    expect(screen.getByText('Quick service')).toBeInTheDocument()
    expect(screen.getByText('Level 1')).toBeInTheDocument()

    expect(screen.getByText('MAINT_20K')).toBeInTheDocument()
    expect(screen.getByText('20,000 km maintenance')).toBeInTheDocument()
    expect(screen.getByText('120 min')).toBeInTheDocument()
    expect(screen.getByText('Standard service')).toBeInTheDocument()
    expect(screen.getByText('Level 3')).toBeInTheDocument()

    expect(screen.getByText('EV_BATTERY')).toBeInTheDocument()
    expect(screen.getByText('EV battery health check')).toBeInTheDocument()
    expect(screen.getByText('90 min')).toBeInTheDocument()
    expect(screen.getByText('EV')).toBeInTheDocument()
    expect(screen.getByText('Level 5')).toBeInTheDocument()

    const activeBadges = screen.getAllByText('Active')
    expect(activeBadges.length).toBeGreaterThanOrEqual(3)
  })

  it('renders Bays tab with code, name, bay type, supported services, and status badges', async () => {
    setupStandardMocks()
    const user = userEvent.setup()

    renderRoute('/staff/master-data')

    const baysTab = await screen.findByRole('tab', { name: /Bays/i })
    await user.click(baysTab)

    const baysPanel = await screen.findByRole('tabpanel')
    const panel = within(baysPanel)

    // Column headers
    expect(panel.getByRole('columnheader', { name: 'Code' })).toBeInTheDocument()
    expect(panel.getByRole('columnheader', { name: 'Bay name' })).toBeInTheDocument()
    expect(panel.getByRole('columnheader', { name: 'Bay type' })).toBeInTheDocument()
    expect(panel.getByRole('columnheader', { name: 'Supported services' })).toBeInTheDocument()
    expect(panel.getByRole('columnheader', { name: 'Status' })).toBeInTheDocument()

    // Row contents
    expect(panel.getByText('K1')).toBeInTheDocument()
    expect(panel.getByText('Bay 1')).toBeInTheDocument()
    expect(panel.getByText('K2')).toBeInTheDocument()
    expect(panel.getByText('Bay 2')).toBeInTheDocument()
    expect(panel.getByText('K3')).toBeInTheDocument()
    expect(panel.getByText('Bay 3')).toBeInTheDocument()
    expect(panel.getByText('KEV')).toBeInTheDocument()
    expect(panel.getByText('Bay EV')).toBeInTheDocument()

    // Bay types
    const quickTypes = panel.getAllByText('Quick service')
    expect(quickTypes).toHaveLength(2)
    expect(panel.getByText('Standard service')).toBeInTheDocument()
    expect(panel.getByText('EV')).toBeInTheDocument()

    // Supported services
    const oilChanges = panel.getAllByText('Oil change')
    expect(oilChanges).toHaveLength(2)
    expect(panel.getByText('20,000 km maintenance')).toBeInTheDocument()
    expect(panel.getByText('EV battery health check')).toBeInTheDocument()

    // Status badges
    const activeBadges = panel.getAllByText('Active')
    expect(activeBadges).toHaveLength(4)
  })

  it('renders Technicians tab with levels, working/not taking bookings status, and action buttons without shift or skills columns', async () => {
    setupStandardMocks()
    const user = userEvent.setup()

    renderRoute('/staff/master-data')

    const techTab = await screen.findByRole('tab', { name: /Technicians/i })
    await user.click(techTab)

    const techPanel = await screen.findByRole('tabpanel')
    const panel = within(techPanel)

    // Column headers
    expect(panel.getByRole('columnheader', { name: 'Technician' })).toBeInTheDocument()
    expect(panel.getByRole('columnheader', { name: 'Level' })).toBeInTheDocument()
    expect(panel.getByRole('columnheader', { name: 'Status' })).toBeInTheDocument()
    expect(panel.getByRole('columnheader', { name: 'Action' })).toBeInTheDocument()

    // Assert no "Shift" or "Skills" columns exist (AC-01.6, D-01.10)
    expect(panel.queryByRole('columnheader', { name: /Shift/i })).not.toBeInTheDocument()
    expect(panel.queryByRole('columnheader', { name: /Skills/i })).not.toBeInTheDocument()

    // Technician names
    expect(panel.getByText('Trần Minh An')).toBeInTheDocument()
    expect(panel.getByText('Lý Hoàng Dũng')).toBeInTheDocument()
    expect(panel.getByText('Võ Thị Chi')).toBeInTheDocument()
    expect(panel.getByText('Ngô Bảo Em')).toBeInTheDocument()
    expect(panel.getByText('Huỳnh Tấn Phát')).toBeInTheDocument()

    // Levels
    expect(panel.getByText('Level 2')).toBeInTheDocument()
    expect(panel.getByText('Level 3')).toBeInTheDocument()
    expect(panel.getByText('Level 4')).toBeInTheDocument()
    expect(panel.getByText('Level 6')).toBeInTheDocument()
    expect(panel.getByText('Level 1')).toBeInTheDocument()

    // Status
    const workingBadges = panel.getAllByText('Working')
    expect(workingBadges).toHaveLength(4)
    expect(panel.getByText('Not taking bookings')).toBeInTheDocument()

    // Action buttons
    const disableButtons = panel.getAllByRole('button', { name: 'Disable' })
    expect(disableButtons).toHaveLength(4)
    const enableButton = panel.getByRole('button', { name: 'Enable' })
    expect(enableButton).toBeInTheDocument()
  })

  it('TC-01-12: verifies Technicians and Opening hours tab formatting per AC-01.6', async () => {
    setupStandardMocks()
    const user = userEvent.setup()

    renderRoute('/staff/master-data')

    // 1. Open Technicians tab
    const techTab = await screen.findByRole('tab', { name: /Technicians/i })
    await user.click(techTab)

    const techPanel = await screen.findByRole('tabpanel')
    const panelTech = within(techPanel)

    // Assert Level 6, Not taking bookings, no Shift or Skills column
    expect(panelTech.getByText('Level 6')).toBeInTheDocument()
    expect(panelTech.getByText('Not taking bookings')).toBeInTheDocument()
    expect(panelTech.queryByRole('columnheader', { name: /Shift/i })).not.toBeInTheDocument()
    expect(panelTech.queryByRole('columnheader', { name: /Skills/i })).not.toBeInTheDocument()

    // 2. Open Opening hours tab
    const openingTab = await screen.findByRole('tab', { name: /Opening hours/i })
    await user.click(openingTab)

    const openingPanel = await screen.findByRole('tabpanel')
    const panelHrs = within(openingPanel)

    // Column headers
    expect(panelHrs.getByRole('columnheader', { name: 'Date' })).toBeInTheDocument()
    expect(panelHrs.getByRole('columnheader', { name: 'Opening hours' })).toBeInTheDocument()

    // Opening hours merged rows
    expect(panelHrs.getByText('Monday to Friday')).toBeInTheDocument()
    expect(panelHrs.getByText('08:00–17:00')).toBeInTheDocument()
    expect(panelHrs.getByText('Monday to Friday · 08:00–17:00')).toBeInTheDocument()

    expect(panelHrs.getByText('Saturday')).toBeInTheDocument()
    expect(panelHrs.getByText('08:00–12:00')).toBeInTheDocument()
    expect(panelHrs.getByText('Saturday · 08:00–12:00')).toBeInTheDocument()

    expect(panelHrs.getByText('Sunday')).toBeInTheDocument()
    expect(panelHrs.getByText('Closed')).toBeInTheDocument()
    expect(panelHrs.getByText('Sunday · Closed')).toBeInTheDocument()

    // Time zone UTC+7, slot step 15 min, fit rule
    expect(panelHrs.getByText('Time zone')).toBeInTheDocument()
    expect(panelHrs.getByText('Asia/Ho_Chi_Minh (UTC+7)')).toBeInTheDocument()
    expect(panelHrs.getByText(/UTC\+7/)).toBeInTheDocument()
    expect(panelHrs.getByText('Slot step')).toBeInTheDocument()
    expect(panelHrs.getByText('15 min')).toBeInTheDocument()
    expect(
      panelHrs.getByText(
        'Appointments must fit entirely within opening hours: the end time cannot pass closing time.',
      ),
    ).toBeInTheDocument()

    // No next holiday box
    expect(panelHrs.queryByText(/holiday/i)).not.toBeInTheDocument()
  })

  it('TC-01-21: disable refused with 409 shows blocked appointments and enable succeeds (WEB-01.5)', async () => {
    let technicians = [
      { id: 'tech-1', name: 'Trần Minh An', level: 2, active: true },
      { id: 'tech-2', name: 'Lý Hoàng Dũng', level: 3, active: true },
      { id: 'tech-3', name: 'Võ Thị Chi', level: 4, active: true },
      { id: 'tech-4', name: 'Ngô Bảo Em', level: 6, active: true },
      { id: 'tech-5', name: 'Huỳnh Tấn Phát', level: 1, active: false },
    ]

    setupStandardMocks({
      technicians: () => technicians,
    })

    const appointments = Array.from({ length: 20 }, (_, i) => {
      const num = (i + 1).toString().padStart(4, '0')
      return {
        id: `apt-${i + 1}`,
        reference: `APT-20261103-${num}`,
        start: '2026-11-03T09:00:00+07:00',
        end: '2026-11-03T10:00:00+07:00',
        customerName: `Customer ${i + 1}`,
      }
    })

    // Mock disable tech-3 (Võ Thị Chi) -> 409 TECHNICIAN_HAS_BOOKINGS with count: 23, 20 appointments
    apiMock.put(`/dealerships/${dealershipId}/technicians/tech-3/status`, () =>
      apiMock.replyProblem(
        409,
        'TECHNICIAN_HAS_BOOKINGS',
        'Technician has upcoming appointments.',
        {
          detail: 'Server internal detail that must never be displayed in the UI',
          count: 23,
          appointments,
        },
      ),
    )

    // Mock enable tech-5 (Huỳnh Tấn Phát) -> 200
    apiMock.put(`/dealerships/${dealershipId}/technicians/tech-5/status`, () => {
      technicians = technicians.map((t) =>
        t.id === 'tech-5' ? { ...t, active: true } : t,
      )
      return [
        200,
        { data: { id: 'tech-5', name: 'Huỳnh Tấn Phát', level: 1, active: true } },
      ]
    })

    const user = userEvent.setup()
    renderRoute('/staff/master-data')

    // Navigate to Technicians tab
    const techTab = await screen.findByRole('tab', { name: /Technicians/i })
    await user.click(techTab)

    // 1. Click "Disable" on row Võ Thị Chi
    const rowChi = screen.getByText('Võ Thị Chi').closest('tr')!
    const disableChiBtn = within(rowChi).getByRole('button', { name: 'Disable' })
    await user.click(disableChiBtn)

    // Confirm dialog opens
    expect(screen.getByRole('dialog')).toBeInTheDocument()
    expect(screen.getByText('Disable Võ Thị Chi?')).toBeInTheDocument()
    expect(
      screen.getByText(
        'Võ Thị Chi will stop getting new appointments. You can enable them again at any time.',
      ),
    ).toBeInTheDocument()

    // 2. Click confirm "Disable technician"
    const confirmBtn = screen.getByRole('button', { name: 'Disable technician' })
    await user.click(confirmBtn)

    // 3. Modal transitions to blocked view
    // Verifies: disable.blocked copy with count: 23
    await waitFor(() => {
      expect(
        screen.getByText(
          'Võ Thị Chi still has 23 upcoming appointments. Move or cancel them before disabling.',
        ),
      ).toBeInTheDocument()
    })

    // Assert server detail is NOT shown (fails if server detail shown)
    expect(
      screen.queryByText(/Server internal detail that must never be displayed/),
    ).not.toBeInTheDocument()

    // Assert 20 references exist with links to /staff/appointments/{id}
    for (let i = 1; i <= 20; i++) {
      const num = i.toString().padStart(4, '0')
      const ref = `APT-20261103-${num}`
      const link = screen.getByRole('link', { name: ref })
      expect(link).toBeInTheDocument()
      expect(link).toHaveAttribute('href', `/staff/appointments/apt-${i}`)
    }

    // Assert "and 3 more" (since count: 23 and appointments.length: 20)
    expect(screen.getByText('and 3 more')).toBeInTheDocument()

    // Assert only a "Close" button is present in the dialog actions
    expect(screen.queryByRole('button', { name: 'Disable technician' })).not.toBeInTheDocument()
    const closeBtn = screen.getByRole('button', { name: 'Close' })
    expect(closeBtn).toBeInTheDocument()

    // 4. Click Close
    await user.click(closeBtn)
    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    })

    // Assert row unchanged (Võ Thị Chi still active/Working)
    const rowChiAfter = screen.getByText('Võ Thị Chi').closest('tr')!
    expect(within(rowChiAfter).getByText('Working')).toBeInTheDocument()
    expect(within(rowChiAfter).getByRole('button', { name: 'Disable' })).toBeInTheDocument()

    // 5. Click "Enable" on disabled row (Huỳnh Tấn Phát)
    const rowPhat = screen.getByText('Huỳnh Tấn Phát').closest('tr')!
    expect(within(rowPhat).getByText('Not taking bookings')).toBeInTheDocument()
    const enablePhatBtn = within(rowPhat).getByRole('button', { name: 'Enable' })
    await user.click(enablePhatBtn)

    // 6. Assert notification "Huỳnh Tấn Phát takes bookings again."
    await waitFor(() => {
      expect(
        screen.getByText('Huỳnh Tấn Phát takes bookings again.'),
      ).toBeInTheDocument()
    })

    // Row updates after refetch to active / Working / Disable button
    await waitFor(() => {
      const rowPhatUpdated = screen.getByText('Huỳnh Tấn Phát').closest('tr')!
      expect(within(rowPhatUpdated).getByText('Working')).toBeInTheDocument()
      expect(within(rowPhatUpdated).getByRole('button', { name: 'Disable' })).toBeInTheDocument()
    })
  })

  it('disables an active technician with confirmation dialog and shows success notification (WEB-01.5)', async () => {
    let technicians = [
      { id: 'tech-1', name: 'Trần Minh An', level: 2, active: true },
      { id: 'tech-2', name: 'Lý Hoàng Dũng', level: 3, active: true },
    ]

    setupStandardMocks({
      technicians: () => technicians,
    })

    apiMock.put(`/dealerships/${dealershipId}/technicians/tech-1/status`, () => {
      technicians = technicians.map((t) =>
        t.id === 'tech-1' ? { ...t, active: false } : t,
      )
      return [
        200,
        { data: { id: 'tech-1', name: 'Trần Minh An', level: 2, active: false } },
      ]
    })

    const user = userEvent.setup()
    renderRoute('/staff/master-data')

    const techTab = await screen.findByRole('tab', { name: /Technicians/i })
    await user.click(techTab)

    const rowAn = screen.getByText('Trần Minh An').closest('tr')!
    const disableBtn = within(rowAn).getByRole('button', { name: 'Disable' })
    await user.click(disableBtn)

    expect(screen.getByRole('dialog')).toBeInTheDocument()
    expect(screen.getByText('Disable Trần Minh An?')).toBeInTheDocument()

    const confirmBtn = screen.getByRole('button', { name: 'Disable technician' })
    await user.click(confirmBtn)

    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    })

    expect(
      await screen.findByText('Trần Minh An no longer takes bookings.'),
    ).toBeInTheDocument()

    await waitFor(() => {
      const rowAnUpdated = screen.getByText('Trần Minh An').closest('tr')!
      expect(within(rowAnUpdated).getByText('Not taking bookings')).toBeInTheDocument()
      expect(within(rowAnUpdated).getByRole('button', { name: 'Enable' })).toBeInTheDocument()
    })
  })
})
