import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {signRequest} from '../lib/edgenext.mjs';
const vectors=JSON.parse(readFileSync(new URL('./signing-vectors.json',import.meta.url),'utf8'));
for(const expected of vectors) test('matches the actual PHP SDK signer: '+expected.method,async()=>{
  const signed=await signRequest({baseUrl:'https://api.example.com/V4/',appId:'test-app',secret:'test-secret',method:expected.method,path:'firewall.policy.save',query:{page:2,data:{name:'hello world',domain:'example.com'}},body:{name:'规则/one',rules:[{logic:'contains',data:['/login']}],enabled:true},now:new Date('2026-10-04T07:00:00Z'),userAgent:'vector-agent'});
  assert.equal(signed.canonical,expected.canonical);
  assert.equal(signed.body,expected.body);
  assert.equal(signed.url,expected.url);
  assert.equal(signed.headers['X-Auth-Sign'],expected.signature);
});
test('rejects endpoint URL injection and insecure configuration',async()=>{
  const base={baseUrl:'https://api.example.com/V4/',appId:'test',secret:'test',method:'GET'};
  for(const path of ['https://attacker.example','../secret','endpoint?token=1','foo/{id}']) await assert.rejects(signRequest({...base,path}));
  await assert.rejects(signRequest({...base,baseUrl:'http://api.example.com/',path:'endpoint'}));
});
