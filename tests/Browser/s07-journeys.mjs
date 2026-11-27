// S07: jornadas reais no Chromium contra o laboratório descartável (tests/Performance/s07-lab.sh).
// Rode num laboratório recém-criado: a jornada grava dados e não é idempotente.
// Uso: LAB_PASSWORD=... LAB_URL=http://127.0.0.1:18207 NODE_PATH=<pasta com playwright-core> node tests/Browser/s07-journeys.mjs
import { chromium } from 'playwright-core';

const root = process.env.LAB_URL || 'http://127.0.0.1:18207';
const password = process.env.LAB_PASSWORD; // Senha sintética do laboratório descartável, nunca versionada.
if (!password) throw new Error('Defina LAB_PASSWORD com a senha dos usuários sintéticos do laboratório.');
const shots = process.env.SHOTS || '/tmp/s07-shots';
const browser = await chromium.launch({
  executablePath: process.env.HOME + '/.cache/ms-playwright/chromium-1223/chrome-linux64/chrome', args: ['--no-sandbox'],
});

const results = [];
const problems = [];
const check = (condition, message) => { results.push([Boolean(condition), message]); if (!condition) console.log('FALHA:', message); };

async function session(email, viewport = { width: 1440, height: 900 }) {
  const context = await browser.newContext({ viewport });
  const page = await context.newPage();
  page.on('pageerror', (error) => problems.push(`js (${email}): ${error.message}`));
  page.on('console', (message) => { if (message.type() === 'error' && !/status of 4\d\d/.test(message.text())) problems.push(`console (${email}): ${message.text()}`); });
  page.on('response', (response) => { if (response.status() >= 500) problems.push(`5xx (${email}): ${response.status()} ${response.url()}`); });
  await page.goto(`${root}/login`);
  await page.fill('input[name=email]', email);
  await page.fill('input[type=password]', password);
  await page.click('button[type=submit]');
  await page.waitForLoadState('networkidle');
  return { context, page };
}

const A = `${root}/contas/grupo-gartan`;
const company = `${A}/empresa/gartan-roupas/institucional`;
const unit = (code) => `${A}/estabelecimento/${code}/institucional`;
const settle = (page) => page.waitForLoadState('networkidle').then(() => page.waitForTimeout(500));
const open = async (page, url) => { await page.goto(url); await settle(page); };

const choose = async (scope, page, label) => {
  await scope.getByRole('combobox').first().click();
  await page.getByRole('option', { name: label, exact: true }).last().click();
};
// Seletores do tema Bold (ClockPicker e DatePicker), no lugar de input type=time e type=date: o texto digitado é 24 h e DD/MM/AAAA.
const clocks = (scope) => scope.locator('.clockPicker input');
const typeTime = async (field, value) => { await field.fill(value); await field.blur(); };
const typeDate = async (scope, label, iso) => {
  const [y, m, d] = iso.split('-');
  const field = scope.getByRole('textbox', { name: label, exact: true });
  await field.fill(`${d}/${m}/${y}`); await field.blur();
};
const overflow = (page) => page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);

// 1. Empresa: horário semanal, copiar dias, turno até 24:00 e validação por campo -----------------------------------
const admin = await session('admin@s07.test');
let page = admin.page;
await open(page, company);
await page.getByRole('button', { name: 'Editar horário de funcionamento' }).click();
await page.waitForSelector('[data-tour=hours-editor]');
await page.waitForTimeout(600);
const mon = page.locator('[data-day=mon]');
await choose(mon, page, 'Aberto');
await mon.getByRole('button', { name: 'Adicionar turno em Segunda-feira' }).click();
let times = clocks(mon);
await typeTime(times.nth(0), '10:00'); await typeTime(times.nth(1), '09:00'); // fim antes do início: erro do servidor por campo
await page.getByRole('dialog').getByRole('button', { name: 'Salvar' }).click();
await page.waitForTimeout(800);
check(await page.locator('[data-day=mon] .formField__error').count() > 0, 'turno invertido mostra erro no campo do turno');
await typeTime(times.nth(0), '08:00'); await typeTime(times.nth(1), '12:00');
await typeTime(times.nth(2), '14:00'); await typeTime(times.nth(3), '18:00');
await page.getByRole('button', { name: 'Copiar segunda-feira para terça a sexta' }).click();
const sat = page.locator('[data-day=sat]');
await choose(sat, page, 'Aberto');
times = clocks(sat);
await typeTime(times.nth(0), '08:00'); await typeTime(times.nth(1), '12:00');
await page.getByRole('dialog').getByRole('button', { name: 'Salvar' }).click();
await settle(page);
const week = await page.locator('[data-testid=hours-week]').innerText();
check(/Seg a Sex\s+08:00 às 12:00 e 14:00 às 18:00/.test(week), 'grade agrupa segunda a sexta com dois turnos');
check(/Sáb\s+08:00 às 12:00/.test(week) && /Dom\s+Fechado/.test(week), 'sábado de manhã e domingo fechado');

