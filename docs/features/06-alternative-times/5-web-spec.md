# F06 · Gợi ý giờ trống khác · Web spec (WEB)

---

# PHẦN A · Cho người đọc

F06 nâng cấp lưới giờ trên hai màn hình có sẵn và thẻ lỗi 409:

- **New booking (nhân viên)**: lưới có giờ gạch ngang; dòng "{n} free start times"; chú thích "Struck-through slots have no free bay or technician."
- **Choose date and time (khách)**: "Morning {n} free slots", "Afternoon {n} free slots"; ngày kín → thẻ "This day is fully booked" + "Next available slot for {service}: {Fri 6 Nov · 08:00}".
- **Thẻ lỗi 409**: "Nearest available slots" với các chip "10:00 today", "11:15 today", "08:00 Tue 3 Nov"; bấm chip là chọn giờ đó.

```
Morning  6 free slots
[08:00] [0̶8̶:̶1̶5̶] [0̶8̶:̶3̶0̶] … [10:00] [10:15]
┌ This day is fully booked ─────────────┐
│ Next available slot for Oil change:    │
│ [Fri 6 Nov · 08:00]                    │
└────────────────────────────────────────┘
```

---

# PHẦN B · Cho agent triển khai

## B0. Quy tắc đọc

> - **PHẢI** / **KHÔNG ĐƯỢC**: bắt buộc. **NÊN**: làm trừ khi có lý do ghi trong commit. **CÓ THỂ**: tuỳ chọn. `CLAUDE.md` áp dụng toàn bộ.
> - Yêu cầu là AC, INV, quyền và câu chữ. Code mẫu, query và thứ tự bước là **giả thuyết**: agent kiểm chúng với code và dữ liệu thật ở HEAD (`implement-ticket` bước 4).
> - Giả thuyết sai mà bản đúng vẫn giữ mọi AC, quyền và câu chữ → làm bản đúng, ghi vào commit và sửa spec. Bản đúng đổi điều người dùng thấy → dừng lại và hỏi.

## B1. Seam

| ID | Seam | File | Hành vi |
| --- | --- | --- | --- |
| WEB-06.1 | `useAvailability({ dealershipId, serviceTypeId, date, excludeAppointmentId? })` | `web/src/shared/api/availability.ts` | `queryKey ['availability', …]`, `staleTime` 15 s; `invalidateAvailability()` gọi sau mọi 409 và sau mỗi lần đặt/đổi/huỷ thành công. |
| WEB-06.2 | `useNextAvailable({ …, after })` | như trên | Chỉ gọi khi `freeCount === 0`. |
| WEB-06.3 | `SlotGrid` | `web/src/shared/booking/SlotGrid.tsx` | Thay `TimeGrid`. Chip không trống: `disabled`, `td="line-through"`, `aria-disabled`, `title` theo `reason` (B3). Nhóm Morning/Afternoon kèm đếm (biến thể khách). Ngày `closed` → "The dealership is closed on this day." |
| WEB-06.4 | `AlternativeChips` | `web/src/shared/booking/AlternativeChips.tsx` | Nhận `alternatives`, `timeZone`, `onPick(start)`; nhãn: cùng ngày với giờ muốn → "{HH:mm} today" (nếu ngày đó là hôm nay) hoặc "{HH:mm}"; ngày khác → "{HH:mm} {Ddd D Mmm}". |
| WEB-06.5 | `FullyBookedCard` | `web/src/customer/booking/FullyBookedCard.tsx` | Khách; dùng WEB-06.2. |

## B2. Hành vi

- Đổi dịch vụ hoặc ngày → giờ đã chọn bị bỏ nếu không còn `available`.
- `onPick(start)` từ chip gợi ý: đổi ngày nếu cần, chọn giờ, ẩn thẻ lỗi, khoá chống lặp mới (input đổi → `useIdempotencyKey` tự tạo khoá mới).
- Sau 409: gọi `invalidateAvailability()` để lưới hiện đúng giờ vừa kín.
- Khách bước 3 nhận 409: quay lại bước 2 kèm `Alert` có `AlternativeChips`.
- Dưới lưới khách: "Times can be taken by someone else until you confirm." (RB-06.1).
- Đang tải lưới: `Skeleton` chip; lỗi: `Alert` + "Retry".

## B3. Câu chữ

| Key | en | vi |
| --- | --- | --- |
| `staff.freeCount` | {n} free start times | {n} giờ bắt đầu còn trống |
| `staff.legend` | Struck-through slots have no free bay or technician. | Giờ bị gạch không còn khoang hoặc kỹ thuật viên trống. |
| `cust.freeSlots` | {n} free slots | {n} khung trống |
| `cust.fullyBooked` | This day is fully booked | Ngày này đã kín lịch |
| `cust.nextFor` | Next available slot for {service}: | Khung trống gần nhất cho {service}: |
| `cust.noneSoon` | No free time in the next 7 days. Call {phone}. | Không còn giờ trống trong 7 ngày tới. Hãy gọi {phone}. |
| `cust.hint` | Times can be taken by someone else until you confirm. | Giờ có thể bị người khác đặt trước khi bạn xác nhận. |
| `nearest` | Nearest available slots | Các khung trống gần nhất |
| `today` | {time} today | {time} hôm nay |
| `noAlternatives` | No free time nearby. Pick another day. | Không có giờ trống gần đó. Hãy chọn ngày khác. |
| `reason.PAST` (tooltip) | This time has passed | Giờ này đã qua |
| `reason.NO_TECHNICIAN_AVAILABLE` | No qualified technician free | Không có kỹ thuật viên phù hợp rảnh |
| `reason.NO_BAY_AVAILABLE` | No bay free | Không còn khoang trống |
| `reason.NO_QUALIFIED_TECHNICIAN` | No technician for this service | Không có kỹ thuật viên cho dịch vụ này |
