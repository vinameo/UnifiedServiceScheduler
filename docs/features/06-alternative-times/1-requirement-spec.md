# F06 · Gợi ý giờ trống khác · Requirement spec (RQ)

Nhánh: `feature/06-alternative-times` · Ưu tiên: Should · Quy ước chung: [README](../README.md)

---

# PHẦN A · Cho người đọc

## A1. Câu chuyện

**Vì sao có feature này.** Khách không nên phải đoán giờ này đến giờ khác. Cố vấn đang nói chuyện điện thoại cần thấy ngay giờ nào còn trống.

**Feature làm gì.**
- Lưới giờ của một ngày (bước 15 phút trong giờ mở cửa) cho biết giờ nào **thật sự** có một kỹ thuật viên và một khoang rảnh cho **trọn** thời gian dịch vụ. Giờ không trống bị gạch ngang (không ẩn) để lưới giữ hình dạng.
- Khi giờ đã chọn vừa bị người khác lấy (`409`), câu trả lời kèm tối đa 3 giờ trống gần nhất: trước hết trong cùng ngày, rồi tối đa 7 ngày sau.
- Khi cả ngày đã kín (app khách), hiện "This day is fully booked" và giờ trống gần nhất.

**Ai dùng.** Cố vấn (New booking), khách (Choose date and time); F09 (đổi giờ) và F10 (giữ chỗ) dùng lại.

**Không làm.** Giữ chỗ (F10). Danh sách giờ là **gợi ý, không phải giữ chỗ**: người khác có thể lấy trước; khi đó 409 là đúng (BR-06.1).

## A2. Các bước sử dụng

1. Chọn dịch vụ và ngày → lưới hiện giờ trống (bình thường) và giờ kín (gạch ngang).
2. Chọn giờ trống → đặt (F02).
3. Nếu bị 409 → thẻ lỗi có "Nearest available slots" → bấm một giờ → giờ được chọn sẵn → xác nhận lại.

## A3. Màn hình và khác biệt so với bản vẽ

Bản vẽ: `Admin/01-new-booking.png` (lưới có gạch ngang), `Admin/03-booking-error-states.png` (gợi ý), `Customer/02-choose-date-time.png` ("{n} free slots", "This day is fully booked", "Next available slot"). F06 làm đúng như bản vẽ; không có khác biệt.

## A4. Luật chính bằng lời thường

- Một giờ được coi là trống chỉ khi F03 tìm được ít nhất một cặp kỹ thuật viên + khoang cho trọn thời lượng dịch vụ.
- Giờ cuối cùng trong ngày = giờ đóng cửa − thời lượng (dịch vụ 60 phút, đóng cửa 17:00 → giờ cuối 16:00).
- Giờ đã qua trong hôm nay không trống.
- Lịch đang giữ chỗ (của khách khác) làm giờ đó kín.

## A5. Ngoài phạm vi

Gợi ý theo kỹ thuật viên yêu thích, gợi ý đại lý khác, thông báo khi có giờ trống.

## A6. Tiêu chí thành công

- NFR-02: giờ trống cho một ngày p95 < 300 ms với 20 khoang, 30 kỹ thuật viên.
- Số truy vấn DB cho một ngày không phụ thuộc số khung giờ (FR-06.4).

## A7. Quyết định

| ID | Quyết định |
| --- | --- |
| D-06.1 | Endpoint `GET /dealerships/{d}/availability?serviceTypeId=&date=` trả **mọi** khung (trống và không) kèm `available` và `reason`, để web gạch ngang (FR-06.5). |
| D-06.2 | `reason` của khung không trống: `PAST`, hoặc lý do F03 (`NO_QUALIFIED_TECHNICIAN`, `NO_TECHNICIAN_AVAILABLE`, `NO_BAY_AVAILABLE`). |
| D-06.3 | `date` trước hôm nay → 422 `START_IN_PAST`; sau hôm nay + 90 → 422 `START_TOO_FAR_AHEAD` (luật F02). Ngày đóng cửa → 200, `closed: true`, `slots: []`. |
| D-06.4 | Thứ tự gợi ý (FR-06.3): khung trống cùng ngày, xa giờ muốn dần (khoảng cách tuyệt đối; bằng nhau thì giờ sớm trước), bỏ giờ đã qua và chính giờ muốn; nếu chưa đủ 3, thêm các ngày +1…+7 theo thứ tự thời gian (sớm nhất trước). Tổng ≤ 3. |
| D-06.5 | Gợi ý được tính **sau** khi transaction lỗi đã rollback, ngoài transaction, với snapshot mới. |
| D-06.6 | `excludeAppointmentId` (tuỳ chọn) bỏ qua lịch đó khi tính trống, để F09 đổi giờ thấy cả khung của chính lịch mình. Người gọi phải xem được lịch đó (`findVisibleTo`), không thì 404 `APPOINTMENT_NOT_FOUND`. |
| D-06.7 | `GET /dealerships/{d}/availability/next?serviceTypeId=&after=` trả khung trống đầu tiên sau `after` trong 7 ngày (cho thẻ "Next available slot"), hoặc `null`. |
| D-06.8 | Giới hạn 120 request/phút mỗi người dùng cho hai endpoint availability. |
| D-06.9 | Web thay `TimeGrid` (F02) bằng `SlotGrid`; luồng F02 không đổi. |

