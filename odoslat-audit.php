<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function finish(int $status,string $heading,string $message): never {
 http_response_code($status);
 echo '<!doctype html><html lang="sk"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>IronCode – formulár</title><style>body{font:18px/1.6 system-ui;background:#e2e8ea;color:#15232a;margin:0;padding:10vh 24px}main{max-width:680px;margin:auto;background:white;padding:40px}a{color:#087f93}</style></head><body><main><h1>'.htmlspecialchars($heading,ENT_QUOTES,'UTF-8').'</h1><p>'.htmlspecialchars($message,ENT_QUOTES,'UTF-8').'</p><a href="/objednat-audit.html">Späť na formulár</a></main></body></html>';
 exit;
}
if ($_SERVER['REQUEST_METHOD']!=='POST') {header('Allow: POST');finish(405,'Nesprávna požiadavka','Použite objednávkový formulár.');}
if (strlen(file_get_contents('php://input'))>18000) finish(413,'Príliš veľká požiadavka','Skráťte text objednávky.');
if (!empty($_POST['website'])) finish(200,'Ďakujeme','Požiadavka bola prijatá.');
$who=trim((string)($_POST['who']??''));
$what=trim((string)($_POST['what']??''));
$email=trim((string)($_POST['email']??''));
$phone=trim((string)($_POST['phone']??''));
$len=static function(string $s): int {return function_exists('mb_strlen')?mb_strlen($s,'UTF-8'):count(preg_split('//u',$s,-1,PREG_SPLIT_NO_EMPTY)?:[]);};
if ($len($who)<2||$len($who)>160||$len($what)<10||$len($what)>2500||$len($phone)<6||$len($phone)>32||!filter_var($email,FILTER_VALIDATE_EMAIL)||$len($email)>254||($_POST['privacy_ack']??'')!=='1') finish(422,'Neúplné údaje','Skontrolujte povinné polia a maximálnu dĺžku textu.');
if (preg_match('/[\r\n]/',$email.$phone.$who)||preg_match('/[^+0-9 ()\-]/u',$phone)) finish(422,'Neplatné údaje','Skontrolujte e-mail a telefón.');
// Configure the secret key ONLY on the server, never in GitHub or browser-side JavaScript.
$url='https://avjmuzrbkpchpwmpqqfa.supabase.co';
$key=getenv('IRONCODE_SUPABASE_SECRET_KEY')?:'';
$privateConfig=dirname(__DIR__).'/ironcode-private.php';
if (is_file($privateConfig)) {
 $cfg=require $privateConfig;
 if (is_array($cfg)) $key=(string)($cfg['supabase_secret_key']??$key);
}
if (!$key || !function_exists('curl_init')) {
 error_log('IronCode: Supabase server integration not configured');
 finish(503,'Služba sa nastavuje','Formulár momentálne nie je dostupný. Kontaktujte nás e-mailom na info@ironcode.site.');
}
function supabaseRequest(string $url,string $key,string $method,array $data): array {
 $curl=curl_init($url);
 curl_setopt_array($curl,[CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_POSTFIELDS=>json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),CURLOPT_HTTPHEADER=>['apikey: '.$key,'Authorization: Bearer '.$key,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>12,CURLOPT_CONNECTTIMEOUT=>5]);
 $response=curl_exec($curl);
 $status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);
 curl_close($curl);
 if ($response===false || $status<200 || $status>=300) throw new RuntimeException('Supabase request failed: HTTP '.$status);
 return json_decode((string)$response,true,512,JSON_THROW_ON_ERROR);
}
try {
 $records=supabaseRequest($url.'/rest/v1/audit_requests',$key,'POST',['requester'=>$who,'description'=>$what,'email'=>$email,'phone'=>$phone,'notification_status'=>'pending']);
 $id=(int)($records[0]['id']??0);
 if ($id<=0) throw new RuntimeException('Missing request ID');
} catch (Throwable $e) {
 error_log('IronCode audit insert: '.$e->getMessage());
 finish(503,'Nepodarilo sa odoslať','Skúste to neskôr alebo nám napíšte na info@ironcode.site.');
}
$recipient='info@ironcode.site';
$subject='=?UTF-8?B?'.base64_encode('Objednávka').'?=';
$body="Nová požiadavka na audit #$id\n\nKTO: $who\nEMAIL: $email\nMOBIL: $phone\n\nČO:\n$what\n";
$headers=['From: IronCode <no-reply@ironcode.site>','Content-Type: text/plain; charset=UTF-8','MIME-Version: 1.0'];
$sent=@mail($recipient,$subject,$body,implode("\r\n",$headers));
try {supabaseRequest($url.'/rest/v1/audit_requests?id=eq.'.$id,$key,'PATCH',['notification_status'=>$sent?'sent':'failed']);} catch (Throwable $e) {error_log('IronCode notification status failed: '.$e->getMessage());}
if (!$sent) error_log('IronCode audit notification failed for request #'.$id);
finish(200,'Požiadavka bola uložená','Ďakujeme. Vaše zadanie evidujeme a budeme vás kontaktovať.');
