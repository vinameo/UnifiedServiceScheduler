# F06 · Gợi ý giờ trống khác · API spec (API)

---

# PHẦN A · Cho người đọc

Hai API đọc: lưới giờ của một ngày (mọi khung, mỗi khung trống hay không), và "giờ trống kế tiếp". Thêm field `alternatives` vào lỗi `409 NO_AVAILABILITY` của mọi endpoint đặt/đổi giờ.

---

# PHẦN B · Cho agent triển khai

## B0. Quy tắc đọc

> - **PHẢI** / **KHÔNG ĐƯỢC**: bắt buộc. **NÊN**: làm trừ khi có lý do ghi trong commit. **CÓ THỂ**: tuỳ chọn. `CLAUDE.md` áp dụng toàn bộ.
> - Yêu cầu là AC, INV, quyền và câu chữ. Code mẫu, query và thứ tự bước là **giả thuyết**: agent kiểm chúng với code và dữ liệu thật ở HEAD (`implement-ticket` bước 4).
> - Giả thuyết sai mà bản đúng vẫn giữ mọi AC, quyền và câu chữ → làm bản đúng, ghi vào commit và sửa spec. Bản đúng đổi điều người dùng thấy → dừng lại và hỏi.

## B1. Endpoint

| ID | Method + path | Ai | Middleware |
| --- | --- | --- | --- |
| API-06.1 | `GET /api/v1/dealerships/{dealership}/availability` | khách (mọi đại lý), nhân viên (đại lý mình) | `auth:sanctum`, `throttle:120,1` |
| API-06.2 | `GET /api/v1/dealerships/{dealership}/availability/next` | như trên | như trên |
| API-06.3 | Field `alternatives` trong `409 NO_AVAILABILITY` | — | — |

## B2. Hợp đồng

### API-06.1

Query: `serviceTypeId` (UUID, bắt buộc), `date` (`YYYY-MM-DD`, bắt buộc), `excludeAppointmentId` (UUID, tuỳ chọn).

```json
{ "data": {
    "date": "2026-11-02", "timezone": "Asia/Ho_Chi_Minh", "closed": false,
    "opensAt": "08:00", "closesAt": "17:00", "stepMinutes": 15, "durationMinutes": 60,
    "freeCount": 30,
    "slots": [
      { "start": "2026-11-02T08:00:00+07:00", "end": "2026-11-02T09:00:00+07:00", "available": true, "reason": null },
      { "start": "2026-11-02T08:15:00+07:00", "end": "2026-11-02T09:15:00+07:00", "available": false, "reason": "NO_TECHNICIAN_AVAILABLE" }
    ] } }
```

Ngày đóng cửa: `closed: true`, `opensAt`/`closesAt` `null`, `slots: []`, `freeCount: 0`.

### API-06.2

Query: `serviceTypeId`, `after` (ISO 8601 có offset). Trả `{ "data": { "nextAvailable": { "start": "…", "end": "…" } | null } }`. Tìm trong `after` … `after + 7 ngày`.

### API-06.3

```json
{ "code": "NO_AVAILABILITY", "status": 409, "reason": "NO_TECHNICIAN_AVAILABLE",
  "requestedStart": "2026-11-02T09:00:00+07:00", "requestedEnd": "2026-11-02T10:00:00+07:00",
  "alternatives": [
    { "start": "2026-11-02T10:00:00+07:00", "end": "2026-11-02T11:00:00+07:00" },
    { "start": "2026-11-02T11:15:00+07:00", "end": "2026-11-02T12:15:00+07:00" },
    { "start": "2026-11-03T08:00:00+07:00", "end": "2026-11-03T09:00:00+07:00" } ] }
```

Câu trả lời 409 có `alternatives` được lưu cùng khoá chống lặp (F04): gửi lại cùng khoá nhận lại đúng gợi ý cũ.

## B3. Bảng lỗi

RQ §B8.

## B4. Bảng đầu vào bất thường

| Endpoint | Đầu vào | Kỳ vọng |
| --- | --- | --- |
| 06.1 | Thiếu `date` / `date=2026-11-31` / `date=today` | 400 |
| 06.1 | `serviceTypeId=abc` | 400 |
| 06.1 | `serviceTypeId` của đại lý khác | 404 `SERVICE_TYPE_NOT_FOUND` |
| 06.1 | `date` hôm qua | 422 `START_IN_PAST` |
| 06.1 | `date` = hôm nay + 91 | 422 `START_TOO_FAR_AHEAD` |
| 06.1 | `excludeAppointmentId` của khách khác (khách gọi) | 404 `APPOINTMENT_NOT_FOUND` |
| 06.1 | `excludeAppointmentId=abc` | 400 |
| 06.2 | `after` không có offset | 400 |
| 06.1, 06.2 | 121 request trong một phút | 429 |

## B5. Giới hạn

| Giới hạn | Giá trị |
| --- | --- |
| Ngày hỏi | hôm nay … hôm nay + 90 |
| Gợi ý | ≤ 3, trong 8 ngày (ngày muốn + 7) |
| `next` | trong 7 ngày sau `after` |
| Rate limit | 120/phút mỗi người dùng |
