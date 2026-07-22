<?php
declare(strict_types=1);
require_once __DIR__.'/../lib/storage.php';
admin_required();ensure_storage();

function safe_id(string $value): string { return preg_replace('/[^a-zA-Z0-9_-]/','',$value); }
function backup_payload(): array {
    $projects=[];$versions=[];
    foreach(glob(PROJECT_DIR.'/*.json')?:[] as $file){$data=read_json($file);if($data)$projects[basename($file,'.json')]=$data;}
    foreach(glob(VERSION_DIR.'/*',GLOB_ONLYDIR)?:[] as $dir){$id=basename($dir);foreach(glob($dir.'/*.json')?:[] as $file){$data=read_json($file);if($data)$versions[$id][basename($file,'.json')]=$data;}}
    return ['format'=>'bukovina-backup','version'=>1,'createdAt'=>gmdate('c'),'projects'=>$projects,'versions'=>$versions,'users'=>read_json(USER_FILE)];
}
function validate_backup(array $data): array {
    if(($data['format']??'')!=='bukovina-backup'||(int)($data['version']??0)!==1)throw new RuntimeException('Súbor nie je platná záloha Bukovina Planner.');
    foreach(['projects','versions','users'] as $key)if(!isset($data[$key])||!is_array($data[$key]))throw new RuntimeException('Záloha nemá kompletnú štruktúru.');
    foreach($data['projects'] as $id=>$project){if(safe_id((string)$id)!==(string)$id||!is_array($project)||($project['id']??'')!==$id)throw new RuntimeException('Záloha obsahuje neplatný projekt.');}
    foreach($data['versions'] as $id=>$items){if(safe_id((string)$id)!==(string)$id||!is_array($items))throw new RuntimeException('Záloha obsahuje neplatné verzie.');foreach($items as $name=>$version){if(!preg_match('/^[0-9]{8}-[0-9]{6}$/',(string)$name)||!is_array($version))throw new RuntimeException('Záloha obsahuje neplatnú verziu projektu.');}}
    foreach($data['users'] as $user)if(!is_array($user)||!filter_var($user['email']??'',FILTER_VALIDATE_EMAIL)||empty($user['passwordHash']))throw new RuntimeException('Záloha obsahuje neplatného používateľa.');
    return $data;
}
function store_internal_backup(array $payload): void {
    $dir=DATA_DIR.'/backups';if(!is_dir($dir)&&!mkdir($dir,0775,true)&&!is_dir($dir))throw new RuntimeException('Nepodarilo sa vytvoriť priečinok záloh.');
    write_json($dir.'/before-restore-'.gmdate('Ymd-His').'.json',$payload);
    $files=glob($dir.'/before-restore-*.json')?:[];rsort($files);foreach(array_slice($files,10) as $old)@unlink($old);
}

if($_SERVER['REQUEST_METHOD']==='GET'&&($_GET['action']??'')==='download'){
    $payload=backup_payload();$json=json_encode($payload,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    header('Content-Type: application/json; charset=utf-8');header('Content-Disposition: attachment; filename="bukovina-backup-'.gmdate('Y-m-d-His').'.json"');header('Content-Length: '.strlen((string)$json));header('Cache-Control: no-store');echo $json;exit;
}
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('Nepovolená metóda.');}
verify_csrf();
if(!isset($_FILES['backup'])||($_FILES['backup']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK){header('Location:settings.php?restore=upload-error');exit;}
if(($_FILES['backup']['size']??0)>50*1024*1024){header('Location:settings.php?restore=too-large');exit;}
try{
    $raw=file_get_contents((string)$_FILES['backup']['tmp_name']);$decoded=json_decode((string)$raw,true,512,JSON_THROW_ON_ERROR);$data=validate_backup($decoded);store_internal_backup(backup_payload());
    $replace=!empty($_POST['replace']);
    if($replace){foreach(glob(PROJECT_DIR.'/*.json')?:[] as $f)@unlink($f);foreach(glob(VERSION_DIR.'/*',GLOB_ONLYDIR)?:[] as $dir){foreach(glob($dir.'/*')?:[] as $f)if(is_file($f))@unlink($f);@rmdir($dir);}}
    foreach($data['projects'] as $id=>$project)write_json(project_path((string)$id),$project);
    foreach($data['versions'] as $id=>$items){$dir=VERSION_DIR.'/'.safe_id((string)$id);if(!is_dir($dir))mkdir($dir,0775,true);foreach($items as $name=>$version)write_json($dir.'/'.safe_id((string)$name).'.json',$version);}
    if(!empty($_POST['restoreUsers']))write_json(USER_FILE,$data['users']);
    header('Location:settings.php?restore=ok&projects='.count($data['projects']));exit;
}catch(Throwable $e){header('Location:settings.php?restore=error&message='.rawurlencode($e->getMessage()));exit;}
