# F08 · Giám sát · Requirement spec (RQ)

Nhánh: `feature/08-monitoring` · Ưu tiên: Should · Quy ước chung: [README](../README.md)

---

# PHẦN A · Cho người đọc

## A1. Câu chuyện

**Vì sao có feature này.** Khi có sự cố, đội vận hành phải trả lời được "request này đã ra sao?" và phân biệt "hệ thống hỏng" với "xưởng hết chỗ". 409 hết chỗ là kết quả nghiệp vụ bình thường; 5xx mới là sự cố.

**Feature làm gì.**
- Log dạng JSON, mỗi dòng có mã truy vết, đại lý, người dùng, lịch hẹn, kết quả; **không bao giờ** có mật khẩu, token, số điện thoại, email.
- Mỗi request có `X-Correlation-Id` (đã có), cùng giá trị trong log và trong lỗi.
- Bộ đếm (metrics) cho đặt lịch theo kết quả, thời gian đặt lịch, số lần thử lại cặp, số lần trả lại câu trả lời cũ, số 412, số đăng nhập lỗi; xuất ở `/metrics` chỉ trong mạng nội bộ.
- Trace OpenTelemetry cho 4 bước của một lần đặt lịch.
- Màn hình "System monitoring" cho admin: sức khoẻ app, DB, outbox; số liệu đặt lịch theo khoảng 1 giờ/24 giờ/7 ngày; lỗi gần đây; tìm theo correlation id.

**Ai dùng.** Admin (màn hình); hệ thống giám sát (Prometheus, OTel collector) qua mạng nội bộ.

**Không làm.** Cảnh báo tự động (gửi email/Slack), dashboard Grafana, lưu log dài hạn.

## A2. Các bước sử dụng

1. Admin → Administration → Monitoring → chọn "24 hours".
2. Thấy "No availability (409) 131 · business outcome, not an outage" khác "System errors (5xx) 15".
3. Khách báo lỗi kèm mã → dán mã vào "Search by correlationId" → thấy dòng lỗi.

## A3. Màn hình và khác biệt so với bản vẽ

Bản vẽ: `Admin/11-system-monitoring.png`.

| Bản vẽ | Spec | Lý do |
| --- | --- | --- |
| "Figures on this screen are sample data." | Bỏ; số liệu thật | — |
| "Application · Running · 12 ms response" | "Running · p95 {n} ms (bookings)" | Số đo thật có sẵn là thời gian đặt lịch |
| "Outbox pending … View" | Nút "View" xuất hiện khi F11 có trang | Thứ tự build |
| Menu Monitoring thấy với advisor | Chỉ admin | AC-08.4 |

## A4. Luật chính bằng lời thường

- Chỉ admin xem màn hình giám sát.
- Admin thấy số liệu và lỗi của đại lý mình, cộng các lỗi hệ thống không gắn đại lý.
- `/metrics` không bao giờ mở ra Internet.
- Log không chứa dữ liệu cá nhân.

## A5. Ngoài phạm vi

Cảnh báo, lưu trữ log tập trung, tracing ngoài luồng đặt lịch.

## A6. Tiêu chí thành công

- Từ một correlation id tìm được dòng log và dòng lỗi trên màn hình trong < 1 phút.
- Quét log sau toàn bộ test: 0 email, 0 số điện thoại, 0 mật khẩu (M-08.1).

## A7. Quyết định

