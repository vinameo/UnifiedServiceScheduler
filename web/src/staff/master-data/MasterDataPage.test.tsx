import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { screen, waitFor } from '@testing-library/react'
import { renderRoute } from '../../test/render'
import { apiMock } from '../../test/apiMock'

describe('MasterDataPage', () => {
  beforeEach(() => {
    apiMock.install()
    apiMock.reset()
  })

  afterEach(() => {
    apiMock.restore()
  })

  it('TC-01-11: renders 4 tabs with counts 3/4/5, read-only badges and seed callout without holiday text', async () => {
    const dealershipId = '0192d111-0000-7000-8000-000000000001'

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
          { id: 'bay-1', code: 'K1', name: 'Bay 1', bayType: 'QUICK', active: true },
          { id: 'bay-2', code: 'K2', name: 'Bay 2', bayType: 'QUICK', active: true },
          { id: 'bay-3', code: 'K3', name: 'Bay 3', bayType: 'STANDARD', active: true },
          { id: 'bay-4', code: 'KEV', name: 'Bay EV', bayType: 'EV', active: true },
        ],
      },
    ])

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
})
