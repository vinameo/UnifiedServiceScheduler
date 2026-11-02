# F08 · Giám sát · Business analysis (BA)

---

# PHẦN A · Cho người đọc

## A1. Vì sao

Không có số liệu thì không biết khi nào cần SDD phase 2 (tỉ lệ thử lại > 5%, p95 > 300 ms), và mỗi lần khách phàn nàn là một cuộc điều tra mò mẫm. Bản vẽ nhấn mạnh tách "409 · business outcome, not an outage" khỏi "System errors (5xx)".

## A2. Bằng chứng

| ID | Nguồn | Nội dung |
| --- | --- | --- |
| E-08.1 | Requirement F08 | FR-08.1–08.5, 4 AC; 6 bộ đếm có tên. |
| E-08.2 | SDD §12, §14 | Đếm 409, 412, thử lại 23P01; p95 `POST /appointments`; ngưỡng phase 2. |
| E-08.3 | Bản vẽ System monitoring | 3 thẻ sức khoẻ, 7 thẻ số liệu, bảng lỗi gần đây, tìm theo correlationId, khoảng 1h/24h/7d. |
| E-08.4 | `CLAUDE.md` | Log JSON có 4 field; không lưu trạng thái trong bộ nhớ PHP-FPM; `/metrics` không ra ngoài. |
| E-08.5 | Code có sẵn | `CorrelationId`, `HealthController`, Nginx chặn `/metrics`. |

## A3. Ưu tiên

Should. Trong F08: log + bộ đếm + màn hình là cốt lõi; tracing là Could trong Should (package mới, mặc định tắt).

## A4. Rủi ro nghiệp vụ

| ID | Rủi ro | Xử lý |
| --- | --- | --- |
| RB-08.1 | Admin hiểu nhầm 409 nhiều là sự cố | Nhãn "business outcome, not an outage" |
| RB-08.2 | Lỗi gần đây lộ dữ liệu khách | `detail` đã che (INV-08.7); chỉ admin |

---

# PHẦN B · Cho agent triển khai

## B0. Quy tắc đọc

> - **PHẢI** / **KHÔNG ĐƯỢC**: bắt buộc. **NÊN**: làm trừ khi có lý do ghi trong commit. **CÓ THỂ**: tuỳ chọn. `CLAUDE.md` áp dụng toàn bộ.
> - Yêu cầu là AC, INV, quyền và câu chữ. Code mẫu, query và thứ tự bước là **giả thuyết**: agent kiểm chúng với code và dữ liệu thật ở HEAD (`implement-ticket` bước 4).
> - Giả thuyết sai mà bản đúng vẫn giữ mọi AC, quyền và câu chữ → làm bản đúng, ghi vào commit và sửa spec. Bản đúng đổi điều người dùng thấy → dừng lại và hỏi.

## B1. ADR

### ADR-08.1 · Bộ đếm trong PostgreSQL

- **Lựa chọn:** (a) APCu/bộ nhớ worker; (b) Redis; (c) bảng `metric_counters`; (d) Prometheus pushgateway.
- **Chọn (c).** (a) mỗi worker một số, mất khi restart, `CLAUDE.md` cấm; (b) không có trong compose; (d) thêm dịch vụ. (c) đơn giản, bền, đủ nhanh với lưu lượng hiện tại (R-08.2).

### ADR-08.2 · `api_events` cho màn hình, log cho điều tra sâu

- Màn hình cần truy vấn (đếm theo khoảng, tìm theo id) → bảng. Log JSON ra stderr cho công cụ log của nền tảng. Hai nguồn cùng `correlationId`.

### ADR-08.3 · `/metrics` qua Nginx nội bộ

- Prometheus không nói FastCGI. Một server Nginx nội bộ (không publish) + cờ `fastcgi_param` cho app biết request đến từ đâu; phòng thủ hai lớp cùng với 404 công khai.

### ADR-08.4 · OpenTelemetry qua seam

- FR-08.4 yêu cầu OTel. Package mới (D-08.8). Seam `Tracer` + `NullTracer` để tắt hoàn toàn và để domain không phụ thuộc SDK.

## B2. Chỉ số

| ID | Chỉ số | SQL / nguồn | Ngưỡng |
| --- | --- | --- | --- |
| M-08.1 | Dòng log có dữ liệu cá nhân | `grep -E '@[A-Za-z0-9.-]+\.[a-z]{2,}|\b0[0-9]{9}\b|"password"'` trên log CI | 0 |
| M-08.2 | Tỉ lệ thử lại (SDD phase 2) | `allocation_retries_total / booking_requests_total` | > 5%/giờ → xem phase 2 |
| M-08.3 | p95 đặt lịch | `SELECT percentile_cont(0.95) WITHIN GROUP (ORDER BY duration_ms) FROM api_events WHERE route = 'POST api/v1/appointments' AND occurred_at > now() - interval '1 hour';` | < 300 ms |
| M-08.4 | 5xx/giờ | `SELECT count(*) FROM api_events WHERE status >= 500 AND occurred_at > now() - interval '1 hour';` | Theo dõi |

## B3. Ràng buộc cho tương lai

| ID | Ràng buộc | Seam |
| --- | --- | --- |
| FC-08.1 | Cảnh báo | Đọc `metric_counters`/`api_events`; không có ở bản này |
| FC-08.2 | Nhiều đại lý, admin toàn hệ thống | Phạm vi INV-08.6 ở một repository |
| FC-08.3 | Lưu lượng cao | Gộp bộ đếm theo phút trước khi upsert |
