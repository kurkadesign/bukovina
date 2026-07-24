<?php
declare(strict_types=1);
require_once __DIR__.'/../lib/storage.php';

ensure_storage();
secure_session_start();

$lockFile=DATA_DIR.'/install.lock';
$mailConfigFile=DATA_DIR.'/mail-config.json';
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
$step=(string)($_GET['step']??'account');
if($step==='smtp'&&empty($_SESSION['install_admin']))$step='account';
$error='';

if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();
 $action=(string)($_POST['action']??'');
 if($action==='account'){
  $email=trim((string)($_POST['email']??''));
  $password=(string)($_POST['password']??'');
  $confirm=(string)($_POST['confirmPassword']??'');
  if(!$requiredOk)$error='Server nespĺňa povinné požiadavky.';
  elseif(!filter_var($email,FILTER_VALIDATE_EMAIL))$error='Zadajte platný e-mail.';
  elseif(strlen($password)<8)$error='Heslo musí mať aspoň 8 znakov.';
  elseif($password!==$confirm)$error='Heslá sa nezhodujú.';
  else{
   $_SESSION['install_admin']=['email'=>$email,'passwordHash'=>password_hash($password,PASSWORD_DEFAULT)];
   header('Location:index.php?step=smtp');exit;
  }
 }elseif($action==='smtp'&&!empty($_SESSION['install_admin'])){
  $baseUrl=rtrim(trim((string)($_POST['baseUrl']??'')),'/');
  $smtpHost=trim((string)($_POST['smtpHost']??''));
  $smtpPort=(int)($_POST['smtpPort']??0);
  $smtpEncryption=strtolower((string)($_POST['smtpEncryption']??''));
  $smtpUser=trim((string)($_POST['smtpUser']??''));
  $smtpPassword=(string)($_POST['smtpPassword']??'');
  $mailFrom=trim((string)($_POST['mailFrom']??''));
  $mailFromName=trim((string)($_POST['mailFromName']??''));
  $organizerEmail=trim((string)($_POST['organizerEmail']??''));
  if($baseUrl===''||!filter_var($baseUrl,FILTER_VALIDATE_URL))$error='Zadajte platnú verejnú URL webu.';
  elseif($smtpHost==='')$error='Zadajte SMTP server.';
  elseif($smtpPort<1||$smtpPort>65535)$error='Zadajte platný SMTP port.';
  elseif(!in_array($smtpEncryption,['ssl','tls','none'],true))$error='Vyberte platný typ šifrovania.';
  elseif($smtpUser==='')$error='Zadajte SMTP používateľské meno.';
  elseif($smtpPassword==='')$error='Zadajte SMTP heslo.';
  elseif(!filter_var($mailFrom,FILTER_VALIDATE_EMAIL))$error='Zadajte platný e-mail odosielateľa.';
  elseif($mailFromName==='')$error='Zadajte názov odosielateľa.';
  elseif(!filter_var($organizerEmail,FILTER_VALIDATE_EMAIL))$error='Zadajte platný e-mail organizátora.';
  else{
   $admin=$_SESSION['install_admin'];
   $mailConfig=['baseUrl'=>$baseUrl,'mailFrom'=>$mailFrom,'mailFromName'=>$mailFromName,'organizerEmail'=>$organizerEmail,'smtpHost'=>$smtpHost,'smtpPort'=>$smtpPort,'smtpUser'=>$smtpUser,'smtpPassword'=>$smtpPassword,'smtpEncryption'=>$smtpEncryption];
   if(file_put_contents($mailConfigFile,json_encode($mailConfig,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX)===false)throw new RuntimeException('Nastavenie e-mailov sa nepodarilo uložiť.');
   write_json(USER_FILE,[['email'=>$admin['email'],'name'=>'','role'=>'manager','passwordHash'=>$admin['passwordHash'],'createdAt'=>gmdate('c')]]);
   if(file_put_contents($lockFile,json_encode(['installedAt'=>gmdate('c'),'php'=>PHP_VERSION],JSON_PRETTY_PRINT),LOCK_EX)===false){@unlink(USER_FILE);@unlink($mailConfigFile);throw new RuntimeException('Inštaláciu sa nepodarilo uzamknúť.');}
   $_SESSION['install_security_result']=external_storage_security_check($baseUrl);
   unset($_SESSION['install_admin']);
   session_regenerate_id(true);$_SESSION['admin']=$admin['email'];
   header('Location:../admin/');exit;
  }
 }
}
function h(string $v):string{return htmlspecialchars($v,ENT_QUOTES,'UTF-8');}
$defaultBaseUrl=((!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http').'://'.($_SERVER['HTTP_HOST']??'localhost').rtrim(dirname(dirname(str_replace('\\','/',$_SERVER['SCRIPT_NAME']??'/install/index.php'))),'/');
?>
<!doctype html>
<html lang="sk">
<head>
 <meta charset="utf-8">
 <meta name="viewport" content="width=device-width,initial-scale=1">
 <title>Inštalácia – Bukovina Planner</title>
 <link rel="stylesheet" href="../style.css">
</head>
<body class="install-page">
<main class="wrap">
 <div class="steps">Krok <?=$step==='smtp'?'2 z 2':'1 z 2'?></div>
 <h1>Bukovina Planner</h1>
 <?php if($step==='account'):?>
 <p>Prvotné nastavenie serverového systému.</p>
 <section class="checks">
  <div class="check"><span>PHP <?=h(PHP_VERSION)?></span><b class="<?=$checks['php']?'ok':'bad'?>"><?=$checks['php']?'Vyhovuje':'Potrebné 8.1+'?></b></div>
  <div class="check"><span>Priečinok data</span><b class="<?=$checks['data']?'ok':'bad'?>"><?=$checks['data']?'Zapisovateľný':'Bez zápisu'?></b></div>
  <div class="check"><span>Projekty a verzie</span><b class="<?=($checks['projects']&&$checks['versions'])?'ok':'bad'?>"><?=($checks['projects']&&$checks['versions'])?'Zapisovateľné':'Bez zápisu'?></b></div>
  <div class="check"><span>HTTPS</span><b class="<?=$checks['https']?'ok':'warn'?>"><?=$checks['https']?'Aktívne':'Odporúčané'?></b></div>
 </section>
 <?php if($error):?><div class="error"><?=h($error)?></div><?php endif?>
 <form method="post">
  <?=csrf_field()?><input type="hidden" name="action" value="account">
  <label class="full">Prihlasovací e-mail<input type="email" name="email" required autocomplete="username" value="<?=h((string)($_POST['email']??''))?>"></label>
  <label>Heslo<input type="password" name="password" minlength="8" required autocomplete="new-password"></label>
  <label>Zopakovať heslo<input type="password" name="confirmPassword" minlength="8" required autocomplete="new-password"></label>
  <button <?=$requiredOk?'':'disabled'?>>Ďalej</button>
 </form>
 <?php else:?>
 <h2>Nastavenie odosielania e-mailov</h2>
 <p>Zadajte údaje SMTP servera a adresy používané systémom.</p>
 <?php if($error):?><div class="error"><?=h($error)?></div><?php endif?>
 <form method="post">
  <?=csrf_field()?><input type="hidden" name="action" value="smtp">
  <label class="full">Verejná URL webu<input type="url" name="baseUrl" required value="<?=h((string)($_POST['baseUrl']??$defaultBaseUrl))?>"></label>
  <label>SMTP server<input name="smtpHost" required value="<?=h((string)($_POST['smtpHost']??'smtp.gmail.com'))?>"></label>
  <label>SMTP port<input type="number" name="smtpPort" min="1" max="65535" required value="<?=h((string)($_POST['smtpPort']??'465'))?>"></label>
  <label>Šifrovanie<select name="smtpEncryption"><option value="ssl" <?=(($_POST['smtpEncryption']??'ssl')==='ssl')?'selected':''?>>SSL</option><option value="tls" <?=(($_POST['smtpEncryption']??'')==='tls')?'selected':''?>>TLS</option><option value="none" <?=(($_POST['smtpEncryption']??'')==='none')?'selected':''?>>Bez šifrovania</option></select></label>
  <label>SMTP používateľ<input name="smtpUser" required autocomplete="username" value="<?=h((string)($_POST['smtpUser']??''))?>"></label>
  <label class="full">SMTP heslo<input type="text" name="smtpPassword" required autocomplete="off" value="<?=h((string)($_POST['smtpPassword']??''))?>"></label>
  <label>E-mail odosielateľa<input type="email" name="mailFrom" required value="<?=h((string)($_POST['mailFrom']??''))?>"><small>Tento e-mail sa používa na všeobecné odosielanie správ, napríklad notifikácií a obnovenia hesla.</small></label>
  <label>Názov odosielateľa<input name="mailFromName" required value="<?=h((string)($_POST['mailFromName']??'Svadobná sála'))?>"></label>
  <label class="full">E-mail organizátora<input type="email" name="organizerEmail" required value="<?=h((string)($_POST['organizerEmail']??''))?>"><small>Na tento e-mail budú prichádzať eventy odoslané klientmi na kontrolu.</small></label>
  <small class="full">Po dokončení sa nastavenie uloží do chráneného priečinka data a inštalátor sa automaticky uzamkne.</small>
  <button>Dokončiť inštaláciu</button>
 </form>
 <?php endif?>
</main>
</body>
</html>
