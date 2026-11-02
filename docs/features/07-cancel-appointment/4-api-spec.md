# F07 · Huỷ lịch hẹn · API spec (API)

---

# PHẦN A · Cho người đọc

Một endpoint: `POST /appointments/{id}/cancel` với lý do. Cần `If-Match` (phiên bản đang thấy) và `Idempotency-Key`. Thành công trả lịch đã huỷ và phiên bản mới.

---

# PHẦN B · Cho agent triển khai

## B0. Quy tắc đọc

> - **PHẢI** / **KHÔNG ĐƯỢC**: bắt buộc. **NÊN**: làm trừ khi có lý do ghi trong commit. **CÓ THỂ**: tuỳ chọn. `CLAUDE.md` áp dụng toàn bộ.
> - Yêu cầu là AC, INV, quyền và câu chữ. Code mẫu, query và thứ tự bước là **giả thuyết**: agent kiểm chúng với code và dữ liệu thật ở HEAD (`implement-ticket` bước 4).
> - Giả thuyết sai mà bản đúng vẫn giữ mọi AC, quyền và câu chữ → làm bản đúng, ghi vào commit và sửa spec. Bản đúng đổi điều người dùng thấy → dừng lại và hỏi.

## B1. Endpoint

| ID | Method + path | Ai | Middleware | Thành công |
| --- | --- | --- | --- | --- |
| API-07.1 | `POST /api/v1/appointments/{appointment}/cancel` | advisor (đại lý mình), customer (của mình) | `auth:sanctum`, `can:cancel-appointment`, `idempotent`, `if-match` | 200 `AppointmentResource` + `ETag` |

## B2. Hợp đồng

```json
{ "reason": "CUSTOMER_BUSY", "note": "Customer will call back to book next week." }
```

| Field | Luật |
| --- | --- |
| `reason` | Cố vấn: bắt buộc, thuộc `CUSTOMER_REQUEST`, `CUSTOMER_BUSY`, `DONE_ELSEWHERE`, `DEALERSHIP_ISSUE`, `OTHER`. Khách: tuỳ chọn, thuộc `SOMETHING_CAME_UP`, `DONE_ELSEWHERE`, `OTHER`. |
| `note` | Tuỳ chọn, chuỗi, trim, ≤ 500 |

Response: `AppointmentResource` với `status: CANCELLED`, `cancellation: { reason, note, at, byRole }` (khách nhận `reason`/`note` chỉ khi chính khách huỷ; nhân viên huỷ → khách thấy `byRole: SERVICE_ADVISOR`, `reason: null`, `note: null`).

`422 TOO_LATE_TO_CANCEL`: `{ "dealershipPhone": "028 3812 3456", "start": "…" }`.

## B3. Bảng lỗi

RQ §B8.

## B4. Bảng đầu vào bất thường

| Đầu vào | Kỳ vọng |
| --- | --- |
| Cố vấn gửi `reason: "SOMETHING_CAME_UP"` (lý do của khách) | 400 |
| Khách gửi `reason: "DEALERSHIP_ISSUE"` | 400 |
| `reason: "customer_busy"` (chữ thường) | 400 |
| `note` 501 ký tự | 400 |
| `note` là số | 400 |
| `{appointment}` = `abc` | 404 `APPOINTMENT_NOT_FOUND` |
| Không `If-Match` | 428 |
| `If-Match: W/"1"` | 400 |
| Không `Idempotency-Key` | 400 `IDEMPOTENCY_KEY_REQUIRED` |
| Cùng khoá, `reason` khác | 422 `IDEMPOTENCY_KEY_MISMATCH` |
| Body có `status: "CONFIRMED"` | Bỏ qua |

## B5. Giới hạn

`note` ≤ 500. Giới hạn 2 giờ cho khách. Không rate limit riêng (mỗi lịch chỉ huỷ được một lần).
