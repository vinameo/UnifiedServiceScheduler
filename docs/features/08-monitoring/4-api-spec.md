# F08 · Giám sát · API spec (API)

---

# PHẦN A · Cho người đọc

Hai API cho màn hình admin (tóm tắt theo khoảng thời gian; tìm lỗi), cộng `/metrics` cho hệ thống giám sát trong mạng nội bộ, và hai endpoint health có sẵn.

---

# PHẦN B · Cho agent triển khai

## B0. Quy tắc đọc

> - **PHẢI** / **KHÔNG ĐƯỢC**: bắt buộc. **NÊN**: làm trừ khi có lý do ghi trong commit. **CÓ THỂ**: tuỳ chọn. `CLAUDE.md` áp dụng toàn bộ.
> - Yêu cầu là AC, INV, quyền và câu chữ. Code mẫu, query và thứ tự bước là **giả thuyết**: agent kiểm chúng với code và dữ liệu thật ở HEAD (`implement-ticket` bước 4).
> - Giả thuyết sai mà bản đúng vẫn giữ mọi AC, quyền và câu chữ → làm bản đúng, ghi vào commit và sửa spec. Bản đúng đổi điều người dùng thấy → dừng lại và hỏi.

## B1. Endpoint

| ID | Method + path | Ai | Ghi chú |
| --- | --- | --- | --- |
| API-08.1 | `GET /api/v1/monitoring/summary?range=1h|24h|7d` | `can:admin` | |
| API-08.2 | `GET /api/v1/monitoring/errors?correlationId=&cursor=&limit=` | `can:admin` | Phân trang cursor (F05), mặc định 20 |
| API-08.3 | `GET /metrics` | Nội bộ (D-08.7) | `text/plain; version=0.0.4` |
| API-08.4 | `GET /health/live`, `GET /health/ready` | Không auth | Có sẵn |

## B2. Hợp đồng

### API-08.1

```json
{ "data": {
  "range": "24h", "from": "2026-11-01T17:00:00+07:00", "to": "2026-11-02T17:00:00+07:00",
  "health": {
    "app": { "status": "UP", "bookingP95Ms": 184 },
    "database": { "status": "UP", "connectionsUsed": 8, "connectionsMax": 100 },
    "outbox": { "pending": 2, "oldestPendingAgeSeconds": 420 } },
  "bookings": { "total": 1248, "confirmed": 1102, "noAvailability": 131, "validationError": 0,
                "error": 15, "p95Ms": 184, "allocationRetries": 23, "idempotentReplays": 41,
                "appointmentModified": 7 },
  "recentErrors": [ ApiEvent… (≤ 20, mới nhất trước) ] } }
```

Số trong `bookings` đếm từ `api_events` trong khoảng (không từ `metric_counters`, vì bộ đếm là tổng từ đầu). `database.status = DOWN` nếu `select 1` lỗi (API vẫn 200 nếu đọc được phần khác; nếu DB chết thì endpoint 500 — màn hình hiện lỗi).

### `ApiEvent`

```json
{ "id": 812, "occurredAt": "2026-11-02T16:44:03+07:00", "endpoint": "POST /appointments",
  "code": "NO_AVAILABILITY", "status": 409, "correlationId": "7f3c9a1e-…",
  "details": "No Standard service bay free 10:00–12:00" }
```

`details` dựng từ `detail` đã che: 409 `NO_AVAILABILITY` → câu theo `reason` và khoảng giờ; 5xx → tên lớp exception + thông điệp đã che, ≤ 200 ký tự; `DELIVERY_FAILED` (F11) → "{event} for {reference}, all 5 attempts used".

### API-08.3 (minh hoạ)

```
# TYPE booking_requests_total counter
booking_requests_total{outcome="confirmed"} 1102
booking_requests_total{outcome="no_availability"} 131
# TYPE booking_duration_seconds histogram
booking_duration_seconds_bucket{le="0.3"} 1180
…
# TYPE outbox_pending_messages gauge
outbox_pending_messages 2
```

## B3. Bảng lỗi

RQ §B8.

## B4. Bảng đầu vào bất thường

| Endpoint | Đầu vào | Kỳ vọng |
| --- | --- | --- |
| 08.1 | `range=30d`, thiếu `range` | 400 |
| 08.2 | `correlationId` > 128 ký tự hoặc có ký tự ngoài `[A-Za-z0-9._-]` | 400 |
| 08.2 | `correlationId` của lỗi ở đại lý khác | 200, `data: []` |
| 08.1, 08.2 | Advisor, foreman, khách | 403 |
| 08.3 | Header `X-Forwarded-For: 10.0.0.1` từ ngoài | 404 (không tin header) |
| 08.3 | Qua Nginx công khai | 404 |

## B5. Giới hạn

`recentErrors` ≤ 20; `errors` `limit` ≤ 100; dữ liệu 7 ngày.
