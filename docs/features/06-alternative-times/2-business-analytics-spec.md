# F06 · Gợi ý giờ trống khác · Business analysis (BA)

---

# PHẦN A · Cho người đọc

## A1. Vì sao

Không có lưới giờ trống, người dùng thử từng giờ và gặp 409 liên tục (RB-02.1). Khi chỗ kín, một câu "không còn chỗ" không đủ: người dùng cần ngay giờ khác. Bản vẽ phân biệt rõ "409 · business outcome, not an outage": hết chỗ là chuyện bình thường của xưởng, không phải lỗi hệ thống.

## A2. Bằng chứng

| ID | Nguồn | Nội dung |
| --- | --- | --- |
| E-06.1 | Requirement F06 | FR-06.1–06.5, BR-06.1, 3 AC. |
| E-06.2 | Bản vẽ New booking | "Struck-through slots have no free bay or technician." |
| E-06.3 | Bản vẽ Booking error states | "Nearest available slots: 10:00 today · 11:15 today · 08:00 Tue 3 Nov". |
| E-06.4 | Bản vẽ khách bước 2 | "Morning {n} free slots", "This day is fully booked", "Next available slot for Oil change: Fri 6 Nov · 08:00". |
| E-06.5 | SDD §12 | Đọc thẳng DB, không cache. |
| E-06.6 | NFR-02 | Giờ trống một ngày p95 < 300 ms. |

## A3. Ưu tiên

Should (requirement). Trong F06: lưới + gợi ý khi 409 là cốt lõi; `next` và đếm "free slots" là Should; `excludeAppointmentId` cần cho F09.

## A4. Rủi ro nghiệp vụ

| ID | Rủi ro | Xử lý |
| --- | --- | --- |
| RB-06.1 | Người dùng tưởng lưới là cam kết | Câu "Times can be taken by someone else until you confirm." dưới lưới (khách) |
| RB-06.2 | Gợi ý ngày xa (7 ngày) không phù hợp khách | Chấp nhận; khách có thể chọn ngày khác trên lưới |

---

# PHẦN B · Cho agent triển khai

## B0. Quy tắc đọc

> - **PHẢI** / **KHÔNG ĐƯỢC**: bắt buộc. **NÊN**: làm trừ khi có lý do ghi trong commit. **CÓ THỂ**: tuỳ chọn. `CLAUDE.md` áp dụng toàn bộ.
> - Yêu cầu là AC, INV, quyền và câu chữ. Code mẫu, query và thứ tự bước là **giả thuyết**: agent kiểm chúng với code và dữ liệu thật ở HEAD (`implement-ticket` bước 4).
> - Giả thuyết sai mà bản đúng vẫn giữ mọi AC, quyền và câu chữ → làm bản đúng, ghi vào commit và sửa spec. Bản đúng đổi điều người dùng thấy → dừng lại và hỏi.

## B1. ADR

### ADR-06.1 · Một snapshot, tính trong bộ nhớ

- FR-06.4 bắt buộc. Dùng lại `CandidateFinder` của F03 cho từng khung → một định nghĩa "trống" duy nhất cho lưới và cho đặt lịch.

### ADR-06.2 · Trả mọi khung kèm `available`

- **Lựa chọn:** (a) chỉ trả khung trống; (b) trả mọi khung + cờ.
- **Chọn (b):** FR-06.5 (gạch ngang, giữ hình lưới) và web không phải tự tính giờ mở cửa.

### ADR-06.3 · Gợi ý tính sau rollback

- Trong transaction lỗi 23P01, PostgreSQL không cho truy vấn tiếp. Tính sau rollback với snapshot mới cũng cho gợi ý đúng hơn.

## B2. Chỉ số

| ID | Chỉ số | SQL / nguồn | Ngưỡng |
| --- | --- | --- | --- |
| M-06.1 | p95 availability | `api_events` route `GET api/v1/dealerships/{dealership}/availability` (F08) | < 300 ms |
| M-06.2 | Tỉ lệ 409 có gợi ý được dùng (đặt lại thành công trong 5 phút với giờ thuộc gợi ý) | Ghép `api_events` 409 (`detail->alternatives`) với lịch tạo sau của cùng user | Theo dõi |
| M-06.3 | Tỉ lệ 409 `NO_AVAILABILITY` có `alternatives` rỗng | `SELECT count(*) FILTER (WHERE jsonb_array_length(detail->'alternatives') = 0)::float / count(*) FROM api_events WHERE code = 'NO_AVAILABILITY' AND occurred_at > now() - interval '7 days';` | Cảnh báo > 20% (thiếu nguồn lực) |

## B3. Ràng buộc cho tương lai

| ID | Ràng buộc | Seam |
| --- | --- | --- |
| FC-06.1 | Gợi ý theo kỹ thuật viên quen | `AllocationRequest.preferTechnicianId` |
| FC-06.2 | Tải rất cao | Cache bị cấm (INV-06.4); SDD phase 2/3 thay vì cache |
| FC-06.3 | Gợi ý đại lý khác | `AlternativeTimesFinder` nhận danh sách đại lý |
