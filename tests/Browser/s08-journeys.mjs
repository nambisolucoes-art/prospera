// S08: jornada administrativa real da biblioteca de mídia no Chromium contra o laboratório descartável (s08-lab.sh).
// Rode num laboratório recém-criado. Uso: LAB_PASSWORD=... LAB_URL=http://127.0.0.1:18207 NODE_PATH=<pasta com playwright-core> node tests/Browser/s08-journeys.mjs
import { chromium } from 'playwright-core';

const root = process.env.LAB_URL || 'http://127.0.0.1:18207';
const password = process.env.LAB_PASSWORD; // Senha sintética do laboratório descartável.
if (!password) throw new Error('Defina LAB_PASSWORD.');
const corpus = process.env.CORPUS || '/tmp/s08-corpus-host';
const shots = process.env.SHOTS || '/tmp/s08-shots';
const browser = await chromium.launch({ executablePath: process.env.HOME + '/.cache/ms-playwright/chromium-1223/chrome-linux64/chrome', args: ['--no-sandbox'] });
const results = []; const problems = [];
const check = (ok, message) => { results.push([Boolean(ok), message]); if (!ok) console.log('FALHA:', message); };

async function session(email, viewport = { width: 1440, height: 900 }) {
  const context = await browser.newContext({ viewport });
  const page = await context.newPage();
  page.on('pageerror', (e) => problems.push(`js (${email}): ${e.message}`));
  page.on('console', (m) => { if (m.type() === 'error' && !/status of 4\d\d/.test(m.text())) problems.push(`console (${email}): ${m.text()}`); });
  page.on('response', (r) => { if (r.status() >= 500) problems.push(`5xx (${email}): ${r.status()} ${r.url()}`); });
  await page.goto(`${root}/login`);
  await page.fill('input[name=email]', email); await page.fill('input[type=password]', password);
  await page.click('button[type=submit]'); await page.waitForLoadState('networkidle');
  return { context, page };
}
const A = `${root}/contas/grupo-gartan`;
const company = `${A}/empresa/gartan-roupas/midia`;
const unit = (code) => `${A}/estabelecimento/${code}/midia`;
const settle = (p) => p.waitForLoadState('networkidle').then(() => p.waitForTimeout(500));
const open = async (p, url) => { await p.goto(url); await settle(p); };
const overflow = (p) => p.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
// O Dropzone cria o input só ao clicar na área: o seletor de arquivos real do navegador é interceptado.
const pick = async (p, scope, file) => {
  const [chooser] = await Promise.all([p.waitForEvent('filechooser'), scope.locator('.dropzone').click()]);
  await chooser.setFiles(`${corpus}/${file}`);
};
const upload = async (p, file, { purpose, alt, license } = {}) => {
  await p.getByRole('button', { name: 'Enviar imagem' }).click();
  const dialog = p.getByRole('dialog'); await dialog.waitFor();
  if (purpose) { await dialog.getByRole('combobox').first().click(); await p.getByRole('option', { name: purpose, exact: true }).last().click(); }
  if (alt) await dialog.getByRole('textbox', { name: 'Texto alternativo' }).fill(alt);
  if (license) await dialog.getByRole('textbox', { name: 'Licença ou crédito' }).fill(license);
  await pick(p, dialog, file);
  await p.waitForTimeout(500);
  await dialog.getByRole('button', { name: 'Salvar' }).click();
  await p.waitForTimeout(2500);
};

// 1. Estado vazio e jornada do administrador ----------------------------------------------------------------------
const admin = await session('admin@s08.test'); let page = admin.page;
await open(page, company);
check(await page.locator('[data-tour=media-empty]').count() === 1, 'biblioteca sem imagens mostra o estado vazio');
await page.screenshot({ path: `${shots}/01-vazio-1440.png`, fullPage: true });

await upload(page, 'jpeg-1600x1200.jpg', { purpose: 'Banner', alt: 'Fachada da loja', license: 'Foto própria' });
await settle(page);
check(await page.locator('.media__card').count() === 1, 'envio válido cria um cartão na biblioteca corporativa');
check((await page.locator('.media__card').first().innerText()).includes('Corporativa'), 'cartão marcado como corporativo');
check(await page.locator('.media__card img').first().evaluate((i) => i.complete && i.naturalWidth > 0), 'miniatura autorizada carrega pela rota da aplicação');
const src = await page.locator('.media__card img').first().getAttribute('src');
check(!/storage|\/app\/|private/.test(src), 'URL da miniatura não expõe caminho do armazenamento');

