<?php
declare(strict_types=1);
require_once __DIR__.'/../lib/storage.php';
require_once __DIR__.'/../lib/email_templates.php';
require_once __DIR__.'/../lib/mailer.php';
admin_required();ensure_storage();enable_asset_versioning();
function h(string $v):string{return htmlspecialchars($v,ENT_QUOTES,'UTF-8');}

$user=current_admin_user();$userEmail=(string)($user['email']??($_SESSION['admin']??''));
$templates=default_email_templates();$signature=default_email_signature($userEmail);$startupError='';
try{$templates=load_email_templates($userEmail);$signature=load_email_signature($userEmail);}catch(Throwable $exception){$startupError='Uložené šablóny sa nepodarilo načítať. Zobrazujú sa predvolené hodnoty: '.$exception->getMessage();}
$selected=(string)($_GET['template']??array_key_first($templates));if(!isset($templates[$selected]))$selected=array_key_first($templates);
$message='';$error=$startupError;
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();$selected=(string)($_POST['templateKey']??'');$action=(string)($_POST['action']??'save');
 if(!isset($templates[$selected]))$error='Neznáma šablóna.';
 else{
  $signature=[
   'greeting'=>trim((string)($_POST['signatureGreeting']??'')),
   'name'=>trim((string)($_POST['signatureName']??'')),
   'position'=>trim((string)($_POST['signaturePosition']??'')),
   'phone'=>trim((string)($_POST['signaturePhone']??'')),
   'website'=>trim((string)($_POST['signatureWebsite']??'')),
  ];
  if($action==='reset'){
   try{$templates[$selected]=default_email_templates()[$selected];save_email_templates($templates,$signature,$userEmail);$message='Šablóna bola obnovená na pôvodný text.';}catch(Throwable $exception){$error='Šablónu sa nepodarilo uložiť: '.$exception->getMessage();}
  }else{
   $subject=trim((string)($_POST['subject']??''));$title=trim((string)($_POST['title']??''));$body=trim((string)($_POST['body']??''));$button=trim((string)($_POST['buttonLabel']??''));
   $signatureLength=array_sum(array_map('strlen',$signature));
   if($subject===''||$title===''||$body==='')$error='Predmet, nadpis a text sú povinné.';
   elseif(strlen($subject)>200||strlen($title)>160||strlen($body)>10000||strlen($button)>100||$signatureLength>1000)$error='Niektoré pole je príliš dlhé.';
   elseif($signature['website']!==''&&!filter_var($signature['website'],FILTER_VALIDATE_URL))$error='Webová URL v podpise nie je platná.';
   else{try{$templates[$selected]['subject']=$subject;$templates[$selected]['title']=$title;$templates[$selected]['body']=$body;$templates[$selected]['buttonLabel']=$button;save_email_templates($templates,$signature,$userEmail);$message='Šablóna a podpis boli uložené.';}catch(Throwable $exception){$error='Šablónu sa nepodarilo uložiť: '.$exception->getMessage();}}
  }
 }
}
$t=$templates[$selected];
$sample=['name'=>'Svadobný event','client'=>['name'=>'Ján Novák','email'=>'jan@example.sk'],'weddingDate'=>'2026-09-12','state'=>['guests'=>array_fill(0,86,[]),'items'=>array_fill(0,14,[])],'meta'=>['templateOwner'=>$userEmail]];
$preview=['subject'=>(string)$t['subject'],'title'=>(string)$t['title'],'body'=>render_template_body((string)$t['body'],email_template_variables($sample,['review_note'=>'Prosíme upraviť sedenie pri stole číslo 4.'])),'buttonLabel'=>(string)$t['buttonLabel'],'signature'=>''];
try{$preview=get_rendered_email_template($selected,$sample,['review_note'=>'Prosíme upraviť sedenie pri stole číslo 4.'],$userEmail);}catch(Throwable $exception){if($error==='')$error='Náhľad sa nepodarilo pripraviť: '.$exception->getMessage();}
$previewHtml=email_layout($preview['title'],$preview['body'],$preview['buttonLabel'],'https://example.sk/planovac',$preview['signature']);
$vars=['{{project_name}}','{{client_name}}','{{client_email}}','{{wedding_date}}','{{guest_count}}','{{item_count}}','{{review_note}}'];
?>
<!doctype html>
<html lang="sk">
<head>
 <meta charset="utf-8">
 <meta name="viewport" content="width=device-width,initial-scale=1">
 <link rel="stylesheet" href="../css/fontawesome.css">
 <link rel="stylesheet" href="../css/sharp-light.css">
 <link rel="stylesheet" href="style.css">
 <title>E-mailové šablóny – Bukovina Planner</title>
