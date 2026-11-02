# F06 · Gợi ý giờ trống khác · Database spec (DB)

---

# PHẦN A · Cho người đọc

F06 chỉ đọc, không có bảng hay migration mới. Mỗi ngày cần tính chỉ đọc một bộ truy vấn cố định (của F03), dù ngày đó có bao nhiêu khung giờ.

---

# PHẦN B · Cho agent triển khai

## B0. Quy tắc đọc

> - **PHẢI** / **KHÔNG ĐƯỢC**: bắt buộc. **NÊN**: làm trừ khi có lý do ghi trong commit. **CÓ THỂ**: tuỳ chọn. `CLAUDE.md` áp dụng toàn bộ.
> - Yêu cầu là AC, INV, quyền và câu chữ. Code mẫu, query và thứ tự bước là **giả thuyết**: agent kiểm chúng với code và dữ liệu thật ở HEAD (`implement-ticket` bước 4).
> - Giả thuyết sai mà bản đúng vẫn giữ mọi AC, quyền và câu chữ → làm bản đúng, ghi vào commit và sửa spec. Bản đúng đổi điều người dùng thấy → dừng lại và hỏi.

## B1. Bảng

Không có bảng mới, không có migration.

## B2. Truy vấn

| Thao tác | Truy vấn | Số lần |
| --- | --- | --- |
| Lưới một ngày | `AllocationSnapshotRepository::forDay` (F03 DB §B2) | 1 snapshot |
| Gợi ý khi 409 | `forDay` cho ngày muốn, rồi tối đa 7 ngày sau | ≤ 8 snapshot, dừng sớm |
| `next` | Như trên từ `after` | ≤ 8 snapshot |
| `excludeAppointmentId` | `findVisibleTo` (F05) | 1 |

Index dùng: `resource_allocations_dealership_period_idx` (F04) cho khoảng bận trong ngày.

Truy vấn bận nên có thể gộp nhiều ngày thành một (cửa sổ 8 ngày) cho gợi ý; giả thuyết, agent đo trước khi làm (TC-06-13).

## B3. Test DB

TC-06-10 (số truy vấn cố định), TC-06-13 (hiệu năng).
