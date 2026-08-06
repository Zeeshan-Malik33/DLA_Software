<?php
// Escape output safely
function h($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// Format a number as money with currency code
function formatMoney($amount, $currency = 'PKR') {
    return $currency . ' ' . number_format((float) $amount, 0);
}

// Nice label for order status
function statusLabel($status) {
    return ucfirst($status);
}

// Tailwind color classes per order status (used for badges/legend dots)
function statusColor($status) {
    return match ($status) {
        'delivered'  => ['bg' => 'bg-emerald-500', 'text' => 'text-emerald-700', 'soft' => 'bg-emerald-50'],
        'shipped'    => ['bg' => 'bg-blue-500',    'text' => 'text-blue-700',    'soft' => 'bg-blue-50'],
        'processing' => ['bg' => 'bg-amber-500',   'text' => 'text-amber-700',   'soft' => 'bg-amber-50'],
        'pending'    => ['bg' => 'bg-gray-400',    'text' => 'text-gray-600',    'soft' => 'bg-gray-100'],
        'cancelled'  => ['bg' => 'bg-red-500',     'text' => 'text-red-700',     'soft' => 'bg-red-50'],
        default      => ['bg' => 'bg-gray-400',    'text' => 'text-gray-600',    'soft' => 'bg-gray-100'],
    };
}