| ID | Quyết định |
| --- | --- |
| D-08.1 | Log: kênh mặc định `stderr`, formatter `Monolog\Formatter\JsonFormatter`; processor thêm `correlationId`, `userId`, `dealershipId`, `appointmentId`, `outcome`; processor che dữ liệu cá nhân (D-08.2). |
| D-08.2 | Che: bỏ mọi khoá ngữ cảnh khớp `password|token|secret|authorization|cookie|phone|email` (không phân biệt hoa thường, cả lồng nhau); thay chuỗi dạng email bằng `[email]` và dãy ≥ 9 chữ số (có thể có dấu cách/chấm/gạch) bằng `[phone]` trong `message` và giá trị chuỗi. |
| D-08.3 | Mỗi request ghi đúng một dòng log "request" khi kết thúc (`method`, `route`, `status`, `code`, `outcome`, `durationMs`). |
| D-08.4 | Bảng `api_events` lưu: mọi `POST /appointments` (để đếm) và mọi response ≥ 400 (để liệt kê lỗi), cộng lỗi gửi outbox `DELIVERY_FAILED` (F11 ghi). Giữ 7 ngày, `monitoring:prune` hằng ngày. |
| D-08.5 | Bộ đếm Prometheus là bộ đếm tăng dần lưu ở bảng `metric_counters` (không trong bộ nhớ PHP-FPM, `CLAUDE.md`), tăng bằng `INSERT … ON CONFLICT DO UPDATE SET value = value + :n` sau khi đã gửi response (`terminate`). |
| D-08.6 | `outcome` của đặt lịch: 201 → `confirmed`; 409 `NO_AVAILABILITY` → `no_availability`; 4xx khác → `validation_error`; 5xx → `error`. Câu trả lời lặp lại (replay) không tính vào `booking_requests_total`, chỉ vào `idempotent_replays_total`. |
| D-08.7 | `/metrics`: route không cần đăng nhập nhưng có middleware chỉ cho qua khi Nginx nội bộ đặt `fastcgi_param METRICS_INTERNAL 1`; còn lại 404. Thêm server Nginx nội bộ cổng 9100, không publish trong compose. Nginx công khai đã trả 404 cho `/metrics`. |
| D-08.8 | Tracing: thêm package `open-telemetry/sdk` và `open-telemetry/exporter-otlp` (người dùng đã duyệt mặc định; README D-G.11). Mặc định `OTEL_SDK_DISABLED=true` (không gửi đi đâu). Span: `http.request`, `booking.validate`, `allocation.find_candidates`, `booking.write`. Seam `App\Support\Tracing\Tracer` để domain không phụ thuộc SDK. |
| D-08.9 | Màn hình: chỉ admin; phạm vi dữ liệu = `dealership_id` của admin hoặc `NULL`. Tự làm mới 30 giây. |
| D-08.10 | Thẻ DB: `UP` khi `select 1` thành công; "{used}/{max} connections" từ `pg_stat_activity` và `max_connections`. |
| D-08.11 | Thẻ outbox: số `PENDING` và tuổi dòng cũ nhất (đọc `outbox_messages` có từ F04). |

## A8. Câu hỏi mở

| ID | Câu hỏi | Mặc định |
| --- | --- | --- |
| Q-08.a | Có gửi trace tới collector nào trong compose? | Không; bật bằng env khi có collector. |
| Q-08.b | Có lưu mọi request (kể cả 2xx đọc) vào `api_events`? | Không (D-08.4), để bảng nhỏ. |

---

# PHẦN B · Cho agent triển khai

## B0. Quy tắc đọc

> - **PHẢI** / **KHÔNG ĐƯỢC**: bắt buộc. **NÊN**: làm trừ khi có lý do ghi trong commit. **CÓ THỂ**: tuỳ chọn. `CLAUDE.md` áp dụng toàn bộ.
> - Yêu cầu là AC, INV, quyền và câu chữ. Code mẫu, query và thứ tự bước là **giả thuyết**: agent kiểm chúng với code và dữ liệu thật ở HEAD (`implement-ticket` bước 4).
> - Giả thuyết sai mà bản đúng vẫn giữ mọi AC, quyền và câu chữ → làm bản đúng, ghi vào commit và sửa spec. Bản đúng đổi điều người dùng thấy → dừng lại và hỏi.

## B1. Trạng thái code hiện tại

Có sẵn từ đầu: `CorrelationId` middleware (`api/app/Http/Middleware/CorrelationId.php`, `Log::shareContext`), `HealthController` (`/health/live`, `/health/ready` → 503 khi DB lỗi), `ProblemRenderer` (thêm `correlationId` vào body), Nginx chặn `/metrics` công khai (`docker/nginx/snippets/app.conf`), `config/logging.php` mặc định `stack` → `single` (file, không JSON). Từ F04: event `AllocationRetried`, `IdempotentReplayed`, `AppointmentModifiedDetected`; bảng `outbox_messages`.

## B2. Phạm vi

| ID | Trong phạm vi |
| --- | --- |
| S-08.1 | Log JSON + processor ngữ cảnh + processor che dữ liệu cá nhân. |
| S-08.2 | Middleware `RecordRequestOutcome` (terminable): dòng log request, `api_events`, bộ đếm. |
| S-08.3 | Bảng `api_events`, `metric_counters`; lệnh `monitoring:prune`. |
| S-08.4 | `/metrics` + guard + server Nginx nội bộ. |
| S-08.5 | Tracing (package + seam `Tracer` + 4 span). |
| S-08.6 | `GET /monitoring/summary`, `GET /monitoring/errors`. |
| S-08.7 | Màn hình System monitoring. |

## B3. Bất biến

