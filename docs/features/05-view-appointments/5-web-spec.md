# F05 · Xem lịch hẹn · Web spec (WEB)

---

# PHẦN A · Cho người đọc

| Màn hình | Đường dẫn | Bản vẽ |
| --- | --- | --- |
| Appointments (nhân viên) | `/staff/appointments` | `Admin/04-appointment-list.png` |
| Appointment details (nhân viên) | `/staff/appointments/:id` | `Admin/05-appointment-details.png` |
| My bookings (khách) | `/my-bookings` | `Customer/05-my-bookings.png` |
| Details (khách) | `/my-bookings/:id` | `Customer/06-details-and-cancel.png` |

```
Appointments                                              [New booking]
Monday, 2 Nov 2026 · times in dealership time zone (GMT+7)
Date [02/11/2026]  Technician [All ▾]  Bay [All ▾]  Status [All ▾]
Time         Customer · Vehicle               Service     Technician    Bay    Status     
09:00–10:00  Nguyễn Văn Hùng · Toyota Vios   Oil change  Trần Minh An  Bay 2  Confirmed  View
…
Showing the first 8 appointments, sorted by start time        [Load more]
```

```
Appointments / APT-20261102-0042
Nguyễn Văn Hùng · Toyota Vios   [Confirmed]
Monday, 2 Nov 2026, 09:00–10:00 · Oil change
[ nút hành động: F07, F09, F12 ]
┌ Appointment info ───────────┐ ┌ Customer and vehicle ──────────────┐
│ Time  09:00–10:00, Mon 2 Nov│ │ Customer  Nguyễn Văn Hùng           │
│ Service Oil change · 60 min │ │ Phone  0903 *** 218  Show number    │
│ Technician Trần Minh An     │ │ Vehicle Toyota Vios 2021 · 51H-123.45│
│ Bay  Bay 2 · Quick service  │ │ VIN  RL4BT3F3X0M001234              │
│ Dealership Saigon Auto · D7 │ └─────────────────────────────────────┘
│ Created by Minh Anh, 28/10… │ ┌ History ───────────────────────────┐
└─────────────────────────────┘ │ • Created · Minh Anh · 28/10 16:40  │
                                └─────────────────────────────────────┘
```

---

# PHẦN B · Cho agent triển khai

## B0. Quy tắc đọc

> - **PHẢI** / **KHÔNG ĐƯỢC**: bắt buộc. **NÊN**: làm trừ khi có lý do ghi trong commit. **CÓ THỂ**: tuỳ chọn. `CLAUDE.md` áp dụng toàn bộ.
> - Yêu cầu là AC, INV, quyền và câu chữ. Code mẫu, query và thứ tự bước là **giả thuyết**: agent kiểm chúng với code và dữ liệu thật ở HEAD (`implement-ticket` bước 4).
> - Giả thuyết sai mà bản đúng vẫn giữ mọi AC, quyền và câu chữ → làm bản đúng, ghi vào commit và sửa spec. Bản đúng đổi điều người dùng thấy → dừng lại và hỏi.

## B1. Route

| Path | Element | Guard |
| --- | --- | --- |
| `/staff/appointments` | `staff/appointments/AppointmentListPage` | mọi vai trò nhân viên |
| `/staff/appointments/:id` | `staff/appointments/AppointmentDetailsPage` | mọi vai trò nhân viên |
| `/my-bookings` | `customer/bookings/MyBookingsPage` | `CUSTOMER` |
| `/my-bookings/:id` | `customer/bookings/BookingDetailsPage` | `CUSTOMER` |

404 từ API → trang "We couldn't find this appointment." với nút về danh sách.

## B2. Seam

