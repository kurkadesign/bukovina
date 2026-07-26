<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/storage.php';

manager_required();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    json_response(['ok' => false, 'error' => 'Nepodporovaná metóda.'], 405);
}

$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) {
    json_response(['ok' => false, 'error' => 'Neplatné údaje.'], 400);
}
secure_session_start();
if (!hash_equals((string) ($_SESSION['csrf'] ?? ''), (string) ($input['csrf'] ?? ''))) {
    json_response(['ok' => false, 'error' => 'Platnosť relácie vypršala.'], 419);
}
if (!isset($input['items']) || !is_array($input['items'])) {
    json_response(['ok' => false, 'error' => 'Chýba zoznam prvkov sály.'], 422);
}

$allowed = ['type','name','x','y','width','height','rotation','number','seats','note','locked','defaultKey'];
$items = [];
foreach ($input['items'] as $item) {
    if (!is_array($item) || empty($item['type']) || !isset($item['x'], $item['y'])) {
        continue;
    }
    $clean = array_intersect_key($item, array_flip($allowed));
    $clean['defaultKey'] = trim((string) ($clean['defaultKey'] ?? '')) ?: 'prvok-' . token(8);
    $clean['locked'] = false;
    $items[] = $clean;
}
if ($items === []) {
    json_response(['ok' => false, 'error' => 'Sála musí obsahovať aspoň jeden prvok.'], 422);
}

$user = current_admin_user();
write_json(DEFAULT_ROOM_FILE, [
    'version' => 1,
    'updatedAt' => gmdate('c'),
    'updatedBy' => (string) ($user['email'] ?? ''),
    'items' => $items,
]);
json_response(['ok' => true, 'items' => $items]);
