import { createBrowserRouter } from 'react-router'
import { CustomerHomePage } from './customer/CustomerHomePage'
import { SignUpPage } from './customer/auth/SignUpPage'
import { SignInPage } from './customer/auth/SignInPage'
import { StaffHomePage } from './staff/StaffHomePage'
import { StaffLayout } from './staff/StaffLayout'
import { StaffSignInPage } from './staff/auth/StaffSignInPage'
import { MasterDataPage } from './staff/master-data/MasterDataPage'
import { NewBookingPage } from './staff/booking/NewBookingPage'
import { BookingConfirmedPage } from './staff/booking/BookingConfirmedPage'
import { RequireRole } from './shared/auth/RequireRole'

export const routes = [
  { path: '/', element: <CustomerHomePage /> },
  { path: '/sign-up', element: <SignUpPage /> },
  { path: '/sign-in', element: <SignInPage /> },
  {
    path: '/book',
    element: (
      <RequireRole roles={['CUSTOMER']} signInPath="/sign-in">
        <CustomerHomePage />
      </RequireRole>
    ),
  },
  {
    path: '/my-bookings',
    element: (
      <RequireRole roles={['CUSTOMER']} signInPath="/sign-in">
        <div>My bookings</div>
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
      { path: 'appointments', element: <StaffHomePage /> },
      {
        path: 'master-data',
        element: (
          <RequireRole roles={['ADMIN']} signInPath="/staff/sign-in">
            <MasterDataPage />
          </RequireRole>
        ),
      },
    ],
  },
]

export const router = createBrowserRouter(routes)
