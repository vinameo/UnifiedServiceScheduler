import { createBrowserRouter } from 'react-router'
import { CustomerHomePage } from './customer/CustomerHomePage'
import { SignUpPage } from './customer/auth/SignUpPage'
import { StaffHomePage } from './staff/StaffHomePage'
import { StaffLayout } from './staff/StaffLayout'
import { StaffSignInPage } from './staff/auth/StaffSignInPage'
import { RequireRole } from './shared/auth/RequireRole'

export const routes = [
  { path: '/', element: <CustomerHomePage /> },
  { path: '/sign-up', element: <SignUpPage /> },
  { path: '/staff/sign-in', element: <StaffSignInPage /> },
  {
    path: '/staff',
    element: (
      <RequireRole roles={['SERVICE_ADVISOR', 'WORKSHOP_FOREMAN', 'ADMIN']} signInPath="/staff/sign-in">
        <StaffLayout />
      </RequireRole>
    ),
    children: [
      { index: true, element: <StaffHomePage /> },
      { path: 'appointments', element: <StaffHomePage /> },
    ],
  },
]

export const router = createBrowserRouter(routes)
