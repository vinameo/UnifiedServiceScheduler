# F07 · Huỷ lịch hẹn · Requirement spec (RQ)

Nhánh: `feature/07-cancel-appointment` · Ưu tiên: Should · Quy ước chung: [README](../README.md)

---

# PHẦN A · Cho người đọc

## A1. Câu chuyện

**Vì sao có feature này.** Khi khách báo không đến được, khung giờ phải được trả lại **ngay** để người khác đặt. Khách cũng muốn tự huỷ trong app.

**Feature làm gì.**
- Cố vấn huỷ một lịch từ trang chi tiết, bắt buộc chọn lý do (có thể thêm ghi chú).
- Khách huỷ lịch của mình trong app, lý do tuỳ chọn, chỉ khi còn ít nhất 2 giờ trước giờ hẹn; sát giờ hơn thì app hiện số điện thoại đại lý.
- Huỷ giải phóng kỹ thuật viên, khoang và xe trong cùng một lần lưu; ghi tin nhắn huỷ vào outbox.
- Huỷ một lịch đã huỷ trả lại trạng thái hiện tại, không lỗi.

**Ai dùng.** Cố vấn (web, Appointment details), khách (app, Details).

**Không làm.** Phí huỷ, khôi phục lịch đã huỷ, huỷ hàng loạt.

## A2. Các bước sử dụng

**Cố vấn**: Appointment details → "Cancel appointment" → hộp thoại "Cancel this appointment?" → chọn lý do, ghi chú → "Cancel appointment" → trang hiện banner "closed", trạng thái Cancelled.

**Khách**: My bookings → View details → "Cancel appointment" → bảng dưới "Cancel your 10:00 appointment on Mon 2 Nov?" → chọn lý do (tuỳ chọn) → "Cancel appointment" → banner "You cancelled this appointment".

## A3. Màn hình và khác biệt so với bản vẽ

Bản vẽ: `Admin/05-appointment-details.png` (hộp thoại huỷ), `Customer/06-details-and-cancel.png`.

| Bản vẽ | Spec | Lý do |
| --- | --- | --- |
| Khách: "A cancellation SMS is on its way." | "We'll send you a cancellation SMS." | Tin nằm trong outbox, F11 gửi |
| Hộp thoại nhân viên ghi tên khoang và kỹ thuật viên | Giữ nguyên, lấy từ lịch | — |

## A4. Luật chính bằng lời thường

- Chỉ huỷ được lịch **Đã xác nhận** hoặc **Đang giữ chỗ**. Lịch đã tiếp nhận xe, đang làm, đã xong → không huỷ được.
- Khách chỉ huỷ lịch của mình, và chỉ khi còn ≥ 2 giờ trước giờ bắt đầu. Cố vấn không bị giới hạn giờ.
- Huỷ dựa trên phiên bản mới nhất: nếu người khác vừa sửa lịch, người huỷ thấy bản mới và quyết định lại.

## A5. Ngoài phạm vi

Phí huỷ, chính sách huỷ khác nhau theo dịch vụ, huỷ thay mặt bởi quản đốc/admin.

## A6. Tiêu chí thành công

- Sau khi huỷ, khung giờ đặt lại được ngay (AC-07.1).
- Không có lịch đã huỷ còn phân bổ active (M-04.2 = 0).

## A7. Quyết định

| ID | Quyết định |
| --- | --- |
| D-07.1 | Lý do của cố vấn (bắt buộc): `CUSTOMER_REQUEST`, `CUSTOMER_BUSY`, `DONE_ELSEWHERE`, `DEALERSHIP_ISSUE`, `OTHER`. Lý do của khách (tuỳ chọn): `SOMETHING_CAME_UP`, `DONE_ELSEWHERE`, `OTHER`. Ghi chú ≤ 500 ký tự, tuỳ chọn cho cả hai. |
| D-07.2 | Chỉ advisor và khách huỷ (README §4); foreman, admin → 403. |
| D-07.3 | Giới hạn 2 giờ (BR-07.2) áp cho khách với lịch `CONFIRMED`: được huỷ khi `start − now ≥ 2 giờ`. Khách huỷ giữ chỗ `HELD` thì không giới hạn. |
| D-07.4 | Huỷ lịch đã `CANCELLED` → 200 với trạng thái hiện tại, không ghi gì, không tăng phiên bản (FR-07.2), kể cả khi `If-Match` cũ. `If-Match` vẫn bắt buộc (428 nếu thiếu). |
| D-07.5 | Nếu cập nhật có phiên bản trả 0 dòng mà đọc lại thấy lịch đã `CANCELLED` (người khác vừa huỷ) → 200 trạng thái hiện tại (kết quả người dùng muốn đã đạt); trạng thái khác → 412. |
| D-07.6 | Outbox `AppointmentCancelled` chỉ khi huỷ từ `CONFIRMED`; huỷ giữ chỗ không gửi tin. |
| D-07.7 | F07 là endpoint thay đổi đầu tiên qua HTTP thật, nên chứng minh lại 412 và 428 của F04 trên endpoint thật. |

