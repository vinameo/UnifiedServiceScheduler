# F06 · Gợi ý giờ trống khác · Test cases (TC)

---

# PHẦN A · Cho QA và product owner

## A1. Chiến lược

- **Định nghĩa "trống"** trùng với F03, nên test chủ yếu là HTTP trên PostgreSQL thật với lịch dựng sẵn (người bận, khoang bận, giờ đã qua).
- **Thứ tự gợi ý** test với dữ liệu nhiều ngày.
- **Hiệu năng và số truy vấn** test riêng (FR-06.4, NFR-02).
- **Màn hình**: test component cho gạch ngang, "fully booked", chip gợi ý.

## A2. Kịch bản chạy tay

| # | Bước | Kỳ vọng |
| --- | --- | --- |
| M1 | Advisor đặt EV battery check 09:00 ngày mai (Em là người duy nhất) | Thành công |
| M2 | New booking lại EV battery check ngày mai | 08:00–10:15 gạch ngang (mọi lịch 90 phút bắt đầu trong khoảng này đều trùng 09:00–10:30); 10:30 trống |
| M3 | Hai tab: tab 1 chọn 10:30, tab 2 đặt 10:30 trước; tab 1 Confirm | Thẻ "The 10:30 slot was just taken" + chip gợi ý; bấm chip → giờ đó được chọn |
| M4 | Khách chọn một ngày mà M1–M3 làm kín (hoặc dùng dữ liệu kín) | "This day is fully booked" + "Next available slot for …" |

## A3. Dữ liệu test

`World::saigonD7()`; lịch dựng qua `AppointmentFactory`; đồng hồ đóng băng 2026-11-02 08:00 +07 (trừ khi ghi khác).

## A4. Không test

Cache (bị cấm). Đặt lịch (F02).

## A5. Điều kiện xong

Mọi TC xanh; M1–M4 đạt.

---

# PHẦN B · Cho agent triển khai

## B0. Quy tắc đọc

> - **PHẢI** / **KHÔNG ĐƯỢC**: bắt buộc. **NÊN**: làm trừ khi có lý do ghi trong commit. **CÓ THỂ**: tuỳ chọn. `CLAUDE.md` áp dụng toàn bộ.
> - Yêu cầu là AC, INV, quyền và câu chữ. Code mẫu, query và thứ tự bước là **giả thuyết**: agent kiểm chúng với code và dữ liệu thật ở HEAD (`implement-ticket` bước 4).
> - Giả thuyết sai mà bản đúng vẫn giữ mọi AC, quyền và câu chữ → làm bản đúng, ghi vào commit và sửa spec. Bản đúng đổi điều người dùng thấy → dừng lại và hỏi.

## B1. Ma trận phủ

| Yêu cầu | TC |
| --- | --- |
| AC-06.1, FR-06.2, INV-06.1 | TC-06-01 |
| AC-06.2, FR-06.1, INV-06.2 | TC-06-02 |
| AC-06.3, FR-06.3 | TC-06-03 |
| AC-06.4, D-06.2 | TC-06-04 |
| AC-06.5, D-06.3 | TC-06-05 |
| AC-06.6, INV-06.6 | TC-06-06 |
| AC-06.7, D-06.4, INV-06.5 | TC-06-07 |
| AC-06.8 | TC-06-08 |
| AC-06.9 | TC-06-09 |
| AC-06.10, FR-06.4, INV-06.3 | TC-06-10 |
| AC-06.11, D-06.6, INV-06.7 | TC-06-11 |
| AC-06.12, D-06.7 | TC-06-12 |
| AC-06.13, FR-06.5 | TC-06-14 |
| AC-06.14 | TC-06-15 |
| AC-06.15 | TC-06-16 |
| AC-06.16, NFR-02 | TC-06-13 |
| D-06.5 | TC-06-03 (409 có gợi ý tức là tính ngoài transaction lỗi) |
| D-06.8, API §B4 | TC-06-17 |
| BR-06.1 | TC-06-18 |
| INV-06.4 | TC-06-18 |
| Gợi ý lưu cùng khoá chống lặp | TC-06-19 |

## B2. Test case

Backend ở `api/tests/Feature/Booking/Availability/`.

