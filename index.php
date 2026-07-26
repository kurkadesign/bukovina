<?php
require_once __DIR__ . '/lib/storage.php';
$roomSettings = isset($_GET['room-settings']);
if ($roomSettings) {
    secure_session_start();
    $users = normalize_admin_users(read_json(USER_FILE));
    restore_admin_from_cookie($users);
    $userIndex = admin_user_index($users, (string) ($_SESSION['admin'] ?? ''));
    if ($userIndex === null) {
        header('Location: admin/index.php');
        exit;
    }
    if (($users[$userIndex]['role'] ?? '') !== 'manager') {
        http_response_code(403);
        exit('Nastavenie sály je dostupné iba správcovi.');
    }
}
$html = file_get_contents(__DIR__ . '/index.html');
$assetFiles=['style.css','panel.css','readonly.css','css/global-font.css','css/fontawesome.css','css/sharp-light.css','js/bootstrap.js','js/app.js','js/state.js','js/pdf-font-data.js','js/vendor/html2canvas.min.js','js/vendor/jspdf.umd.min.js'];
$version=(string)max(array_map(static fn(string $file):int=>(int)@filemtime(__DIR__.'/'.$file),$assetFiles));
$html = str_replace('</head>', '<link rel="stylesheet" href="readonly.css"></head>', $html);
$html = preg_replace('/<script type="module" src="js\/app\.js(?:\?v=[^"]*)?"><\/script>/', '<script type="module" src="js/bootstrap.js"></script>', $html);
$html = preg_replace('/((?:href|src)="(?!https?:\/\/)[^"?]+\.(?:css|js))(?:\?v=[^"]*)?"/', '$1?v='.$version.'"', $html);
$html = str_replace('</head>', '<script>window.__ASSET_VERSION__='.json_encode($version).';window.__ROOM_SETTINGS__='.json_encode($roomSettings).';window.__DEFAULT_ROOM_ITEMS__='.json_encode(default_room_items(),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).';window.__ROOM_CSRF__='.json_encode($roomSettings?csrf_token():'').';window.saveDefaultRoomItems=async function(items){const response=await fetch("api/default-room.php",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({csrf:window.__ROOM_CSRF__,items:items}),cache:"no-store"});const data=await response.json();if(!response.ok)throw new Error(data.error||"Sálu sa nepodarilo uložiť.");window.__DEFAULT_ROOM_ITEMS__=data.items;return data};</script></head>', $html);
echo $html;
