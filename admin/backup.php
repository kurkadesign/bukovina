<?php
declare(strict_types=1);
require_once __DIR__.'/../lib/backup.php';
manager_required();ensure_storage();ensure_backup_storage();

function backup_redirect(string $status,string $message=''): never {
    $query=['restore'=>$status];
    if($message!=='')$query['message']=$message;
    header('Location:settings.php?'.http_build_query($query).'#backup-settings');
    exit;
}
function send_backup_file(string $path): never {
    $name=basename($path);
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="'.$name.'"');
    header('Content-Length: '.filesize($path));
    header('Cache-Control: no-store');
    readfile($path);
    exit;
}

if(($_SERVER['REQUEST_METHOD']??'GET')==='GET'){
    http_response_code(405);exit('Nepovolená metóda.');
}

verify_csrf();
$action=(string)($_POST['action']??'restore');
if($action==='delete'){
    $selected=basename((string)($_POST['storedBackup']??''));
    if(!preg_match('/^\d{8}-\d{6}(?:-man)?\.zip$/',$selected))backup_redirect('error','Vyberte platnú ZIP zálohu na vymazanie.');
    $path=BACKUP_DIR.'/'.$selected;
    if(!is_file($path)||!unlink($path))backup_redirect('error','Vybranú zálohu sa nepodarilo vymazať.');
    $meta=backup_metadata();
    $meta['history']=array_values(array_filter((array)$meta['history'],fn(array $item):bool=>(string)($item['file']??'')!==$selected));
    foreach(['automatic','manual'] as $type){
        $items=array_values(array_filter($meta['history'],fn(array $item):bool=>($item['type']??'')===$type&&is_file(BACKUP_DIR.'/'.basename((string)($item['file']??'')))));
        $last=$items?end($items):null;$prefix=$type==='automatic'?'Automatic':'Manual';
        $meta['last'.$prefix.'At']=$last['createdAt']??null;
        $meta['last'.$prefix.'File']=$last['file']??null;
    }
    write_json(BACKUP_META_FILE,$meta);
    backup_redirect('backup-deleted');
}
if($action==='download'){
    $selected=basename((string)($_POST['storedBackup']??''));
    if(!preg_match('/^\d{8}-\d{6}-man\.zip$/',$selected))backup_redirect('error','Vyberte manuálnu ZIP zálohu na stiahnutie.');
    $path=BACKUP_DIR.'/'.$selected;
    if(!is_file($path))backup_redirect('error','Vybraná manuálna záloha neexistuje.');
    send_backup_file($path);
}
if($action==='create'){
    try{create_data_backup(true);backup_redirect('backup-created');}
    catch(Throwable $error){backup_redirect('error',$error->getMessage());}
}

$source=(string)($_POST['restoreSource']??'stored');
$zipPath='';
$uploadedTmp='';
if($source==='upload'){
    if(!isset($_FILES['backup'])||($_FILES['backup']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)backup_redirect('upload-error');
    if(($_FILES['backup']['size']??0)>500*1024*1024)backup_redirect('too-large');
    $uploadedTmp=(string)$_FILES['backup']['tmp_name'];$zipPath=$uploadedTmp;
}else{
    $selected=basename((string)($_POST['storedBackup']??''));
    if($selected===''||!preg_match('/^\d{8}-\d{6}(?:-man)?\.zip$/',$selected))backup_redirect('error','Vyberte platnú uloženú zálohu.');
    $zipPath=BACKUP_DIR.'/'.$selected;
    if(!is_file($zipPath))backup_redirect('error','Vybraná záloha neexistuje.');
}

try{
    restore_data_backup($zipPath);
    backup_redirect('ok');
}catch(Throwable $error){
    backup_redirect('error',$error->getMessage());
}
