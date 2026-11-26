// S06: isolamento com workers FrankenPHP persistentes. Exige o laboratório s04 com as contas A e B e os usuários admin, ed, gui e b.
import { chromium } from 'playwright-core';
const browser = await chromium.launch({ executablePath: process.env.HOME + '/.cache/ms-playwright/chromium-1223/chrome-linux64/chrome', args: ['--no-sandbox'] });
const root=process.env.LAB_URL||'http://127.0.0.1:18400';
const password=process.env.LAB_PASSWORD; // Senha sintética do laboratório descartável, nunca versionada.
if(!password) throw new Error('Defina LAB_PASSWORD com a senha dos usuários sintéticos do laboratório.');
async function session(email){ const ctx=await browser.newContext(); const page=await ctx.newPage(); await page.goto(root+'/login'); await page.fill('input[name=email]',email); await page.fill('input[type=password]',password); await page.click('button[type=submit]'); await page.waitForLoadState('networkidle'); const jar=ctx; ctx.request.__ctx=jar; return ctx.request; }
const users = { admin: await session('admin@s04.test'), ed: await session('ed@s04.test'), gui: await session('gui@s04.test'), b: await session('b@s04.test') };
const get = async (who, path) => { const r = await users[who].get(root+path, { headers: { Accept: 'application/json' } }); const text = await r.text(); let json=null; try { json=JSON.parse(text);} catch {} return { status: r.status(), json, text }; };
const A='/contas/grupo-gartan/empresa/gartan-roupas/institucional', U='/contas/grupo-gartan/estabelecimento/gartan-roupas-guanambi/institucional', O='/contas/grupo-gartan/estabelecimento/gartan-roupas-caetite/institucional', B='/contas/grupo-b/empresa/empresa-b/institucional';
const about = (j, st) => j.sections.find(s=>s.key==='about')[st].value.title;
let checks=0, fails=[];
const ok=(c,msg)=>{checks++; if(!c) fails.push(msg);};
const rounds = Number(process.argv[2]||40);
for (let i=0;i<rounds;i++){
  const order = ['admin','b','ed','gui','admin','gui','b','ed'].sort(()=>Math.random()-0.5);
  await Promise.all(order.map(async (who)=>{
    if (who==='admin'){ const r=await get('admin',A); ok(r.status===200 && about(r.json,'draft')==='A-rascunho' && about(r.json,'published')==='A-publicado' && r.json.canPublish===true,'admin A'); const x=await get('admin',B); ok(x.status!==200 || !JSON.stringify(x.json).includes('B-'),'admin vê B'); }
    if (who==='b'){ const r=await get('b',B); ok(r.status===200 && about(r.json,'draft')==='B-rascunho' && !JSON.stringify(r.json).includes('A-'),'b B'); const x=await get('b',A); ok(x.status!==200 && !x.text.includes('A-'),'b vê A'); }
    if (who==='ed'){ const r=await get('ed',A); ok(r.status===200 && r.json.canUpdate===true && r.json.canPublish===false,'ed permissões'); }
    if (who==='gui'){ const r=await get('gui',U); ok(r.status===200 && r.json.canPublish===true && r.json.owner.code==='gartan-roupas-guanambi','gui U'); const c=await get('gui',A); ok(c.status===404,'gui empresa '+c.status); const o=await get('gui',O); ok(o.status===404,'gui outra unidade '+o.status); }
  }));
}
// Escritas alternadas com leituras: o valor lido logo depois vem do banco, nunca de memória da requisição anterior.
const csrf = async (who) => (await (await users[who].get(root+'/api/csrf-token').catch(()=>null))?.text?.()) ;
for (let i=0;i<15;i++){
  const cookies = await users.admin.__ctx.cookies(root); const xsrf = decodeURIComponent(cookies.find(c=>c.name==='XSRF-TOKEN')?.value||'');
  const w = await users.admin.patch(root+A+'/secoes/about', { headers:{Accept:'application/json','X-XSRF-TOKEN':xsrf}, multipart:{ mode:'override', 'value[title]':'A-w'+i } });
  ok(w.status()===204,'patch '+w.status());
  if (w.status()===204) {
    const r=await get('admin',A); ok(about(r.json,'draft')==='A-w'+i,'leitura após escrita '+i);
    const x=await get('b',B); ok(about(x.json,'draft')==='B-rascunho','B intacta '+i);
    const q=await Promise.all([get('admin',A),get('ed',A),get('gui',U)]); ok(about(q[0].json,'published')==='A-publicado','publicado intacto');
  }
}
console.log('verificações:', checks, '| falhas:', fails.length, fails.slice(0,5));
await browser.close();
