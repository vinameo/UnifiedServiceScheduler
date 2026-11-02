# F08 · Giám sát · Test cases (TC)

---

# PHẦN A · Cho QA và product owner

## A1. Chiến lược

- **Log và che dữ liệu cá nhân**: chạy luồng thật (đăng ký, đăng nhập sai, đặt lịch, huỷ) với log ghi vào bộ nhớ, rồi quét từng dòng.
- **Bộ đếm**: mỗi loại kết quả một test; đọc `metric_counters` trước/sau.
- **`/metrics` không lộ ra ngoài**: một test app-level (thiếu cờ nội bộ) và một kiểm tay qua Nginx.
- **Màn hình**: test component; một lượt tay.

## A2. Kịch bản chạy tay

| # | Bước | Kỳ vọng |
| --- | --- | --- |
| M1 | `curl -i http://localhost:8088/metrics` | 404 |
| M2 | `docker compose exec nginx wget -qO- http://localhost:9100/metrics` | Văn bản Prometheus có `booking_requests_total` |
| M3 | `docker compose port nginx 9100` | Không có cổng publish |
| M4 | Đặt một lịch kín (409), đăng nhập admin → Monitoring | "No availability (409)" ≥ 1; dòng NO_AVAILABILITY trong Recent errors |
| M5 | Chép correlationId từ tab Network của M4, dán vào ô tìm | Đúng dòng đó |
| M6 | `docker compose stop postgres`; `curl -i localhost:8088/health/ready` | 503 |
| M7 | `docker compose logs php | tail` | Dòng JSON, có `correlationId`; không có email/số điện thoại |

## A3. Dữ liệu test

`World`; log channel test ghi vào handler bộ nhớ (`TestHandler` của Monolog).

## A4. Không test

Gửi trace tới collector thật (tắt mặc định). Dashboard ngoài.

## A5. Điều kiện xong

Mọi TC xanh; M1–M7 đạt.

---

# PHẦN B · Cho agent triển khai

## B0. Quy tắc đọc

> - **PHẢI** / **KHÔNG ĐƯỢC**: bắt buộc. **NÊN**: làm trừ khi có lý do ghi trong commit. **CÓ THỂ**: tuỳ chọn. `CLAUDE.md` áp dụng toàn bộ.
> - Yêu cầu là AC, INV, quyền và câu chữ. Code mẫu, query và thứ tự bước là **giả thuyết**: agent kiểm chúng với code và dữ liệu thật ở HEAD (`implement-ticket` bước 4).
> - Giả thuyết sai mà bản đúng vẫn giữ mọi AC, quyền và câu chữ → làm bản đúng, ghi vào commit và sửa spec. Bản đúng đổi điều người dùng thấy → dừng lại và hỏi.

## B1. Ma trận phủ

| Yêu cầu | TC |
| --- | --- |
| AC-08.1, FR-08.2, INV-08.2 | TC-08-01 |
| AC-08.2, FR-08.3, D-08.6 | TC-08-02 |
| AC-08.3, FR-08.5, INV-08.8 | TC-08-03 |
| AC-08.4, INV-08.6 | TC-08-04 |
| AC-08.5, FR-08.1, INV-08.1, D-08.1, D-08.2 | TC-08-05, TC-08-14 |
| AC-08.6, D-08.5 | TC-08-06 |
| AC-08.7, INV-08.5, D-08.7 | TC-08-07, TC-08-08 |
| AC-08.8, D-08.9 | TC-08-09 |
| AC-08.9 | TC-08-10 |
| AC-08.10, FR-08.4, D-08.8 | TC-08-11 |
| AC-08.11, D-08.4 | TC-08-12 |
| AC-08.12 | TC-08-13 |
| INV-08.3 | TC-08-06 |
| INV-08.4 | TC-08-15 |
| INV-08.7 | TC-08-16 |
| INV-08.9 | TC-03-22 (F03, chạy lại) |
| D-08.3 | TC-08-01 |
| D-08.10, D-08.11 | TC-08-09 |
| API §B4 | TC-08-17 |

## B2. Test case

Backend ở `api/tests/Feature/Monitoring/`.

