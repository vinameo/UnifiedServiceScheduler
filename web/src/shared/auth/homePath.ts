export function homePathFor(role: string | null | undefined): string {
  switch (role) {
    case 'CUSTOMER':
      return '/book'
    case 'SERVICE_ADVISOR':
      return '/staff'
    case 'WORKSHOP_FOREMAN':
      return '/staff/workshop'
    case 'ADMIN':
      return '/staff/master-data'
    default:
      return '/staff'
  }
}
