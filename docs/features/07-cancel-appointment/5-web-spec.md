# F07 · Huỷ lịch hẹn · Web spec (WEB)

---

# PHẦN A · Cho người đọc

**Nhân viên** (bản vẽ `Admin/05-appointment-details.png`): nút "Cancel appointment" (đỏ, viền) trên trang chi tiết → hộp thoại:

```
Cancel this appointment?
Bay 2 and technician Trần Minh An will be released for 09:00–10:00 so other
customers can book it. This cannot be undone.
Reason  [Customer is busy ▾]
Note (optional) [Customer will call back to book next week.]
                         [Keep appointment] [Cancel appointment]
```

**Khách** (bản vẽ `Customer/06-details-and-cancel.png`): nút "Cancel appointment" → bảng trượt từ dưới:

```
Cancel your 10:00 appointment on Mon 2 Nov?
The slot will be released for others to book. You cannot restore it after cancelling.
Reason (optional)
( ) Something came up   ( ) I had it done elsewhere   ( ) Other reason
[Keep appointment]  [Cancel appointment]
```

Sau khi huỷ: banner "You cancelled this appointment · The time slot has been released. We'll send you a cancellation SMS."

---

# PHẦN B · Cho agent triển khai

## B0. Quy tắc đọc

> - **PHẢI** / **KHÔNG ĐƯỢC**: bắt buộc. **NÊN**: làm trừ khi có lý do ghi trong commit. **CÓ THỂ**: tuỳ chọn. `CLAUDE.md` áp dụng toàn bộ.
> - Yêu cầu là AC, INV, quyền và câu chữ. Code mẫu, query và thứ tự bước là **giả thuyết**: agent kiểm chúng với code và dữ liệu thật ở HEAD (`implement-ticket` bước 4).
> - Giả thuyết sai mà bản đúng vẫn giữ mọi AC, quyền và câu chữ → làm bản đúng, ghi vào commit và sửa spec. Bản đúng đổi điều người dùng thấy → dừng lại và hỏi.

## B1. Seam

| ID | Seam | File | Hành vi |
| --- | --- | --- | --- |
| WEB-07.1 | `useCancelAppointment(id)` | `web/src/shared/api/appointments.ts` | Mutation: đọc `etag` từ `appointmentQuery`, gửi `If-Match` + khoá (`useIdempotencyKey({id, reason, note})`). 200 → `setAppointment(…)` + invalidate danh sách, availability. 412 → trả `latest` cho `AppointmentModifiedAlert`. |
| WEB-07.2 | `CancelAppointmentModal` | `web/src/staff/appointments/CancelAppointmentModal.tsx` | Mantine `Modal`. Câu thân dùng `bay.name`, `technician.name`, `range` của lịch đang hiện. Nút xác nhận `SubmitButton` màu đỏ ("Cancelling…"). |
| WEB-07.3 | `CancelBookingSheet` | `web/src/customer/bookings/CancelBookingSheet.tsx` | Mantine `Drawer position="bottom"`; `Radio.Group` lý do (không chọn = null). "Other reason" mở `Textarea` → `note`. |
| WEB-07.4 | `canCustomerChange(appointment, now)` | `web/src/shared/appointments/changeWindow.ts` | Gương của `CustomerChangePolicy`; F09 dùng lại. `now` từ đồng hồ đã hiệu chỉnh theo header `Date` của server (F10 xây đầy đủ; F07 dùng `Date.now()`). |

## B2. Hành vi

**Nhân viên**
- Nút "Cancel appointment" chỉ hiện cho advisor, khi `status ∈ {CONFIRMED, HELD}`.
- Lý do mặc định không chọn; nút xác nhận `disabled` tới khi chọn.
- 200 → đóng hộp thoại, thông báo "Appointment cancelled", trang hiện badge Cancelled + banner closed (F05), nút hành động ẩn.
- 412 → trong hộp thoại hiện `AppointmentModifiedAlert`; "Review and retry" cập nhật trang với bản mới; nếu trạng thái mới không huỷ được thì đóng hộp thoại.
- 422 `INVALID_STATUS_TRANSITION` → `Alert` câu README §5 với `{status}`, cập nhật trang.

