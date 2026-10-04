import {spawn} from 'node:child_process';
import {existsSync} from 'node:fs';
import {fileURLToPath} from 'node:url';
const root=fileURLToPath(new URL('..',import.meta.url));
process.chdir(root);
if(existsSync('.env.local')) process.loadEnvFile('.env.local');
const env={...process.env,APP_ORIGIN:'http://localhost:5173'};
const php=process.env.PHP_BIN||'php';
const args=[...(process.env.PHP_INI?['-c',process.env.PHP_INI]:[]),'-S','127.0.0.1:8081','-t','selfhost/public','selfhost/router.php'];
const api=spawn(php,args,{env,stdio:'inherit'});
let vite;
let stopping=false;
function stop(code=0){if(stopping)return;stopping=true;api.kill();vite?.kill();process.exitCode=code;}
api.on('error',error=>{console.error('Could not start PHP. Install PHP with cURL or set PHP_BIN.');stop(1);});
api.on('exit',code=>stop(code||0));
api.on('spawn',()=>{
  vite=spawn(process.execPath,['node_modules/vite/bin/vite.js','--config','vite.selfhost.config.ts'],{env,stdio:'inherit'});
  vite.on('error',()=>{console.error('Could not start the frontend.');stop(1);});
  vite.on('exit',code=>stop(code||0));
});
process.on('SIGINT',()=>stop());process.on('SIGTERM',()=>stop());
