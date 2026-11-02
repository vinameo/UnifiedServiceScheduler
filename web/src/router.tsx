import { createBrowserRouter } from 'react-router'
import { CustomerHomePage } from './customer/CustomerHomePage'
import { StaffHomePage } from './staff/StaffHomePage'
import { StaffLayout } from './staff/StaffLayout'

export const routes = [
  { path: '/', element: <CustomerHomePage /> },
  {
    path: '/staff',
    element: <StaffLayout />,
    children: [{ index: true, element: <StaffHomePage /> }],
  },
]

export const router = createBrowserRouter(routes)