## A8. Câu hỏi mở

| ID | Câu hỏi | Mặc định |
| --- | --- | --- |
| Q-06.a | Gợi ý có tính giờ **trước** giờ muốn trong cùng ngày? | Có (khoảng cách tuyệt đối). |
| Q-06.b | Lưới có tự làm mới? | Có: khi đổi ngày/dịch vụ, khi quay lại tab, và ngay sau một 409. |

---

# PHẦN B · Cho agent triển khai

## B0. Quy tắc đọc

> - **PHẢI** / **KHÔNG ĐƯỢC**: bắt buộc. **NÊN**: làm trừ khi có lý do ghi trong commit. **CÓ THỂ**: tuỳ chọn. `CLAUDE.md` áp dụng toàn bộ.
> - Yêu cầu là AC, INV, quyền và câu chữ. Code mẫu, query và thứ tự bước là **giả thuyết**: agent kiểm chúng với code và dữ liệu thật ở HEAD (`implement-ticket` bước 4).
> - Giả thuyết sai mà bản đúng vẫn giữ mọi AC, quyền và câu chữ → làm bản đúng, ghi vào commit và sửa spec. Bản đúng đổi điều người dùng thấy → dừng lại và hỏi.

## B1. Trạng thái code hiện tại

Giả định F00–F05 đã merge: `CandidateFinder`, `OpeningHours`, `AllocationSnapshotRepository::forDay`, `BookingTimeRules`, `CreateBooking` (ném `NoAvailability`), `findVisibleTo`, web `TimeGrid`, `BookingErrorCard` (slot `actions`), `BookingDraft`.

## B2. Phạm vi

| ID | Trong phạm vi |
| --- | --- |
| S-06.1 | `AvailabilityCalculator::forDay(dealershipId, serviceType, localDate, now, ?excludeAppointmentId)`. |
| S-06.2 | `AlternativeTimesFinder::near(…)`, `::next(…)`. |
| S-06.3 | 2 endpoint availability. |
| S-06.4 | Gắn `alternatives` vào mọi `409 NO_AVAILABILITY` (F02 hôm nay; F09 dùng lại). |
| S-06.5 | Web: `SlotGrid`, thẻ "fully booked", chip gợi ý trong thẻ lỗi. |

## B3. Bất biến

| ID | Bất biến | Đọc ở đâu, khi nào |
| --- | --- | --- |
| INV-06.1 | Khung `t` có `available: true` khi và chỉ khi `t > now`, `[t, t+duration)` nằm trong giờ mở cửa ngày đó, và `CandidateFinder::find` trả ít nhất một cặp trên snapshot của ngày (FR-06.2). | `AvailabilityCalculator`, một snapshot cho cả ngày. |
| INV-06.2 | Khung đầu = giờ mở cửa; khung cuối = giờ đóng − thời lượng; bước 15 phút (FR-06.1). | `OpeningHours` + `BookingTimeRules::STEP_MINUTES`. |
| INV-06.3 | Số truy vấn DB cho một ngày cố định, không phụ thuộc số khung (FR-06.4). | `forDay` gọi `AllocationSnapshotRepository::forDay` **một** lần. |
| INV-06.4 | Không có cache giữa các request (SDD §12). | — |
| INV-06.5 | Gợi ý chỉ chứa khung `available: true` tại thời điểm tính, ≤ 3, theo D-06.4, không trùng nhau, không chứa giờ muốn. | `AlternativeTimesFinder`. |
| INV-06.6 | Nhân viên chỉ hỏi đại lý mình (404 `DEALERSHIP_NOT_FOUND`); khách hỏi mọi đại lý nhưng chỉ dịch vụ `active` (404 `SERVICE_TYPE_NOT_FOUND`). | Route binding `{dealership}` (F01) + đọc dịch vụ. |
| INV-06.7 | `excludeAppointmentId` chỉ có tác dụng nếu người gọi xem được lịch đó; không thì 404, không lặng lẽ bỏ qua. | `findVisibleTo` trước khi tính. |

Trường hợp biên:

