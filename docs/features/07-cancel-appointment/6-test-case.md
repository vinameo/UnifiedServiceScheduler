# F07 · Huỷ lịch hẹn · Test cases (TC)

---

# PHẦN A · Cho QA và product owner

## A1. Chiến lược

- **Huỷ trả chỗ ngay**: test HTTP huỷ rồi đặt lại cùng giờ, trên PostgreSQL thật.
- **Luật trạng thái, 2 giờ, quyền**: test HTTP, mỗi trường hợp biên một test, đồng hồ đóng băng.
- **Huỷ đồng thời**: N process thật.
- **Màn hình**: test component cho hộp thoại, bảng khách, banner, câu "sát giờ".

## A2. Kịch bản chạy tay

| # | Bước | Kỳ vọng |
| --- | --- | --- |
| M1 | Advisor mở một lịch CONFIRMED → Cancel appointment | Hộp thoại ghi đúng khoang, kỹ thuật viên, giờ |
| M2 | Chọn "Customer is busy" → Cancel appointment | Badge Cancelled; banner closed; không còn nút |
| M3 | New booking cùng giờ, cùng kỹ thuật viên | Đặt được |
| M4 | Hai tab cùng lịch: tab 1 đổi gì đó (khi có F09/F12) hoặc huỷ; tab 2 huỷ | Tab 2 nhận 200 nếu tab 1 đã huỷ; hộp thoại "changed by someone else" nếu tab 1 sửa khác |
| M5 | Khách mở lịch còn > 2 giờ → Cancel appointment → "Something came up" | Banner "You cancelled this appointment" |
| M6 | Khách mở lịch còn < 2 giờ | Không có nút; câu "Please call 028 3812 3456 to cancel." |

## A3. Dữ liệu test

`World`, `AppointmentFactory`; đồng hồ 2026-11-02 08:00 +07; lịch 10:00 (còn 2 giờ), 09:00 (còn 1 giờ).

## A4. Không test

Gửi SMS huỷ (F11).

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
| AC-07.1, FR-07.1, INV-07.1 | TC-07-01 |
| AC-07.2, FR-07.2, D-07.4 | TC-07-02 |
| AC-07.3, BR-07.1, INV-07.2 | TC-07-03, TC-07-12 |
| AC-07.4, BR-07.2, INV-07.3, D-07.3 | TC-07-04, TC-07-13 |
| AC-07.5 | TC-07-05 |
| AC-07.6, FR-07.4, D-07.1 | TC-07-06 |
| AC-07.7, INV-07.4 | TC-07-07 |
| AC-07.8, D-07.2 | TC-07-08 |
| AC-07.9, D-07.5, INV-07.5 | TC-07-09 |
| AC-07.10, D-07.6 | TC-07-10 |
| AC-07.11, FR-07.3, D-07.7 | TC-07-11 |
| AC-07.12 | TC-07-15 |
| AC-07.13 | TC-07-16 |
| INV-07.6 | TC-07-01 (kiểm cột) |
| API §B4 | TC-07-17 |
| Q-07.b | TC-07-18 |
| Cùng khoá gửi lại | TC-07-14 |

## B2. Test case

Backend ở `api/tests/Feature/Booking/Cancel/`.

