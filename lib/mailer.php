<?php
declare(strict_types=1);
require_once __DIR__.'/../config.php';
require_once __DIR__.'/email_templates.php';
function mail_configured():bool{return SMTP_HOST!==''&&SMTP_USER!==''&&SMTP_PASSWORD!==''&&MAIL_FROM!=='';}
function smtp_read($s):string{$r='';while(($l=fgets($s,1024))!==false){$r.=$l;if(strlen($l)<4||$l[3]===' ')break;}if($r===''){$meta=stream_get_meta_data($s);if(!empty($meta['timed_out']))throw new RuntimeException('SMTP server neodpovedal v časovom limite. Skontrolujte host, port a typ šifrovania.');if(!empty($meta['eof']))throw new RuntimeException('SMTP server ukončil spojenie bez odpovede. Skontrolujte kombináciu portu a šifrovania.');throw new RuntimeException('SMTP server neposlal žiadnu odpoveď.');}return $r;}
function smtp_expect($s,array $c,string $stage='komunikácia'):string{$r=smtp_read($s);$n=(int)substr($r,0,3);if(!in_array($n,$c,true))throw new RuntimeException('SMTP chyba počas kroku '.$stage.': '.trim($r));return $r;}
function smtp_command($s,string $cmd,array $c,string $stage='príkaz'):string{if(fwrite($s,$cmd."\r\n")===false)throw new RuntimeException('SMTP príkaz sa nepodarilo odoslať počas kroku '.$stage.'.');return smtp_expect($s,$c,$stage);}
function send_app_mail(string $to,string $subject,string $html,string $text=''):array{
 if(!filter_var($to,FILTER_VALIDATE_EMAIL))return['ok'=>false,'error'=>'Neplatná e-mailová adresa.'];
 if(!mail_configured())return['ok'=>false,'error'=>'SMTP nie je nastavené.'];
 $transport=SMTP_ENCRYPTION==='ssl'?'ssl://':'';
 $s=@stream_socket_client($transport.SMTP_HOST.':'.SMTP_PORT,$errno,$errstr,15,STREAM_CLIENT_CONNECT);
 if(!$s)return['ok'=>false,'error'=>'SMTP spojenie zlyhalo: '.($errstr!==''?$errstr:'chyba '.$errno)];
 stream_set_timeout($s,15);
 try{
  smtp_expect($s,[220],'pripojenie');
  smtp_command($s,'EHLO '.($_SERVER['SERVER_NAME']??'localhost'),[250],'EHLO');
  if(SMTP_ENCRYPTION==='tls'){
   smtp_command($s,'STARTTLS',[220],'STARTTLS');
   if(!stream_socket_enable_crypto($s,true,STREAM_CRYPTO_METHOD_TLS_CLIENT))throw new RuntimeException('TLS spojenie sa nepodarilo aktivovať.');
   smtp_command($s,'EHLO '.($_SERVER['SERVER_NAME']??'localhost'),[250],'EHLO po STARTTLS');
  }
  smtp_command($s,'AUTH LOGIN',[334],'prihlásenie');
  smtp_command($s,base64_encode(SMTP_USER),[334],'používateľské meno');
  smtp_command($s,base64_encode(SMTP_PASSWORD),[235],'heslo aplikácie');
  smtp_command($s,'MAIL FROM:<'.MAIL_FROM.'>',[250],'odosielateľ');
  smtp_command($s,'RCPT TO:<'.$to.'>',[250,251],'príjemca');
  smtp_command($s,'DATA',[354],'začiatok správy');
  $b='b'.bin2hex(random_bytes(12));
  $related='r'.bin2hex(random_bytes(12));
  $plain=$text!==''?$text:trim(strip_tags(str_replace(['<br>','<br/>','<br />'],"\n",$html)));
  $logoPath=__DIR__.'/../assets/bukovina.png';
  $logoData=is_file($logoPath)?file_get_contents($logoPath):false;
  if($logoData!==false)$html=str_replace(htmlspecialchars(mail_public_url().'/assets/bukovina.png',ENT_QUOTES),'cid:bukovina-logo',$html);
  $headers=['From: '.MAIL_FROM_NAME.' <'.MAIL_FROM.'>','To: <'.$to.'>','Subject: =?UTF-8?B?'.base64_encode($subject).'?=','MIME-Version: 1.0','Content-Type: multipart/related; boundary="'.$related.'"','Date: '.date(DATE_RFC2822),'Message-ID: <'.bin2hex(random_bytes(12)).'@'.($_SERVER['SERVER_NAME']??'localhost').'>'];
  $alternative='--'.$b."\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n".$plain."\r\n".'--'.$b."\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n".$html."\r\n".'--'.$b."--\r\n";
  $body=implode("\r\n",$headers)."\r\n\r\n".'--'.$related."\r\nContent-Type: multipart/alternative; boundary=\"".$b."\"\r\n\r\n".$alternative;
  if($logoData!==false)$body.='--'.$related."\r\nContent-Type: image/png; name=\"bukovina.png\"\r\nContent-Transfer-Encoding: base64\r\nContent-ID: <bukovina-logo>\r\nContent-Disposition: inline; filename=\"bukovina.png\"\r\n\r\n".chunk_split(base64_encode($logoData),76,"\r\n");
  $body.='--'.$related."--\r\n";
  $payload=str_replace("\r\n.","\r\n..",$body).".\r\n";
  if(fwrite($s,$payload)===false)throw new RuntimeException('SMTP správu sa nepodarilo odoslať.');
  smtp_expect($s,[250],'odoslanie správy');
  smtp_command($s,'QUIT',[221],'ukončenie spojenia');
  fclose($s);
  return['ok'=>true];
 }catch(Throwable $e){fclose($s);return['ok'=>false,'error'=>$e->getMessage()];}
}
function mail_public_url():string{if(BASE_URL!=='')return rtrim(BASE_URL,'/');$scheme=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http';$host=$_SERVER['HTTP_HOST']??'localhost';$script=str_replace('\\','/',$_SERVER['SCRIPT_NAME']??'/admin/index.php');$root=rtrim(dirname(dirname($script)),'/');return $scheme.'://'.$host.($root===''?'':$root);}
function email_layout(string $title,string $content,string $buttonLabel='',string $buttonUrl=''):string{$button=$buttonUrl!==''&&$buttonLabel!==''?'<p style="margin:28px 0"><a href="'.htmlspecialchars($buttonUrl,ENT_QUOTES).'" style="display:inline-block;background:#708e8e;color:#fff;text-decoration:none;padding:13px 20px;border-radius:0;font-weight:700">'.htmlspecialchars($buttonLabel).'</a></p>':'';$logo=htmlspecialchars(mail_public_url().'/assets/bukovina.png',ENT_QUOTES);return '<!doctype html><html><body style="margin:0;background:#fff;font-family:Arial,sans-serif;color:#243535;text-align:left"><div style="max-width:620px;margin:0;background:#fff;padding:32px;text-align:left"><img src="'.$logo.'" alt="Bukovina" width="184" style="display:block;width:184px;height:auto;margin:0 0 30px"><h1 style="margin:0 0 20px;font-size:25px;text-align:left">'.htmlspecialchars($title).'</h1><div style="text-align:left">'.$content.'</div>'.$button.'<p style="margin-top:32px;color:#748383;font-size:13px;text-align:left">'.htmlspecialchars(MAIL_FROM_NAME).'</p></div></body></html>';}
function send_from_template(string $key,string $to,array $project,string $buttonUrl='',array $extra=[]):array{$t=get_rendered_email_template($key,$project,$extra);return send_app_mail($to,$t['subject'],email_layout($t['title'],$t['body'],$t['buttonLabel'],$buttonUrl));}
function send_client_invitation(array $p,string $url):array{return send_from_template('invitation',(string)($p['client']['email']??''),$p,$url);}
function send_submission_notifications(array $p,string $adminUrl=''):array{$r=[];if(ORGANIZER_EMAIL!=='')$r['organizer']=send_from_template('submission_organizer',ORGANIZER_EMAIL,$p,$adminUrl);$email=(string)($p['client']['email']??'');if($email!=='')$r['client']=send_from_template('submission_client',$email,$p);return$r;}
function send_review_decision(array $p,string $decision,string $note,string $editUrl):array{$email=(string)($p['client']['email']??'');if($email==='')return['ok'=>false,'error'=>'Klient nemá e-mail.'];$key=$decision==='approved'?'approved':'revision';return send_from_template($key,$email,$p,$decision==='approved'?'':$editUrl,['review_note'=>$note]);}
function send_admin_password_reset(string $email,string $password):array{$safe=htmlspecialchars($password,ENT_QUOTES,'UTF-8');$content='<p>Pre váš administrátorský účet bolo vygenerované nové dočasné heslo:</p><p style="font-size:22px;font-weight:700;letter-spacing:1px"><code>'.$safe.'</code></p><p>Dočasné heslo platí 12 hodín. Pôvodné heslo zostáva platné. Ak sa úspešne prihlásite pôvodným heslom, dočasné heslo sa okamžite zruší. Až keď sa prvýkrát prihlásite dočasným heslom, stane sa novým hlavným heslom a pôvodné prestane platiť.</p><p>Ak ste o obnovu nežiadali, tento e-mail môžete ignorovať.</p>';return send_app_mail($email,'Nové heslo do administrácie',email_layout('Obnovenie hesla',$content,'Otvoriť administráciu',mail_public_url().'/admin/'));}
function send_admin_user_invitation(string $email,string $password,string $role):array{$safe=htmlspecialchars($password,ENT_QUOTES,'UTF-8');$roleLabel=htmlspecialchars(admin_role_label($role),ENT_QUOTES,'UTF-8');$content='<p>Bol vám vytvorený účet do administrácie Bukovina Planner s rolou <strong>'.$roleLabel.'</strong>.</p><p>Vaše dočasné heslo:</p><p style="font-size:22px;font-weight:700;letter-spacing:1px"><code>'.$safe.'</code></p><p>Po prvom prihlásení si musíte nastaviť vlastné heslo.</p>';return send_app_mail($email,'Prístup do administrácie Bukovina Planner',email_layout('Nový administrátorský účet',$content,'Prihlásiť sa',mail_public_url().'/admin/'));}