| ID | Seam | File | Hành vi |
| --- | --- | --- | --- |
| WEB-05.1 | `appointmentQuery(id)` | `web/src/shared/api/appointmentQuery.ts` | `queryKey ['appointment', id]`; dữ liệu `{ appointment, etag }` (`etag` đọc từ header `ETag`). `setAppointment(qc, appointment, etag)` dùng bởi mọi mutation và bởi 412 (`AppointmentModifiedAlert.onReview`). `withIfMatch(etag)` → header. |
| WEB-05.2 | `AppointmentDetailsLayout` | `web/src/staff/appointments/AppointmentDetailsLayout.tsx` | Props: `appointment`, `actions` (ReactNode), `banner`; dùng lại ở F07, F09, F11, F12. |
| WEB-05.3 | `useDealershipAppointments(filters)` | `web/src/shared/api/appointments.ts` | `useInfiniteQuery`, `getNextPageParam = meta.nextCursor`; `refetchInterval` 60 s. |
| WEB-05.4 | `useMyAppointments(scope)` | như trên | `useInfiniteQuery`. |
| WEB-05.5 | `useAppointmentHistory(id)` | như trên | Chỉ gọi khi là nhân viên. |
| WEB-05.6 | `isClosed(status)` | `web/src/shared/appointments/status.ts` | `COMPLETED`, `CANCELLED`, `NO_SHOW`, `EXPIRED`. |

## B3. Hành vi

**Appointments**
- Bộ lọc nằm trong URL (`?date=2026-11-02&technicianId=…&status=CONFIRMED`) để chia sẻ link và giữ khi F5. `date` mặc định là hôm nay theo múi giờ đại lý (không theo trình duyệt).
- Technician/Bay `Select` lấy từ `useTechnicians`, `useBays` (F01); "All" là mặc định. Status `Select` gồm 8 trạng thái + "All".
- Cột Time "09:00–10:00"; Customer · Vehicle hai dòng (tên đậm; "{make} {model} · {plate}" mờ); Status = `AppointmentStatusBadge`; Actions = link "View".
- Footer: "Showing the first {n} appointments, sorted by start time" (n = số dòng đang hiện); "Load more" khi còn `nextCursor`.
- Trống: "No appointments on this day."
- Nút "New booking" chỉ cho advisor.

**Appointment details (nhân viên)**
- Breadcrumb "Appointments / {reference}"; tiêu đề "{fullName} · {make} {model}"; badge; dòng "{dayLong}, {range} · {service}".
- Banner khi `isClosed`: "This appointment is closed and can no longer be changed. Its bay and technician have been released for this time slot."
- Appointment info: Time, Service "{name} · {duration} min", Technician, Bay "{name} · {bayTypeLabel}", Dealership, Created by "{name}, {dd/MM/yyyy HH:mm}" (giờ đại lý).
- Customer and vehicle: Customer, Phone (`maskPhone` + nút "Show number" đổi thành số đầy đủ, link `tel:`), Vehicle "{make} {model} {year} · {plate}", VIN (ẩn dòng nếu không có).
- History: Timeline, mỗi dòng "{label} · {actor name or 'System'} · {dd/MM HH:mm}"; nhãn theo `type` (B4).

**My bookings (khách)**
- Tiêu đề "My bookings"; "Signed in as {email}" + nút "Sign out".
- `SegmentedControl` Upcoming | Past (lưu trong URL `?tab=past`).
- Thẻ: "{dayShort} · {range}" + badge; tên dịch vụ; "{make} {model} · {dealership name} {branchName}"; dòng cuối "{reference}" + link "View details".
- Trống: Upcoming "You have no upcoming bookings." + nút "New booking"; Past "No past bookings yet."
- Thanh dưới (bản vẽ): "New booking" (`/book`), "My bookings".

**Details (khách)**
- "Appointment details", reference; "{dayShort} · {range}" + badge; "{service} · about {duration} min"; Vehicle; "Booked by {fullName}"; "Reminder SMS 1 day before" khi `reminderOptIn`; Location "{name} · {branchName}" + số điện thoại (link `tel:`).
- Vùng nút: F07 (Cancel), F09 (Change time); luôn có "New booking".

**Liên kết thêm vào F02 (D-05.8)**: "View appointment" ở trang confirmed nhân viên; "View existing booking" trong thẻ `VEHICLE_ALREADY_BOOKED` (`/staff/appointments/{existingAppointment.id}`; khách → `/my-bookings/{id}`); "View my bookings" ở trang confirmed khách; "My bookings" ở header khách; "Check appointment list" trong `IdempotentRetryPanel` (nhân viên → `/staff/appointments?date=…`; khách → `/my-bookings`).