// 2. Unidade: herda, personaliza, volta a herdar -------------------------------------------------------------------
await open(page, unit('gartan-roupas-guanambi'));
check(/Herdado da empresa/.test(await page.locator('[data-section=hours_weekly]').innerText()), 'unidade mostra a origem herdada');
check(await page.locator('[data-tour=hours-now]').getByText('Horário herdado da empresa').count() === 1, 'situação agora mostra horário herdado');
await page.getByRole('button', { name: 'Editar horário de funcionamento' }).click();
await page.waitForTimeout(700);
await choose(page.locator('.modalLayer__dialog').last().locator('.formField').first(), page, 'Personalizar neste estabelecimento');
await page.waitForSelector('[data-tour=hours-editor]');
await page.waitForTimeout(300);
const gMon = page.locator('[data-day=mon]');
const gTimes = clocks(gMon);
await typeTime(gTimes.nth(0), '09:00'); await typeTime(gTimes.nth(1), '13:00');
await page.getByRole('dialog').getByRole('button', { name: 'Salvar' }).click();
await settle(page);
check(/Informação própria/.test(await page.locator('[data-section=hours_weekly]').innerText()), 'unidade personalizada mostra horário próprio');
check(await page.locator('[data-tour=hours-now]').getByText('Horário próprio desta unidade').count() === 1, 'situação agora mostra horário próprio');
await open(page, company);
check(/Horário próprio/.test(await page.locator('[data-tour=hours-units]').innerText()), 'a empresa lista a unidade com horário próprio');

// 3. Exceção corporativa com confirmação de impacto ------------------------------------------------------------------
await page.getByRole('button', { name: 'Cadastrar exceção' }).click();
await page.waitForSelector('[data-tour=hours-impact]');
await page.waitForTimeout(500);
const dialog = page.locator('.modalLayer__dialog').last();
await typeDate(dialog, 'Primeiro dia', '2099-12-25');
await page.waitForTimeout(900);
check(/herdam o horário da empresa/.test(await dialog.locator('[data-tour=hours-impact]').innerText()), 'resumo lista as unidades que herdam');
check(/Guanambi/.test(await dialog.locator('[data-tour=hours-impact]').innerText()), 'resumo nomeia a unidade com horário próprio');
await dialog.getByLabel('Aplicar também às unidades com horário próprio').check();
const save = dialog.getByRole('button', { name: 'Salvar' });
check(await save.isDisabled(), 'Salvar fica desabilitado até confirmar o alcance');
await dialog.getByLabel(/Confirmo que 1 unidade/).check();
check(await save.isEnabled(), 'confirmar libera o Salvar');
await page.screenshot({ path: `${shots}/excecao-empresa.png` });
await save.click();
await settle(page);
check((await page.locator('[data-tour=hours-exceptions]').innerText()).includes('25/12/2099'), 'a exceção aparece na lista');
check((await page.locator('[data-tour=hours-exceptions]').innerText()).includes('Inclui unidades com horário próprio'), 'a lista marca o alcance sobre horário próprio');

// 4. Unidade que herda vê a exceção da empresa; a que tem horário próprio vê só porque a exceção a alcança ----------------
await open(page, unit('gartan-roupas-caetite'));
check((await page.locator('[data-tour=hours-exceptions]').innerText()).includes('Herdada da empresa'), 'unidade que herda vê a exceção como herdada');

// 5. Exceção da unidade: horário especial, edição e exclusão ----------------------------------------------------------
await page.getByRole('button', { name: 'Cadastrar exceção' }).click();
await page.waitForTimeout(600);
let d = page.locator('.modalLayer__dialog').last();
await choose(d.locator('.formField').first(), page, 'Horário especial');
await typeDate(d, 'Primeiro dia', '2099-12-31');
await d.locator('input[name=note]').fill('Véspera de ano novo');
times = clocks(d);
await typeTime(times.nth(0), '08:00'); await typeTime(times.nth(1), '13:00');
await d.getByRole('button', { name: 'Salvar' }).click();
await settle(page);
let list = await page.locator('[data-tour=hours-exceptions]').innerText();
check(/Horário especial\s+31\/12\/2099: 08:00 às 13:00/.test(list) && list.includes('Véspera de ano novo'), 'horário especial da unidade com turnos e observação');
await page.getByRole('button', { name: /Editar horário especial de 31\/12\/2099/ }).click();
await page.waitForTimeout(600);
d = page.locator('.modalLayer__dialog').last();
await typeDate(d, 'Último dia', '2099-12-30');
await d.getByRole('button', { name: 'Salvar' }).click();
await page.waitForTimeout(800);
check(await d.locator('.formField__error').count() > 0, 'último dia antes do primeiro é recusado no campo');
await d.getByRole('button', { name: 'Cancelar' }).click();
await page.waitForTimeout(400);
await page.getByRole('button', { name: /Excluir horário especial de 31\/12\/2099/ }).click();
await page.waitForTimeout(500);
await page.locator('.modalLayer__dialog').last().getByRole('button', { name: 'Excluir' }).click();
await settle(page);
check(!(await page.locator('[data-tour=hours-exceptions]').innerText()).includes('Véspera'), 'a exceção excluída some da lista');

