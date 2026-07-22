<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';

function mail_configured(): bool {
    return SMTP_HOST !== '' && SMTP_USER !== '' && SMTP_PASSWORD !== '' && MAIL_FROM !== '';
}

function smtp_read($socket): string {
    $response='';
    while (($line=fgets($socket, 1024)) !== false) {
        $response.=$line;
        if (strlen($line) < 4 || $line[3] === ' ') break;
    }
    return $response;
}

function smtp_expect($socket, array $codes): string {
    $response=smtp_read($socket);
    $code=(int)substr($response,0,3);
    if (!in_array($code,$codes,true)) throw new RuntimeException('SMTP chyba: '.trim($response));
    return $response;
}

function smtp_command($socket,string $command,array $codes): string {
    fwrite($socket,$command."\r\n");
    return smtp_expect($socket,$codes);
}

function send_app_mail(string $to,string $subject,string $html,string $text=''): array {
    if (!filter_var($to,FILTER_VALIDATE_EMAIL)) return ['ok'=>false,'error'=>'Neplatná e-mailová adresa.'];
    if (!mail_configured()) return ['ok'=>false,'error'=>'SMTP nie je nastavené.'];
    $transport=SMTP_ENCRYPTION==='ssl'?'ssl://':'';
    $socket=@stream_socket_client($transport.SMTP_HOST.':'.SMTP_PORT,$errno,$errstr,15,STREAM_CLIENT_CONNECT);
    if (!$socket) return ['ok'=>false,'error'=>'SMTP spojenie zlyhalo: '.$errstr];
    stream_set_timeout($socket,15);
    try {
        smtp_expect($socket,[220]);
        smtp_command($socket,'EHLO '.($_SERVER['SERVER_NAME']??'localhost'),[250]);
        if (SMTP_ENCRYPTION==='tls') {
            smtp_command($socket,'STARTTLS',[220]);
            if (!stream_socket_enable_crypto($socket,true,STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new RuntimeException('TLS spojenie sa nepodarilo aktivovať.');
            smtp_command($socket,'EHLO '.($_SERVER['SERVER_NAME']??'localhost'),[250]);
        }
        smtp_command($socket,'AUTH LOGIN',[334]);
        smtp_command($socket,base64_encode(SMTP_USER),[334]);
        smtp_command($socket,base64_encode(SMTP_PASSWORD),[235]);
        smtp_command($socket,'MAIL FROM:<'.MAIL_FROM.'>',[250]);
        smtp_command($socket,'RCPT TO:<'.$to.'>',[250,251]);
        smtp_command($socket,'DATA',[354]);
        $boundary='b'.bin2hex(random_bytes(12));
        $plain=$text!==''?$text:trim(strip_tags(str_replace(['<br>','<br/>','<br />'],"\n",$html)));
        $headers=[
            'From: '.MAIL_FROM_NAME.' <'.MAIL_FROM.'>',
            'To: <'.$to.'>',
            'Subject: =?UTF-8?B?'.base64_encode($subject).'?=',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="'.$boundary.'"',
            'Date: '.date(DATE_RFC2822),
            'Message-ID: <'.bin2hex(random_bytes(12)).'@'.($_SERVER['SERVER_NAME']??'localhost').'>'
        ];
        $body=implode("\r\n",$headers)."\r\n\r\n";
        $body.='--'.$boundary."\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n".$plain."\r\n";
        $body.='--'.$boundary."\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n".$html."\r\n";
        $body.='--'.$boundary."--\r\n.";
        fwrite($socket,str_replace("\r\n.","\r\n..",$body)."\r\n");
        smtp_expect($socket,[250]);
        smtp_command($socket,'QUIT',[221]);
        fclose($socket);
        return ['ok'=>true];
    } catch (Throwable $e) {
        fclose($socket);
        return ['ok'=>false,'error'=>$e->getMessage()];
    }
}

function email_layout(string $title,string $content,string $buttonLabel='',string $buttonUrl=''): string {
    $button=$buttonUrl!==''?'<p style="margin:28px 0"><a href="'.htmlspecialchars($buttonUrl,ENT_QUOTES).'" style="display:inline-block;background:#708e8e;color:#fff;text-decoration:none;padding:13px 20px;border-radius:10px;font-weight:700">'.htmlspecialchars($buttonLabel).'</a></p>':'';
    return '<!doctype html><html><body style="margin:0;background:#f4f7f7;font-family:Arial,sans-serif;color:#243535"><div style="max-width:620px;margin:30px auto;background:#fff;border-radius:16px;padding:32px"><h1 style="font-size:25px">'.htmlspecialchars($title).'</h1>'.$content.$button.'<p style="margin-top:32px;color:#748383;font-size:13px">'.htmlspecialchars(MAIL_FROM_NAME).'</p></div></body></html>';
}

function send_client_invitation(array $project,string $editUrl): array {
    $name=trim((string)($project['client']['name']??''));
    $greeting=$name!==''?'Dobrý deň, '.htmlspecialchars($name).',':'Dobrý deň,';
    $content='<p>'.$greeting.'</p><p>na nasledujúcom odkaze môžete pripraviť rozloženie stolov a zasadací poriadok pre projekt <b>'.htmlspecialchars((string)$project['name']).'</b>.</p><p>Zmeny sa ukladajú automaticky a k návrhu sa môžete kedykoľvek vrátiť.</p>';
    return send_app_mail((string)$project['client']['email'],'Plánovanie svadobnej sály – '.(string)$project['name'],email_layout('Váš svadobný plánovač',$content,'Otvoriť plánovač',$editUrl));
}

function send_submission_notifications(array $project,string $adminUrl=''): array {
    $results=[];
    if (ORGANIZER_EMAIL!=='') {
        $content='<p>Klient odoslal nový návrh projektu <b>'.htmlspecialchars((string)$project['name']).'</b>.</p><p>Počet hostí: '.count($project['state']['guests']??[]).'<br>Počet prvkov: '.count($project['state']['items']??[]).'</p>';
        $results['organizer']=send_app_mail(ORGANIZER_EMAIL,'Odoslaný návrh – '.(string)$project['name'],email_layout('Nový návrh bol odoslaný',$content,$adminUrl!==''?'Otvoriť projekt':'',$adminUrl));
    }
    $clientEmail=(string)($project['client']['email']??'');
    if ($clientEmail!=='') {
        $content='<p>Potvrdzujeme, že návrh projektu <b>'.htmlspecialchars((string)$project['name']).'</b> bol úspešne odoslaný organizátorom.</p><p>Ak návrh neskôr upravíte, môžete odoslať jeho aktualizovanú verziu.</p>';
        $results['client']=send_app_mail($clientEmail,'Potvrdenie odoslania – '.(string)$project['name'],email_layout('Návrh bol odoslaný',$content));
    }
    return $results;
}
