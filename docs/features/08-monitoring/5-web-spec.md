# F08 · Giám sát · Web spec (WEB)

---

# PHẦN A · Cho người đọc

Màn hình "System monitoring" (`/staff/monitoring`, chỉ admin), theo bản vẽ `Admin/11-system-monitoring.png`:

```
System monitoring                                 [1 hour | 24 hours | 7 days]
Service health, booking metrics and recent errors.
┌ Application ──────┐ ┌ Database ──────────────┐ ┌ Outbox pending ───────┐
│ Running · p95 184 │ │ PostgreSQL · 8/100 conn│ │ 2 events · oldest 7min│
│ ms (bookings)  UP │ │                     UP │ │                 [View]│
└───────────────────┘ └────────────────────────┘ └───────────────────────┘
Bookings in the last 24 hours
[Booking requests 1,248] [Confirmed 1,102 · 88.3%] [No availability (409) 131 · business outcome, not an outage]
[System errors (5xx) 15 · 1.2%] [p95 184 ms · Target under 300 ms] [Allocation retries 23] [Idempotent replays 41]
Recent errors                                  [Search by correlationId ___]
Time      Endpoint            Error code       HTTP  correlationId  Details
16:44:03  POST /appointments  NO_AVAILABILITY  409   7f3c9a1e       No Standard service bay free 10:00–12:00
```

---

# PHẦN B · Cho agent triển khai

## B0. Quy tắc đọc

> - **PHẢI** / **KHÔNG ĐƯỢC**: bắt buộc. **NÊN**: làm trừ khi có lý do ghi trong commit. **CÓ THỂ**: tuỳ chọn. `CLAUDE.md` áp dụng toàn bộ.
> - Yêu cầu là AC, INV, quyền và câu chữ. Code mẫu, query và thứ tự bước là **giả thuyết**: agent kiểm chúng với code và dữ liệu thật ở HEAD (`implement-ticket` bước 4).
> - Giả thuyết sai mà bản đúng vẫn giữ mọi AC, quyền và câu chữ → làm bản đúng, ghi vào commit và sửa spec. Bản đúng đổi điều người dùng thấy → dừng lại và hỏi.

## B1. Route và dữ liệu

| Path | Element | Guard |
| --- | --- | --- |
| `/staff/monitoring` | `staff/monitoring/MonitoringPage` | `RequireRole ['ADMIN']` |

| ID | Hook | API | Ghi chú |
| --- | --- | --- | --- |
| WEB-08.1 | `useMonitoringSummary(range)` | API-08.1 | `refetchInterval` 30 s; `range` trong URL `?range=24h` (mặc định `1h`) |
| WEB-08.2 | `useMonitoringErrors(correlationId)` | API-08.2 | Chạy khi ô tìm có ≥ 8 ký tự (debounce 300 ms) |

## B2. Hiển thị

- Thẻ sức khoẻ: badge `UP` xanh / `DOWN` đỏ; Outbox "{n} events · oldest {age}" (`age` "7 min", "2 h"); nút "View" → `/staff/notifications?status=PENDING` khi F11 đã có trang.
- Thẻ số: số định dạng `en-GB` (1,248); phần trăm 1 chữ số thập phân so với `total`; p95 đỏ khi ≥ 300.
- Bảng lỗi: Time `HH:mm:ss` theo múi giờ đại lý; correlationId hiện 8 ký tự đầu, bấm để chép cả chuỗi (`CopyButton`); dòng 5xx màu đỏ nhạt.
- Khi đang tìm: bảng hiện kết quả tìm; xoá ô → quay lại "Recent errors".
- Lỗi tải: `Alert` + "Retry"; 403 không xảy ra vì guard.
- Có thêm `HealthBadge` (có sẵn ở `web/src/shared/HealthBadge.tsx`) cho `/health/ready` ở góc thẻ Application.

## B3. Câu chữ

| Key | en | vi |
| --- | --- | --- |
| `title` | System monitoring | Giám sát hệ thống |
| `subtitle` | Service health, booking metrics and recent errors. | Sức khoẻ dịch vụ, số liệu đặt lịch và lỗi gần đây. |
| `range.1h` / `24h` / `7d` | 1 hour / 24 hours / 7 days | 1 giờ / 24 giờ / 7 ngày |
| `health.app` | Application | Ứng dụng |
| `health.appBody` | Running · p95 {ms} ms (bookings) | Đang chạy · p95 {ms} ms (đặt lịch) |
| `health.db` | Database | Cơ sở dữ liệu |
| `health.dbBody` | PostgreSQL · {used}/{max} connections | PostgreSQL · {used}/{max} kết nối |
| `health.outbox` | Outbox pending | Outbox đang chờ |
| `health.outboxBody` | {n} events · oldest {age} | {n} sự kiện · cũ nhất {age} |
| `health.view` | View | Xem |
| `bookings.title` | Bookings in the last {range} | Đặt lịch trong {range} qua |
| `bookings.total` | Booking requests | Yêu cầu đặt lịch |
| `bookings.totalNote` | Total POST /appointments | Tổng POST /appointments |
| `bookings.confirmed` | Confirmed | Đã xác nhận |
| `bookings.ofRequests` | {pct}% of requests | {pct}% số yêu cầu |
| `bookings.noAvailability` | No availability (409) | Hết chỗ (409) |
| `bookings.noAvailabilityNote` | {pct}% · business outcome, not an outage | {pct}% · kết quả nghiệp vụ, không phải sự cố |
| `bookings.errors` | System errors (5xx) | Lỗi hệ thống (5xx) |
| `bookings.p95` | p95 response time | Thời gian phản hồi p95 |
| `bookings.p95Note` | Target under 300 ms | Mục tiêu dưới 300 ms |
| `bookings.retries` | Allocation retries | Lần thử lại phân bổ |
| `bookings.retriesNote` | Constraint conflicts, retried automatically | Xung đột ràng buộc, đã tự thử lại |
| `bookings.replays` | Idempotent replays | Trả lại kết quả cũ |
| `bookings.replaysNote` | Returned the original result, no duplicate | Trả kết quả ban đầu, không tạo trùng |
| `errors.title` | Recent errors | Lỗi gần đây |
| `errors.search` | Search by correlationId | Tìm theo correlationId |
| `errors.columns` | Time · Endpoint · Error code · HTTP · correlationId · Details | Giờ · Endpoint · Mã lỗi · HTTP · correlationId · Chi tiết |
| `errors.empty` | No errors in this period. | Không có lỗi trong khoảng này. |
| `errors.notFound` | No error with this correlation id. | Không có lỗi nào với mã này. |
| `errors.copied` | Copied | Đã chép |