| Quyết định | Trường hợp biên | TC |
| --- | --- | --- |
| INV-06.1 | Kỹ thuật viên duy nhất bận 09–10; giờ đã qua; khung chạm giờ đóng; giữ chỗ còn hạn / hết hạn | TC-06-01, 06-04, 06-02, TC-10-xx (F10) |
| INV-06.5 | Cùng ngày đủ 3; cùng ngày thiếu; 7 ngày kín hết; hai khung cách đều giờ muốn | TC-06-03, 06-07, 06-08, 06-09 |
| INV-06.6 | Nhân viên đại lý khác; khách dịch vụ inactive | TC-06-06 |
| INV-06.7 | Khách exclude lịch của người khác | TC-06-11 |

## B4. Thay đổi theo file

| File | Thay đổi |
| --- | --- |
| `api/app/Domain/Booking/Availability/AvailabilityCalculator.php` | S-06.1. |
| `api/app/Domain/Booking/Availability/AlternativeTimesFinder.php` | S-06.2. |
| `api/app/Domain/Booking/Exceptions/NoAvailability.php` | Thêm field `alternatives` (mảng `{start, end}`), mặc định `[]`. |
| `api/app/Domain/Booking/Actions/CreateBooking.php` | Bắt `NoAvailability`, tính gợi ý (D-06.5), ném lại kèm `alternatives`. |
| `api/app/Http/Controllers/Api/V1/AvailabilityController.php`, `Requests/AvailabilityRequest.php`, `Resources/AvailabilityResource.php` | API. |
| `api/routes/api.php` | 2 route + `throttle:120,1`. |
| `web/src/shared/booking/SlotGrid.tsx`, `web/src/shared/api/availability.ts` | WEB. |
| `web/src/staff/booking/NewBookingPage.tsx`, `web/src/customer/booking/ChooseTimePage.tsx`, `web/src/shared/booking/BookingErrorCard.tsx` | Dùng `SlotGrid`, chip gợi ý. |

## B5. Hợp đồng

[4-api-spec.md](4-api-spec.md), [5-web-spec.md](5-web-spec.md). Không có bảng mới.

```php
// minh hoạ — INV-06.1, 06.3
final class AvailabilityCalculator {
    /** @return list<SlotState> SlotState{start: CarbonImmutable, available: bool, reason: ?string} */
    public function forDay(string $dealershipId, ServiceType $type, string $localDate, CarbonImmutable $now, ?string $excludeAppointmentId = null): DayAvailability;
}
final class AlternativeTimesFinder {
    /** @return list<TimeRange> ≤ $limit — INV-06.5 */
    public function near(string $dealershipId, ServiceType $type, CarbonImmutable $wanted, CarbonImmutable $now, ?string $excludeAppointmentId = null, int $limit = 3): array;
    public function next(string $dealershipId, ServiceType $type, CarbonImmutable $after, CarbonImmutable $now): ?TimeRange;
}
```

## B6. Seam

| Seam | Dùng lại ở |
| --- | --- |
| `AvailabilityCalculator` | F09 (lưới đổi giờ với `excludeAppointmentId`), F10 |
| `AlternativeTimesFinder::near` | F09 (409 khi đổi giờ) |
| Web `SlotGrid`, `AlternativeChips` | F09 (nhân viên, khách), F10 |

FC: FC-06.1 (gợi ý kỹ thuật viên quen) → `AllocationRequest.preferTechnicianId` đã có; FC-06.2 (cache ngắn khi tải cao) → bị cấm hiện tại (INV-06.4), cân nhắc ở SDD phase 2.

## B7. Thuật toán

**`forDay`**: lấy cửa sổ mở cửa của ngày (đóng → `closed`); nạp snapshot một lần; với mỗi `t` từ `opens` đến `closes − duration` bước 15: `t <= now` → `PAST`; còn lại `find(AllocationRequest(range = [t, t+duration), excludeAppointmentId))` → `available` hoặc `reason`.

**`near`**: `forDay(ngày của wanted)` → lọc `available`, bỏ `wanted` → sắp `(|t − wanted|, t)` → lấy tối đa 3. Nếu < 3: lần lượt ngày +1…+7: `forDay` → lấy các khung `available` theo thứ tự thời gian tới khi đủ 3. Dừng sớm khi đủ.

**`next`**: như phần "ngày sau" của `near` nhưng bắt đầu từ `after` (cùng ngày, các khung > `after`), giới hạn 1.

## B8. Bảng lỗi

| Tình huống | HTTP | `code` |
| --- | --- | --- |
| Thiếu/sai `serviceTypeId`, `date`, `after`; `excludeAppointmentId` sai dạng | 400 | `VALIDATION_ERROR` |
| Vượt 120/phút | 429 | `TOO_MANY_ATTEMPTS` |
| Đại lý khác (nhân viên) | 404 | `DEALERSHIP_NOT_FOUND` |
| Dịch vụ không có/inactive/khác đại lý | 404 | `SERVICE_TYPE_NOT_FOUND` |
| `excludeAppointmentId` không xem được | 404 | `APPOINTMENT_NOT_FOUND` |
| `date` đã qua | 422 | `START_IN_PAST` |
| `date` quá 90 ngày | 422 | `START_TOO_FAR_AHEAD` |