## A8. Câu hỏi mở

| ID | Câu hỏi | Mặc định |
| --- | --- | --- |
| Q-07.a | Lý do `OTHER` của cố vấn có bắt buộc ghi chú? | Không. |
| Q-07.b | Khách có xem lý do cố vấn ghi khi huỷ? | Không; chỉ thấy "Cancelled by the dealership". |

---

# PHẦN B · Cho agent triển khai

## B0. Quy tắc đọc

> - **PHẢI** / **KHÔNG ĐƯỢC**: bắt buộc. **NÊN**: làm trừ khi có lý do ghi trong commit. **CÓ THỂ**: tuỳ chọn. `CLAUDE.md` áp dụng toàn bộ.
> - Yêu cầu là AC, INV, quyền và câu chữ. Code mẫu, query và thứ tự bước là **giả thuyết**: agent kiểm chúng với code và dữ liệu thật ở HEAD (`implement-ticket` bước 4).
> - Giả thuyết sai mà bản đúng vẫn giữ mọi AC, quyền và câu chữ → làm bản đúng, ghi vào commit và sửa spec. Bản đúng đổi điều người dùng thấy → dừng lại và hỏi.

## B1. Trạng thái code hiện tại

Giả định F00–F06 đã merge: `TransitionAppointment`, `AppointmentStatus::canMoveTo`, middleware `idempotent`, `if-match`, `ExpectedVersion`, `AppointmentModified` (412 có bản mới), `findVisibleTo`, `OutboxWriter`, `AppointmentResponder`, web `appointmentQuery`, `AppointmentDetailsLayout`, `AppointmentModifiedAlert`, `SubmitButton`, `useIdempotencyKey`.

## B2. Phạm vi

| ID | Trong phạm vi |
| --- | --- |
| S-07.1 | `POST /api/v1/appointments/{appointment}/cancel`. |
| S-07.2 | Action `CancelAppointment`; enum `CancelReason`. |
| S-07.3 | Web: hộp thoại huỷ (nhân viên), bảng huỷ (khách), banner sau huỷ. |

## B3. Bất biến

| ID | Bất biến | Đọc ở đâu, khi nào |
| --- | --- | --- |
| INV-07.1 | Huỷ đặt `status = CANCELLED` và mọi phân bổ `active = false` trong **một** transaction (FR-07.1), qua `TransitionAppointment` (F04 INV-04.5). | Transaction của `TransitionAppointment`. |
| INV-07.2 | Chỉ `CONFIRMED`, `HELD` → `CANCELLED` (BR-07.1); khác → 422 `INVALID_STATUS_TRANSITION` với `currentStatus`. | `canMoveTo` trên trạng thái đọc qua `findVisibleTo`; 412 thắng 422 khi phiên bản cũ (F04 TC-04-26). |
| INV-07.3 | Khách: `start_at − now ≥ 2 giờ` cho lịch `CONFIRMED`; `now` từ đồng hồ app; `start_at` đọc cùng lần đọc với trạng thái. | `CancelAppointment` trước transaction. Đổi giờ song song (F09) làm `start_at` cũ → `UPDATE … version` sẽ 0 dòng → 412, nên luật 2 giờ không bị lách. |
| INV-07.4 | Người huỷ phải xem được lịch (`findVisibleTo`); khách huỷ lịch người khác → 404. | Bước đầu. |
| INV-07.5 | Huỷ lại lịch đã huỷ không ghi gì (D-07.4); va chạm với người huỷ khác trả 200 (D-07.5). Không bao giờ tự thử lại 412 với trạng thái khác (BR-04.2). | `CancelAppointment`. |
| INV-07.6 | `cancel_reason`, `cancel_note`, `cancelled_at`, `cancelled_by_user_id` được ghi cùng `UPDATE` tăng phiên bản. | `changes` truyền vào `TransitionAppointment`. |

