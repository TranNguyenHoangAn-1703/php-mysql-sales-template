# Hệ thống quản lý bán hàng PHP + MySQL + Docker

Bản hoàn chỉnh sau Hands-on 01 đến 15: Docker Compose, CRUD danh mục, người giao hàng, sản phẩm (nhiều ảnh), trang bán hàng có tìm kiếm và phân trang, giỏ hàng, đặt hàng, quản lý đơn hàng, tài khoản khách hàng và API JSON.

## Chạy dự án

```bash
docker compose up -d --build
```

- Trang bán hàng: http://localhost:8080/
- Khu quản trị: http://localhost:8080/admin/
- API: http://localhost:8080/api/products.php và http://localhost:8080/api/categories.php

Lần chạy đầu tiên MySQL tự nạp `database/schema.sql` rồi `database/seed.sql`. Hai file này chỉ chạy khi volume còn trống. Muốn nạp lại dữ liệu mẫu:

```bash
docker compose down -v
docker compose up -d --build
```

Nếu tải ảnh lên báo lỗi quyền ghi (thường gặp trên Linux), cấp quyền cho thư mục ảnh:

```bash
chmod -R 777 public/uploads/products
```

## Cấu trúc

```
compose.yaml
docker/php/Dockerfile
database/schema.sql, seed.sql
src/config        kết nối database, session
src/helpers       upload ảnh, trạng thái đơn hàng
src/includes      header, navbar, footer (admin và frontend)
public/           trang web (admin, api, uploads)
```

## Tài khoản

Dữ liệu mẫu không có tài khoản đăng nhập. Hãy tạo tài khoản ở `/register.php`.