| ID | Proves | Level | Given | When | Then | Fails if | Where |
| --- | --- | --- | --- | --- | --- | --- | --- |
| TC-07-01 | AC-07.1, INV-07.1, INV-07.6 | Integration | `onlyTechnicians(['An'])`; lịch A 09:00 ngày mai | Advisor huỷ A (`CUSTOMER_BUSY`, note); đặt B 09:00 | Huỷ 200, `ETag "2"`, cột `cancel_reason`, `cancel_note`, `cancelled_at`, `cancelled_by_user_id` đúng; 0 phân bổ active của A; B 201 | Không tắt phân bổ | `CancelAppointmentTest::test_AC_07_1_cancel_frees_the_slot_at_once` |
| TC-07-02 | AC-07.2, D-07.4 | Integration | Lịch đã huỷ, v2 | Huỷ lại với `If-Match: "2"` và với `"1"` | Cả hai 200, `status CANCELLED`, `ETag "2"`; không dòng lịch sử mới | Ghi lại hoặc 412 | `…::test_AC_07_2_cancelling_twice_returns_current_state` |
| TC-07-03 | AC-07.3 | Integration | Lịch `COMPLETED` | Huỷ | 422 `INVALID_STATUS_TRANSITION`, `currentStatus COMPLETED` | Cho huỷ | `…::test_AC_07_3_completed_cannot_be_cancelled` |
| TC-07-04 | AC-07.4 | Integration | Đồng hồ 08:00; lịch khách 09:00 | Khách huỷ | 422 `TOO_LATE_TO_CANCEL`, `dealershipPhone "028 3812 3456"`; lịch vẫn `CONFIRMED` | Không kiểm giờ | `…::test_AC_07_4_customer_too_late` |
| TC-07-05 | AC-07.5 | Integration | Lịch 08:30 | Advisor huỷ | 200 | Áp luật khách cho cố vấn | `…::test_AC_07_5_advisor_has_no_time_limit` |
| TC-07-06 | AC-07.6 | Integration | — | Advisor huỷ không `reason` | 400 `VALIDATION_ERROR` field `reason` | Lý do không bắt buộc | `…::test_AC_07_6_staff_reason_required` |
| TC-07-07 | AC-07.7 | Integration | Lịch của B | A huỷ | 404 `APPOINTMENT_NOT_FOUND` | 403/lộ | `…::test_AC_07_7_cannot_cancel_another_customers_booking` |
| TC-07-08 | AC-07.8 | Integration | Foreman, admin | Huỷ | 403 | Gate sai | `…::test_AC_07_8_foreman_and_admin_cannot_cancel` |
| TC-07-09 | AC-07.9 | Concurrency | Lịch v1 | 5 process huỷ cùng `If-Match: "1"`, khoá khác nhau | 5 × 200 `CANCELLED`; `version` = 2; 1 dòng `appointment_events` CANCELLED; 1 `event_id` outbox | 412 cho người thua | `ParallelCancelTest::test_AC_07_9_parallel_cancels_all_succeed_once` |
| TC-07-10 | AC-07.10, D-07.6 | Integration | Lịch `CONFIRMED`; lịch `HELD` (factory) | Huỷ mỗi cái (khách) | `CONFIRMED`: 1 `event_id` `AppointmentCancelled` (3 kênh); `HELD`: 0 | Gửi tin cho giữ chỗ | `…::test_AC_07_10_outbox_only_for_confirmed` |
| TC-07-11 | AC-07.11 | Integration | Lịch v2 `CONFIRMED` | Huỷ với `"1"`; huỷ không `If-Match` | 412, body `appointment.version 2`, `ETag "2"`; 428 | Endpoint thiếu `if-match` | `…::test_AC_07_11_stale_and_missing_if_match` |
| TC-07-12 | INV-07.2 | Integration | Lịch ở `CHECKED_IN`, `IN_PROGRESS`, `NO_SHOW`, `EXPIRED` | Huỷ | 422 mỗi cái | Bảng chuyển trạng thái sai | `…::test_INV_07_2_non_cancellable_statuses` (data provider) |
| TC-07-13 | INV-07.3, D-07.3 | Integration | Đồng hồ 08:00; lịch 10:00 (đúng 2 giờ), 09:59; `HELD` 08:30 | Khách huỷ từng cái | 200; 422; 200 | Biên sai; áp cho `HELD` | `…::test_INV_07_3_two_hour_boundary` |
| TC-07-14 | F04 + F07 | Integration | Huỷ với K → 200 | Gửi lại K (lịch giờ đã v2) | 200 đã lưu, `Idempotent-Replayed: true` | 412 | `…::test_retry_with_same_key_replays` |
| TC-07-15 | AC-07.12, WEB-07.2 | Web component | API giả lịch CONFIRMED (Bay 2, Trần Minh An, 09:00–10:00); cancel 200 | Mở hộp thoại, chọn lý do, xác nhận | Câu "Bay 2 and technician Trần Minh An will be released for 09:00–10:00 …"; request có `If-Match "1"`; sau đó badge Cancelled, banner, không còn nút | Thiếu `If-Match` | `web/src/staff/appointments/CancelAppointmentModal.test.tsx` |
| TC-07-16 | AC-07.13, WEB-07.4 | Web component | Đồng hồ giả 08:00; lịch 09:00 | Render chi tiết khách | Không nút Cancel; câu "It's less than 2 hours … call 028 3812 3456 to cancel." | Hiện nút | `web/src/customer/bookings/BookingDetailsPage.test.tsx` |
| TC-07-17 | API §B4 | Integration | — | Mỗi dòng bảng đầu vào bất thường | Như bảng | Thiếu luật | `CancelAdversarialInputTest` |
| TC-07-18 | Q-07.b | Integration | Advisor huỷ với note | Khách `GET /appointments/{id}` | `cancellation.byRole = SERVICE_ADVISOR`, `reason null`, `note null` | Lộ ghi chú nội bộ | `CancelAppointmentTest::test_customer_does_not_see_staff_note` |
| TC-07-19 | WEB-07.1, 412 | Web component | API giả: cancel 412 với lịch đã đổi giờ | Xác nhận huỷ | `AppointmentModifiedAlert` "Time: 09:00–10:00 → 14:00–15:00"; không gửi lại tự động | Tự thử lại | `CancelAppointmentModal.test.tsx` |
| TC-07-20 | Toàn bộ | Manual | Stack chạy | M1–M6 | Như A2 | — | Ghi vào A2 |

## B3. Fixture

`AppointmentFactory` (F04) với `->status()`; `Parallel` (F04).

## B4. Hồi quy

F04 (`TransitionTest`, `IfMatchTest`), F05 (`AppointmentDetailsPage.test.tsx`, `MyAppointmentsTest`), F06 (`AvailabilityTest`).