| ID | Bất biến | Kiểm ở đâu |
| --- | --- | --- |
| INV-08.1 | Không dòng log nào chứa mật khẩu, token, email, số điện thoại (FR-08.1, NFR-05). | Processor D-08.2 chạy cho mọi kênh; TC quét log. |
| INV-08.2 | Mọi response có `X-Correlation-Id`; mọi dòng log của request đó có cùng `correlationId` (FR-08.2). | `CorrelationId` có sẵn (prepend) + `Log::shareContext`. |
| INV-08.3 | Bộ đếm không giảm (trừ khi xoá DB); không lưu trong bộ nhớ worker. | `metric_counters`, chỉ `UPDATE value = value + n`. |
| INV-08.4 | Ghi số liệu không làm chậm hay hỏng request: chạy trong `terminate()`, lỗi khi ghi chỉ được log (không ném). | `RecordRequestOutcome::terminate`. |
| INV-08.5 | `/metrics` trả 404 với mọi request không qua Nginx nội bộ (FR-08.5). | Middleware `EnsureInternalMetrics` + Nginx. |
| INV-08.6 | Admin chỉ thấy `api_events` có `dealership_id` của mình hoặc `NULL`; vai trò khác 403 (AC-08.4). | Gate `admin`; `WHERE` trong repository. |
| INV-08.7 | `api_events.detail` không chứa dữ liệu cá nhân (cùng processor che D-08.2 áp cho detail). | Khi ghi. |
| INV-08.8 | `/health/ready` trả 503 khi DB không trả lời (FR-08.5, AC-08.3). | Có sẵn; F08 thêm test DB-down. |
| INV-08.9 | `App\Domain\Allocation` không phụ thuộc tracing (INV-03.1); span bọc lời gọi từ `Booking`. | TC-03-22 vẫn xanh. |

## B4. Thay đổi theo file

| File | Thay đổi |
| --- | --- |
| `api/config/logging.php`, `api/.env.example` | `LOG_CHANNEL=stderr`, `LOG_STDERR_FORMATTER=Monolog\Formatter\JsonFormatter`; tap thêm processor. |
| `api/app/Support/Logging/{ContextProcessor,RedactPersonalData}.php` | D-08.1, D-08.2. |
| `api/app/Http/Middleware/RecordRequestOutcome.php` | D-08.3–08.6; đăng ký global sau `CorrelationId`. |
| `api/app/Support/Monitoring/{RequestMetrics,MetricCounterRepository,ApiEventRepository}.php` | Thu sự kiện F04 trong request (binding `scoped`), ghi khi terminate. |
| `api/app/Listeners/CollectBookingSignals.php` | Lắng nghe event F04. |
| `api/database/migrations/2026_11_08_*` | `api_events`, `metric_counters`. |
| `api/routes/health.php`, `api/app/Http/Controllers/MetricsController.php`, `api/app/Http/Middleware/EnsureInternalMetrics.php` | D-08.7. |
| `docker/nginx/default.conf`, `docker/nginx/production.conf` | Server nội bộ `listen 9100;` chỉ `location = /metrics`. |
| `api/composer.json` | D-08.8 (2 package). |
| `api/app/Support/Tracing/{Tracer,OtelTracer,NullTracer}.php`, `api/app/Providers/TracingServiceProvider.php` | D-08.8. |
| `api/app/Domain/Booking/Actions/CreateBooking.php` | Bọc 3 bước bằng `Tracer::span`. |
| `api/app/Http/Controllers/Api/V1/MonitoringController.php`, `Resources/{MonitoringSummary,ApiEvent}Resource.php` | API. |
| `api/routes/console.php` | `monitoring:prune` daily. |
| `web/src/staff/monitoring/*` | WEB. |

## B5. Hợp đồng

[4-api-spec.md](4-api-spec.md), [3-database-spec.md](3-database-spec.md), [5-web-spec.md](5-web-spec.md).

```php
// minh hoạ — INV-08.9
interface Tracer { public function span(string $name, callable $fn, array $attributes = []): mixed; }
```

## B6. Seam

| Seam | Dùng lại ở |
| --- | --- |
| `RedactPersonalData` | F11 (`last_error` của outbox) |
| `ApiEventRepository::record()` | F11 ghi `DELIVERY_FAILED` |
| `Tracer` | F09, F12 (CÓ THỂ bọc thêm span) |
| `CursorPaginator` (F05) | `GET /monitoring/errors` |

## B7. Thuật toán (`RecordRequestOutcome`)

1. `handle`: ghi `startedAt` (hrtime); `$next`.
2. `terminate(request, response)`:
   1. `durationMs`, `route` (mẫu route, ví dụ `POST api/v1/appointments`), `status`, `code` (đọc từ body problem nếu `Content-Type` là problem+json).
   2. Ghi một dòng log `request` (D-08.3).
   3. Nếu là `POST /appointments`: replay → `idempotent_replays_total += 1`; không replay → `booking_requests_total{outcome}` += 1, histogram `booking_duration_seconds`.
   4. `allocation_retries_total += số AllocationRetried trong request`.
   5. `code = APPOINTMENT_MODIFIED` → `appointment_modified_total += 1`.
   6. Route sign-in và `code ∈ {INVALID_CREDENTIALS, TOO_MANY_ATTEMPTS}` → `sign_in_failures_total += 1`.
   7. Nếu là `POST /appointments` hoặc `status ≥ 400` → chèn `api_events`.
   8. Mọi lỗi ở bước 2–7 → `Log::warning` (không ném).

