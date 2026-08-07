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

// Initials for a customer with no profile photo, e.g. "Mirwais Ali" -> "MA"
function initials($name) {
    $parts = preg_split('/\s+/', trim($name ?? ''));
    $parts = array_filter($parts);
    if (empty($parts)) return '?';
    $first = mb_substr($parts[0], 0, 1);
    $last  = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';
    return mb_strtoupper($first . $last);
}

// Deterministic pastel-ish background color for an initials avatar, based on the name
function avatarColor($name) {
    $palette = ['bg-blue-500', 'bg-emerald-500', 'bg-orange-500', 'bg-purple-500', 'bg-pink-500', 'bg-amber-500', 'bg-cyan-500'];
    $index = crc32($name ?? '') % count($palette);
    return $palette[$index];
}

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
