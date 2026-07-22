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
  'project_name'=>(string)($project['name']??''),'client_name'=>(string)($project['client']['name']??''),'client_email'=>(string)($project['client']['email']??''),'wedding_date'=>(string)($project['weddingDate']??''),
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
