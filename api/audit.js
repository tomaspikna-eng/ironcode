const nodemailer = require('nodemailer');
const SUPABASE_URL = 'https://avjmuzrbkpchpwmpqqfa.supabase.co';
const escape = value => String(value).replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
function page(res,status,title,message){
  res.status(status).setHeader('Content-Type','text/html; charset=utf-8');
  return res.end('<!doctype html><html lang="sk"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>IronCode – formulár</title><style>body{font:18px/1.6 system-ui;background:#e2e8ea;color:#15232a;margin:0;padding:10vh 24px}main{max-width:680px;margin:auto;background:white;padding:40px}a{color:#087f93}</style></head><body><main><h1>'+escape(title)+'</h1><p>'+escape(message)+'</p><a href="/objednat-audit.html">Späť na formulár</a></main></body></html>');
}
async function db(path,method,key,payload){
 const r = await fetch(SUPABASE_URL+'/rest/v1/'+path,{method,headers:{apikey:key,Authorization:'Bearer '+key,'Content-Type':'application/json',Prefer:'return=representation'},body:JSON.stringify(payload),signal:AbortSignal.timeout(12000)});
 if(!r.ok)throw new Error('Supabase HTTP '+r.status);
 return r.json();
}
module.exports = async function handler(req,res){
 res.setHeader('Cache-Control','no-store');
 res.setHeader('X-Content-Type-Options','nosniff');
 if(req.method!=='POST'){res.setHeader('Allow','POST');return page(res,405,'Nesprávna požiadavka','Použite objednávkový formulár.');}
 const body=req.body||{};
 const who=String(body.who||'').trim(),what=String(body.what||'').trim(),email=String(body.email||'').trim(),phone=String(body.phone||'').trim();
 if(body.website)return page(res,200,'Ďakujeme','Požiadavka bola prijatá.');
 if(who.length<2||who.length>160||what.length<10||Array.from(what).length>2500||email.length>254||!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)||phone.length<6||phone.length>32||!/^[+0-9 ()-]+$/.test(phone)||body.privacy_ack!=='1')
   return page(res,422,'Neúplné údaje','Skontrolujte povinné polia formulára.');
 if(/[\r\n]/.test(who+email+phone))return page(res,422,'Neplatné údaje','Skontrolujte kontaktné údaje.');
 const key=process.env.IRONCODE_SUPABASE_SECRET_KEY;
 if(!key)return page(res,503,'Služba sa nastavuje','Napíšte nám na info@ironcode.site.');
 let id;
 try {
  const records=await db('audit_requests','POST',key,{requester:who,description:what,email,phone,notification_status:'pending'});
  id=records?.[0]?.id;
  if(!id)throw new Error('No insert ID');
 }catch(e){console.error('Audit DB insert:',e.message);return page(res,503,'Objednávku sa nepodarilo uložiť','Skúste to neskôr alebo napíšte na info@ironcode.site.');}
 let sent=false;
 if(process.env.IRONCODE_SMTP_HOST&&process.env.IRONCODE_SMTP_USER&&process.env.IRONCODE_SMTP_PASS){
  try {
   const transport=nodemailer.createTransport({host:process.env.IRONCODE_SMTP_HOST,port:Number(process.env.IRONCODE_SMTP_PORT||465),secure:Number(process.env.IRONCODE_SMTP_PORT||465)===465,auth:{user:process.env.IRONCODE_SMTP_USER,pass:process.env.IRONCODE_SMTP_PASS}});
   await transport.sendMail({from:process.env.IRONCODE_SMTP_USER,to:'info@ironcode.site',subject:'Objednávka',replyTo:email,text:'Nová objednávka auditu #'+id+'\nKTO: '+who+'\nE-MAIL: '+email+'\nMOBIL: '+phone+'\n\nČO:\n'+what});
   sent=true;
  }catch(e){console.error('Audit SMTP:',e.message);}
 }
 try{await db('audit_requests?id=eq.'+encodeURIComponent(String(id)),'PATCH',key,{notification_status:sent?'sent':'failed'});}catch(e){console.error('Audit status:',e.message);}
 return page(res,200,'Požiadavka bola uložená','Zadanie evidujeme a budeme vás kontaktovať.');
};