Trường hợp biên:

| Quyết định | Trường hợp biên | TC |
| --- | --- | --- |
| Trạng thái (INV-07.2) | `COMPLETED`, `CHECKED_IN`, `IN_PROGRESS`, `NO_SHOW`, `EXPIRED`, `HELD` | TC-07-03, TC-07-12 |
| 2 giờ (INV-07.3) | Còn 1 giờ; đúng 2 giờ; 1 giờ 59 phút; cố vấn còn 30 phút; khách huỷ `HELD` sát giờ | TC-07-04, TC-07-05, TC-07-13 |
| Huỷ trùng (INV-07.5) | Huỷ lại sau khi đã huỷ; hai người huỷ cùng lúc; cùng khoá gửi lại | TC-07-02, TC-07-09, TC-07-14 |
| Quyền (INV-07.4, D-07.2) | Khách khác; foreman; nhân viên đại lý khác | TC-07-07, TC-07-08 |

## B4. Thay đổi theo file

| File | Thay đổi |
| --- | --- |
| `api/app/Domain/Booking/CancelReason.php` | Enum + `forStaff()`, `forCustomer()`. |
| `api/app/Domain/Booking/Actions/CancelAppointment.php` | B7. |
| `api/app/Domain/Booking/Exceptions/TooLateToCancel.php` | 422 `TOO_LATE_TO_CANCEL`, field `dealershipPhone`. |
| `api/app/Http/Requests/CancelAppointmentRequest.php` | API §B2. |
| `api/app/Http/Controllers/Api/V1/AppointmentController.php` | Thêm `cancel`. |
| `api/app/Providers/AuthServiceProvider.php` | Ability `cancel-appointment` (advisor, customer). |
| `api/routes/api.php` | Route với `auth:sanctum`, `can:cancel-appointment`, `idempotent`, `if-match`. |
| `api/app/Http/Resources/AppointmentResource.php` | `cancellation` (F04 API §B2); khách nhận `byRole` nhưng không nhận `reason`/`note` của nhân viên (Q-07.b). |
| `web/src/staff/appointments/CancelAppointmentModal.tsx`, `web/src/customer/bookings/CancelBookingSheet.tsx`, `web/src/shared/api/appointments.ts` (`useCancelAppointment`) | WEB. |

## B5. Hợp đồng

[4-api-spec.md](4-api-spec.md), [5-web-spec.md](5-web-spec.md). Không có bảng mới ([3-database-spec.md](3-database-spec.md)).

```php
// minh hoạ
final class CancelAppointment {
    public function __invoke(User $actor, string $appointmentId, int $expectedVersion, ?CancelReason $reason, ?string $note, CarbonImmutable $now): Appointment;
}
```

## B6. Seam

Dùng: `findVisibleTo`, `TransitionAppointment`, `ExpectedVersion`, web `appointmentQuery`, `AppointmentDetailsLayout`, `AppointmentModifiedAlert`.
Giao mới: hàm `isWithinCustomerChangeWindow(appointment, now)` (2 giờ) trong `api/app/Domain/Booking/CustomerChangePolicy.php` và `web/src/shared/appointments/changeWindow.ts` — F09 dùng lại cho đổi giờ.

## B7. Thuật toán (`CancelAppointment`)

1. `appointment = findVisibleTo(actor, id)` (404).
2. `status == CANCELLED` → trả 200 với lịch hiện tại (D-07.4).
3. Vai trò: cố vấn cần `reason` ∈ `forStaff()` (FormRequest đã kiểm, 400); khách `reason` ∈ `forCustomer()` hoặc null.
4. Khách + `CONFIRMED` + `start − now < 2 giờ` → 422 `TOO_LATE_TO_CANCEL` (`dealershipPhone`).
5. `TransitionAppointment(id, expected, CANCELLED, changes{cancel_reason, cancel_note, cancelled_at = now, cancelled_by_user_id}, outbox = was CONFIRMED ? AppointmentCancelled : null, actor, now)`.
   - `InvalidStatusTransition` → 422.
   - `AppointmentModified` → đọc lại; `CANCELLED` → 200 với bản mới (D-07.5); khác → ném lại (412).
6. 200 `AppointmentResource` + `ETag` mới.

## B8. Bảng lỗi