// recusas com mensagem útil
for (const [file, text] of [['jpeg-48mp.jpg', /máximo é 18 megapixels/], ['jpeg-20mp.jpg', /até 18 megapixels/], ['disfarcado-html.jpg', /SVG e HTML/], ['jpeg-truncado.jpg', /truncado/], ['svg-com-script.svg', /não é permitido/], ['gif-animado.gif', /não é permitido/]]) {
  await page.getByRole('button', { name: 'Enviar imagem' }).click();
  const d = page.getByRole('dialog'); await d.waitFor();
  await pick(page, d, file);
  const save = d.getByRole('button', { name: 'Salvar' });
  if (await save.isEnabled()) await save.click();
  await page.waitForTimeout(2500);
  const message = await d.getByRole('alert').innerText().catch(() => '');
  check(await d.isVisible() && text.test(message), `${file} é recusado com mensagem: ${message.slice(0, 60)}`);
  check(await d.getByRole('button', { name: 'Salvar' }).isDisabled(), `${file}: Salvar volta a ficar desabilitado sem arquivo válido`);
  await d.getByRole('button', { name: 'Cancelar' }).click(); await page.waitForTimeout(300);
}
check(await page.locator('.media__card').count() === 1, 'recusas não criam mídia');
await page.getByRole('button', { name: 'Enviar imagem' }).click();
check(/Até 5 MB e 18 megapixels/.test(await page.getByRole('dialog').innerText()), 'a tela de envio anuncia o limite prático de 18 megapixels');
await page.getByRole('dialog').getByRole('button', { name: 'Cancelar' }).click(); await page.waitForTimeout(300);

// detalhes, edição, variantes
await page.locator('.media__thumb').first().click();
let dialog = page.getByRole('dialog'); await dialog.waitFor();
const detail = await dialog.innerText();
check(/1600/.test(detail) && /w1280|1280/.test(detail), 'detalhes mostram dimensões e variantes');
check(!/\/app\/|storage\/app|\.jpg/.test(detail.replace(/Baixar original/, '')), 'detalhes não expõem caminhos privados');
await dialog.getByRole('textbox', { name: 'Texto alternativo' }).fill('Fachada nova'); await dialog.getByRole('button', { name: 'Salvar' }).click(); await settle(page);
check((await page.locator('.media__card').first().innerText()).length > 0 && await page.getByLabel(/Fachada nova/).count() === 1, 'edição do texto alternativo persiste');
await page.screenshot({ path: `${shots}/02-lista-1440.png`, fullPage: true });

// filtros
await page.getByRole('searchbox').fill('inexistente'); await page.getByRole('button', { name: 'Buscar' }).click(); await settle(page);
check(await page.locator('[data-tour=media-empty]').count() === 1, 'filtro sem resultado mostra a mensagem de filtro');
await open(page, company);

// exclusão
await page.locator('.media__thumb').first().click(); dialog = page.getByRole('dialog'); await dialog.waitFor();
await dialog.getByRole('button', { name: 'Excluir imagem' }).click();
await dialog.getByRole('button', { name: 'Excluir', exact: true }).click(); await settle(page);
check(await page.locator('.media__card').count() === 0, 'exclusão de mídia sem uso remove o cartão');

// 2. Unidade: biblioteca local + corporativa só leitura -----------------------------------------------------------
await upload(page, 'png-alpha.png', { purpose: 'Logotipo', alt: 'Logo' });
const gui = await session('gui@s08.test');
await open(gui.page, unit('gartan-roupas-guanambi'));
await upload(gui.page, 'jpeg-pequeno-300x200.jpg', { purpose: 'Imagem de galeria', alt: 'Vitrine' }); await settle(gui.page);
const cards = await gui.page.locator('.media__card').allInnerTexts();
check(cards.length === 2 && cards.some((c) => c.includes('Corporativa')) && cards.some((c) => c.includes('Guanambi')), 'unidade vê a corporativa e a sua local');
await gui.page.locator('.media__card', { hasText: 'Corporativa' }).locator('.media__thumb').click();
dialog = gui.page.getByRole('dialog'); await dialog.waitFor();
check(await dialog.getByRole('button', { name: 'Excluir imagem' }).count() === 0 && /biblioteca corporativa/.test(await dialog.innerText()), 'unidade não edita nem exclui a mídia corporativa');
await gui.page.keyboard.press('Escape'); await gui.page.waitForTimeout(300);
await gui.page.goto(unit('gartan-roupas-caetite')); await settle(gui.page);
check(await gui.page.getByRole('heading', { name: 'Mídia' }).count() === 0 || /404|indispon|não encontr/i.test(await gui.page.locator('body').innerText()), 'unidade não abre a biblioteca de outra unidade');

