<?php
declare(strict_types=1);
require_once __DIR__.'/../lib/storage.php';

ensure_storage();
secure_session_start();

$lockFile=DATA_DIR.'/install.lock';
$users=read_json(USER_FILE);
if(is_file($lockFile)||$users){header('Location:../admin/');exit;}

$checks=[
 'php'=>version_compare(PHP_VERSION,'8.1.0','>='),
 'data'=>is_dir(DATA_DIR)&&is_writable(DATA_DIR),
 'projects'=>is_dir(PROJECT_DIR)&&is_writable(PROJECT_DIR),
 'versions'=>is_dir(VERSION_DIR)&&is_writable(VERSION_DIR),
 'https'=>(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'),
];
$requiredOk=$checks['php']&&$checks['data']&&$checks['projects']&&$checks['versions'];
$error='';

if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();
 $name=trim((string)($_POST['name']??''));
 $email=trim((string)($_POST['email']??''));
 $password=(string)($_POST['password']??'');
 $confirm=(string)($_POST['confirmPassword']??'');
 if(!$requiredOk)$error='Server nespĺňa povinné požiadavky.';
 elseif($name==='')$error='Zadajte meno správcu.';
 elseif(!filter_var($email,FILTER_VALIDATE_EMAIL))$error='Zadajte platný e-mail.';
 elseif(strlen($password)<12)$error='Heslo musí mať aspoň 12 znakov.';
 elseif($password!==$confirm)$error='Heslá sa nezhodujú.';
 else{
  write_json(USER_FILE,[['email'=>$email,'name'=>$name,'role'=>'manager','passwordHash'=>password_hash($password,PASSWORD_DEFAULT),'createdAt'=>gmdate('c')]]);
  if(file_put_contents($lockFile,json_encode(['installedAt'=>gmdate('c'),'php'=>PHP_VERSION],JSON_PRETTY_PRINT),LOCK_EX)===false){@unlink(USER_FILE);throw new RuntimeException('Inštaláciu sa nepodarilo uzamknúť.');}
  session_regenerate_id(true);$_SESSION['admin']=$email;
  header('Location:../admin/');exit;
 }
}
function h(string $v):string{return htmlspecialchars($v,ENT_QUOTES,'UTF-8');}
?><!doctype html><html lang="sk"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Inštalácia – Bukovina Planner</title><style>*{box-sizing:border-box}body{margin:0;background:#f5f7f7;color:#243535;font:15px system-ui;min-height:100vh;display:grid;place-items:center;padding:24px}.wrap{width:min(760px,100%);background:#fff;border:1px solid #dce5e5;border-radius:22px;padding:30px;box-shadow:0 18px 60px #20353518}h1{margin:0 0 6px;font-size:32px}p{color:#718181}.checks{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:24px 0}.check{display:flex;justify-content:space-between;padding:12px;border-radius:10px;background:#f5f8f8}.ok{color:#2e7d4f;font-weight:700}.bad{color:#a33b3b;font-weight:700}.warn{color:#9a6a1d;font-weight:700}form{display:grid;grid-template-columns:1fr 1fr;gap:14px}label{display:grid;gap:6px;font-weight:650}.full{grid-column:1/-1}input{padding:12px;border:1px solid #ccd8d8;border-radius:9px;font:inherit}button{grid-column:1/-1;justify-self:start;border:0;border-radius:10px;padding:12px 18px;background:#708e8e;color:#fff;font-weight:700;cursor:pointer}.error{padding:13px;border-radius:10px;background:#f7e5e5;color:#7c3434;margin:15px 0}small{color:#7a8888}@media(max-width:650px){.checks,form{grid-template-columns:1fr}.full{grid-column:auto}}</style></head><body><main class="wrap"><h1>Bukovina Planner</h1><p>Prvotné nastavenie serverového systému.</p><section class="checks"><div class="check"><span>PHP <?=h(PHP_VERSION)?></span><b class="<?=$checks['php']?'ok':'bad'?>"><?=$checks['php']?'Vyhovuje':'Potrebné 8.1+'?></b></div><div class="check"><span>Priečinok data</span><b class="<?=$checks['data']?'ok':'bad'?>"><?=$checks['data']?'Zapisovateľný':'Bez zápisu'?></b></div><div class="check"><span>Projekty a verzie</span><b class="<?=($checks['projects']&&$checks['versions'])?'ok':'bad'?>"><?=($checks['projects']&&$checks['versions'])?'Zapisovateľné':'Bez zápisu'?></b></div><div class="check"><span>HTTPS</span><b class="<?=$checks['https']?'ok':'warn'?>"><?=$checks['https']?'Aktívne':'Odporúčané'?></b></div></section><?php if($error):?><div class="error"><?=h($error)?></div><?php endif?><form method="post"><?=csrf_field()?><label class="full">Meno správcu<input name="name" required autocomplete="name"></label><label class="full">Prihlasovací e-mail<input type="email" name="email" required autocomplete="username"></label><label>Heslo<input type="password" name="password" minlength="12" required autocomplete="new-password"></label><label>Zopakovať heslo<input type="password" name="confirmPassword" minlength="12" required autocomplete="new-password"></label><small class="full">Po dokončení sa vytvorí súbor <code>data/install.lock</code> a inštalátor sa automaticky uzamkne.</small><button <?=$requiredOk?'':'disabled'?>>Dokončiť inštaláciu</button></form></main></body></html>