| ID | Proves | Level | Given | When | Then | Fails if | Where |
| --- | --- | --- | --- | --- | --- | --- | --- |
| TC-08-01 | AC-08.1, D-08.3 | Integration | Log vào `TestHandler` | `GET /api/v1/me` (401) không gửi header | Header `X-Correlation-Id` = X; có đúng 1 dòng log `request` với `correlationId` X, `status 401`, `durationMs` | Thiếu dòng request hoặc khác id | `RequestLogTest::test_AC_08_1_response_and_log_share_correlation_id` |
| TC-08-02 | AC-08.2 | Integration | Không còn chỗ | `POST /appointments` → 409 | `booking_requests_total{outcome="no_availability"}` +1; các outcome khác không đổi | Đếm sai outcome | `MetricsTest::test_AC_08_2_no_availability_is_counted` |
| TC-08-03 | AC-08.3 | Integration | Kết nối DB trỏ tới host không tồn tại (config runtime) | `GET /health/ready` | 503, `checks.database = fail` | Trả 200 | `Feature/Platform/HealthTest::test_AC_08_3_ready_is_503_when_database_is_down` |
| TC-08-04 | AC-08.4 | Integration | Advisor, foreman, khách | `GET /monitoring/summary?range=1h` | 403 cả ba; admin 200 | Gate sai | `MonitoringApiTest::test_AC_08_4_admin_only` |
| TC-08-05 | AC-08.5, INV-08.1 | Integration | `TestHandler` | Sign-up (email, phone), sign-in sai, đặt lịch, huỷ (F07), một 500 cố ý (route test) | Mọi bản ghi `json_encode` được; mỗi bản có `correlationId`; không bản nào khớp regex email, `\b0\d{9}\b`, `"password"`, `XSRF`, `laravel_session` | Thiếu processor che | `LogRedactionTest::test_AC_08_5_logs_have_no_personal_data` |
| TC-08-06 | AC-08.6, INV-08.3 | Integration | Đọc bộ đếm trước | 201; 422; 500 (route test); replay cùng khoá; 412 (`touch` F04); sign-in sai; một `AllocationRetried` | `confirmed`, `validation_error`, `error`, `idempotent_replays_total`, `appointment_modified_total`, `sign_in_failures_total`, `allocation_retries_total` mỗi cái +1; replay **không** tăng `booking_requests_total` | Replay đếm hai lần | `MetricsTest::test_AC_08_6_each_counter_moves_once` |
| TC-08-07 | AC-08.7, INV-08.5 | Integration | — | `GET /metrics` không cờ; có `X-Forwarded-For`; với server var `METRICS_INTERNAL=1` | 404; 404; 200 `text/plain` có `# TYPE booking_requests_total counter` | Tin header | `MetricsEndpointTest::test_AC_08_7_metrics_only_from_internal_nginx` |
| TC-08-08 | AC-08.7, D-08.7 | Manual | Stack chạy | M1, M2, M3 | Như A2 | Cổng 9100 publish | Ghi vào A2 |
| TC-08-09 | AC-08.8, D-08.9–08.11 | Integration | Admin D1; `api_events` của D1 (2), D2 (3), NULL (1); 2 outbox PENDING | `summary?range=24h` | `recentErrors` 3 dòng (D1 + NULL); `outbox.pending 2`; `database.status UP`, `connectionsMax > 0` | Lộ D2 | `MonitoringApiTest::test_AC_08_8_summary_is_scoped` |
| TC-08-10 | AC-08.9 | Integration | Lỗi với correlation X | `errors?correlationId=X` | 1 dòng | Tìm sai cột | `…::test_AC_08_9_search_by_correlation_id` |
| TC-08-11 | AC-08.10 | Integration | `InMemoryExporter`, `OTEL_SDK_DISABLED=false` | Đặt lịch | Span `http.request`, `booking.validate`, `allocation.find_candidates`, `booking.write`; cùng `traceId`; 3 span sau là con của `http.request` | Thiếu span | `TracingTest::test_AC_08_10_booking_spans` |
| TC-08-12 | AC-08.11 | Integration | `api_events` 8 ngày và 6 ngày tuổi | `monitoring:prune` | Dòng 8 ngày bị xoá; 6 ngày còn | Prune sai | `PruneTest::test_AC_08_11_events_older_than_7_days_are_pruned` |
| TC-08-13 | AC-08.12 | Web component | API giả summary 24h | Render, bấm "7 days" | Thẻ "No availability (409)" "131" "10.5% · business outcome, not an outage"; URL `?range=7d`, gọi lại API với `7d` | Range không vào request | `web/src/staff/monitoring/MonitoringPage.test.tsx` |
| TC-08-14 | D-08.2, R-08.4 | Unit | — | `RedactPersonalData` trên: `{"user":{"email":"a@b.c"}}`, message "call 0903 123 218", UUID, `{"Authorization":"x"}` | email bỏ; "call [phone]"; UUID giữ nguyên; khoá Authorization bỏ | Che quá tay hoặc thiếu | `api/tests/Unit/Support/RedactPersonalDataTest` |
| TC-08-15 | INV-08.4 | Integration | `MetricCounterRepository` giả ném lỗi | `POST /appointments` | 201 bình thường; 1 dòng log warning | Lỗi giám sát làm hỏng request | `MetricsTest::test_INV_08_4_metrics_failure_does_not_break_request` |
| TC-08-16 | INV-08.7 | Integration | Route test ném exception có email trong message | Gọi | `api_events.detail` không chứa email | Lưu message thô | `ApiEventsTest::test_INV_08_7_detail_is_redacted` |
| TC-08-17 | API §B4 | Integration | — | Mỗi dòng bảng đầu vào bất thường | Như bảng | Thiếu luật | `MonitoringAdversarialInputTest` |
| TC-08-18 | Toàn bộ | Manual | Stack chạy | M1–M7 | Như A2 | — | Ghi vào A2 |

## B3. Fixture

- `Tests\Support\Logging\CapturesLogs`: thay kênh mặc định bằng `TestHandler` qua processor thật (không bỏ qua processor).
- Route test `/api/v1/_testing/fail` (provider F04) ném `RuntimeException` có message chứa email.

## B4. Hồi quy

`Feature/Platform/*` (CorrelationId, Health, ProblemDetails), toàn bộ F02, F04 (middleware mới chạy trên mọi request).