## B8. Bảng lỗi

| Tình huống | HTTP | `code` |
| --- | --- | --- |
| `range` không thuộc `1h|24h|7d`; `correlationId` sai dạng | 400 | `VALIDATION_ERROR` |
| Không phiên | 401 | `UNAUTHENTICATED` |
| Không phải admin | 403 | `FORBIDDEN` |
| `/metrics` từ ngoài | 404 | `NOT_FOUND` |
| DB lỗi (`/health/ready`) | 503 | (JSON health, không phải problem) |

## B9. Acceptance criteria

| AC | Given | When | Then | TC |
| --- | --- | --- | --- | --- |
| AC-08.1 | Client không gửi `X-Correlation-Id` | Gọi API bất kỳ | Response có `X-Correlation-Id`; log có cùng giá trị | TC-08-01 |
| AC-08.2 | Không còn chỗ | Đặt lịch trả 409 | `booking_requests_total{outcome="no_availability"}` tăng 1 | TC-08-02 |
| AC-08.3 | DB không chạy | `/health/ready` | 503 | TC-08-03 |
| AC-08.4 | Advisor đăng nhập | Mở System monitoring | 403 `FORBIDDEN` (chỉ admin) | TC-08-04 |
| AC-08.5 (mới) | — | Chạy luồng đăng ký, đăng nhập sai, đặt lịch, huỷ | Mọi dòng log là JSON hợp lệ, có `correlationId`; không có email, số điện thoại, mật khẩu | TC-08-05 |
| AC-08.6 (mới) | — | 201, 422, 500, replay, 412, sign-in sai, một lần thử lại cặp | Mỗi bộ đếm tương ứng tăng đúng 1 | TC-08-06 |
| AC-08.7 (mới) | — | `GET /metrics` qua Nginx công khai; trực tiếp vào app không có cờ nội bộ; qua Nginx nội bộ | 404; 404; 200 định dạng Prometheus | TC-08-07, TC-08-08 |
| AC-08.8 (mới) | Admin D1; lỗi của D1, D2 và lỗi không đại lý | `GET /monitoring/summary?range=24h` | Đếm và lỗi gần đây chỉ của D1 và không đại lý | TC-08-09 |
| AC-08.9 (mới) | Một lỗi có correlation id X | `GET /monitoring/errors?correlationId=X` | Đúng dòng đó | TC-08-10 |
| AC-08.10 (mới) | Exporter trong bộ nhớ | Đặt lịch | Có 4 span đúng tên, cùng trace, span con nằm trong `http.request` | TC-08-11 |
| AC-08.11 (mới) | Dòng `api_events` 8 ngày tuổi | `monitoring:prune` | Bị xoá; dòng 6 ngày còn | TC-08-12 |
| AC-08.12 (mới) | Admin mở System monitoring | Màn hình | Thẻ sức khoẻ, thẻ số liệu đặt lịch, bảng lỗi; chọn 1 hour/24 hours/7 days đổi số liệu | TC-08-13 |

## B10. Thứ tự triển khai

1. Ticket 01 (prefactor): processor che dữ liệu + log JSON; không đổi API.
2. Tracer bullet: `RecordRequestOutcome` + `metric_counters` + `/metrics` + AC-08.2, rồi `/code-review`.
3. `api_events` + summary/errors API + màn hình.
4. Tracing.
5. Nghiệm thu.

## B11. Definition of Done

Mọi TC xanh; README §8 xanh; `docker compose up` không lộ cổng 9100 ra máy host.

## B12. Rủi ro kỹ thuật

| ID | Rủi ro | Giảm thiểu |
| --- | --- | --- |
| R-08.1 | `terminate()` không chạy khi PHP-FPM bị kill | Mất một điểm số liệu; chấp nhận |
| R-08.2 | Ghi `metric_counters` mỗi request tạo tranh chấp dòng hot | Upsert ngắn, ngoài transaction nghiệp vụ; đo; nếu cần, gộp theo phút |
| R-08.3 | Package OTel không tương thích PHP 8.4/Laravel 13 | Seam `Tracer` + `NullTracer`; nếu package lỗi, giữ `NullTracer` và ghi lại |
| R-08.4 | Regex che số điện thoại xoá nhầm id số dài | Chỉ áp cho chuỗi; UUID có chữ nên không khớp; test TC-08-14 |
