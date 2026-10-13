<?php

/**
 * Nhãn hiển thị và màu badge cho từng trạng thái đơn hàng.
 */
function orderStatusLabels(): array
{
    return [
        'Pending'   => ['Chờ xác nhận', 'warning'],
        'Confirmed' => ['Đã xác nhận', 'info'],
        'Shipping'  => ['Đang giao', 'primary'],
        'Completed' => ['Hoàn thành', 'success'],
        'Cancelled' => ['Đã hủy', 'danger'],
    ];
}

/**
 * Các trạng thái được phép chuyển tiếp từ trạng thái hiện tại.
 */
function orderStatusTransitions(): array
{
    return [
        'Pending'   => ['Confirmed', 'Cancelled'],
        'Confirmed' => ['Shipping', 'Cancelled'],
        'Shipping'  => ['Completed', 'Cancelled'],
        'Completed' => [],
        'Cancelled' => [],
    ];
}

function orderStatusBadge(string $status): string
{
    $labels = orderStatusLabels();
    [$text, $color] = $labels[$status] ?? [$status, 'secondary'];

    return '<span class="badge bg-' . $color . '">' . htmlspecialchars($text) . '</span>';
}
