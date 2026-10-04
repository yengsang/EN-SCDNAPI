import officialCatalog from './edgenext-catalog.json';
export type Field = { name: string; label?: string; type: string; required?: boolean; description?: string; example?: unknown; enum?: unknown[]; location: 'query' | 'body' | 'path'; schema?: Record<string, unknown> };
export type Endpoint = { id: string; category: string; name: string; path: string; method: string; description: string; fields: Field[]; source: string; example?: boolean; bodyRequired?: boolean };
const source = 'https://github.com/edgenextapisdk/edgenext-php/blob/main/tests/FirewallTest.php';
export const sdkExamples: Endpoint[] = [
  { id: 'policy-group', category: 'Firewall / Rule sets', name: 'Save a rule set', path: 'firewall.policyGroup.save', method: 'POST', description: 'Create a precise access-control rule set. Parameters are taken from the SDK example; the example does not define formal requiredness.', source, example: true, fields: [
    {name:'name',label:'Rule set name',type:'string',description:'Name of the rule set.',example:'example',location:'body'},
    {name:'remark',label:'Remark',type:'string',description:'A note about this rule set.',location:'body'},
    {name:'from',label:'Rule set source',type:'string',enum:['diy','system','quote'],example:'diy',description:'Custom, system, or referenced rule set.',location:'body'},
    {name:'domain_id',label:'Domain ID',type:'integer',description:'Your EdgeNext domain ID.',location:'body'}]},
  { id: 'policy', category: 'Firewall / Policies', name: 'Save a firewall policy', path: 'firewall.policy.save', method: 'POST', description: 'Create a policy, or edit one by supplying its ID. Nested action settings and rules accept JSON. Requiredness is not specified in the SDK example.', source, example: true, fields: [
    {name:'id',label:'Policy ID',type:'integer',description:'Supply this only when editing an existing policy.',location:'body'},
    {name:'group_id',label:'Rule set ID',type:'integer',description:'ID returned when the rule set was created.',location:'body'},
    {name:'domain_id',label:'Domain ID',type:'integer',description:'The domain this policy applies to.',location:'body'},
    {name:'remark',label:'Remark',type:'string',location:'body'},
    {name:'type',label:'Policy type',type:'string',example:'plus',description:'The SDK example uses plus for SCDN domains.',location:'body'},
    {name:'action',label:'Action',type:'string',enum:['block','verification'],description:'Actions demonstrated by the SDK; other actions may be supported.',example:'block',location:'body'},
    {name:'action_data',label:'Action settings',type:'object',example:{time_unit:'minute',interval:10},description:'For block: time_unit and interval. Verification settings differ.',location:'body'},
    {name:'rules',label:'Rules',type:'array',example:[{rule_type:'url',logic:'contains',data:['/login']},{rule_type:'ip_url_rate_limit',logic:'greater_than',data:{interval:10,reqs:25}}],description:'An array of rule_type, logic, and data objects.',location:'body'}]},
  ...['GET','POST','PUT','PATCH','DELETE'].map(method => ({id:'sdk-'+method.toLowerCase(),category:'SDK / Test examples',name:method+' request example',path:'test.sdk.'+method.toLowerCase(),method,description:'A demonstration endpoint from the SDK README. Availability on your production API is not guaranteed.',source:'https://github.com/edgenextapisdk/edgenext-php#making-requests',example:true,fields:method==='DELETE'?[]:[{name:'page',label:'Page',type:'integer',example:1,location:method==='GET'?'query':'body'},{name:'pagesize',label:'Page size',type:'integer',example:10,location:method==='GET'?'query':'body'},...(['GET','POST'].includes(method)?[{name:'data',label:'Data',type:'object',example:{name:'name',domain:'baidu.com'},location:method==='GET'?'query':'body'}]:[])]} as Endpoint)),
];

