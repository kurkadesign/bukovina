<?php
declare(strict_types=1);
require_once __DIR__.'/storage.php';

function ensure_backup_storage(): void {
    if (!is_dir(BACKUP_DIR) && !mkdir(BACKUP_DIR, 0775, true) && !is_dir(BACKUP_DIR)) {
        throw new RuntimeException('Priečinok záloh sa nepodarilo vytvoriť.');
    }
    $protection = BACKUP_DIR.'/.htaccess';
    if (!is_file($protection)) {
        @file_put_contents($protection, "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n", LOCK_EX);
    }
}

function backup_metadata(): array {
    ensure_backup_storage();
    return array_merge([
        'version'=>1,
        'lastAutomaticAt'=>null,
        'lastAutomaticFile'=>null,
        'lastManualAt'=>null,
        'lastManualFile'=>null,
        'history'=>[],
    ], read_json(BACKUP_META_FILE, []));
}

function backup_files(): array {
    ensure_backup_storage();
    $files=glob(BACKUP_DIR.'/*.zip')?:[];
    usort($files, static fn(string $a,string $b):int => filemtime($b)<=>filemtime($a));
    return $files;
}

function prune_automatic_backups(): array {
    $files=array_values(array_filter(glob(BACKUP_DIR.'/*.zip')?:[],static fn(string $file):bool => preg_match('/^\d{8}-\d{6}\.zip$/',basename($file))===1));
    usort($files,static fn(string $a,string $b):int => filemtime($a)<=>filemtime($b));
    $deleted=[];
    while(count($files)>AUTOMATIC_BACKUP_RETENTION){
        $oldest=array_shift($files);
        if($oldest!==null&&is_file($oldest)&&unlink($oldest))$deleted[]=basename($oldest);
    }
    return$deleted;
}

function backup_add_directory(ZipArchive $zip,string $source,string $prefix='data'): void {
    $zip->addEmptyDir($prefix);
    $iterator=new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source,FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach($iterator as $entry){
        if($entry->isLink())continue;
        $relative=str_replace('\\','/',substr($entry->getPathname(),strlen($source)+1));
        $target=$prefix.'/'.$relative;
        if($entry->isDir())$zip->addEmptyDir($target);
        elseif($entry->isFile()&&!$zip->addFile($entry->getPathname(),$target))throw new RuntimeException('Do ZIP zálohy sa nepodarilo pridať súbor '.$relative.'.');
    }
}

