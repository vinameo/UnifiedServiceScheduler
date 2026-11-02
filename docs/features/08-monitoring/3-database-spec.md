# F08 · Giám sát · Database spec (DB)

---

# PHẦN A · Cho người đọc

Hai bảng mới: `api_events` (các lần đặt lịch và các lỗi, giữ 7 ngày, cho màn hình giám sát) và `metric_counters` (bộ đếm tăng dần cho `/metrics`).

---

# PHẦN B · Cho agent triển khai

## B0. Quy tắc đọc

> - **PHẢI** / **KHÔNG ĐƯỢC**: bắt buộc. **NÊN**: làm trừ khi có lý do ghi trong commit. **CÓ THỂ**: tuỳ chọn. `CLAUDE.md` áp dụng toàn bộ.
> - Yêu cầu là AC, INV, quyền và câu chữ. Code mẫu, query và thứ tự bước là **giả thuyết**: agent kiểm chúng với code và dữ liệu thật ở HEAD (`implement-ticket` bước 4).
> - Giả thuyết sai mà bản đúng vẫn giữ mọi AC, quyền và câu chữ → làm bản đúng, ghi vào commit và sửa spec. Bản đúng đổi điều người dùng thấy → dừng lại và hỏi.

## B1. Bảng

### DB-08.1 · `api_events`

| Cột | Kiểu | Ghi chú |
| --- | --- | --- |
| `id` | `bigserial` | PK |
| `occurred_at` | `timestamptz` | NOT NULL |
| `correlation_id` | `varchar(128)` | NOT NULL; index |
| `dealership_id` | `uuid` | NULL |
| `user_id` | `bigint` | NULL (không FK: giữ lỗi khi user bị xoá) |
| `appointment_id` | `uuid` | NULL |
| `method` | `varchar(8)` | NOT NULL (`-` cho job) |
| `route` | `varchar(160)` | NOT NULL (mẫu route hoặc `outbox-relay`) |
| `status` | `smallint` | NULL (job không có) |
| `code` | `varchar(48)` | NULL |
| `outcome` | `varchar(24)` | NULL (`confirmed`, `no_availability`, `validation_error`, `error`) |
| `duration_ms` | `integer` | NULL |
| `idempotent_replay` | `boolean` | NOT NULL DEFAULT false |
| `allocation_retries` | `smallint` | NOT NULL DEFAULT 0 |
| `detail` | `jsonb` | NOT NULL DEFAULT `'{}'` (đã che dữ liệu cá nhân) |

Index: `(occurred_at)`; `(dealership_id, occurred_at)`; `(route, occurred_at) WHERE route = 'POST api/v1/appointments'`; `(correlation_id)`.

### DB-08.2 · `metric_counters`

| Cột | Kiểu | Ghi chú |
| --- | --- | --- |
| `name` | `varchar(80)` | NOT NULL |
| `labels` | `varchar(200)` | NOT NULL DEFAULT `''`; chuỗi chuẩn hoá `k1="v1",k2="v2"` sắp theo khoá |
| `value` | `bigint` | NOT NULL DEFAULT 0, `CHECK (value >= 0)` |
| `updated_at` | `timestamptz` | NOT NULL |
| PK | | `(name, labels)` |

Tên: `booking_requests_total` (label `outcome`), `booking_duration_seconds_bucket` (label `le` ∈ 0.05, 0.1, 0.2, 0.3, 0.5, 1, 2, 5, +Inf), `booking_duration_seconds_sum_ms`, `booking_duration_seconds_count`, `allocation_retries_total`, `idempotent_replays_total`, `appointment_modified_total`, `sign_in_failures_total`. `/metrics` đổi `…_sum_ms` sang giây khi xuất.

## B2. Migration

| Migration | `down()` |
| --- | --- |
| `create_api_events_table` | `dropIfExists` |
| `create_metric_counters_table` | `dropIfExists` |

Bảng mới, không cần kiểm dữ liệu cũ. Không soft delete.

## B3. Truy vấn chính

| Truy vấn | Index |
| --- | --- |
| Đếm theo `outcome` trong khoảng cho một đại lý | `(dealership_id, occurred_at)` |
| p95 đặt lịch | `(route, occurred_at)` partial |
| 20 lỗi gần đây (`status >= 400`) | `(dealership_id, occurred_at)` |
| Tìm theo correlation id | `(correlation_id)` |
| Prune `occurred_at < now - 7 ngày` | `(occurred_at)` |
| Tăng bộ đếm: `INSERT … ON CONFLICT (name, labels) DO UPDATE SET value = metric_counters.value + EXCLUDED.value, updated_at = EXCLUDED.updated_at` | PK |
| DB connections: `SELECT count(*) FROM pg_stat_activity WHERE datname = current_database()`; `SHOW max_connections` | — |
| Outbox: `SELECT count(*), min(created_at) FROM outbox_messages WHERE status = 'PENDING'` | `(next_attempt_at) WHERE status='PENDING'` (F04); CÓ THỂ thêm index nếu chậm |

## B4. Test DB

TC-08-06 (bộ đếm), TC-08-12 (prune), TC-08-09 (phạm vi).