// 3. Consulta (sem botão de envio) --------------------------------------------------------------------------------
const viewer = await session('viewer@s08.test');
await open(viewer.page, company);
check(await viewer.page.getByRole('button', { name: 'Enviar imagem' }).count() === 0 && await viewer.page.locator('.media__card').count() >= 1, 'usuário de consulta lista e não vê Enviar imagem');

// 4. Teclado ------------------------------------------------------------------------------------------------------
await open(page, company);
await page.locator('.media__thumb').first().focus(); await page.keyboard.press('Enter'); await page.waitForTimeout(500);
check(await page.getByRole('dialog').count() === 1, 'Enter abre os detalhes pelo teclado');
check(await page.evaluate(() => !!document.activeElement.closest('[role=dialog]')), 'foco entra no modal');
await page.keyboard.press('Escape'); await page.waitForTimeout(400);
check(await page.getByRole('dialog').count() === 0, 'Escape fecha o modal');

// 5. Larguras: lista, modal de envio e detalhes sem rolagem horizontal ---------------------------------------------
for (const width of [1440, 1024, 768, 390, 320]) {
  await page.setViewportSize({ width, height: 800 }); await open(page, company);
  check(await overflow(page) <= 0, `${width} px: lista sem rolagem horizontal`);
  await page.screenshot({ path: `${shots}/lista-${width}.png`, fullPage: true });
  await page.getByRole('button', { name: 'Enviar imagem' }).click(); await page.getByRole('dialog').waitFor();
  const box = await page.getByRole('dialog').boundingBox();
  check(box && box.x >= 0 && box.x + box.width <= width + 1 && await overflow(page) <= 0, `${width} px: modal de envio cabe na tela`);
  await page.waitForTimeout(700);
  await page.screenshot({ path: `${shots}/envio-${width}.png` });
  await page.getByRole('dialog').getByRole('button', { name: 'Cancelar' }).click(); await page.waitForTimeout(300);
  await page.locator('.media__thumb').first().click(); await page.getByRole('dialog').waitFor();
  const box2 = await page.getByRole('dialog').boundingBox();
  check(box2 && box2.x >= 0 && box2.x + box2.width <= width + 1, `${width} px: modal de detalhes cabe na tela`);
  await page.waitForTimeout(700);
  await page.screenshot({ path: `${shots}/detalhes-${width}.png` });
  await page.keyboard.press('Escape'); await page.waitForTimeout(300);
}

// 6. Ajuda e tour ---------------------------------------------------------------------------------------------------
await page.setViewportSize({ width: 1440, height: 900 }); await open(page, company);
await page.getByRole('button', { name: 'Tutorial desta tela' }).click(); await page.waitForTimeout(800);
const tour = page.getByRole('dialog');
check(await tour.count() > 0, 'o botão Tutorial desta tela abre o tour guiado');
const tourText = (await tour.first().innerText()).replace(/\s+/g, ' ');
check(/Mídia|imagens|biblioteca/i.test(tourText), `o tour fala da biblioteca: ${tourText.slice(0, 70)}`);
await page.keyboard.press('Escape'); await page.waitForTimeout(400);
const help = await page.request.get(`${A}/ajuda/midia`, { headers: { Accept: "application/json" } }).then((r) => r.status());
check(help === 200, `Central de Ajuda serve o artigo de mídia (HTTP ${help})`);

const failed = results.filter(([ok]) => !ok);
console.log(`${results.length} verificações, ${failed.length} falhas`);
failed.forEach(([, m]) => console.log(' -', m));
console.log(problems.length ? 'PROBLEMAS:\n' + [...new Set(problems)].join('\n') : 'sem erro de JavaScript nem resposta 5xx');
await browser.close();
