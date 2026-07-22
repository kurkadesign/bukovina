<?php
declare(strict_types=1);
require_once __DIR__.'/../lib/storage.php';
admin_required();
ensure_storage();
$id=preg_replace('/[^a-zA-Z0-9_-]/','',(string)($_GET['id']??''));
$type=(string)($_GET['type']??'current');
$project=read_json(project_path($id));
if(!$id||!$project){http_response_code(404);exit('Projekt sa nenašiel.');}
$file=project_path($id);$downloadName=$id.'-aktualny.json';
if($type==='version'){
  $version=preg_replace('/[^0-9-]/','',(string)($_GET['version']??''));
  $candidate=VERSION_DIR.'/'.$id.'/'.$version.'.json';
  if(!$version||!is_file($candidate)){http_response_code(404);exit('Verzia sa nenašla.');}
  $file=$candidate;$downloadName=$id.'-'.$version.'.json';
}
header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$downloadName.'"');
header('Content-Length: '.filesize($file));
header('Cache-Control: no-store');
readfile($file);