// 6. Fuso da unidade -----------------------------------------------------------------------------------------------
await open(page, unit('gartan-roupas-guanambi'));
const form = page.locator('[data-tour=hours-timezone]');
await form.getByRole('combobox').click();
await page.keyboard.type('Bahia');
await page.getByRole('option', { name: 'America/Bahia', exact: true }).click();
await form.getByRole('button', { name: 'Salvar' }).click();
await settle(page);
check(/America\/Bahia/.test(await page.locator('[data-tour=hours-now]').innerText()), 'o fuso novo aparece na situação agora');
await form.getByRole('combobox').click();
await page.keyboard.type('Sao');
await page.getByRole('option', { name: 'America/Sao Paulo', exact: true }).click();
await form.getByRole('button', { name: 'Salvar' }).click();
await settle(page);

// 7. Permissões: consulta, gestor de unidade ---------------------------------------------------------------------------
const viewer = await session('viewer@s07.test');
await open(viewer.page, company);
check(await viewer.page.getByRole('button', { name: 'Cadastrar exceção' }).count() === 0, 'consulta não vê Cadastrar exceção');
check(await viewer.page.getByRole('button', { name: 'Editar horário de funcionamento' }).count() === 0, 'consulta não vê Editar horário');
const gui = await session('gui@s07.test');
await open(gui.page, unit('gartan-roupas-guanambi'));
check(await gui.page.getByRole('button', { name: 'Cadastrar exceção' }).count() === 1, 'gestor da unidade cadastra exceção na própria unidade');
// A página HTML é a casca da aplicação; a autorização vale na API, que responde 404 e a tela mostra "indisponível".
for (const [label, url] of [['outra unidade', unit('gartan-roupas-caetite')], ['a empresa', company]]) {
  const api = await gui.context.request.get(url, { headers: { Accept: 'application/json' } });
  check(api.status() === 404, `gestor da unidade não alcança ${label} (API 404)`);
  await open(gui.page, url);
  check(/indispon/i.test(await gui.page.locator('main').innerText()), `gestor da unidade vê tela indisponível para ${label}`);
}

// 8. Larguras: sem rolagem horizontal, nas telas e nos modais -----------------------------------------------------------
for (const width of [1440, 1024, 768, 390, 320]) {
  // A mesma sessão muda de largura: entradas repetidas acionam o limite de tentativas de login.
  const s = admin;
  await s.page.setViewportSize({ width, height: 900 });
  await open(s.page, company);
  check(await overflow(s.page) <= 0, `empresa sem rolagem horizontal em ${width}px`);
  await s.page.screenshot({ path: `${shots}/empresa-${width}.png`, fullPage: true });
  await s.page.getByRole('button', { name: 'Editar horário de funcionamento' }).click();
  await s.page.waitForSelector('[data-tour=hours-editor]');
  await s.page.waitForTimeout(600);
  check(await overflow(s.page) <= 0, `editor de horário sem rolagem horizontal em ${width}px`);
  await s.page.screenshot({ path: `${shots}/editor-${width}.png` });
  await s.page.keyboard.press('Escape');
  await s.page.waitForTimeout(500);
  await s.page.getByRole('button', { name: 'Cadastrar exceção' }).click();
  await s.page.waitForSelector('[data-tour=hours-impact]');
  await s.page.waitForTimeout(600);
  check(await overflow(s.page) <= 0, `modal de exceção sem rolagem horizontal em ${width}px`);
  await s.page.screenshot({ path: `${shots}/excecao-${width}.png` });
  await open(s.page, unit('gartan-roupas-guanambi'));
  check(await overflow(s.page) <= 0, `unidade sem rolagem horizontal em ${width}px`);
  await s.page.screenshot({ path: `${shots}/unidade-${width}.png`, fullPage: true });
}
await admin.page.setViewportSize({ width: 1440, height: 900 });

// 9. Teclado: abrir e fechar o modal de exceção sem mouse ------------------------------------------------------------
await open(page, company);
await page.getByRole('button', { name: 'Cadastrar exceção' }).focus();
await page.keyboard.press('Enter');
await page.waitForSelector('[data-tour=hours-impact]');
await page.waitForTimeout(500);
check(await page.evaluate(() => Boolean(document.activeElement && document.activeElement.closest('[role=dialog]'))), 'o foco entra no modal aberto pelo teclado');
await page.keyboard.press('Escape');
await page.waitForTimeout(500);
check(await page.locator('[data-tour=hours-impact]').count() === 0, 'Escape fecha o modal');

check(problems.length === 0, 'sem erro de JavaScript nem resposta 5xx: ' + problems.slice(0, 3).join(' | '));
const failed = results.filter(([ok]) => !ok);
console.log(`verificações: ${results.length} | falhas: ${failed.length}`);
for (const [, message] of failed) console.log(' -', message);
await browser.close();
process.exit(failed.length === 0 ? 0 : 1);
