import { createBrowserRouter, Navigate } from 'react-router'
import { CustomerHomePage } from './customer/CustomerHomePage'
import { SignUpPage } from './customer/auth/SignUpPage'
import { SignInPage } from './customer/auth/SignInPage'
import { ChooseServicePage } from './customer/booking/ChooseServicePage'
import { ChooseTimePage } from './customer/booking/ChooseTimePage'
import { HoldPage } from './customer/booking/HoldPage'
import { CustomerBookingConfirmedPage } from './customer/booking/CustomerBookingConfirmedPage'
import { CustomerBookingLayout } from './customer/booking/CustomerBookingLayout'
import { MyBookingsPage } from './customer/bookings/MyBookingsPage'
import { BookingDetailsPage } from './customer/bookings/BookingDetailsPage'
import { ChangeTimePage } from './customer/bookings/ChangeTimePage'
import { StaffLayout } from './staff/StaffLayout'
import { StaffSignInPage } from './staff/auth/StaffSignInPage'
import { MasterDataPage } from './staff/master-data/MasterDataPage'
import { MonitoringPage } from './staff/monitoring/MonitoringPage'
import { NewBookingPage } from './staff/booking/NewBookingPage'
import { BookingConfirmedPage } from './staff/booking/BookingConfirmedPage'
import { AppointmentListPage } from './staff/appointments/AppointmentListPage'
import { AppointmentDetailsPage } from './staff/appointments/AppointmentDetailsPage'
import { ReschedulePage } from './staff/appointments/ReschedulePage'
import { RequireRole } from './shared/auth/RequireRole'

export const routes = [
  { path: '/', element: <CustomerHomePage /> },
  { path: '/sign-up', element: <SignUpPage /> },
  { path: '/sign-in', element: <SignInPage /> },
  {
    path: '/book',
    element: (
      <RequireRole roles={['CUSTOMER']} signInPath="/sign-in">
        <CustomerBookingLayout />
      </RequireRole>
    ),
    children: [
      { index: true, element: <ChooseServicePage /> },
      { path: 'time', element: <ChooseTimePage /> },
      { path: 'hold/:id', element: <HoldPage /> },
      { path: 'details', element: <Navigate to="/book/time" replace /> },
      { path: 'confirmed/:id', element: <CustomerBookingConfirmedPage /> },
    ],
  },
  {
    path: '/my-bookings',
    element: (
      <RequireRole roles={['CUSTOMER']} signInPath="/sign-in">
        <MyBookingsPage />
      </RequireRole>
    ),
  },
  {
    path: '/my-bookings/:id',
    element: (
      <RequireRole roles={['CUSTOMER']} signInPath="/sign-in">
        <BookingDetailsPage />
      </RequireRole>
    ),
  },
  {
    path: '/my-bookings/:id/change',
    element: (
      <RequireRole roles={['CUSTOMER']} signInPath="/sign-in">
        <ChangeTimePage />
      </RequireRole>
    ),
  },
  {
    path: '/staff/sign-in',
    element: <StaffSignInPage />,
  },
  {
    path: '/staff',
    element: (
      <RequireRole roles={['SERVICE_ADVISOR', 'WORKSHOP_FOREMAN', 'ADMIN']} signInPath="/staff/sign-in">
        <StaffLayout />
      </RequireRole>
    ),
    children: [
      { index: true, element: <NewBookingPage /> },
      { path: 'bookings/:id/confirmed', element: <BookingConfirmedPage /> },
      { path: 'appointments', element: <AppointmentListPage /> },
      { path: 'appointments/:id', element: <AppointmentDetailsPage /> },
      {
        path: 'appointments/:id/reschedule',
        element: (
          <RequireRole roles={['SERVICE_ADVISOR']} signInPath="/staff/sign-in">
            <ReschedulePage />
          </RequireRole>
        ),
      },
      {
        path: 'master-data',
        element: (
          <RequireRole roles={['ADMIN']} signInPath="/staff/sign-in">
            <MasterDataPage />
          </RequireRole>
        ),
      },
      {
        path: 'monitoring',
        element: (
          <RequireRole roles={['ADMIN']} signInPath="/staff/sign-in">
            <MonitoringPage />
          </RequireRole>
        ),
      },
    ],
  },
]

export const router = createBrowserRouter(routes)