function isObject(value: unknown): value is Record<string, any> { return !!value && typeof value==='object' && !Array.isArray(value); }
export function importCatalog(input: unknown): Endpoint[] {
  if (!isObject(input)) throw new Error('Choose an OpenAPI JSON file or a catalog object.');
  if (Array.isArray(input.endpoints)) {
    if (!input.endpoints.length) throw new Error('The catalog contains no endpoints.');
    return input.endpoints.map((e: any,i: number) => {
      if (!isObject(e) || typeof e.path!=='string' || !/^(GET|POST|PUT|PATCH|DELETE)$/.test(e.method) || !Array.isArray(e.fields)) throw new Error('Each endpoint needs path, method, and a fields array.');
      const fields = e.fields.map((f: any) => { if (!isObject(f)||typeof f.name!=='string'||!['query','body','path'].includes(f.location)||!['string','integer','number','boolean','object','array'].includes(f.type)) throw new Error('Each field needs a name, supported type, and location.'); return f as Field; });
      return {...e,fields,id:'import-'+i,category:e.category||'Imported API',name:e.name||e.path,description:e.description||'',source:e.source||'Imported catalog'} as Endpoint;
    });
  }
  if (!input.openapi?.startsWith('3.') || !isObject(input.paths)) throw new Error('Supported formats: OpenAPI 3.x JSON, or { "endpoints": [...] }.');
  const resolve = (item: any,seen=new Set<string>()): any => {
    if (!item?.$ref) return item;
    if (!item.$ref.startsWith('#/')) throw new Error('External schema references must be bundled into the JSON file.');
    if(seen.has(item.$ref)) throw new Error('Circular schema references must be simplified before importing.');
    seen.add(item.$ref);
    const value = item.$ref.slice(2).split('/').reduce((o:any,k:string)=>o?.[k.replace(/~1/g,'/').replace(/~0/g,'~')],input);
    if(!value) throw new Error('Unresolved schema reference: '+item.$ref);
    return resolve(value,seen);
  };
  const result: Endpoint[]=[];
  for(const [path,rawItem] of Object.entries(input.paths)) {
    const item=resolve(rawItem);
    for(const method of ['get','post','put','patch','delete']) {
      const op=resolve(item[method]); if(!op) continue;
      const fields: Field[]=[];
      const params=new Map<string,any>();
      for(const p0 of [...(item.parameters||[]),...(op.parameters||[])]) {const p=resolve(p0);params.set(p.in+':'+p.name,p);}
      for(const p of params.values()) {
        if(!['query','path'].includes(p.in)) throw new Error('This importer supports query/path parameters and JSON bodies. '+method.toUpperCase()+' '+path+' also uses '+p.in+' parameters.');
        if(p.style && !['form','simple'].includes(p.style)) throw new Error('Unsupported parameter serialization style: '+p.style);
        if(p.explode===false) throw new Error('Non-exploded parameters need an explicit catalog adapter.');
        const s=resolve(p.schema)||{};
        fields.push({name:p.name,label:p.name,type:s.type||'string',required:!!p.required,location:p.in,description:p.description||s.description,example:p.example??s.example??s.default,enum:s.enum,schema:s});
      }
      const rb=resolve(op.requestBody); const media=rb?.content?.['application/json'];
      if(rb && !media) throw new Error('Only JSON request bodies are supported by this importer.');
      if(media) {
        const schema=resolve(media.schema)||{};
        if(schema.allOf||schema.oneOf||schema.anyOf) throw new Error('Combine composed request schemas into object properties before importing.');
        if(schema.properties) for(const [name,raw] of Object.entries(schema.properties)) {
          const s=resolve(raw);fields.push({name,label:name,type:s.type||'string',required:!!schema.required?.includes(name),location:'body',description:s.description,example:s.example??s.default,enum:s.enum,schema:s});
        } else throw new Error('Request bodies must define object properties.');
      }
      result.push({id:'openapi-'+result.length,category:op.tags?.[0]||'General',name:op.summary||op.operationId||path,path,method:method.toUpperCase(),description:op.description||'',fields,source:input.info?.title||'Imported OpenAPI',bodyRequired:!!rb?.required});
    }
  }
  if(!result.length) throw new Error('No supported API operations were found.');
  return result;
}

export const endpoints = officialCatalog as Endpoint[];
