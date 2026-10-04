// SDK 2.0.0 signing compatibility: src/Request/Signer.php at bb4238e5.
const encoder = new TextEncoder();
const hex = buffer => Array.from(new Uint8Array(buffer), b=>b.toString(16).padStart(2,'0')).join('');
const escape = s => encodeURIComponent(String(s)).replace(/[!'()*]/g,c=>'%'+c.charCodeAt(0).toString(16).toUpperCase());
export function flattenQuery(value, prefix='', output=[]) {
  for(const [key,v] of Object.entries(value)) {
    const name=prefix?`${prefix}[${key}]`:key;
    if(v!==null && typeof v==='object') flattenQuery(v,name,output);
    else if(v!==null && v!==undefined) output.push([name,typeof v==='boolean'?(v?'1':'0'):String(v)]);
  } return output;
}
export async function signRequest({baseUrl,appId,secret,method,path,query={},body={},now=new Date(),userAgent='EdgeNext API Explorer / SDK 2.0.0 compatibility'}) {
  const base = new URL(baseUrl);
  if(base.protocol!=='https:' || base.username || base.password || base.search || base.hash) throw new Error('The configured API base URL must be HTTPS without credentials, query, or fragment.');
  if(!/^[A-Za-z0-9_./{}-]+$/.test(path)||path.startsWith('//')||path.includes('..')||path.includes('{')||path.includes('}')) throw new Error('Use a relative endpoint path with all path parameters filled.');
  const url=path.startsWith('/')?new URL(path,base.origin):new URL(base.href.replace(/\/$/,'')+'/'+path);
  const defaults={user_id:0,client_ip:'',client_userAgent:userAgent,fromadmin:0};
  const queryString=flattenQuery(method==='GET'?{...defaults,...query}:query).sort((a,b)=>a[0]<b[0]?-1:a[0]>b[0]?1:a[1]<b[1]?-1:a[1]>b[1]?1:0).map(([k,v])=>escape(k)+'='+escape(v)).join('&');
  // GET's empty PHP array is encoded as [] by signedRequest().
  const payload=method==='GET'?'[]':JSON.stringify({...defaults,...body}).replace(/[^\x00-\x7F]/g,c=>'\\u'+c.charCodeAt(0).toString(16).padStart(4,'0')).replace(/\//g,'\\/');
  const canonicalUri=url.pathname.split('/').map(v=>escape(decodeURIComponent(v))).join('/').replace(/\/?$/,'/');
  const date=now.toISOString().replace(/[-:]/g,'').replace(/\.\d{3}Z$/,'Z');
  const payloadHash=hex(await crypto.subtle.digest('SHA-256',encoder.encode(payload)));
  const canonical=`${method}\n${canonicalUri}\n${queryString}\n${payloadHash}`;
  const stringToSign=`SDK-HMAC-SHA256\n${date}\n${hex(await crypto.subtle.digest('SHA-256',encoder.encode(canonical)))}`;
  const key=await crypto.subtle.importKey('raw',encoder.encode(secret),{name:'HMAC',hash:'SHA-256'},false,['sign']);
  const signature=hex(await crypto.subtle.sign('HMAC',key,encoder.encode(stringToSign)));
  url.search=queryString;
  return {url:url.href,body:payload,headers:{'Content-Type':'application/json',format:'json',algorithm:'HMAC-SHA256',issued_at:String(Math.floor(now.getTime()/1000)),'X-Sdk-Date':date,'X-Auth-App-Id':appId,'X-Auth-Sdk-Version':'2.0.0','X-Auth-Sign':'Bearer '+signature},canonical};
}
