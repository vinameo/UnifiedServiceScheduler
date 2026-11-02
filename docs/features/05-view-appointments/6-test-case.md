# F05 · Xem lịch hẹn · Test cases (TC)

---

# PHẦN A · Cho QA và product owner

## A1. Chiến lược

- **Ai thấy gì**: test HTTP trên PostgreSQL thật cho mọi tổ hợp (khách của mình / khách khác / nhân viên cùng đại lý / nhân viên đại lý khác).
- **Ngày theo múi giờ đại lý** và **phân trang**: test HTTP với dữ liệu chọn sẵn (lịch sáng sớm, nhiều lịch cùng giờ).
- **Màn hình**: test component (bộ lọc trong URL, "Load more", banner, lịch sử, tab khách).
- **Một lượt tay**.

## A2. Kịch bản chạy tay

| # | Bước | Kỳ vọng |
| --- | --- | --- |
| M1 | Advisor → Appointments | Ngày hôm nay; dòng phụ "times in dealership time zone (GMT+7)" |
| M2 | Lọc Technician = Trần Minh An | Chỉ lịch của An; URL có `technicianId` |
| M3 | F5 | Bộ lọc giữ nguyên |
| M4 | View một lịch | Trang chi tiết; "Show number" hiện số đầy đủ; History có "Booked" |
| M5 | Khách Hùng → My bookings | Tab Upcoming có lịch vừa đặt; "Signed in as hung@example.test" |
| M6 | Khách Khánh dán URL `/my-bookings/{id của Hùng}` | "We couldn't find this appointment." |

## A3. Dữ liệu test

`World::saigonD7()`, 2 khách, lịch tạo qua `AppointmentFactory` (F04). Đồng hồ đóng băng 2026-11-02 08:00 +07.

## A4. Không test

Hiệu năng ở quy mô lớn (M-05.1 theo dõi qua F08).

## A5. Điều kiện xong

Mọi TC xanh; M1–M6 đạt.

---

# PHẦN B · Cho agent triển khai

## B0. Quy tắc đọc

> - **PHẢI** / **KHÔNG ĐƯỢC**: bắt buộc. **NÊN**: làm trừ khi có lý do ghi trong commit. **CÓ THỂ**: tuỳ chọn. `CLAUDE.md` áp dụng toàn bộ.
> - Yêu cầu là AC, INV, quyền và câu chữ. Code mẫu, query và thứ tự bước là **giả thuyết**: agent kiểm chúng với code và dữ liệu thật ở HEAD (`implement-ticket` bước 4).
> - Giả thuyết sai mà bản đúng vẫn giữ mọi AC, quyền và câu chữ → làm bản đúng, ghi vào commit và sửa spec. Bản đúng đổi điều người dùng thấy → dừng lại và hỏi.

## B1. Ma trận phủ

| Yêu cầu | TC |
| --- | --- |
| AC-05.1, FR-05.1, INV-05.2 | TC-05-01 |
| AC-05.2, FR-05.2, INV-05.3 | TC-05-02 |
| AC-05.3 | TC-05-03 |
| AC-05.4, AC-05.8, FR-05.3, D-05.3 | TC-05-06 |
| AC-00.8, INV-05.1 | TC-05-04 |
| AC-00.7 | TC-05-10 |
| AC-05.5, INV-05.1 | TC-05-05 |
| AC-05.6, INV-05.6 | TC-05-08, TC-05-09 |
| AC-05.7, FR-05.4, INV-05.4, INV-05.5 | TC-05-07 |
| AC-05.9 | TC-05-14 |
| AC-05.10 | TC-05-15 |
| D-05.2 | TC-05-08 |
| D-05.4, INV-05.7 | TC-05-13 |
| D-05.6, API §B4 | TC-05-11 |
| D-05.7 | TC-05-15 |
| D-05.8 | TC-05-17 |
| WEB-05.1 | TC-05-16 |
| WEB-05.4 | TC-05-18 |
| DB §B2 (không N+1) | TC-05-12 |
| R-05.1 | TC-05-04, TC-05-05 |

## B2. Test case

Backend ở `api/tests/Feature/Booking/View/`.