function create_data_backup(bool $manual=false): array {
    if (!class_exists('ZipArchive')) throw new RuntimeException('Server nemá dostupné rozšírenie ZIP.');
    ensure_storage();ensure_backup_storage();
    $lock=fopen(BACKUP_DIR.'/.backup.lock','c+');
    if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Zálohu sa nepodarilo uzamknúť.');
    try{
        if(!$manual){
            $currentMeta=backup_metadata();$last=strtotime((string)($currentMeta['lastAutomaticAt']??''))?:0;
            $currentFile=basename((string)($currentMeta['lastAutomaticFile']??''));
            if($last>0&&time()-$last<AUTOMATIC_BACKUP_INTERVAL_DAYS*86400&&$currentFile!==''&&is_file(BACKUP_DIR.'/'.$currentFile)){
                return['file'=>$currentFile,'path'=>BACKUP_DIR.'/'.$currentFile,'createdAt'=>$currentMeta['lastAutomaticAt'],'manual'=>false];
            }
        }
        $stamp=(new DateTimeImmutable('now',new DateTimeZone('Europe/Bratislava')))->format('dmY-His');
        $name=$stamp.($manual?'-man':'').'.zip';
        $path=BACKUP_DIR.'/'.$name;
        $tmp=$path.'.tmp';
        $zip=new ZipArchive();
        if($zip->open($tmp,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new RuntimeException('ZIP zálohu sa nepodarilo vytvoriť.');
        backup_add_directory($zip,DATA_DIR);
        if(!$zip->close())throw new RuntimeException('ZIP zálohu sa nepodarilo dokončiť.');
        if(!rename($tmp,$path))throw new RuntimeException('ZIP zálohu sa nepodarilo uložiť.');
        $meta=backup_metadata();$now=gmdate('c');
        if($manual){$meta['lastManualAt']=$now;$meta['lastManualFile']=$name;}
        else{$meta['lastAutomaticAt']=$now;$meta['lastAutomaticFile']=$name;}
        $meta['history'][]=['file'=>$name,'type'=>$manual?'manual':'automatic','createdAt'=>$now,'size'=>(int)filesize($path)];
        if(!$manual){
            $deleted=prune_automatic_backups();
            if($deleted)$meta['history']=array_values(array_filter($meta['history'],static fn(array $item):bool => !in_array((string)($item['file']??''),$deleted,true)));
        }
        $meta['history']=array_slice($meta['history'],-250);
        write_json(BACKUP_META_FILE,$meta);
        return['file'=>$name,'path'=>$path,'createdAt'=>$now,'manual'=>$manual];
    }finally{
        if(is_resource($lock)){flock($lock,LOCK_UN);fclose($lock);}
    }
}

function maybe_create_automatic_backup(): void {
    try{
        $meta=backup_metadata();$last=strtotime((string)($meta['lastAutomaticAt']??''))?:0;
        if($last>0&&time()-$last<AUTOMATIC_BACKUP_INTERVAL_DAYS*86400)return;
        create_data_backup(false);
    }catch(Throwable $error){
        error_log('Automatická záloha zlyhala: '.$error->getMessage());
    }
}

function remove_tree(string $path): void {
    if(!is_dir($path))return;
    $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($iterator as $entry){if($entry->isDir()&&!$entry->isLink())@rmdir($entry->getPathname());else@unlink($entry->getPathname());}
    @rmdir($path);
}

function validate_backup_zip(ZipArchive $zip): void {
    $hasData=false;
    for($i=0;$i<$zip->numFiles;$i++){
        $name=str_replace('\\','/',$zip->getNameIndex($i));
        if($name===''||str_starts_with($name,'/')||str_contains($name,'../')||!str_starts_with($name,'data/'))throw new RuntimeException('ZIP obsahuje neplatnú cestu.');
        if(preg_match('/\.(?:php\d*|phtml|phar|cgi|pl|html?|js)$/i',$name))throw new RuntimeException('ZIP obsahuje nepovolený spustiteľný súbor.');
        if($name!=='data/')$hasData=true;
    }
    if(!$hasData)throw new RuntimeException('ZIP neobsahuje dáta na obnovenie.');
}

function restore_data_backup(string $zipPath): void {
    if(!class_exists('ZipArchive'))throw new RuntimeException('Server nemá dostupné rozšírenie ZIP.');
    ensure_backup_storage();
    $zip=new ZipArchive();
    if($zip->open($zipPath)!==true)throw new RuntimeException('ZIP zálohu sa nepodarilo otvoriť.');
    validate_backup_zip($zip);
    $restoreDir=BACKUP_DIR.'/restore-'.token(8);
    if(!mkdir($restoreDir,0775,true))throw new RuntimeException('Dočasný priečinok obnovy sa nepodarilo vytvoriť.');
    try{
        if(!$zip->extractTo($restoreDir))throw new RuntimeException('ZIP zálohu sa nepodarilo rozbaliť.');
    }finally{$zip->close();}
    $restoredData=$restoreDir.'/data';
    if(!is_dir($restoredData)){remove_tree($restoreDir);throw new RuntimeException('V ZIP zálohe chýba priečinok data.');}
    create_data_backup(true);
    $oldData=BACKUP_DIR.'/old-data-'.token(8);
    if(!rename(DATA_DIR,$oldData)){remove_tree($restoreDir);throw new RuntimeException('Aktuálne dáta sa nepodarilo pripraviť na obnovu.');}
    try{
        if(!rename($restoredData,DATA_DIR))throw new RuntimeException('Obnovené dáta sa nepodarilo aktivovať.');
        remove_tree($oldData);
        remove_tree($restoreDir);
        ensure_storage();
    }catch(Throwable $error){
        if(!is_dir(DATA_DIR)&&is_dir($oldData))@rename($oldData,DATA_DIR);
        remove_tree($restoreDir);
        throw $error;
    }
}