</head>
<body>
<header><b>Bukovina Planner</b><nav><a class="header-icon" href="settings.php" title="Nastavenia" aria-label="Nastavenia"><i class="fa-sharp fa-light fa-gear" aria-hidden="true"></i></a><a class="header-icon" href="index.php?logout=1" title="Odhlásiť sa" aria-label="Odhlásiť sa"><i class="fa-sharp fa-light fa-circle-xmark" aria-hidden="true"></i></a></nav></header>
<main>
 <a class="back" href="settings.php">← Späť na nastavenia</a>
 <section class="hero project-hero"><div><h1>E-mailové šablóny</h1><p>Každý používateľ má vlastné texty správ a vlastný podpis.</p></div></section>
 <?php if($message):?><div class="notice"><?=h($message)?></div><?php endif?>
 <?php if($error):?><div class="error"><?=h($error)?></div><?php endif?>
 <div class="template-layout">
  <aside class="card template-list"><h2>Správy</h2><?php foreach($templates as $key=>$item):?><a class="<?=$selected===$key?'active':''?>" href="?template=<?=h($key)?>"><?=h((string)$item['label'])?></a><?php endforeach?></aside>
  <section class="card">
   <h2><?=h((string)$t['label'])?></h2>
   <form method="post" class="form-grid single-column">
    <?=csrf_field()?><input type="hidden" name="templateKey" value="<?=h($selected)?>">
    <label>Predmet<input id="tplSubject" name="subject" maxlength="200" required value="<?=h((string)$t['subject'])?>"></label>
    <label>Nadpis e-mailu<input id="tplTitle" name="title" maxlength="160" required value="<?=h((string)$t['title'])?>"></label>
    <label>Text správy<textarea id="tplBody" name="body" rows="11" maxlength="10000" required><?=h((string)$t['body'])?></textarea></label>
    <label>Text tlačidla<input id="tplButton" name="buttonLabel" maxlength="100" value="<?=h((string)$t['buttonLabel'])?>"></label>
    <fieldset class="signature-fields"><legend>Podpis</legend>
     <label>Pozdrav<input id="signatureGreeting" name="signatureGreeting" maxlength="120" value="<?=h((string)$signature['greeting'])?>"></label>
     <label>Meno<input id="signatureName" name="signatureName" maxlength="160" value="<?=h((string)$signature['name'])?>"></label>
     <label>Pozícia<input id="signaturePosition" name="signaturePosition" maxlength="160" value="<?=h((string)$signature['position'])?>"></label>
     <label>Telefón<input id="signaturePhone" name="signaturePhone" maxlength="80" value="<?=h((string)$signature['phone'])?>"></label>
     <label>Webová URL<input id="signatureWebsite" type="url" name="signatureWebsite" maxlength="300" value="<?=h((string)$signature['website'])?>"></label>
     <small>Medzi pozdravom a kontaktnými údajmi sa automaticky zobrazí malé logo.</small>
    </fieldset>
    <div class="template-actions"><button name="action" value="save">Uložiť šablónu</button><button type="submit" name="action" value="reset" class="secondary" formnovalidate onclick="return confirm('Obnoviť pôvodný text tejto šablóny?')">Obnoviť pôvodnú</button></div>
   </form>
   <h3>Dostupné premenné</h3>
   <div class="variable-chips"><?php foreach($vars as $v):?><button type="button" class="variable-chip" data-variable="<?=h($v)?>"><?=h($v)?></button><?php endforeach?></div>
   <p class="muted">Premenná <code>{{review_note}}</code> sa používa pri schválení a vrátení na dopracovanie. Neznáme premenné zostanú v texte nezmenené.</p>
  </section>
  <section class="card preview-card"><h2>Náhľad</h2><div class="preview-subject"><small>Predmet</small><b id="previewSubject"><?=h($preview['subject'])?></b></div><iframe id="emailPreview" title="Náhľad e-mailu" sandbox srcdoc="<?=h($previewHtml)?>"></iframe></section>
 </div>