## B9. Acceptance criteria

| AC | Given | When | Then | TC |
| --- | --- | --- | --- | --- |
| AC-06.1 | Kỹ thuật viên đủ điều kiện duy nhất bận 09:00–10:00 | Lấy giờ trống cho dịch vụ 60 phút | 08:15 đến 09:45 không trống; 08:00 và 10:00 trống | TC-06-01 |
| AC-06.2 | Đóng cửa 17:00 | Lấy giờ trống cho dịch vụ 60 phút | Khung cuối là 16:00 | TC-06-02 |
| AC-06.3 | 09:00 kín, 10:00 và 11:00 trống | Đặt 09:00 | 409 với gợi ý bắt đầu bằng 10:00 | TC-06-03 (qua `POST /appointments`) |
| AC-06.4 (mới) | Hôm nay, bây giờ 10:05 | Lấy giờ trống hôm nay | Khung ≤ 10:00 `available: false`, `reason: PAST` | TC-06-04 |
| AC-06.5 (mới) | Chủ nhật đóng cửa | Lấy giờ trống Chủ nhật | 200, `closed: true`, `slots: []` | TC-06-05 |
| AC-06.6 (mới) | Nhân viên D2; khách | Nhân viên hỏi D1; khách hỏi D1 với dịch vụ inactive | 404 `DEALERSHIP_NOT_FOUND`; 404 `SERVICE_TYPE_NOT_FOUND` | TC-06-06 |
| AC-06.7 (mới) | Hôm nay chỉ còn 15:00 trống; ngày mai trống từ 08:00 | Đặt 09:00 bị 409 | Gợi ý: 15:00 hôm nay, 08:00 và 08:15 ngày mai | TC-06-07 |
| AC-06.8 (mới) | 8 ngày tới kín hết | Đặt bị 409 | `alternatives: []` | TC-06-08 |
| AC-06.9 (mới) | 10:00 và 08:00 cùng cách 09:00 một giờ | Gợi ý cho 09:00 | 08:00 trước 10:00 | TC-06-09 |
| AC-06.10 (mới) | 36 khung và 4 khung (dịch vụ dài) | Đếm truy vấn | Bằng nhau | TC-06-10 |
| AC-06.11 (mới) | Lịch X của khách A giữ An 10–11 (An là người duy nhất) | A hỏi 10:00 với `excludeAppointmentId=X`; B hỏi với X | A: 10:00 trống; B: 404 | TC-06-11 |
| AC-06.12 (mới) | Ngày kín, thứ Sáu 08:00 trống | `GET …/availability/next` | `nextAvailable` = thứ Sáu 08:00 | TC-06-12 |
| AC-06.13 (mới) | Lưới có giờ kín | Màn hình | Giờ kín gạch ngang, không bấm được, vẫn hiện | TC-06-14 |
| AC-06.14 (mới) | App khách, ngày kín | Màn hình | "This day is fully booked" + "Next available slot for Oil change: Fri 6 Nov · 08:00"; bấm → chọn ngày và giờ đó | TC-06-15 |
| AC-06.15 (mới) | New booking nhận 409 có gợi ý | Bấm chip "10:00 today" | Giờ 10:00 được chọn; thẻ lỗi biến mất; khoá mới cho lần xác nhận | TC-06-16 |
| AC-06.16 (mới) | 20 khoang, 30 kỹ thuật viên, 200 lịch | Gọi availability | p95 < 300 ms | TC-06-13 |

## B10. Thứ tự triển khai

1. Ticket 01 (prefactor): `TimeGrid` nhận trạng thái từng chip từ props; không đổi hành vi.
2. Tracer bullet: `AvailabilityCalculator` + endpoint + `SlotGrid` ở New booking, rồi `/code-review`.
3. `AlternativeTimesFinder` + gắn vào 409 + chip gợi ý.
4. App khách: đếm "free slots", "fully booked", `next`.
5. `excludeAppointmentId`, hiệu năng, nghiệm thu.

## B11. Definition of Done

Mọi TC xanh; README §8 xanh; TC-06-13 đạt ngưỡng.

## B12. Rủi ro kỹ thuật

| ID | Rủi ro | Giảm thiểu |
| --- | --- | --- |
| R-06.1 | Gợi ý 8 ngày = 8 snapshot → chậm 409 | Dừng sớm khi đủ 3; mỗi snapshot ≤ 6 truy vấn; đo TC-06-13 |
| R-06.2 | Lưới cũ (người khác vừa đặt) → người dùng chọn giờ đã kín | BR-06.1: chấp nhận; 409 + làm mới lưới ngay |
