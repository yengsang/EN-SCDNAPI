import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {createRequire} from 'node:module';
import ts from 'typescript';
const api=JSON.parse(readFileSync(new URL('../data/edgenext-openapi.json',import.meta.url),'utf8'));
const catalog=JSON.parse(readFileSync(new URL('../lib/edgenext-catalog.json',import.meta.url),'utf8'));
test('every operation in the official API definition appears exactly once',()=>{
  const expected=[];for(const [path,item]of Object.entries(api.paths))for(const method of ['get','post','put','patch','delete'])if(item[method])expected.push(method.toUpperCase()+' '+path);
  assert.deepEqual(catalog.map(e=>e.method+' '+e.path).sort(),expected.sort());
  assert.equal(catalog.length,313);assert.equal(new Set(catalog.map(e=>e.category)).size,33);
});
test('retains required flags, enums, descriptions, and nested schema definitions',()=>{
  const create=catalog.find(e=>e.path==='/api/v5/ruletpls'&&e.method==='POST');
  assert.equal(create.fields.find(f=>f.name==='name').required,true);
  assert.deepEqual(create.fields.find(f=>f.name==='app_type').enum,['network_speed']);
  const bind=create.fields.find(f=>f.name==='bind_domain');assert.equal(bind.required,true);
  assert.equal(bind.schema.properties.is_bind.type,'boolean');assert.ok(bind.schema.required.includes('is_bind'));
  const query=catalog.find(e=>e.path==='/api/v5/ruletpls'&&e.method==='GET');
  assert.equal(query.fields.find(f=>f.name==='page').schema.minimum,1);
});
test('the browser importer accepts the full official OpenAPI definition',()=>{
  const url=new URL('../lib/catalog.ts',import.meta.url);
  const js=ts.transpileModule(readFileSync(url,'utf8'),{compilerOptions:{module:ts.ModuleKind.CommonJS,target:ts.ScriptTarget.ES2020,esModuleInterop:true}}).outputText;
  const mod={exports:{}};new Function('require','exports','module',js)(createRequire(url),mod.exports,mod);
  assert.equal(mod.exports.importCatalog(api).length,313);
});
test('navigation matches the requested product hierarchy and retains every operation',()=>{
  const url=new URL('../lib/navigation.ts',import.meta.url);
  const js=ts.transpileModule(readFileSync(url,'utf8'),{compilerOptions:{module:ts.ModuleKind.CommonJS,target:ts.ScriptTarget.ES2020,esModuleInterop:true}}).outputText;
  const mod={exports:{}};new Function('require','exports','module',js)(createRequire(url),mod.exports,mod);
  const tree=mod.exports.buildNavigation(catalog);
  assert.deepEqual(tree.map(n=>n.title),['SCDN','DNS','Operation Logs']);
  assert.deepEqual(tree[0].children.map(n=>n.title),['Domain Management','Certificate Management','Resource Package Management','Speed and Network','Security Protection','Ban Management','Rule Management','Analytics','Log Management','Layer 4 Proxy','Public APIs']);
  assert.deepEqual(tree[1].children.map(n=>n.title),['Domain Management','Record Management','Batch Tasks']);
  const groups=mod.exports.endpointGroups(tree);
  assert.deepEqual([...new Set(groups.flatMap(g=>g.endpoints.map(e=>e.id)))].sort(),catalog.map(e=>e.id).sort());
  assert.ok(groups.some(g=>g.label==='SCDN / Rule Management / IP Intelligence / IP Lists'));
  assert.equal(tree[2].endpoints.length,3);
  const unknown={...catalog[0],id:'custom-import',method:'GET',path:'/other/api',category:'Custom category'};
  const imported=mod.exports.buildNavigation([unknown]);
  assert.equal(imported[0].title,'Imported APIs');
  assert.equal(mod.exports.endpointGroups(imported)[0].endpoints[0].id,unknown.id);
});