| ID | Proves | Level | Given | When | Then | Fails if | Where |
| --- | --- | --- | --- | --- | --- | --- | --- |
| TC-06-01 | AC-06.1 | Integration | `onlyTechnicians(['An'])`, An bận 09:00–10:00 ngày mai | `GET availability` OIL ngày mai | 08:00 `true`; 08:15…09:45 `false` (`NO_TECHNICIAN_AVAILABLE`); 10:00 `true` | Chỉ kiểm giờ bắt đầu | `AvailabilityTest::test_AC_06_1_busy_technician_blocks_overlapping_starts` |
| TC-06-02 | AC-06.2 | Integration | Đóng 17:00 | OIL ngày mai | Khung cuối `start 16:00`; không có 16:15 | Liệt kê tới 16:45 | `…::test_AC_06_2_last_start_is_close_minus_duration` |
| TC-06-03 | AC-06.3, D-06.5 | Integration | 09:00 kín (An + duy nhất); 10:00, 11:00 trống | `POST /appointments` 09:00 | 409 `NO_AVAILABILITY`; `alternatives[0].start` = 10:00 | Không gắn gợi ý | `AlternativesOnConflictTest::test_AC_06_3_conflict_returns_alternatives` |
| TC-06-04 | AC-06.4 | Integration | Đồng hồ 10:05 hôm nay | Hôm nay | Khung ≤ 10:00 `PAST`; 10:15 theo F03 | So với giờ DB | `AvailabilityTest::test_AC_06_4_past_slots_are_not_available` |
| TC-06-05 | AC-06.5 | Integration | — | Chủ nhật | 200, `closed: true`, `slots: []` | 422 hoặc mảng giờ | `…::test_AC_06_5_closed_day` |
| TC-06-06 | AC-06.6 | Integration | Advisor D2; dịch vụ inactive | Advisor hỏi D1; khách hỏi dịch vụ inactive | 404 `DEALERSHIP_NOT_FOUND`; 404 `SERVICE_TYPE_NOT_FOUND` | Phạm vi sai | `…::test_AC_06_6_scope` |
| TC-06-07 | AC-06.7 | Integration | Hôm nay chỉ 15:00 trống (với giờ muốn 09:00); ngày mai trống | `POST` 09:00 | `alternatives` = [15:00 hôm nay, 08:00 mai, 08:15 mai] | Sai thứ tự D-06.4 | `AlternativesOnConflictTest::test_AC_06_7_same_day_first_then_next_days` |
| TC-06-08 | AC-06.8 | Integration | 8 ngày kín (chỉ một người, dựng bận cả ngày) | `POST` | `alternatives: []` | Tìm quá 7 ngày | `…::test_AC_06_8_no_alternatives_within_a_week` |
| TC-06-09 | AC-06.9 | Unit | Khung trống 08:00, 10:00; muốn 09:00 | `near` | [08:00, 10:00] | Bằng nhau thì giờ muộn trước | `api/tests/Unit/Booking/AlternativeOrderTest::test_AC_06_9_ties_prefer_earlier` |
| TC-06-10 | AC-06.10, INV-06.3 | Integration | OIL (36 khung) và dịch vụ 480 phút giả (2 khung) | Đếm truy vấn mỗi lần gọi | Bằng nhau | Truy vấn mỗi khung | `AvailabilityTest::test_AC_06_10_query_count_independent_of_slots` |
| TC-06-11 | AC-06.11, INV-06.7 | Integration | Lịch X của A giữ An 10–11; An duy nhất | A hỏi với `excludeAppointmentId=X`; B hỏi với X | A: 10:00 `true`; B: 404 `APPOINTMENT_NOT_FOUND` | Bỏ qua lặng lẽ | `…::test_AC_06_11_exclude_own_appointment_only` |
| TC-06-12 | AC-06.12 | Integration | Thứ Hai–Năm kín; thứ Sáu 08:00 trống | `availability/next?after=thứ Hai 09:00` | `nextAvailable.start` = thứ Sáu 08:00 | Tìm sai ngày | `NextAvailableTest::test_AC_06_12_next_free_slot` |
| TC-06-13 | AC-06.16, NFR-02 | Integration (`@group performance`) | 20 khoang, 30 kỹ thuật viên, 200 lịch trong ngày | 30 lần `GET availability` | p95 < 300 ms | Chậm | `AvailabilityPerformanceTest` |
| TC-06-14 | AC-06.13, WEB-06.3 | Web component | API giả: 3 khung, khung giữa `false` | Render New booking, bấm khung giữa | Khung giữa gạch ngang, `aria-disabled`, không chọn được; hiện "2 free start times" | Ẩn khung | `web/src/shared/booking/SlotGrid.test.tsx` |
| TC-06-15 | AC-06.14, WEB-06.5 | Web component | API giả: `freeCount 0`; `next` = Fri 6 Nov 08:00 | Render bước 2, bấm chip | "This day is fully booked"; "Next available slot for Oil change:"; "Fri 6 Nov · 08:00"; ngày chọn đổi sang 6 Nov, giờ 08:00 | Không đổi ngày | `web/src/customer/booking/ChooseTimePage.test.tsx` |
| TC-06-16 | AC-06.15, WEB-06.4 | Web component | API giả: 409 có 3 gợi ý, lần 2 201 | Confirm; bấm "10:00 today"; Confirm | Thẻ biến mất, 10:00 chọn; lần gửi 2 `desiredStart` 10:00 và `Idempotency-Key` khác lần 1 | Dùng lại khoá cũ cho body mới | `web/src/staff/booking/NewBookingPage.test.tsx` |
| TC-06-17 | D-06.8, API §B4 | Integration | — | Mỗi dòng bảng đầu vào bất thường | Như bảng | Thiếu luật | `AvailabilityAdversarialInputTest` |
| TC-06-18 | BR-06.1, INV-06.4 | Integration | Lấy availability (10:00 trống); đặt 10:00 bằng user khác | Lấy lại availability | 10:00 `false` ngay (không cache) | Có cache | `AvailabilityTest::test_list_is_a_hint_and_never_cached` |
| TC-06-19 | F04 + F06 | Integration | `POST` 09:00 với K → 409 có gợi ý | Đổi dữ liệu để gợi ý khác; gửi lại K | Nhận đúng 409 và `alternatives` cũ | Gợi ý tính lại khi replay | `AlternativesOnConflictTest::test_replayed_409_keeps_saved_alternatives` |
| TC-06-20 | Toàn bộ | Manual | Stack chạy | M1–M4 | Như A2 | — | Ghi vào A2 |

## B3. Fixture

`World` + `AppointmentFactory::busyAllDay($technician, $date)` (thêm ở F06) để dựng ngày kín.

## B4. Hồi quy

F02 (`CreateBookingTest`, `NewBookingPage.test.tsx`, `BookingFlow.test.tsx` — `TimeGrid` được thay), F03, F04.
