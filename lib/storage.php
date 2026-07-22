<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
function ensure_storage(): void { foreach ([DATA_DIR, PROJECT_DIR, VERSION_DIR] as $d) if (!is_dir($d)) mkdir($d, 0775, true); }
function json_response(array $data, int $status=200): never { http_response_code($status); header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
function read_json(string $file, array $fallback=[]): array { if (!is_file($file)) return $fallback; $v=json_decode((string)file_get_contents($file), true); return is_array($v)?$v:$fallback; }
function write_json(string $file, array $data): void { ensure_storage(); $tmp=$file.'.tmp'; file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), LOCK_EX); rename($tmp,$file); }
function token(int $bytes=24): string { return bin2hex(random_bytes($bytes)); }
function project_path(string $id): string { return PROJECT_DIR.'/'.preg_replace('/[^a-zA-Z0-9_-]/','',$id).'.json'; }
function all_projects(): array { ensure_storage(); $out=[]; foreach (glob(PROJECT_DIR.'/*.json') ?: [] as $f) { $p=read_json($f); if ($p) $out[]=$p; } usort($out,fn($a,$b)=>strcmp($b['meta']['updatedAt']??'', $a['meta']['updatedAt']??'')); return $out; }
function find_project_by_token(string $value, string &$mode=''): ?array { foreach (all_projects() as $p) { if (hash_equals((string)($p['access']['editToken']??''),$value)) {$mode='edit';return $p;} if (($p['access']['shareEnabled']??false)&&hash_equals((string)($p['access']['shareToken']??''),$value)) {$mode='share';return $p;} } return null; }
function admin_required(): void { session_start(); if (empty($_SESSION['admin'])) { header('Location: index.php'); exit; } }