**Khách**
- Nút "Cancel appointment" hiện khi `canCustomerChange` đúng và `status = CONFIRMED`. Sát giờ (< 2 giờ) → thay nút bằng câu "It's less than 2 hours before your appointment. Please call {phone} to cancel." (`tel:` link).
- 422 `TOO_LATE_TO_CANCEL` (đồng hồ lệch) → cùng câu trên trong `Alert`.
- 200 → đóng bảng; banner trên thẻ: tiêu đề "You cancelled this appointment", thân "The time slot has been released. We'll send you a cancellation SMS."; ẩn nút Cancel/Change time.
- Lịch bị cố vấn huỷ: banner "This appointment was cancelled by the dealership. Call {phone} if you have questions."

## B3. Câu chữ

| Key | en | vi |
| --- | --- | --- |
| `cancel.button` | Cancel appointment | Huỷ lịch hẹn |
| `cancel.staff.title` | Cancel this appointment? | Huỷ lịch hẹn này? |
| `cancel.staff.body` | {bay} and technician {technician} will be released for {range} so other customers can book it. This cannot be undone. | {bay} và kỹ thuật viên {technician} sẽ được giải phóng cho khung {range} để khách khác đặt. Không thể hoàn tác. |
| `cancel.reason` | Reason | Lý do |
| `cancel.note` | Note (optional) | Ghi chú (không bắt buộc) |
| `cancel.keep` | Keep appointment | Giữ lịch hẹn |
| `cancel.submitting` | Cancelling… | Đang huỷ… |
| `cancel.done` | Appointment cancelled | Đã huỷ lịch hẹn |
| `reason.CUSTOMER_REQUEST` | Customer asked to cancel | Khách yêu cầu huỷ |
| `reason.CUSTOMER_BUSY` | Customer is busy | Khách bận |
| `reason.DONE_ELSEWHERE` (staff) | Service done elsewhere | Đã làm ở nơi khác |
| `reason.DEALERSHIP_ISSUE` | Dealership issue | Vấn đề từ đại lý |
| `reason.OTHER` (staff) | Other | Khác |
| `cancel.cust.title` | Cancel your {time} appointment on {day}? | Huỷ lịch hẹn {time} ngày {day}? |
| `cancel.cust.body` | The slot will be released for others to book. You cannot restore it after cancelling. | Khung giờ sẽ được trả lại cho người khác đặt. Bạn không thể khôi phục sau khi huỷ. |
| `cancel.cust.reason` | Reason (optional) | Lý do (không bắt buộc) |
| `reason.SOMETHING_CAME_UP` | Something came up | Có việc đột xuất |
| `reason.DONE_ELSEWHERE` (customer) | I had it done elsewhere | Tôi đã làm ở nơi khác |
| `reason.OTHER` (customer) | Other reason | Lý do khác |
| `cancel.cust.doneTitle` | You cancelled this appointment | Bạn đã huỷ lịch hẹn này |
| `cancel.cust.doneBody` | The time slot has been released. We'll send you a cancellation SMS. | Khung giờ đã được trả lại. Chúng tôi sẽ nhắn tin xác nhận huỷ cho bạn. |
| `cancel.cust.byDealership` | This appointment was cancelled by the dealership. Call {phone} if you have questions. | Lịch hẹn này đã được đại lý huỷ. Gọi {phone} nếu bạn có thắc mắc. |
| `cancel.cust.tooLate` | It's less than 2 hours before your appointment. Please call {phone} to cancel. | Chỉ còn dưới 2 giờ trước giờ hẹn. Vui lòng gọi {phone} để huỷ. |