| ID | Proves | Level | Given | When | Then | Fails if | Where |
| --- | --- | --- | --- | --- | --- | --- | --- |
| TC-05-01 | AC-05.1, INV-05.2 | Integration | Đặt lịch qua `POST /appointments`, lưu body | `$this->refreshApplication()`; đăng nhập lại; `GET /appointments/{id}` | Body giống hệt body lúc tạo; `ETag: "1"` | Dữ liệu chỉ trong bộ nhớ | `ShowAppointmentTest::test_AC_05_1_appointment_survives_restart` |
| TC-05-02 | AC-05.2, INV-05.3 | Integration | Lịch `start_at = 2026-11-01T23:30:00Z` (06:30 ngày 02 địa phương; chèn qua factory, bỏ qua giờ mở cửa) | Advisor `?date=2026-11-02`; `?date=2026-11-01` | Có lịch; không có lịch | Lọc theo ngày UTC | `DealershipAppointmentsTest::test_AC_05_2_date_is_local_to_the_dealership` |
| TC-05-03 | AC-05.3 | Integration | — | `GET /appointments/{uuid ngẫu nhiên}` | 404 `APPOINTMENT_NOT_FOUND` | 500 | `ShowAppointmentTest::test_AC_05_3_unknown_id` |
| TC-05-04 | AC-00.8, INV-05.1 | Integration | Lịch của khách B | Khách A `GET /appointments/{id}` | 404 `APPOINTMENT_NOT_FOUND`, body giống TC-05-03 trừ `correlationId` | 403 hoặc lộ khác biệt | `…::test_AC_00_8_customer_cannot_see_another_customers_appointment` |
| TC-05-05 | AC-05.5, INV-05.1 | Integration | Lịch của D1 | Advisor D2 `GET /appointments/{id}` | 404 `APPOINTMENT_NOT_FOUND` | Không lọc đại lý | `…::test_AC_05_5_staff_of_another_dealership_gets_404` |
| TC-05-06 | AC-05.4, AC-05.8, D-05.3 | Integration | A: 2 lịch `CONFIRMED` tương lai, 1 `CONFIRMED` hôm qua, 1 `CANCELLED` tuần sau, 1 `HELD`, 1 `EXPIRED`; B: 3 lịch | A gọi `scope=upcoming`, `scope=past` | Upcoming: đúng 2, sớm nhất trước; Past: hôm qua + đã huỷ, mới nhất trước; không `HELD`/`EXPIRED`; không lịch của B | Sai luật D-05.3 | `MyAppointmentsTest::test_AC_05_4_and_05_8_upcoming_and_past` |
| TC-05-07 | AC-05.7, INV-05.4, INV-05.5 | Integration | 120 lịch cùng ngày, 10 lịch cùng `start_at` | `limit=50` theo `nextCursor` tới hết | 50, 50, 20; hợp các id = 120 id khác nhau; thứ tự `(start, id)` tăng; trang cuối `nextCursor: null` | Cursor chỉ theo `start_at` | `DealershipAppointmentsTest::test_AC_05_7_cursor_paging_has_no_gaps_or_duplicates` |
| TC-05-08 | AC-05.6, D-05.2 | Integration | 5 lịch: An/K1 CONFIRMED, Dũng/K2 CHECKED_IN, An/K2 CANCELLED, EXPIRED, Chi/K3 COMPLETED | Lọc `technicianId=An`; `bayId=K2`; `status=CONFIRMED,CHECKED_IN`; không lọc | 2; 2; 2; 4 (không EXPIRED) | Bộ lọc sai | `…::test_AC_05_6_filters` |
| TC-05-09 | INV-05.6 | Integration | Kỹ thuật viên của D2 | Advisor D1 lọc `technicianId` đó | 200, `data: []` | Lỗi hoặc lộ lịch D2 | `…::test_INV_05_6_foreign_filter_returns_nothing` |
| TC-05-10 | AC-00.7 | Integration | Khách | `GET /dealerships/{d}/appointments?date=…` | 403 `FORBIDDEN` | Gate thiếu | `…::test_AC_00_7_customer_cannot_list_staff_appointments` |
| TC-05-11 | D-05.6, API §B4 | Integration | — | Mỗi dòng bảng đầu vào bất thường | Như bảng | Thiếu luật | `AppointmentListAdversarialInputTest` (data provider) |
| TC-05-12 | DB §B2 | Integration | 5 rồi 50 lịch | Đếm truy vấn của danh sách | Bằng nhau | N+1 | `DealershipAppointmentsTest::test_query_count_is_constant` |
| TC-05-13 | D-05.4, INV-05.7 | Integration | Lịch đã tạo rồi `touch` (F04 route test) | Advisor `GET /history`; khách chủ lịch `GET /history` | 2 dòng (`CREATED`, …) theo thời gian, không có phone/email; khách 403 | History mở cho khách | `AppointmentHistoryTest::test_D_05_4_history_is_staff_only` |
| TC-05-14 | AC-05.9 | Web component | API giả 2 trang | Render `/staff/appointments`, chọn Technician, bấm "Load more" | URL có `technicianId`; request có `technicianId`; trang 2 nối vào; footer "Showing the first 60 appointments…" | Bộ lọc không vào URL | `web/src/staff/appointments/AppointmentListPage.test.tsx` |
| TC-05-15 | AC-05.10, D-05.7 | Web component | API giả lịch `CANCELLED` + history | Render chi tiết; bấm "Show number" | Banner closed; History có "Cancelled · Minh Anh"; số đầy đủ hiện ra | Không phân biệt closed | `…/AppointmentDetailsPage.test.tsx` |
| TC-05-16 | WEB-05.1 | Web unit | — | `appointmentQuery` fetch với header `ETag: "3"`; `setAppointment(…, '"4"')` | Cache có `etag '"3"'` rồi `'"4"'`; `withIfMatch` trả `{ 'If-Match': '"4"' }` | Mất ETag | `web/src/shared/api/appointmentQuery.test.ts` |
| TC-05-17 | D-05.8 | Web component | Trang confirmed nhân viên; thẻ `VEHICLE_ALREADY_BOOKED` có `existingAppointment` | Render | Có "View appointment" → `/staff/appointments/{id}`; "View existing booking" → đúng id | Link thiếu | `web/src/staff/booking/BookingConfirmedPage.test.tsx`, `BookingErrorCard.test.tsx` |
| TC-05-18 | WEB-05.4 | Web component | API giả Upcoming 1, Past 0 | Render `/my-bookings`, đổi sang Past | Thẻ "Mon 2 Nov · 10:00–11:00" + "Confirmed"; Past: "No past bookings yet."; URL `?tab=past` | Tab không vào URL | `web/src/customer/bookings/MyBookingsPage.test.tsx` |
| TC-05-19 | Toàn bộ | Manual | Stack chạy | M1–M6 | Như A2 | — | Ghi vào A2 |

## B3. Fixture

- `AppointmentFactory` (F04) thêm `->at('2026-11-01T23:30:00Z')->status('CANCELLED')->forCustomer($c)` (bỏ qua giờ mở cửa chỉ trong test).
- Web: `web/src/test/fixtures/appointment.ts` (F02) thêm biến thể theo trạng thái.

## B4. Hồi quy

Test F00–F04, F02; đặc biệt `CreateBookingTest`, `BookingFlow.test.tsx` (liên kết mới không được đổi luồng).
