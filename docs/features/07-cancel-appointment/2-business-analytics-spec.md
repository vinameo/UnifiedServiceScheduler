# F07 · Huỷ lịch hẹn · Business analysis (BA)

---

# PHẦN A · Cho người đọc

## A1. Vì sao

Một khung giờ bị giữ bởi khách không đến là doanh thu mất. Huỷ phải trả chỗ ngay. Giới hạn 2 giờ cho khách giữ cho xưởng thời gian sắp xếp; cố vấn không bị giới hạn vì họ đang nói chuyện trực tiếp với khách.

## A2. Bằng chứng

| ID | Nguồn | Nội dung |
| --- | --- | --- |
| E-07.1 | Requirement F07 | FR-07.1–07.4, BR-07.1–07.2, 4 AC. |
| E-07.2 | SDD §6 | "Customer and advisor: customer moves while advisor cancels → cancelled booking comes back" — lý do cần `If-Match`. |
| E-07.3 | SDD §10 | Huỷ: kiểm phiên bản, không EXCLUDE, lỗi 412. |
| E-07.4 | Bản vẽ | Hộp thoại nhân viên (Reason + Note), bảng khách (3 lý do), banner sau huỷ, số đại lý "028 3812 3456". |

## A3. Ưu tiên

Should. Trong F07: endpoint + luật là cốt lõi; giao diện khách Should.

## A4. Rủi ro nghiệp vụ

| ID | Rủi ro | Xử lý |
| --- | --- | --- |
| RB-07.1 | Khách huỷ sát giờ hàng loạt | Luật 2 giờ; M-07.2 |
| RB-07.2 | Cố vấn huỷ nhầm | Hộp thoại xác nhận nói rõ "This cannot be undone."; lịch sử ghi người huỷ |

---

# PHẦN B · Cho agent triển khai

## B0. Quy tắc đọc

> - **PHẢI** / **KHÔNG ĐƯỢC**: bắt buộc. **NÊN**: làm trừ khi có lý do ghi trong commit. **CÓ THỂ**: tuỳ chọn. `CLAUDE.md` áp dụng toàn bộ.
> - Yêu cầu là AC, INV, quyền và câu chữ. Code mẫu, query và thứ tự bước là **giả thuyết**: agent kiểm chúng với code và dữ liệu thật ở HEAD (`implement-ticket` bước 4).
> - Giả thuyết sai mà bản đúng vẫn giữ mọi AC, quyền và câu chữ → làm bản đúng, ghi vào commit và sửa spec. Bản đúng đổi điều người dùng thấy → dừng lại và hỏi.

## B1. ADR

### ADR-07.1 · Huỷ trùng là thành công

- **Lựa chọn:** (a) 412/422 khi đã huỷ; (b) 200 với trạng thái hiện tại.
- **Chọn (b)** theo FR-07.2 và mở rộng cho va chạm đồng thời (D-07.5): mục tiêu của người dùng đã đạt, báo lỗi chỉ gây hoang mang.
- **Ràng buộc:** chỉ khi trạng thái đọc lại là `CANCELLED`; không ghi thêm gì.

### ADR-07.2 · Lý do là mã cố định

- Mã cho phép thống kê (M-07.1) và dịch câu chữ; ghi chú tự do riêng.

## B2. Chỉ số

| ID | Chỉ số | SQL | Ngưỡng |
| --- | --- | --- | --- |
| M-07.1 | Huỷ theo lý do và kênh (30 ngày) | `SELECT cancel_reason, source, count(*) FROM appointments WHERE status = 'CANCELLED' AND cancelled_at > now() - interval '30 days' GROUP BY 1, 2;` | Theo dõi |
| M-07.2 | Khách bị chặn vì sát giờ | `SELECT count(*) FROM api_events WHERE code = 'TOO_LATE_TO_CANCEL' AND occurred_at > now() - interval '7 days';` | Theo dõi |
| M-07.3 | Khung đã huỷ được đặt lại | Lịch mới có cùng kỹ thuật viên + khoảng giao với lịch đã huỷ, tạo sau `cancelled_at` | Theo dõi |

## B3. Ràng buộc cho tương lai

| ID | Ràng buộc | Seam |
| --- | --- | --- |
| FC-07.1 | Phí huỷ | `CancelAppointment` trả thêm thông tin phí; không cột nào hôm nay |
| FC-07.2 | Giới hạn giờ theo đại lý | `CustomerChangePolicy` nhận số giờ từ đại lý |
