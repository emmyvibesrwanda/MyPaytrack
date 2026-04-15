<?php
require_once __DIR__ . '/db.php';

function formatCurrency($amount) {
    return number_format(floatval($amount), 0, ',', '.') . ' RWF';
}

function getStatusBadge($status) {
    $badges = [
        'paid' => '<span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium">Paid</span>',
        'unpaid' => '<span class="px-2 py-1 bg-red-100 text-red-800 rounded-full text-xs font-medium">Unpaid</span>',
        'partial' => '<span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs font-medium">Partial</span>',
        'overdue' => '<span class="px-2 py-1 bg-red-100 text-red-800 rounded-full text-xs font-medium">Overdue</span>'
    ];
    return $badges[$status] ?? '<span class="px-2 py-1 bg-gray-100 rounded-full text-xs">' . $status . '</span>';
}

function getPaymentMethodBadge($method) {
    $badges = [
        'cash' => '<span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs">Cash</span>',
        'bank_transfer' => '<span class="px-2 py-1 bg-purple-100 text-purple-800 rounded-full text-xs">Bank Transfer</span>',
        'mobile_money' => '<span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs">Mobile Money</span>',
        'check' => '<span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs">Check</span>'
    ];
    return $badges[$method] ?? '<span class="px-2 py-1 bg-gray-100 rounded-full text-xs">' . $method . '</span>';
}

function generateInvoiceNumber() {
    return 'INV-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

function logActivity($user_id, $action, $description) {
    $conn = getDB();
    $desc = $conn->real_escape_string($description);
    $conn->query("INSERT INTO activity_logs (user_id, action, description) VALUES ($user_id, '$action', '$desc')");
}

function getRecentActivity($user_id, $limit = 10) {
    $conn = getDB();
    $activities = [];
    $result = $conn->query("SELECT * FROM activity_logs WHERE user_id = $user_id ORDER BY created_at DESC LIMIT $limit");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $activities[] = $row;
        }
    }
    return $activities;
}

// WhatsApp and SMS functions (keep these, they don't conflict)
function sendWhatsAppReminder($phone, $message) {
    if (empty($phone)) return false;
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) == 9) {
        $phone = '250' . $phone;
    }
    $log = date('Y-m-d H:i:s') . " - WhatsApp to: $phone - Message: $message\n";
    file_put_contents(__DIR__ . '/../whatsapp_log.txt', $log, FILE_APPEND);
    return true;
}

function sendSMSReminder($phone, $message) {
    if (empty($phone)) return false;
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) == 9) {
        $phone = '250' . $phone;
    }
    $log = date('Y-m-d H:i:s') . " - SMS to: $phone - Message: $message\n";
    file_put_contents(__DIR__ . '/../sms_log.txt', $log, FILE_APPEND);
    return true;
}
?>