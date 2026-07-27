<?php
declare(strict_types=1);
require_once __DIR__.'/../lib/backup.php';

$token=(string)($_GET['token']??'');
if($token==='room-settings'){
    manager_required();
}else{
    $mode='';
    if($token===''||find_project_by_token($token,$mode)===null)json_response(['ok'=>false,'error'=>'Neplatný prístup.'],403);
}

maybe_create_automatic_backup();
json_response(['ok'=>true]);
