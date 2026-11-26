// S07: isolamento de horários, fusos e permissões com workers FrankenPHP persistentes.
// Exige o laboratório s07 (contas A e B). Uso: LAB_PASSWORD=... node tests/Browser/s07-octane.mjs [rodadas]
import { chromium } from 'playwright-core';

const root = process.env.LAB_URL || 'http://127.0.0.1:18207';
const password = process.env.LAB_PASSWORD; // Senha sintética do laboratório descartável, nunca versionada.
if (!password) throw new Error('Defina LAB_PASSWORD com a senha dos usuários sintéticos do laboratório.');
const browser = await chromium.launch({
  executablePath: process.env.HOME + '/.cache/ms-playwright/chromium-1223/chrome-linux64/chrome', args: ['--no-sandbox'],
});

async function session(email) {
  const context = await browser.newContext();
  const page = await context.newPage();
  await page.goto(root + '/login');
  await page.fill('input[name=email]', email);
  await page.fill('input[type=password]', password);
  await page.click('button[type=submit]');
  await page.waitForLoadState('networkidle');
  return context;
}

const users = { admin: await session('admin@s07.test'), ed: await session('ed@s07.test'), gui: await session('gui@s07.test'), viewer: await session('viewer@s07.test'), b: await session('b@s07.test') };
const A = '/contas/grupo-gartan/empresa/gartan-roupas/institucional';
const U = (code) => `/contas/grupo-gartan/estabelecimento/${code}/institucional`;
const B = '/contas/grupo-b/empresa/empresa-b/institucional';
const get = async (who, path) => {
  const response = await users[who].request.get(root + path, { headers: { Accept: 'application/json' } });
  const text = await response.text();
  let json = null;
  try { json = JSON.parse(text); } catch { /* corpo sem JSON */ }
  return { status: response.status(), json, text };
};
const xsrf = async (who) => decodeURIComponent((await users[who].cookies(root)).find((cookie) => cookie.name === 'XSRF-TOKEN')?.value || '');
const send = async (who, method, path, form) => {
  const response = await users[who].request.fetch(root + path, { method, headers: { Accept: 'application/json', 'X-XSRF-TOKEN': await xsrf(who) }, multipart: form });
  return response.status();
};
const week = (kind, start, end) => ({ 'mode': 'override', 'value[days][mon][kind]': kind, 'value[days][mon][shifts][0][start]': start, 'value[days][mon][shifts][0][end]': end });
const monday = (json) => json.sections.find((section) => section.key === 'hours_weekly').draft.value.days.mon.shifts[0];

let checks = 0;
const failures = [];
const ok = (condition, message) => { checks++; if (!condition) failures.push(message); };

// Dados: horários diferentes por conta e uma exceção da empresa A.
ok(await send('admin', 'PATCH', A + '/secoes/hours_weekly', week('open', '08:15', '17:45')) === 204, 'A grava horário');
ok(await send('b', 'PATCH', B + '/secoes/hours_weekly', week('open', '06:00', '07:00')) === 204, 'B grava horário');
await send('admin', 'POST', A + '/excecoes', { kind: 'holiday', starts_on: '2099-12-25', note: 'Natal A' });

const rounds = Number(process.argv[2] || 40);
const zones = { manaus: 'America/Manaus', 'nova-york': 'America/New_York' };

