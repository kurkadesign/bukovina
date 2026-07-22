<?php
declare(strict_types=1);
require_once __DIR__.'/../lib/storage.php';
ensure_storage();
$method=$_SERVER['REQUEST_METHOD'];
$input=json_decode((string)file_get_contents('php://input'),true) ?: [];
$access=(string)($_GET['token'] ?? $input['token'] ?? '');
$action=(string)($_GET['action'] ?? $input['action'] ?? 'load');
$mode=''; $project=$access ? find_project_by_token($access,$mode) : null;
if (!$project) json_response(['ok'=>false,'error'=>'Neplatný alebo zrušený odkaz.'],404);
if ($action==='load') { $state=$project['state']??[]; if ($mode==='share') { foreach (($state['guests']??[]) as &$g) { if (!($project['share']['showDiet']??false)) { $g['allergies']=[]; unset($g['note']); } } } json_response(['ok'=>true,'mode'=>$mode,'project'=>['id'=>$project['id'],'name'=>$project['name'],'status'=>$project['status'],'state'=>$state,'updatedAt'=>$project['meta']['updatedAt']??null]]); }
if ($method!=='POST' || $mode!=='edit') json_response(['ok'=>false,'error'=>'Tento odkaz nemá právo zapisovať.'],403);
if ($action==='save') { $state=$input['state']??null; if (!is_array($state)||!isset($state['items'],$state['guests'],$state['wedding'])) json_response(['ok'=>false,'error'=>'Neplatný formát projektu.'],422); $project['state']=$state; $project['status']=$project['status']==='sent'?'changed-after-send':'editing'; $project['meta']['updatedAt']=gmdate('c'); write_json(project_path($project['id']),$project); json_response(['ok'=>true,'savedAt'=>$project['meta']['updatedAt']]); }
if ($action==='share') { $project['access']['shareEnabled']=true; if (empty($project['access']['shareToken'])) $project['access']['shareToken']=token(); $project['share']['showDiet']=(bool)($input['showDiet']??false); $project['meta']['updatedAt']=gmdate('c'); write_json(project_path($project['id']),$project); json_response(['ok'=>true,'shareToken'=>$project['access']['shareToken']]); }
if ($action==='submit') { $stamp=gmdate('Ymd-His'); $dir=VERSION_DIR.'/'.$project['id']; if(!is_dir($dir))mkdir($dir,0775,true); $project['state']=$input['state']??$project['state']; write_json($dir.'/'.$stamp.'.json',$project); $project['status']='sent'; $project['meta']['submittedAt']=gmdate('c'); $project['meta']['updatedAt']=$project['meta']['submittedAt']; write_json(project_path($project['id']),$project); json_response(['ok'=>true,'submittedAt'=>$project['meta']['submittedAt']]); }
json_response(['ok'=>false,'error'=>'Neznáma akcia.'],400);