## B4. Câu chữ

| Key | en | vi |
| --- | --- | --- |
| `list.title` | Appointments | Lịch hẹn |
| `list.subtitle` | {dayLong} · times in dealership time zone ({offset}) | {dayLong} · giờ theo múi giờ đại lý ({offset}) |
| `list.filters.*` | Date · Technician · Bay · Status · All | Ngày · Kỹ thuật viên · Khoang · Trạng thái · Tất cả |
| `list.columns.*` | Time · Customer · Vehicle · Service · Technician · Bay · Status · Actions | Giờ · Khách · Xe · Dịch vụ · Kỹ thuật viên · Khoang · Trạng thái · Thao tác |
| `list.view` | View | Xem |
| `list.footer` | Showing the first {n} appointments, sorted by start time | Đang hiện {n} lịch đầu tiên, sắp theo giờ bắt đầu |
| `list.loadMore` | Load more | Tải thêm |
| `list.empty` | No appointments on this day. | Không có lịch hẹn nào trong ngày này. |
| `details.closed` | This appointment is closed and can no longer be changed. Its bay and technician have been released for this time slot. | Lịch hẹn đã đóng và không thể thay đổi. Khoang và kỹ thuật viên đã được giải phóng cho khung giờ này. |
| `details.info` | Appointment info | Thông tin lịch hẹn |
| `details.customerVehicle` | Customer and vehicle | Khách hàng và xe |
| `details.fields.*` | Time · Service · Technician · Bay · Dealership · Created by · Customer · Phone · Vehicle · VIN | Giờ · Dịch vụ · Kỹ thuật viên · Khoang · Đại lý · Người tạo · Khách hàng · Điện thoại · Xe · VIN |
| `details.showNumber` | Show number | Hiện số |
| `details.history` | History | Lịch sử |
| `details.system` | System | Hệ thống |
| `history.CREATED` | Booked | Đã đặt |
| `history.HELD` | Slot held | Đã giữ chỗ |
| `history.CONFIRMED` | Confirmed | Đã xác nhận |
| `history.CANCELLED` | Cancelled | Đã huỷ |
| `history.RESCHEDULED` | Time changed | Đã đổi giờ |
| `history.EXPIRED` | Hold expired | Hết hạn giữ chỗ |
| `history.CHECKED_IN` | Checked in | Đã tiếp nhận xe |
| `history.STARTED` | Work started | Bắt đầu làm |
| `history.COMPLETED` | Completed | Hoàn thành |
| `history.NO_SHOW` | Marked no-show | Đánh dấu không đến |
| `history.EXTENDED` | Time extended | Đã gia hạn |
| `history.REASSIGNED` | Technician or bay changed | Đổi kỹ thuật viên hoặc khoang |
| `history.CONTACT_REQUIRED` | Customer contact needed | Cần liên hệ khách |
| `notFound` | We couldn't find this appointment. | Không tìm thấy lịch hẹn này. |
| `my.title` | My bookings | Lịch của tôi |
| `my.signedInAs` | Signed in as {email} | Đăng nhập với {email} |
| `my.upcoming` / `my.past` | Upcoming / Past | Sắp tới / Đã qua |
| `my.viewDetails` | View details | Xem chi tiết |
| `my.emptyUpcoming` | You have no upcoming bookings. | Bạn chưa có lịch hẹn sắp tới. |
| `my.emptyPast` | No past bookings yet. | Chưa có lịch hẹn đã qua. |
| `my.newBooking` | New booking | Đặt lịch mới |
| `cdetails.title` | Appointment details | Chi tiết lịch hẹn |
| `cdetails.about` | {service} · about {duration} min | {service} · khoảng {duration} phút |
| `cdetails.bookedBy` | Booked by {name} | Người đặt {name} |
| `cdetails.reminder` | Reminder SMS 1 day before | SMS nhắc trước 1 ngày |
| `cdetails.location` | Location | Địa điểm |
| `viewAppointment` | View appointment | Xem lịch hẹn |
| `viewExisting` | View existing booking | Xem lịch đang có |
| `viewMyBookings` | View my bookings | Xem lịch của tôi |
| `checkList` | Check appointment list | Xem danh sách lịch |
