import { signRequest } from '../../../lib/edgenext.mjs';
const object=(v: unknown): v is Record<string,unknown> => !!v&&typeof v==='object'&&!Array.isArray(v);
export async function POST(request: Request) {
  const origin=request.headers.get('origin');
  if(origin!==new URL(request.url).origin) return Response.json({error:'Requests must come from this site.'},{status:403});
  if(Number(request.headers.get('content-length'))>131072) return Response.json({error:'Request exceeds 128 KB.'},{status:413});
  let input;
  try {const raw=await request.text();if(raw.length>131072) throw new Error('Request exceeds 128 KB.');input=JSON.parse(raw);} catch {return Response.json({error:'Use a valid JSON request under 128 KB.'},{status:400});}
  if(!object(input)||!['GET','POST','PUT','PATCH','DELETE'].includes(String(input.method))||typeof input.path!=='string'||!object(input.query)||!object(input.body)||! /^[A-Za-z0-9_./-]+$/.test(input.path)||input.path.includes('..')) return Response.json({error:'Invalid method, relative endpoint path, query, or body.'},{status:400});
  const bridge=process.env.EDGENEXT_PHP_BRIDGE_URL;
  const started=Date.now();
  try {
    let upstream: Response;
    if(bridge && process.env.EDGENEXT_PHP_BRIDGE_TOKEN) {
      const url=new URL(bridge);if(url.protocol!=='https:') throw new Error('The PHP bridge must use HTTPS.');
      upstream=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json',Authorization:'Bearer '+process.env.EDGENEXT_PHP_BRIDGE_TOKEN},body:JSON.stringify(input),redirect:'error',signal:AbortSignal.timeout(15000)});
      const text=await upstream.text();let data;try{data=JSON.parse(text);}catch{return Response.json({error:'The PHP bridge returned an invalid response.'},{status:502});}
      return Response.json(data,{status:upstream.ok?200:502,headers:{'Cache-Control':'no-store'}});
    }
    const baseUrl=process.env.SDK_API_PRE,appId=process.env.SDK_APP_ID,secret=process.env.SDK_APP_SECRET;
    if(!baseUrl||!appId||!secret) return Response.json({error:'Connection not configured. Add SDK_API_PRE, SDK_APP_ID, and SDK_APP_SECRET to the server environment, or connect the PHP SDK bridge.'},{status:503});
    const signed=await signRequest({baseUrl,appId,secret,method:String(input.method),path:input.path,query:input.query,body:input.body});
    // Fetch cannot send GET bodies. Send its signed empty-array hash explicitly.
    const headers: Record<string,string> = {...signed.headers};
    if(input.method==='GET') headers['X-Sdk-Content-Sha256']=Array.from(new Uint8Array(await crypto.subtle.digest('SHA-256',new TextEncoder().encode(signed.body))),b=>b.toString(16).padStart(2,'0')).join('');
    upstream=await fetch(signed.url,{method:String(input.method),headers,body:input.method==='GET'?undefined:signed.body,redirect:'error',signal:AbortSignal.timeout(15000)});
    const reader=upstream.body?.getReader();let text='';let bytes=0;const decoder=new TextDecoder();if(reader) {while(true){const {done,value}=await reader.read();if(done) break;bytes+=value.byteLength;if(bytes>2_000_000){await reader.cancel();throw new Error('Response exceeds 2 MB.');}text+=decoder.decode(value,{stream:true});}text+=decoder.decode();}
    let data;try{data=JSON.parse(text);}catch{data=text;}
    return Response.json({status:upstream.status,duration:Date.now()-started,data,contentType:upstream.headers.get('content-type')},{headers:{'Cache-Control':'no-store'}});
  } catch (error) {
    const message=error instanceof Error?error.message:'';
    return Response.json({error:message.includes('Response exceeds')?message:'Could not complete the EdgeNext request. Check the server connection settings and API availability.'},{status:502});
  }
}
