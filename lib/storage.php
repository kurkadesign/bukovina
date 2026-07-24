<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
function ensure_storage(): void {
 foreach([DATA_DIR,PROJECT_DIR,VERSION_DIR]as$d)if(!is_dir($d))mkdir($d,0775,true);
 $protectionFile=DATA_DIR.'/.htaccess';
 if(is_dir(DATA_DIR)&&is_writable(DATA_DIR)&&!is_file($protectionFile))file_put_contents($protectionFile,"Require all denied\nDeny from all\n",LOCK_EX);
}
function json_response(array $data, int $status=200): never { http_response_code($status); header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
function enable_asset_versioning(): void { static $enabled=false;if($enabled)return;$enabled=true;$version=(string)max((int)@filemtime(__DIR__.'/../admin/style.css'),(int)@filemtime(__DIR__.'/../admin/icons.css'),(int)@filemtime(__DIR__.'/../admin/layout.css'),(int)@filemtime(__DIR__.'/../css/fontawesome.css'),(int)@filemtime(__DIR__.'/../css/sharp-light.css'));ob_start(static function(string $html)use($version):string{$html=str_replace('<header><b>Bukovina Planner</b>','<header><a class="admin-logo" href="index.php" aria-label="Bukovina Planner"><img src="../assets/bukovina.png" alt="Bukovina Planner"></a>',$html);$html=str_replace('<h1>Bukovina Planner</h1>','<h1 class="admin-login-logo"><img src="../assets/bukovina.png" alt="Bukovina Planner"></h1>',$html);$html=strtr($html,['Názov svadby'=>'Názov eventu','názov svadby'=>'názov eventu','Dátum svadby'=>'Dátum eventu','dátum svadby'=>'dátum eventu','Svadba Novákovci'=>'Event Novákovci','Svadobné'=>'Eventové','Svadobná'=>'Eventová','Svadobný'=>'Eventový','svadobné'=>'eventové','svadobná'=>'eventová','svadobný'=>'eventový','projektov'=>'eventov','Projektov'=>'Eventov','projektu'=>'eventu','Projektu'=>'Eventu','projektom'=>'eventom','Projektom'=>'Eventom','projekty'=>'eventy','Projekty'=>'Eventy','projekt'=>'event','Projekt'=>'Event']);$html=str_replace('</head>','<link rel="stylesheet" href="layout.css"></head>',$html);return(string)preg_replace('/((?:href|src)="(?!https?:\/\/)[^"?]+\.(?:css|js))(?:\?v=[^"]*)?"/','$1?v='.$version.'"',$html);}); }
function read_json(string $file, array $fallback=[]): array { if (!is_file($file)) return $fallback; $v=json_decode((string)file_get_contents($file), true); return is_array($v)?$v:$fallback; }
function write_json(string $file, array $data): void { ensure_storage(); if(isset($data['access']['shareToken']))$data['access']['shareEnabled']=true;$tmp=$file.'.tmp'; if(file_put_contents($tmp,json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX)===false) throw new RuntimeException('Súbor sa nepodarilo zapísať.'); if(!rename($tmp,$file)) throw new RuntimeException('Súbor sa nepodarilo uložiť.'); }
function token(int $bytes=24): string { return bin2hex(random_bytes($bytes)); }
function generated_admin_password(int $length=16): string { $alphabet='ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';$out='';$max=strlen($alphabet)-1;for($i=0;$i<$length;$i++)$out.=$alphabet[random_int(0,$max)];return$out; }
function generated_admin_numeric_password(): string { return str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT); }
function external_storage_security_check(string $baseUrl=BASE_URL): array {
 ensure_storage();
 $baseUrl=rtrim($baseUrl,'/');
 $probeName='.bukovina-security-'.token(6).'.txt';
 $targets=[
  ['label'=>'Používateľské účty','dir'=>DATA_DIR,'url'=>$baseUrl.'/data/'.$probeName],
  ['label'=>'Eventy','dir'=>PROJECT_DIR,'url'=>$baseUrl.'/data/projects/'.$probeName],
 ];
 $results=[];
 foreach($targets as $target){
  $file=$target['dir'].'/'.$probeName;
  if(file_put_contents($file,'bukovina-security-probe',LOCK_EX)===false){$results[]=['label'=>$target['label'],'status'=>'unknown','httpCode'=>0,'message'=>'Kontrolný súbor sa nepodarilo vytvoriť.'];continue;}
  $code=0;
  try{
   $context=stream_context_create(['http'=>['method'=>'GET','timeout'=>5,'ignore_errors'=>true,'follow_location'=>1,'max_redirects'=>3],'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]]);
   $http_response_header=[];
   @file_get_contents($target['url'],false,$context);
   foreach($http_response_header as $header)if(preg_match('~^HTTP/\S+\s+(\d{3})~i',$header,$match))$code=(int)$match[1];
  }finally{@unlink($file);}
  if($code===200)$results[]=['label'=>$target['label'],'status'=>'unsafe','httpCode'=>$code,'message'=>'Kontrolný súbor je verejne dostupný.'];
  elseif(in_array($code,[401,403,404],true))$results[]=['label'=>$target['label'],'status'=>'safe','httpCode'=>$code,'message'=>'Prístup zvonku je zablokovaný.'];
  else $results[]=['label'=>$target['label'],'status'=>'unknown','httpCode'=>$code,'message'=>'Verejnú dostupnosť sa nepodarilo spoľahlivo overiť.'];
 }
 $statuses=array_column($results,'status');
 $overall=in_array('unsafe',$statuses,true)?'unsafe':(in_array('unknown',$statuses,true)?'unknown':'safe');
 return['status'=>$overall,'checkedAt'=>gmdate('c'),'baseUrl'=>$baseUrl,'targets'=>$results];
}
function admin_role_label(string $role): string { return $role==='manager'?'Správca':'Administrátor'; }
function normalize_admin_users(array $users): array {
 $hasManager=false;
 foreach($users as &$user){$role=(string)($user['role']??'');if(!in_array($role,['manager','administrator'],true))$role='administrator';$user['role']=$role;if($role==='manager')$hasManager=true;}
 unset($user);
 if(!$hasManager&&!empty($users))$users[array_key_first($users)]['role']='manager';
 return array_values($users);
}
function admin_user_index(array $users,string $email): ?int { foreach($users as $i=>$user)if(strcasecmp((string)($user['email']??''),$email)===0)return$i;return null; }
function current_admin_user(): ?array { secure_session_start();$email=(string)($_SESSION['admin']??'');if($email==='')return null;$users=normalize_admin_users(read_json(USER_FILE));$index=admin_user_index($users,$email);return$index===null?null:$users[$index]; }
function admin_is_manager(): bool { return (current_admin_user()['role']??'')==='manager'; }
function default_event_items(): array { return [
 ['id'=>token(16),'type'=>'head-table','name'=>'Hlavný stôl','x'=>500,'y'=>90,'width'=>520,'height'=>100,'rotation'=>0,'number'=>0,'seats'=>10,'note'=>'','locked'=>false,'defaultKey'=>'hlavny-stol'],
 ['id'=>token(16),'type'=>'round-table','name'=>'Stôl 1','x'=>380,'y'=>350,'width'=>180,'height'=>180,'rotation'=>0,'number'=>1,'seats'=>8,'note'=>'','locked'=>false,'defaultKey'=>'okruhly-stol-1'],
 ['id'=>token(16),'type'=>'round-table','name'=>'Stôl 2','x'=>800,'y'=>320,'width'=>180,'height'=>180,'rotation'=>0,'number'=>2,'seats'=>8,'note'=>'','locked'=>false,'defaultKey'=>'okruhly-stol-2'],
 ['id'=>token(16),'type'=>'round-table','name'=>'Stôl 3','x'=>610,'y'=>670,'width'=>180,'height'=>180,'rotation'=>0,'number'=>3,'seats'=>8,'note'=>'','locked'=>false,'defaultKey'=>'okruhly-stol-3'],
 ['id'=>token(16),'type'=>'dj','name'=>'DJ','x'=>90,'y'=>450,'width'=>150,'height'=>85,'rotation'=>0,'number'=>1,'seats'=>0,'note'=>'','locked'=>false,'defaultKey'=>'dj-1'],
 ['id'=>token(16),'type'=>'bar','name'=>'Bar','x'=>1450,'y'=>880,'width'=>190,'height'=>75,'rotation'=>0,'number'=>1,'seats'=>0,'note'=>'','locked'=>false,'defaultKey'=>'bar-1'],
 ['id'=>token(16),'type'=>'dance-floor','name'=>'Tanečný parket','x'=>1100,'y'=>350,'width'=>360,'height'=>300,'rotation'=>0,'number'=>1,'seats'=>0,'note'=>'','locked'=>false,'defaultKey'=>'tanecny-parket-1'],
 ]; }
function format_date_sk(mixed $value,string $fallback='—'): string { $value=trim((string)$value);if($value==='')return $fallback;try{return(new DateTimeImmutable($value))->format('d.m.Y');}catch(Throwable){return $fallback;} }
function project_path(string $id): string { return PROJECT_DIR.'/'.preg_replace('/[^a-zA-Z0-9_-]/','',$id).'.json'; }
function all_projects(): array { ensure_storage(); $out=[]; foreach (glob(PROJECT_DIR.'/*.json') ?: [] as $f) { $p=read_json($f); if ($p) $out[]=$p; } usort($out,fn($a,$b)=>strcmp($b['meta']['updatedAt']??'', $a['meta']['updatedAt']??'')); return $out; }
function find_project_by_token(string $value, string &$mode=''): ?array { foreach (all_projects() as $p) { if (hash_equals((string)($p['access']['editToken']??''),$value)) {$mode='edit';return $p;} if (hash_equals((string)($p['access']['shareToken']??''),$value)) {$mode='share';return $p;} } return null; }
function activity_add(array &$project,string $type,string $actor,string $label,array $details=[],int $dedupeSeconds=0): void { $now=time();$items=$project['activity']??[];$last=$items?end($items):null;if($dedupeSeconds>0&&is_array($last)&&($last['type']??'')===$type&&($last['actor']??'')===$actor&&$now-strtotime((string)($last['at']??'1970-01-01'))<$dedupeSeconds)return;$items[]=['id'=>token(6),'type'=>$type,'actor'=>$actor,'label'=>$label,'details'=>$details,'at'=>gmdate('c')];$project['activity']=array_slice($items,-250); }
function secure_session_start(): void { if(session_status()===PHP_SESSION_NONE){$secure=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';ini_set('session.gc_maxlifetime',(string)(86400*30));session_set_cookie_params(['lifetime'=>86400*30,'path'=>'/','httponly'=>true,'secure'=>$secure,'samesite'=>'Lax']);session_start();} }
function admin_cookie_options(int $expires): array { return ['expires'=>$expires,'path'=>'/','secure'=>(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'),'httponly'=>true,'samesite'=>'Lax']; }
function issue_admin_remember_token(array &$users,int $index): void { $raw=token(32);$expires=time()+86400*365*5;$items=array_values(array_filter((array)($users[$index]['rememberTokens']??[]),fn($x)=>(int)($x['expiresAt']??0)>time()));$items[]=['hash'=>hash('sha256',$raw),'expiresAt'=>$expires,'createdAt'=>gmdate('c')];$users[$index]['rememberTokens']=array_slice($items,-10);write_json(USER_FILE,$users);setcookie('bukovina_admin',$raw,admin_cookie_options($expires)); }
function restore_admin_from_cookie(array &$users): bool { secure_session_start();if(!empty($_SESSION['admin']))return true;$raw=(string)($_COOKIE['bukovina_admin']??'');if($raw==='')return false;$hash=hash('sha256',$raw);$changed=false;foreach($users as $i=>&$user){$valid=[];foreach((array)($user['rememberTokens']??[]) as $item){if((int)($item['expiresAt']??0)<=time()){$changed=true;continue;}$valid[]=$item;if(hash_equals((string)($item['hash']??''),$hash)){$_SESSION['admin']=$user['email'];$user['rememberTokens']=$valid;if($changed)write_json(USER_FILE,$users);return true;}}$user['rememberTokens']=$valid;}unset($user);if($changed)write_json(USER_FILE,$users);setcookie('bukovina_admin','',admin_cookie_options(time()-3600));return false; }
function revoke_current_admin_token(array &$users): void { $raw=(string)($_COOKIE['bukovina_admin']??'');if($raw!==''){$hash=hash('sha256',$raw);foreach($users as &$user)$user['rememberTokens']=array_values(array_filter((array)($user['rememberTokens']??[]),fn($x)=>!hash_equals((string)($x['hash']??''),$hash)));unset($user);write_json(USER_FILE,$users);}setcookie('bukovina_admin','',admin_cookie_options(time()-3600)); }
function admin_required(): void { secure_session_start();$users=normalize_admin_users(read_json(USER_FILE));restore_admin_from_cookie($users);$index=admin_user_index($users,(string)($_SESSION['admin']??''));if($index===null){unset($_SESSION['admin']);header('Location: index.php');exit;}if(!empty($users[$index]['mustChangePassword'])&&basename((string)($_SERVER['SCRIPT_NAME']??''))!=='settings.php'){header('Location: settings.php?password=required');exit;} }
function manager_required(): void { admin_required();if(!admin_is_manager()){http_response_code(403);exit('Táto akcia je dostupná iba správcovi.');} }
function csrf_token(): string { secure_session_start(); return $_SESSION['csrf'] ??= token(24); }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="'.htmlspecialchars(csrf_token(),ENT_QUOTES,'UTF-8').'">'; }
function verify_csrf(): void { secure_session_start(); if(!hash_equals((string)($_SESSION['csrf']??''),(string)($_POST['csrf']??''))){http_response_code(419);exit('Platnosť formulára vypršala. Obnovte stránku a skúste to znova.');} }
function security_status(): array { ensure_storage(); return ['dataExists'=>is_dir(DATA_DIR),'dataWritable'=>is_writable(DATA_DIR),'projectsWritable'=>is_writable(PROJECT_DIR),'versionsWritable'=>is_writable(VERSION_DIR),'phpVersion'=>PHP_VERSION,'https'=>(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')]; }
