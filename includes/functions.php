<?php
// ---------------------------------------------------------
// Returns the lowest available customer_id (filling gaps left by deletions).
// If the table is empty the result is 1.
// ---------------------------------------------------------
function nextCustomerId(PDO $pdo): int {
    // Find the smallest positive gap: the minimum N where N is not present
    // but N-1 is (or N=1). This is equivalent to:
    //   SELECT MIN(t.customer_id + 1) FROM customers t
    //   WHERE NOT EXISTS (SELECT 1 FROM customers t2 WHERE t2.customer_id = t.customer_id + 1)
    // plus a fallback to 1 when the table is empty.
    $row = $pdo->query("
        SELECT COALESCE(
            (SELECT MIN(t.customer_id + 1)
             FROM customers t
             WHERE NOT EXISTS (
                 SELECT 1 FROM customers t2
                 WHERE t2.customer_id = t.customer_id + 1
             )
             AND t.customer_id + 1 > 0),
            1
        ) AS next_id
    ")->fetch();
    return (int) $row['next_id'];
}

// Escape output safely
function h($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// Format a number as money with currency symbol (PKR = Pakistani Rupee)
function formatMoney($amount, $currency = 'PKR') {
    return 'Rs. ' . number_format((float) $amount, 0);
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

// Display-friendly order code, e.g. order_id 345 -> #ORD-10345
function orderCode($id) {
    return '#ORD-' . (10000 + (int) $id);
}

// Human-readable relative time, e.g. "3 months ago"
function timeAgo($datetime) {
    if (!$datetime) return 'never';
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    $units = [31536000 => 'year', 2592000 => 'month', 86400 => 'day', 3600 => 'hour', 60 => 'minute'];
    foreach ($units as $seconds => $label) {
        $count = floor($diff / $seconds);
        if ($count >= 1) return $count . ' ' . $label . ($count > 1 ? 's' : '') . ' ago';
    }
    return 'just now';
}

// Tailwind badge classes per expense category
function expenseCategoryColor($category) {
    $palette = [
        'Rent' => 'bg-blue-50 text-blue-700', 'Utilities' => 'bg-amber-50 text-amber-700',
        'Food' => 'bg-emerald-50 text-emerald-700', 'Transport' => 'bg-purple-50 text-purple-700',
        'Shopping' => 'bg-pink-50 text-pink-700', 'Health' => 'bg-red-50 text-red-700',
        'Entertainment' => 'bg-cyan-50 text-cyan-700', 'Supplies' => 'bg-indigo-50 text-indigo-700',
        'Other' => 'bg-gray-100 text-gray-600',
    ];
    return $palette[$category] ?? 'bg-gray-100 text-gray-600';
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
