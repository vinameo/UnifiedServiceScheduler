import { createBrowserRouter } from 'react-router'
import { CustomerHomePage } from './customer/CustomerHomePage'
import { SignUpPage } from './customer/auth/SignUpPage'
import { SignInPage } from './customer/auth/SignInPage'
import { ChooseServicePage } from './customer/booking/ChooseServicePage'
import { ChooseTimePage } from './customer/booking/ChooseTimePage'
import { ContactDetailsPage } from './customer/booking/ContactDetailsPage'
import { CustomerBookingConfirmedPage } from './customer/booking/CustomerBookingConfirmedPage'
import { CustomerBookingLayout } from './customer/booking/CustomerBookingLayout'
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
        <CustomerBookingLayout />
      </RequireRole>
    ),
    children: [
      { index: true, element: <ChooseServicePage /> },
      { path: 'time', element: <ChooseTimePage /> },
      { path: 'details', element: <ContactDetailsPage /> },
      { path: 'confirmed/:id', element: <CustomerBookingConfirmedPage /> },
    ],
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
