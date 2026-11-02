# F07 · Huỷ lịch hẹn · Database spec (DB)

---

# PHẦN A · Cho người đọc

F07 không thêm bảng. Huỷ ghi trạng thái và lý do vào dòng lịch hẹn (các cột đã có từ F04), tắt phân bổ, thêm một dòng lịch sử và (nếu lịch đã xác nhận) tin nhắn huỷ vào outbox — tất cả trong một lần lưu.

---

# PHẦN B · Cho agent triển khai

## B0. Quy tắc đọc

> - **PHẢI** / **KHÔNG ĐƯỢC**: bắt buộc. **NÊN**: làm trừ khi có lý do ghi trong commit. **CÓ THỂ**: tuỳ chọn. `CLAUDE.md` áp dụng toàn bộ.
> - Yêu cầu là AC, INV, quyền và câu chữ. Code mẫu, query và thứ tự bước là **giả thuyết**: agent kiểm chúng với code và dữ liệu thật ở HEAD (`implement-ticket` bước 4).
> - Giả thuyết sai mà bản đúng vẫn giữ mọi AC, quyền và câu chữ → làm bản đúng, ghi vào commit và sửa spec. Bản đúng đổi điều người dùng thấy → dừng lại và hỏi.

## B1. Bảng

Không có bảng mới, không có migration. Cột dùng (F04 DB-04.1): `status`, `cancel_reason` (`varchar(32)`, mã D-07.1), `cancel_note` (`varchar(500)`), `cancelled_at`, `cancelled_by_user_id`, `version`, `updated_at`.

Không thêm CHECK cho `cancel_reason` (danh sách có thể đổi; kiểm ở app). CÓ THỂ thêm `CHECK (status <> 'CANCELLED' OR cancelled_at IS NOT NULL)` bằng migration mới nếu agent thấy cần — kèm kiểm trước: `SELECT count(*) FROM appointments WHERE status = 'CANCELLED' AND cancelled_at IS NULL` phải 0.

## B2. Ghi trong một transaction (INV-07.1)

| # | Câu lệnh | Bởi |
| --- | --- | --- |
| 1 | `UPDATE appointments SET status='CANCELLED', cancel_reason, cancel_note, cancelled_at, cancelled_by_user_id, version = version + 1, updated_at WHERE id AND version` | `updateVersioned` |
| 2 | `UPDATE resource_allocations SET active=false, deactivated_at=:now WHERE appointment_id = :id AND active` | `deactivateFor` |
| 3 | `INSERT appointment_events (type CANCELLED, data {reason})` | `AppointmentEventRecorder` |
| 4 | `INSERT outbox_messages` × 3 kênh (chỉ khi từ `CONFIRMED`) | `OutboxWriter` |

## B3. Test DB

TC-07-01 (khung được giải phóng), TC-07-09 (đồng thời), TC-07-10 (outbox).
