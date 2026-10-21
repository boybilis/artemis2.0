const fs=require('fs'),vm=require('vm'),assert=require('assert');
const source=fs.readFileSync('public/script.js','utf8');
const context={getCsrfToken:()=>'',fetch:async()=>{throw new Error('Failed to fetch');},showToast:()=>context.toasts++,toasts:0};
vm.createContext(context);
vm.runInContext(source.slice(source.indexOf('async function apiRequest('),source.indexOf('// ─── Toast System')),context);
(async()=>{
 await assert.rejects(context.apiRequest('/background','GET',null,{silent:true}));
 assert.equal(context.toasts,0);
 await assert.rejects(context.apiRequest('/user-action'));
 assert.equal(context.toasts,1);
 console.log('Background failures stay quiet; user-action failures remain visible.');
})().catch(error=>{console.error(error);process.exitCode=1;});
