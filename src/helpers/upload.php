<?php

const PRODUCT_UPLOAD_DIR = '/var/www/html/uploads/products/';
const MAX_IMAGE_SIZE = 2 * 1024 * 1024;
const MAX_IMAGES_PER_PRODUCT = 4;

/**
 * Gom các file thực sự được chọn từ input name="images[]".
 */
function collectUploadedFiles(string $field): array
{
    $files = [];

    if (!isset($_FILES[$field]) || !is_array($_FILES[$field]['name'])) {
        return $files;
    }

    foreach ($_FILES[$field]['name'] as $i => $name) {
        if ($_FILES[$field]['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $files[] = [
            'name'     => $name,
            'tmp_name' => $_FILES[$field]['tmp_name'][$i],
            'error'    => $_FILES[$field]['error'][$i],
            'size'     => $_FILES[$field]['size'][$i],
        ];
    }

    return $files;
}

/**
 * Kiểm tra và di chuyển ảnh vào thư mục uploads.
 * Trả về [danh sách tên file đã lưu, thông báo lỗi].
 * Nếu có lỗi, các file đã di chuyển trước đó được xóa.
 */
function saveProductImages(array $files): array
{
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    $saved = [];
    $finfo = new finfo(FILEINFO_MIME_TYPE);

    foreach ($files as $file) {

        if ($file['error'] !== UPLOAD_ERR_OK) {
            deleteProductImageFiles($saved);
            return [[], 'Tải ảnh lên không thành công.'];
        }

        if ($file['size'] > MAX_IMAGE_SIZE) {
            deleteProductImageFiles($saved);
            return [[], 'Mỗi ảnh tối đa 2 MB.'];
        }

        $mime = $finfo->file($file['tmp_name']);

        if (!isset($allowed[$mime])) {
            deleteProductImageFiles($saved);
            return [[], 'Chỉ chấp nhận ảnh JPG, PNG hoặc WebP.'];
        }

        $fileName = 'product-' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];

        if (!move_uploaded_file($file['tmp_name'], PRODUCT_UPLOAD_DIR . $fileName)) {
            deleteProductImageFiles($saved);
            return [[], 'Không thể lưu ảnh lên máy chủ.'];
        }

        $saved[] = $fileName;
    }

    return [$saved, ''];
}

function deleteProductImageFiles(array $fileNames): void
{
    foreach ($fileNames as $fileName) {
        $path = PRODUCT_UPLOAD_DIR . basename($fileName);

        if (is_file($path)) {
            unlink($path);
        }
    }
}
