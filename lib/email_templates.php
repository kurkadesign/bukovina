<?php
declare(strict_types=1);
require_once __DIR__.'/storage.php';

function email_template_file(): string { return DATA_DIR.'/email-templates.json'; }
function default_email_templates(): array {
 return [
  'invitation'=>['label'=>'Pozvánka do plánovača','subject'=>'Plánovanie svadobnej sály – {{project_name}}','title'=>'Váš svadobný plánovač','body'=>"Dobrý deň, {{client_name}},\n\nna nasledujúcom odkaze môžete pripraviť rozloženie stolov a zasadací poriadok pre projekt {{project_name}}.\n\nZmeny sa ukladajú automaticky a k návrhu sa môžete kedykoľvek vrátiť.",'buttonLabel'=>'Otvoriť plánovač'],
  'submission_organizer'=>['label'=>'Návrh odoslaný organizátorovi','subject'=>'Odoslaný návrh – {{project_name}}','title'=>'Nový návrh bol odoslaný','body'=>"Klient {{client_name}} odoslal nový návrh projektu {{project_name}}.\n\nPočet hostí: {{guest_count}}\nPočet prvkov: {{item_count}}\nDátum svadby: {{wedding_date}}",'buttonLabel'=>'Otvoriť projekt'],
  'submission_client'=>['label'=>'Potvrdenie klientovi','subject'=>'Potvrdenie odoslania – {{project_name}}','title'=>'Návrh bol odoslaný','body'=>"Potvrdzujeme, že návrh projektu {{project_name}} bol úspešne odoslaný organizátorom.\n\nAk návrh neskôr upravíte, môžete odoslať jeho aktualizovanú verziu.",'buttonLabel'=>''],
  'approved'=>['label'=>'Schválenie návrhu','subject'=>'Návrh bol schválený – {{project_name}}','title'=>'Návrh bol schválený','body'=>"Váš návrh projektu {{project_name}} bol organizátorom schválený.\n\n{{review_note}}",'buttonLabel'=>''],
  'revision'=>['label'=>'Vrátenie na dopracovanie','subject'=>'Návrh je potrebné dopracovať – {{project_name}}','title'=>'Prosíme o dopracovanie návrhu','body'=>"Organizátor vrátil projekt {{project_name}} na dopracovanie.\n\n{{review_note}}",'buttonLabel'=>'Otvoriť plánovač'],
 ];
}
function default_email_signature(string $userEmail=''): array {
 $name='';$position='';$userEmail=strtolower(trim($userEmail));
 if($userEmail!==''){
  $users=normalize_admin_users(read_json(USER_FILE));$index=admin_user_index($users,$userEmail);
  if($index!==null){$name=trim((string)($users[$index]['name']??''));$position=admin_role_label((string)($users[$index]['role']??'administrator'));}
 }
 return['greeting'=>'S pozdravom','name'=>$name,'position'=>$position,'phone'=>'','website'=>BASE_URL];
}
function email_template_user_email(string $explicit=''): string {
 $explicit=strtolower(trim($explicit));if($explicit!=='')return$explicit;
 $user=current_admin_user();$email=strtolower(trim((string)($user['email']??'')));if($email!=='')return$email;
 $users=normalize_admin_users(read_json(USER_FILE));foreach($users as $candidate)if(($candidate['role']??'')==='manager')return strtolower((string)$candidate['email']);
 return strtolower((string)($users[0]['email']??''));
}
function load_email_template_store(): array {
 $saved=read_json(email_template_file());
 if(isset($saved['users'])&&is_array($saved['users']))return array_merge(['version'=>2,'legacyTemplates'=>[],'users'=>[]],$saved);
 $legacy=[];
 foreach(default_email_templates() as $key=>$tpl)if(isset($saved[$key])&&is_array($saved[$key]))$legacy[$key]=$saved[$key];
 return['version'=>2,'legacyTemplates'=>$legacy,'users'=>[]];
}
function save_email_template_store(array $store): void { $store['version']=2;write_json(email_template_file(),$store); }
function merge_email_templates(array $saved): array {
 $templates=default_email_templates();
 foreach($templates as $key=>$tpl)if(isset($saved[$key])&&is_array($saved[$key]))$templates[$key]=array_merge($tpl,array_intersect_key($saved[$key],$tpl));
 return$templates;
}
function load_email_templates(string $userEmail=''): array {
 $store=load_email_template_store();$userEmail=email_template_user_email($userEmail);$saved=(array)$store['legacyTemplates'];
 if($userEmail!==''&&isset($store['users'][$userEmail]['templates'])&&is_array($store['users'][$userEmail]['templates']))$saved=array_replace_recursive($saved,$store['users'][$userEmail]['templates']);
 return merge_email_templates($saved);
}
function load_email_signature(string $userEmail=''): array {
 $userEmail=email_template_user_email($userEmail);$defaults=default_email_signature($userEmail);$store=load_email_template_store();
 $saved=$userEmail!==''?(array)($store['users'][$userEmail]['signature']??[]):[];
 return array_merge($defaults,array_intersect_key($saved,$defaults));
}
function save_email_templates(array $templates,array $signature=[],string $userEmail=''): void {
 $userEmail=email_template_user_email($userEmail);if($userEmail==='')throw new RuntimeException('Používateľ šablóny nie je určený.');
 $store=load_email_template_store();$store['users'][$userEmail]=['templates'=>$templates,'signature'=>array_merge(default_email_signature($userEmail),array_intersect_key($signature,default_email_signature($userEmail)))];
 save_email_template_store($store);
}
function email_template_variables(array $project,array $extra=[]): array {
 unset($extra['_template_user']);
 return array_merge([
  'project_name'=>(string)($project['name']??''),'client_name'=>(string)($project['client']['name']??''),'client_email'=>(string)($project['client']['email']??''),'wedding_date'=>format_date_sk($project['weddingDate']??''),
  'guest_count'=>(string)count($project['state']['guests']??[]),'item_count'=>(string)count($project['state']['items']??[]),'review_note'=>'',
 ],$extra);
}
function render_template_text(string $text,array $vars): string {
 return preg_replace_callback('/\{\{([a-z0-9_]+)\}\}/i',fn($m)=>(string)($vars[$m[1]]??$m[0]),$text)??$text;
}
function render_template_body(string $text,array $vars): string {
 $rendered=trim(render_template_text($text,$vars));$paragraphs=preg_split('/\R{2,}/',$rendered)?:[];$html='';
 foreach($paragraphs as $p){$safe=nl2br(htmlspecialchars(trim($p),ENT_QUOTES,'UTF-8'));if($safe!=='')$html.='<p>'.$safe.'</p>';}
 return$html;
}
function render_email_signature(array $signature): string {
 $greeting=htmlspecialchars(trim((string)($signature['greeting']??'')),ENT_QUOTES,'UTF-8');
 $name=htmlspecialchars(trim((string)($signature['name']??'')),ENT_QUOTES,'UTF-8');
 $position=htmlspecialchars(trim((string)($signature['position']??'')),ENT_QUOTES,'UTF-8');
 $phone=htmlspecialchars(trim((string)($signature['phone']??'')),ENT_QUOTES,'UTF-8');
 $website=trim((string)($signature['website']??''));$websiteSafe=htmlspecialchars($website,ENT_QUOTES,'UTF-8');
 $logo=htmlspecialchars(mail_public_url().'/assets/bukovina.png',ENT_QUOTES,'UTF-8');
 $html='<div style="margin-top:34px;color:#536767;font-size:13px;line-height:1.5">';
 if($greeting!=='')$html.='<p style="margin:0 0 12px">'.$greeting.'</p>';
 $html.='<img src="'.$logo.'" alt="Bukovina" width="92" style="display:block;width:92px;height:auto;margin:0 0 12px">';
 if($name!=='')$html.='<b style="display:block;color:#243535">'.$name.'</b>';
 if($position!=='')$html.='<span style="display:block">'.$position.'</span>';
 if($phone!=='')$html.='<span style="display:block">'.$phone.'</span>';
 if($website!=='')$html.='<a href="'.$websiteSafe.'" style="display:block;color:#527878;text-decoration:none">'.$websiteSafe.'</a>';
 return$html.'</div>';
}
function get_rendered_email_template(string $key,array $project,array $extra=[],string $userEmail=''): array {
 $userEmail=email_template_user_email($userEmail!==''?$userEmail:(string)($extra['_template_user']??($project['meta']['templateOwner']??'')));
 $templates=load_email_templates($userEmail);$tpl=$templates[$key]??default_email_templates()[$key]??null;
 if(!$tpl)throw new InvalidArgumentException('Neznáma e-mailová šablóna.');
 $vars=email_template_variables($project,$extra);
 return['subject'=>render_template_text((string)$tpl['subject'],$vars),'title'=>render_template_text((string)$tpl['title'],$vars),'body'=>render_template_body((string)$tpl['body'],$vars),'buttonLabel'=>render_template_text((string)$tpl['buttonLabel'],$vars),'signature'=>render_email_signature(load_email_signature($userEmail))];
}
<?php
declare(strict_types=1);
require_once __DIR__.'/storage.php';

