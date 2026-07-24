<?php
declare(strict_types=1);
require_once __DIR__.'/../lib/storage.php';
require_once __DIR__.'/../lib/mailer.php';
admin_required();ensure_storage();enable_asset_versioning();
$users=normalize_admin_users(read_json(USER_FILE));$current=(string)$_SESSION['admin'];$currentIndex=admin_user_index($users,$current);$isManager=admin_is_manager();$mustChange=!empty($users[$currentIndex]['mustChangePassword']);$message='';$error='';$securityResult=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();$action=(string)($_POST['action']??'');
 if($action==='change-password'){
  $old=(string)($_POST['oldPassword']??'');$new=(string)($_POST['newPassword']??'');$confirm=(string)($_POST['confirmPassword']??'');
  if($currentIndex===null||!password_verify($old,(string)($users[$currentIndex]['passwordHash']??'')))$error='Aktuálne heslo nie je správne.';
  elseif(strlen($new)<12)$error='Nové heslo musí mať aspoň 12 znakov.';
  elseif($new!==$confirm)$error='Nové heslá sa nezhodujú.';
  else{$users[$currentIndex]['passwordHash']=password_hash($new,PASSWORD_DEFAULT);$users[$currentIndex]['passwordChangedAt']=gmdate('c');unset($users[$currentIndex]['mustChangePassword']);write_json(USER_FILE,$users);session_regenerate_id(true);if($mustChange){header('Location:index.php');exit;}$message='Heslo bolo zmenené.';}
 }elseif($mustChange){$error='Najprv si zmeňte dočasné heslo.';
 }elseif($action==='security-check'){
  if(!$isManager){http_response_code(403);exit('Táto akcia je dostupná iba správcovi.');}
  $securityResult=external_storage_security_check();
 }elseif($action==='add-user'){
  if(!$isManager){http_response_code(403);exit('Táto akcia je dostupná iba správcovi.');}
  $email=strtolower(trim((string)($_POST['email']??'')));$role=(string)($_POST['role']??'administrator');
  if(!filter_var($email,FILTER_VALIDATE_EMAIL))$error='Zadajte platný e-mail používateľa.';
  elseif(!in_array($role,['manager','administrator'],true))$error='Vyberte platnú rolu.';
  elseif(admin_user_index($users,$email)!==null)$error='Používateľ s týmto e-mailom už existuje.';
  elseif(!mail_configured())$error='Používateľa nemožno pridať, kým nie je nastavené SMTP odosielanie.';
  else{
   $password=generated_admin_numeric_password();$result=send_admin_user_invitation($email,$password,$role);
   if(!$result['ok'])$error='Pozvánku sa nepodarilo odoslať: '.($result['error']??'Neznáma chyba.');
   else{$users[]=['email'=>$email,'name'=>'','role'=>$role,'passwordHash'=>password_hash($password,PASSWORD_DEFAULT),'mustChangePassword'=>true,'createdAt'=>gmdate('c'),'invitedBy'=>$current];write_json(USER_FILE,$users);$message='Používateľ bol pridaný a dočasné heslo mu bolo odoslané e-mailom.';}
  }
 }elseif($action==='delete-user'){
  if(!$isManager){http_response_code(403);exit('Táto akcia je dostupná iba správcovi.');}
  $email=trim((string)($_POST['email']??''));$index=admin_user_index($users,$email);$managerCount=count(array_filter($users,fn($user)=>(string)($user['role']??'')==='manager'));
  if($index===null)$error='Používateľ sa nenašiel.';
  elseif(strcasecmp($email,$current)===0)$error='Nemôžete odstrániť vlastný účet.';
  elseif(($users[$index]['role']??'')==='manager'&&$managerCount<=1)$error='Posledného správcu nie je možné odstrániť.';
  else{array_splice($users,$index,1);write_json(USER_FILE,$users);$message='Používateľ bol odstránený.';}
 }
}
if(($_GET['password']??'')==='required'&&$message==='')$message='Pred pokračovaním si nastavte vlastné heslo.';
if(($_GET['restore']??'')==='ok')$message='Záloha bola obnovená. Importovaných projektov: '.(int)($_GET['projects']??0).'.';elseif(($_GET['restore']??'')==='upload-error')$error='Súbor zálohy sa nepodarilo nahrať.';elseif(($_GET['restore']??'')==='too-large')$error='Záloha je väčšia ako povolených 50 MB.';elseif(($_GET['restore']??'')==='error')$error=(string)($_GET['message']??'Zálohu sa nepodarilo obnoviť.');
function h(string $v):string{return htmlspecialchars($v,ENT_QUOTES,'UTF-8');}
?><!doctype html><html lang="sk"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="../css/fontawesome.css"><link rel="stylesheet" href="../css/sharp-light.css"><link rel="stylesheet" href="style.css"><title>Nastavenia – Bukovina Planner</title></head><body><header><b>Bukovina Planner</b><nav><a class="header-icon" href="settings.php" title="Nastavenia" aria-label="Nastavenia"><i class="fa-sharp fa-light fa-gear" aria-hidden="true"></i></a><a class="header-icon" href="index.php?logout=1" title="Odhlásiť sa" aria-label="Odhlásiť sa"><i class="fa-sharp fa-light fa-circle-xmark" aria-hidden="true"></i></a></nav></header><main><a class="back" href="index.php">← Späť na projekty</a><section class="hero project-hero"><div><h1>Nastavenia a bezpečnosť</h1><p><?=h($current)?> · <?=h(admin_role_label((string)($users[$currentIndex]['role']??'administrator')))?></p></div></section><?php if($message):?><div class="notice"><?=h($message)?></div><?php endif?><?php if($error):?><div class="error"><?=h($error)?></div><?php endif?>
<div class="detail-grid"><section class="card"><h2>Zmeniť heslo</h2><form method="post" class="form-grid single-column"><?=csrf_field()?><input type="hidden" name="action" value="change-password"><label>Aktuálne heslo<input type="password" name="oldPassword" required autocomplete="current-password"></label><label>Nové heslo<input type="password" name="newPassword" required minlength="12" autocomplete="new-password"></label><label>Zopakovať nové heslo<input type="password" name="confirmPassword" required minlength="12" autocomplete="new-password"></label><button>Zmeniť heslo</button></form></section><?php if(!$mustChange&&$isManager):?><section class="card user-management"><h2>Pridať používateľa</h2><p class="muted">Na zadaný e-mail sa odošle vygenerované dočasné heslo. Používateľ si ho po prvom prihlásení povinne zmení.</p><form method="post" class="form-grid single-column user-add-form"><?=csrf_field()?><input type="hidden" name="action" value="add-user"><label>E-mail<input type="email" name="email" required autocomplete="off"></label><label>Rola<select name="role"><option value="administrator">Administrátor</option><option value="manager">Správca</option></select></label><button>Pridať používateľa</button></form></section><?php endif?></div>
<?php if(!$mustChange):?>
<?php if($isManager):?><section class="card user-list"><h2>Zoznam používateľov</h2><div class="admin-users"><?php foreach($users as $user):$isSelf=strcasecmp((string)$user['email'],$current)===0;$lastManager=($user['role']??'')==='manager'&&count(array_filter($users,fn($item)=>($item['role']??'')==='manager'))<=1;?><div><span><b><?=h((string)$user['email'])?></b><small><?=h(admin_role_label((string)$user['role']))?><?=$isSelf?' · aktuálny účet':''?></small></span><?php if(!$isSelf&&!$lastManager):?><form method="post" onsubmit="return confirm('Odstrániť používateľa <?=h((string)$user['email'])?>?')"><?=csrf_field()?><input type="hidden" name="action" value="delete-user"><input type="hidden" name="email" value="<?=h((string)$user['email'])?>"><button class="danger outline">Vymazať</button></form><?php endif?></div><?php endforeach?></div></section><?php endif?>
<?php if($isManager):?><section class="card"><h2>E-mailové šablóny</h2><p class="muted">Upravte predmety a texty pozvánky, potvrdenia, schválenia aj vrátenia na dopracovanie. Zmeny sa použijú pri najbližšom odoslaní.</p><a class="button-link" href="email-templates.php">Spravovať e-mailové šablóny</a></section>
<section class="card backup-card"><h2>Záloha systému</h2><p class="muted">Záloha obsahuje všetky projekty, odoslané verzie, e-mailové šablóny a administrátorské účty. SMTP heslá v nej nie sú, pretože sa ukladajú v prostredí servera.</p><a class="button-link" href="backup.php?action=download">Stiahnuť kompletnú zálohu</a></section>
<section class="card restore-card"><h2>Obnoviť zo zálohy</h2><div class="warning-box">Pred obnovou sa automaticky uloží interná poistná kópia aktuálneho stavu. Systém uchová posledných 10 takýchto kópií.</div><form method="post" action="backup.php" enctype="multipart/form-data" class="form-grid single-column"><?=csrf_field()?><label>Súbor zálohy<input type="file" name="backup" accept="application/json,.json" required></label><label class="check-row"><input type="checkbox" name="replace" value="1"> Pred importom odstrániť existujúce projekty a verzie</label><label class="check-row"><input type="checkbox" name="restoreUsers" value="1"> Obnoviť aj administrátorské účty a heslá</label><button class="danger" onclick="return confirm('Naozaj chcete obnoviť dáta zo zálohy?')">Obnoviť zálohu</button></form></section>
<section class="card security-check-card"><h2>Overenie bezpečnosti zložiek</h2><p class="muted">Kontrola overí, či sú priečinky s používateľskými účtami a eventmi dostupné z internetu. Počas kontroly sa vytvoria dva dočasné neškodné súbory a systém sa ich pokúsi načítať cez verejnú URL webu.</p><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="security-check"><button>Vykonaj kontrolu</button></form><?php if($securityResult):?><div class="security-result <?=$securityResult['status']?>"><b><?=$securityResult['status']==='safe'?'Priečinky sú chránené':($securityResult['status']==='unsafe'?'Priečinky nie sú dostatočne chránené':'Kontrolu sa nepodarilo dokončiť')?></b><?php foreach($securityResult['targets'] as $target):?><span><?=h((string)$target['label'])?>: <?=h((string)$target['message'])?><?=$target['httpCode']?' (HTTP '.(int)$target['httpCode'].')':''?></span><?php endforeach?></div><?php endif?></section><?php endif?><?php endif?></main></body></html>