</main>
<script>
const sample={project_name:'Svadobný event',client_name:'Ján Novák',client_email:'jan@example.sk',wedding_date:'12.09.2026',guest_count:'86',item_count:'14',review_note:'Prosíme upraviť sedenie pri stole číslo 4.'};
const logoUrl=new URL('../assets/bukovina.png',location.href).href;
const render=s=>s.replace(/\{\{([a-z0-9_]+)\}\}/gi,(m,k)=>sample[k]??m);
const esc=s=>s.replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
function updatePreview(){
 const subject=render(document.getElementById('tplSubject').value),title=render(document.getElementById('tplTitle').value),body=render(document.getElementById('tplBody').value).trim().split(/\n\s*\n/).map(p=>'<p>'+esc(p).replace(/\n/g,'<br>')+'</p>').join(''),button=render(document.getElementById('tplButton').value);
 const greeting=esc(document.getElementById('signatureGreeting').value),name=esc(document.getElementById('signatureName').value),position=esc(document.getElementById('signaturePosition').value),phone=esc(document.getElementById('signaturePhone').value),website=esc(document.getElementById('signatureWebsite').value);
 const signature='<div style="margin-top:34px;color:#536767;font-size:13px;line-height:1.5">'+(greeting?'<p style="margin:0 0 12px">'+greeting+'</p>':'')+'<img src="'+esc(logoUrl)+'" alt="Bukovina" width="92" style="display:block;width:92px;height:auto;margin:0 0 12px">'+(name?'<b style="display:block;color:#243535">'+name+'</b>':'')+(position?'<span style="display:block">'+position+'</span>':'')+(phone?'<span style="display:block">'+phone+'</span>':'')+(website?'<span style="display:block;color:#527878">'+website+'</span>':'')+'</div>';
 document.getElementById('previewSubject').textContent=subject;
 document.getElementById('emailPreview').srcdoc='<!doctype html><html><body style="margin:0;background:#fff;font-family:Arial,sans-serif;color:#243535;text-align:left"><div style="max-width:620px;margin:0;background:#fff;padding:32px;text-align:left"><h1 style="margin:0 0 20px;font-size:25px;text-align:left">'+esc(title)+'</h1><div style="text-align:left">'+body+'</div>'+(button?'<p style="margin:28px 0"><span style="display:inline-block;background:#708e8e;color:#fff;padding:13px 20px;font-weight:700">'+esc(button)+'</span></p>':'')+signature+'</div></body></html>';
}
document.querySelectorAll('#tplSubject,#tplTitle,#tplBody,#tplButton,#signatureGreeting,#signatureName,#signaturePosition,#signaturePhone,#signatureWebsite').forEach(el=>el.addEventListener('input',updatePreview));
document.querySelectorAll('.variable-chip').forEach(btn=>btn.onclick=()=>{const field=document.activeElement?.matches('input,textarea')?document.activeElement:document.getElementById('tplBody');const start=field.selectionStart??field.value.length;field.setRangeText(btn.dataset.variable,start,field.selectionEnd??start,'end');field.focus();updatePreview()});
</script>
</body>
</html>