| Tình huống | HTTP | `code` |
| --- | --- | --- |
| Thiếu `reason` (cố vấn); `reason` không thuộc danh sách của vai trò; `note` > 500 | 400 | `VALIDATION_ERROR` |
| Thiếu `Idempotency-Key` | 400 | `IDEMPOTENCY_KEY_REQUIRED` |
| Foreman, admin | 403 | `FORBIDDEN` |
| Không thấy lịch | 404 | `APPOINTMENT_NOT_FOUND` |
| Phiên bản cũ (và chưa bị huỷ) | 412 | `APPOINTMENT_MODIFIED` |
| Trạng thái không huỷ được | 422 | `INVALID_STATUS_TRANSITION` |
| Khách huỷ sát giờ | 422 | `TOO_LATE_TO_CANCEL` |
| Thiếu `If-Match` | 428 | `PRECONDITION_REQUIRED` |

## B9. Acceptance criteria

| AC | Given | When | Then | TC |
| --- | --- | --- | --- | --- |
| AC-07.1 | Lịch A 09:00 dùng kỹ thuật viên duy nhất | Huỷ A, rồi đặt B 09:00 | Huỷ 200; B 201 | TC-07-01 |
| AC-07.2 | Lịch đã `CANCELLED` | Huỷ lại | 200, vẫn `CANCELLED`, phiên bản không đổi | TC-07-02 |
| AC-07.3 | Lịch `COMPLETED` | Huỷ | 422 `INVALID_STATUS_TRANSITION` | TC-07-03 |
| AC-07.4 | Lịch của khách bắt đầu sau 1 giờ | Khách huỷ trong app | 422 `TOO_LATE_TO_CANCEL` | TC-07-04 |
| AC-07.5 (mới) | Lịch bắt đầu sau 30 phút | Cố vấn huỷ | 200 | TC-07-05 |
| AC-07.6 (mới) | — | Cố vấn huỷ không có `reason` | 400 `VALIDATION_ERROR` | TC-07-06 |
| AC-07.7 (mới) | Lịch của khách B | Khách A huỷ | 404 `APPOINTMENT_NOT_FOUND` | TC-07-07 |
| AC-07.8 (mới) | Foreman | Huỷ | 403 `FORBIDDEN` | TC-07-08 |
| AC-07.9 (mới) | 5 request huỷ song song (khoá khác nhau, cùng `If-Match`) | Chạy | Cả 5 nhận 200 `CANCELLED`; phiên bản tăng đúng 1; 1 dòng lịch sử; 1 sự kiện outbox | TC-07-09 |
| AC-07.10 (mới) | Lịch `CONFIRMED`; lịch `HELD` | Huỷ mỗi cái | `CONFIRMED`: 1 sự kiện `AppointmentCancelled`; `HELD`: không có | TC-07-10 |
| AC-07.11 (mới) | Lịch v2 | Huỷ với `If-Match: "1"`; không `If-Match` | 412 có `appointment` + `ETag: "2"`; 428 | TC-07-11 |
| AC-07.12 (mới) | Nhân viên huỷ trên web | Bấm "Cancel appointment" | Hộp thoại đúng câu chữ; sau khi xác nhận: badge Cancelled + banner closed; nút hành động ẩn | TC-07-15 |
| AC-07.13 (mới) | Khách, lịch còn 1 giờ | Mở chi tiết | Không có nút huỷ; câu "It's less than 2 hours…" với số đại lý | TC-07-16 |

## B10. Thứ tự triển khai

1. Ticket 01 (prefactor): `CustomerChangePolicy` (+ bản web) tách riêng; không đổi gì người dùng thấy.
2. Tracer bullet: endpoint + hộp thoại nhân viên, rồi `/code-review`.
3. Luật khách + bảng huỷ khách.
4. Đồng thời + nghiệm thu.

## B11. Definition of Done

Mọi TC xanh; README §8 xanh; `AssertsNoOverlap` sau mọi test.

## B12. Rủi ro kỹ thuật

| ID | Rủi ro | Giảm thiểu |
| --- | --- | --- |
| R-07.1 | Kiểm 2 giờ trên dữ liệu cũ | INV-07.3: cập nhật có phiên bản chặn |
| R-07.2 | Trả 200 cho huỷ trùng che mất lỗi thật | Chỉ khi trạng thái đọc lại là `CANCELLED` (D-07.5) |