function email_template_file(): string { return DATA_DIR.'/email-templates.json'; }
function default_email_templates(): array {
 return [
  'invitation'=>['label'=>'Pozvánka do plánovača','subject'=>'Plánovanie svadobnej sály – {{project_name}}','title'=>'Váš svadobný plánovač','body'=>"Dobrý deň, {{client_name}},\n\nna nasledujúcom odkaze môžete pripraviť rozloženie stolov a zasadací poriadok pre projekt {{project_name}}.\n\nZmeny sa ukladajú automaticky a k návrhu sa môžete kedykoľvek vrátiť.",'buttonLabel'=>'Otvoriť plánovač'],
  'submission_organizer'=>['label'=>'Návrh odoslaný organizátorovi','subject'=>'Odoslaný návrh – {{project_name}}','title'=>'Nový návrh bol odoslaný','body'=>"Klient {{client_name}} odoslal nový návrh projektu {{project_name}}.\n\nPočet hostí: {{guest_count}}\nPočet prvkov: {{item_count}}\nDátum svadby: {{wedding_date}}",'buttonLabel'=>'Otvoriť projekt'],
  'submission_client'=>['label'=>'Potvrdenie klientovi','subject'=>'Potvrdenie odoslania – {{project_name}}','title'=>'Návrh bol odoslaný','body'=>"Potvrdzujeme, že návrh projektu {{project_name}} bol úspešne odoslaný organizátorom.\n\nAk návrh neskôr upravíte, môžete odoslať jeho aktualizovanú verziu.",'buttonLabel'=>''],
  'approved'=>['label'=>'Schválenie návrhu','subject'=>'Návrh bol schválený – {{project_name}}','title'=>'Návrh bol schválený','body'=>"Váš návrh projektu {{project_name}} bol organizátorom schválený.\n\n{{review_note}}",'buttonLabel'=>''],
  'revision'=>['label'=>'Vrátenie na dopracovanie','subject'=>'Návrh je potrebné dopracovať – {{project_name}}','title'=>'Prosíme o dopracovanie návrhu','body'=>"Organizátor vrátil projekt {{project_name}} na dopracovanie.\n\n{{review_note}}",'buttonLabel'=>'Otvoriť plánovač'],
 ];
}
function load_email_templates(): array {
 $defaults=default_email_templates();$saved=read_json(email_template_file());
 foreach($defaults as $key=>$tpl) if(isset($saved[$key])&&is_array($saved[$key])) $defaults[$key]=array_merge($tpl,array_intersect_key($saved[$key],$tpl));
 return $defaults;
}
function save_email_templates(array $templates): void { write_json(email_template_file(),$templates); }
function email_template_variables(array $project,array $extra=[]): array {
 return array_merge([
  'project_name'=>(string)($project['name']??''),'client_name'=>(string)($project['client']['name']??''),'client_email'=>(string)($project['client']['email']??''),'wedding_date'=>format_date_sk($project['weddingDate']??''),
  'guest_count'=>(string)count($project['state']['guests']??[]),'item_count'=>(string)count($project['state']['items']??[]),'review_note'=>'',
 ],$extra);
}
function render_template_text(string $text,array $vars): string {
 return preg_replace_callback('/\{\{([a-z0-9_]+)\}\}/i',fn($m)=>(string)($vars[$m[1]]??$m[0]),$text)??$text;
}
function render_template_body(string $text,array $vars): string {
 $rendered=trim(render_template_text($text,$vars));$paragraphs=preg_split('/\R{2,}/',$rendered)?:[];$html='';
 foreach($paragraphs as $p){$safe=nl2br(htmlspecialchars(trim($p),ENT_QUOTES,'UTF-8'));if($safe!=='')$html.='<p>'.$safe.'</p>';}
 return $html;
}
function get_rendered_email_template(string $key,array $project,array $extra=[]): array {
 $templates=load_email_templates();$tpl=$templates[$key]??default_email_templates()[$key]??null;if(!$tpl)throw new InvalidArgumentException('Neznáma e-mailová šablóna.');$vars=email_template_variables($project,$extra);
 return ['subject'=>render_template_text((string)$tpl['subject'],$vars),'title'=>render_template_text((string)$tpl['title'],$vars),'body'=>render_template_body((string)$tpl['body'],$vars),'buttonLabel'=>render_template_text((string)$tpl['buttonLabel'],$vars)];
}