for (let round = 0; round < rounds; round++) {
  const jobs = ['admin', 'b', 'ed', 'gui', 'viewer', 'admin', 'gui', 'b', 'viewer', 'ed'].sort(() => Math.random() - 0.5);
  await Promise.all(jobs.map(async (who) => {
    if (who === 'admin') {
      const company = await get('admin', A);
      ok(company.status === 200 && monday(company.json).start === '08:15', 'admin vê o horário de A');
      ok(company.json.hours.units.length === 5 && !company.text.includes('Natal B') && !company.text.includes('06:00'), 'admin: 5 unidades e nada de B');
      const manaus = await get('admin', U('gartan-roupas-manaus'));
      ok(manaus.json.hours.timezone === 'America/Manaus' && manaus.json.hours.now.timezone === 'America/Manaus', 'fuso de Manaus');
      const york = await get('admin', U('gartan-roupas-nova-york'));
      ok(york.json.hours.timezone === 'America/New_York' && york.json.hours.now.timezone === 'America/New_York', 'fuso de Nova York');
      ok(manaus.json.hours.now.local_time !== york.json.hours.now.local_time || manaus.json.hours.now.local_date !== york.json.hours.now.local_date, 'fusos diferentes dão horas locais diferentes');
      ok(JSON.stringify(manaus.json.hours.inherited_exceptions).includes('Natal A'), 'exceção da empresa A chega a quem herda');
    }
    if (who === 'b') {
      const company = await get('b', B);
      ok(company.status === 200 && monday(company.json).start === '06:00', 'b vê o horário de B');
      ok(!company.text.includes('08:15') && !company.text.includes('Natal A') && company.json.hours.units.length === 1, 'b não vê nada de A');
      ok((await get('b', A)).status === 404, 'b não alcança A');
      ok((await get('b', U('gartan-roupas-manaus'))).status === 404, 'b não alcança unidade de A');
    }
    if (who === 'ed') {
      const company = await get('ed', A);
      ok(company.status === 200 && company.json.canUpdate === true && company.json.hours.timezones.length === 0, 'ed edita a empresa (sem lista de fusos, que é da unidade)');
      const unit = await get('ed', U('gartan-roupas-manaus'));
      ok(unit.status === 200 && unit.json.hours.timezones.length > 300, 'ed recebe os fusos da unidade');
    }
    if (who === 'gui') {
      const own = await get('gui', U('gartan-roupas-guanambi'));
      ok(own.status === 200 && own.json.owner.code === 'gartan-roupas-guanambi' && own.json.hours.timezone === 'America/Sao_Paulo', 'gui vê a própria unidade');
      ok((await get('gui', A)).status === 404 && (await get('gui', U('gartan-roupas-caetite'))).status === 404, 'gui não alcança empresa nem outra unidade');
    }
    if (who === 'viewer') {
      const company = await get('viewer', A);
      ok(company.status === 200 && company.json.canUpdate === false && company.json.hours.timezones.length === 0, 'viewer só consulta, sem lista de fusos');
      const status = await send('viewer', 'POST', A + '/excecoes', { kind: 'holiday', starts_on: '2099-07-01' });
      ok(status === 403, 'viewer não grava exceção: ' + status);
    }
  }));
}

// Escritas alternadas com leituras: o valor lido logo depois vem do banco, nunca da memória de uma requisição anterior.
for (let i = 0; i < 12; i++) {
  const zone = i % 2 === 0 ? 'America/Bahia' : 'America/Fortaleza';
  ok(await send('admin', 'PATCH', U('gartan-roupas-caetite') + '/fuso', { timezone: zone }) === 204, 'troca de fuso');
  const [admin, ed, b] = await Promise.all([get('admin', U('gartan-roupas-caetite')), get('ed', U('gartan-roupas-caetite')), get('b', B)]);
  ok(admin.json.hours.timezone === zone && ed.json.hours.timezone === zone, 'a troca de fuso aparece para todos os operadores de A: ' + zone);
  ok(b.json.hours.units[0].timezone === 'America/Sao_Paulo', 'o fuso de A não vaza para B');
  const start = `0${(i % 8) + 1}:00`;
  await send('admin', 'PATCH', A + '/secoes/hours_weekly', week('open', start, '17:45'));
  const [again, other] = await Promise.all([get('admin', A), get('b', B)]);
  ok(monday(again.json).start === start && monday(other.json).start === '06:00', 'horário lido após escrita: ' + start);
}
await send('admin', 'PATCH', U('gartan-roupas-caetite') + '/fuso', { timezone: 'America/Sao_Paulo' });

console.log(`verificações: ${checks} | falhas: ${failures.length}`, failures.slice(0, 6));
await browser.close();
process.exit(failures.length === 0 ? 0 : 1);
