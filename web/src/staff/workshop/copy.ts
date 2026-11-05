export const workshopCopy = {
  board: {
    title: 'Workshop board',
    updated: (dayLong: string, time: string) => `${dayLong} · updated ${time}`,
    overrunTitle: (bay: string, minutes: number) => `${bay} is running ${minutes} minutes over`,
    overrunBodyFree: (customer: string, service: string, technician: string, time: string) =>
      `${customer} · ${service} · ${technician}. The next booking for this bay and technician at ${time} was cancelled, so nothing is affected yet.`,
    overrunBodyNext: (customer: string, service: string, technician: string, time: string) =>
      `${customer} · ${service} · ${technician}. The next booking at ${time} will be moved or flagged if you extend.`,
    overrunBodyNone: (customer: string, service: string, technician: string) =>
      `${customer} · ${service} · ${technician}. No other booking follows today.`,
    extend30: 'Extend 30 min',
    byBay: 'Schedule by bay',
    legend: 'Completed · In progress · Checked in · Confirmed · On hold · Overrun',
    legendLabel: 'Legend:',
    caption: 'The red line is the current time. Each block is an appointment holding a bay; cancelled and no-show appointments are not shown.',
    technicians: 'Technicians today',
    columns: 'Technician · Level · Booked · Status',
    technicianColumns: { technician: 'Technician', level: 'Level', booked: 'Booked', status: 'Status' },
    bookedMinutes: (minutes: number) => `${minutes} min`,
    working: 'Working',
    overrun: 'Overrun',
    level: (n: number) => `Level ${n}`,
    displaced: 'Needs customer contact',
    displacedColumns: { booking: 'Booking', time: 'Time', reason: 'Reason' },
  },
}
