# Service Scheduler · Admin and Customer designs

UI kit: Mantine (ui.mantine.dev). 18 screens: 11 Admin (desktop, 1440 px) and 7 Customer (phone, 390 × 844 px).

## Folders

- `Admin/screenshots`, `Customer/screenshots`: one PNG per screen, at 2× resolution. Use these in slides or documents.
- `Admin/html`, `Customer/html`: the interactive screens. Open `index.html` in Chrome, Edge or Firefox, then click a screen. Buttons and links move between screens. Each folder needs its own `support.js` (the design runtime), so keep the files together.

## Admin screens (service advisor, workshop foreman)

| # | Screen | Features | File |
|---|---|---|---|
| 01 | New booking | F02 F03 F06 | Main.dc.html |
| 02 | Booking confirmed | F02 | BookingResult.dc.html |
| 03 | Booking error states | F02 F04 F06 | BookingErrors.dc.html |
| 04 | Appointment list | F05 | Appointments.dc.html |
| 05 | Appointment details | F05 F07 F11 F12 | AppointmentDetail.dc.html |
| 06 | Reschedule appointment | F09 | Reschedule.dc.html |
| 07 | Workshop board | F12 F03 | Workshop.dc.html |
| 08 | Report absence and reassign | F12 | Reassign.dc.html |
| 09 | Master data | F01 | MasterData.dc.html |
| 10 | Notifications and outbox | F11 | Notifications.dc.html |
| 11 | System monitoring | F08 | Monitoring.dc.html |

## Customer screens (mobile app)

| # | Screen | Features | File |
|---|---|---|---|
| 01 | Step 1, choose a service | F02 | CustomerService.dc.html |
| 02 | Step 2, choose date and time | F03 F06 | CustomerSlots.dc.html |
| 03 | Step 3, temporary hold | F10 | Hold.dc.html |
| 04 | Booking confirmed | F02 F11 | CustomerConfirmed.dc.html |
| 05 | My bookings | F05 | CustomerMyBookings.dc.html |
| 06 | Details and cancel | F05 F07 | CustomerBooking.dc.html |
| 07 | Change time | F09 | CustomerReschedule.dc.html |

Online design canvas (editable): https://claude.ai/artifact/DaoHdgKWcCRKumWNS7Tvx8
