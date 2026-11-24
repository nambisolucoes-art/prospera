// E10: validação visual e de interação no Chromium, com login real e dados criados pelas jornadas HTTP.
// Uso: E10_URL=http://127.0.0.1:18110 E10_MAIL=http://<ip-do-mailpit>:8025 node tests/Browser/e10.mjs
const { chromium } = await import(
    process.env.E10_PLAYWRIGHT || "/tmp/prospera-e10-browser/node_modules/playwright/index.mjs"
);
import { mkdirSync, readFileSync, writeFileSync } from "node:fs";

const root = process.env.E10_URL || "http://127.0.0.1:18110";
const mail = process.env.E10_MAIL;
const out = process.env.E10_SHOTS || "/tmp/prospera-e10/evidence/navegador";
const password = "Sintetica-E10-senha";
// Estado das jornadas HTTP (IDs de cargos), copiado de /tmp/e10-state.json do laboratório.
const state = JSON.parse(readFileSync(process.env.E10_STATE || "/tmp/prospera-e10/state.json", "utf8"));
const widths = [1440, 1024, 768, 390, 320];
mkdirSync(out, { recursive: true });

const browser = await chromium.launch({
    args: ["--no-sandbox"],
    executablePath: process.env.E10_CHROMIUM || undefined,
});
const results = [];
const jsErrors = [];
const serverErrors = [];

function record(check, passed, detail = null) {
    // Tokens de convite nunca vão para a evidência, mesmo sintéticos e já consumidos.
    detail = detail === null ? null : JSON.parse(JSON.stringify(detail).replace(/\/convites\/[A-Za-z0-9]{64}/g, "/convites/{token}"));
    results.push({ check, passed, detail });
    if (!passed) console.error("[FALHA]", check, JSON.stringify(detail));
}

async function session(email, secret = password) {
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await context.newPage();
    watch(page, email);
    await page.goto(root + "/login", { waitUntil: "domcontentloaded" });
    await page.locator("input[name=email]").fill(email);
    await page.locator("input[name=password]").fill(secret);
    await page.locator("button[type=submit]").click();
    await page.waitForURL((u) => !u.pathname.includes("login"), { timeout: 15000 });
    return { context, page };
}

function watch(page, who) {
    page.on("pageerror", (e) => jsErrors.push({ who, url: page.url(), message: e.message }));
    page.on("response", (r) => {
        if (r.status() >= 500) serverErrors.push({ who, url: r.url(), status: r.status() });
    });
}

async function api(context, path, method = "GET", data) {
    const cookies = await context.cookies();
    const xsrf = decodeURIComponent(cookies.find((c) => c.name === "XSRF-TOKEN")?.value || "");
    const response = await context.request.fetch(root + path, {
        method,
        data,
        headers: { Accept: "application/json", "X-XSRF-TOKEN": xsrf },
        maxRedirects: 0,
    });
    let body;
    try {
        body = await response.json();
    } catch {
        body = null;
    }
    return { status: response.status(), body };
}

async function settle(page) {
    await page.waitForLoadState("networkidle", { timeout: 15000 }).catch(() => {});
    await page.waitForTimeout(350);
}

async function overflow(page) {
    return page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
}

// Varredura: cada tela em cada largura, sem rolagem horizontal, sem erro JavaScript e sem 5xx.
async function sweep(page, label, path) {
    for (const width of widths) {
        await page.setViewportSize({ width, height: width <= 390 ? 780 : 900 });
        await page.goto(root + path, { waitUntil: "domcontentloaded" });
        await settle(page);
        const extra = await overflow(page);
        const text = (await page.locator("body").innerText()).trim();
        record(`${label} ${width}px sem rolagem horizontal`, extra <= 0, { extra, url: page.url() });
        record(`${label} ${width}px renderizada`, text.length > 20, { url: page.url() });
        if (width === 1440 || width === 390 || width === 320) {
            await page.screenshot({ path: `${out}/${label}-${width}.png`, fullPage: false });
        }
    }
}

// Identidades e dados vêm das jornadas HTTP (tests/Performance/e10).
const ana = await session("ana@gartan.e10.test");
const accounts = (await api(ana.context, "/contas")).body.accounts;
const byName = (part) => accounts.find((a) => a.name.includes(part)).code;
const A = "/contas/" + byName("Gartan");
const B = "/contas/" + byName("Outra");
record("Seleção com três contas após o login", accounts.length === 3, accounts.map((a) => a.code));
record("Login com várias contas leva à seleção", new URL(ana.page.url()).pathname === "/contas", ana.page.url());

const anonymous = await browser.newContext();
const loginPage = await anonymous.newPage();
watch(loginPage, "anonimo");
await sweep(loginPage, "login", "/login");

await sweep(ana.page, "selecao-contas", "/contas");
const organization = (await api(ana.context, A + "/etiquetas")).body.organization_context;
const units = (organization?.establishments || organization?.units || []).map((u) => u.code);
const screens = [
    ["inicio-conta-a", A],
    ["empresas", A + "/empresas"],
    ["estabelecimentos", A + "/estabelecimentos"],
    ["organizacao", A + "/organizacao"],
    ["clientes-consolidado", A + "/estabelecimento/todos/clientes?unidades=" + units.join(",")],
    ["fornecedores-consolidado", A + "/estabelecimento/todos/fornecedores?unidades=" + units.join(",")],
    ["usuarios", A + "/usuarios"],
    ["cargos", A + "/cargos"],
    ["cargo-cadastro", A + "/cargos/cadastrar"],
    ["etiquetas", A + "/etiquetas"],
    ["auditoria", A + "/auditoria"],
    ["ajuda", A + "/ajuda"],
    ["estabelecimento-cadastro", A + "/estabelecimentos/cadastrar"],
];
for (const [label, path] of screens) {
    await sweep(ana.page, label, path);
}

// Estado vazio: filtro sem resultados.
await ana.page.setViewportSize({ width: 390, height: 780 });
await ana.page.goto(root + A + "/etiquetas?search=inexistente-e10", { waitUntil: "domcontentloaded" });
await settle(ana.page);
await ana.page.screenshot({ path: `${out}/etiquetas-vazio-390.png` });
record("Estado vazio sem rolagem em 390px", (await overflow(ana.page)) <= 0);

// Interações em desktop e celular.
for (const width of [1440, 390, 320]) {
    const page = ana.page;
    await page.setViewportSize({ width, height: width <= 390 ? 780 : 900 });
    await page.goto(root + A + "/etiquetas", { waitUntil: "domcontentloaded" });
    await settle(page);

    // Menu do avatar com troca de conta.
    await page.getByLabel(/^Menu de/).click();
    const switcher = page.getByText("Trocar de conta", { exact: true });
    await switcher.waitFor({ timeout: 5000 }).catch(() => {});
    record(`Avatar com Trocar de conta (${width}px)`, await switcher.isVisible());
    await page.waitForTimeout(400);
    await page.screenshot({ path: `${out}/avatar-${width}.png` });
    await page.keyboard.press("Escape");

    // Área de Trabalho: todas as unidades e Abrir área de trabalho.
    const selector = page.getByLabel(/^Área de trabalho,/);
    if (await selector.count()) {
        await selector.click();
        const dialog = page.getByRole("dialog", { name: "Área de trabalho", exact: true });
        await dialog.waitFor({ timeout: 5000 });
        await page.waitForTimeout(300);
        await page.screenshot({ path: `${out}/area-de-trabalho-${width}.png` });
        record(`Seletor da Área de Trabalho sem rolagem (${width}px)`, (await overflow(page)) <= 0);
        await page.getByRole("checkbox", { name: "Selecionar todas", exact: true }).check().catch(() => {});
        await page.getByText("Abrir área de trabalho", { exact: false }).click();
        await page.waitForURL((u) => u.pathname.includes("/estabelecimento"), { timeout: 10000 }).catch(() => {});
        record(`Abrir área de trabalho navega para o contexto (${width}px)`, page.url().includes("/estabelecimento"), page.url());
        await settle(page);
    } else {
        record(`Seletor da Área de Trabalho presente (${width}px)`, false);
    }

    // Tour da tela: abre e fecha com Escape.
    await page.goto(root + A + "/etiquetas", { waitUntil: "domcontentloaded" });
    await settle(page);
    const tour = page.getByRole("button", { name: "Tutorial desta tela", exact: true });
    if (await tour.count()) {
        await tour.click();
        await page.waitForTimeout(600);
        await page.screenshot({ path: `${out}/tour-${width}.png` });
        record(`Tour abre sem rolagem horizontal (${width}px)`, (await overflow(page)) <= 0);
        await page.keyboard.press("Escape");
        await page.waitForTimeout(300);
    } else {
        record(`Botão do tour presente (${width}px)`, false);
    }

    // Modal com erro de validação, Cancelar branco e Salvar com toast.
    await page.getByRole("button", { name: "Cadastrar etiqueta", exact: true }).first().click();
    const modal = page.getByRole("dialog", { name: "Cadastrar etiqueta" });
    await modal.waitFor();
    await page.getByRole("button", { name: "Salvar", exact: true }).click();
    await page.waitForTimeout(500);
    const invalid = await modal.locator(".invalid-feedback, [role=alert], .is-invalid").count();
    record(`Erro de validação visível no modal (${width}px)`, invalid > 0, invalid);
    await page.screenshot({ path: `${out}/etiqueta-erro-${width}.png` });
    record(`Modal sem rolagem horizontal (${width}px)`, (await overflow(page)) <= 0);
    await page.locator("input[name=name]").fill(`Etiqueta navegador ${width} ${Date.now() % 100000}`);
    await page.getByRole("button", { name: "Salvar", exact: true }).click();
    await modal.waitFor({ state: "hidden", timeout: 10000 }).catch(() => {});
    const toast = page.locator(".notificationToast").first();
    await toast.waitFor({ timeout: 5000 }).catch(() => {});
    record(`Toast de sucesso após salvar (${width}px)`, await toast.isVisible().catch(() => false));
    await page.screenshot({ path: `${out}/etiqueta-salva-${width}.png` });

    // Teclado: o foco percorre controles visíveis.
    await page.keyboard.press("Tab");
    await page.keyboard.press("Tab");
    await page.keyboard.press("Tab");
    const focused = await page.evaluate(() => {
        const el = document.activeElement;
        if (!el || el === document.body) return null;
        const box = el.getBoundingClientRect();
        return { tag: el.tagName, visible: box.width > 0 && box.height > 0 };
    });
    record(`Navegação por teclado alcança controle visível (${width}px)`, focused?.visible === true, focused);
}

// Operadora de uma unidade na Conta B: sem seletor múltiplo, sem botão de cadastro.
const olga = await session("olga@outra.e10.test");
await sweep(olga.page, "operadora-b-etiquetas", B + "/etiquetas");
record("Operadora sem Cadastrar etiqueta", (await olga.page.getByRole("button", { name: "Cadastrar etiqueta", exact: true }).count()) === 0);

// Tour de Etiquetas percorrido inteiro por quem só consulta: passos de cadastro ficam fora (availableWith).
for (const width of [1440, 320]) {
    await olga.page.setViewportSize({ width, height: width <= 390 ? 780 : 900 });
    await olga.page.goto(root + B + "/etiquetas", { waitUntil: "domcontentloaded" });
    await settle(olga.page);
    await olga.page.getByRole("button", { name: "Tutorial desta tela", exact: true }).click();
    let steps = 0;
    for (; steps < 25; steps++) {
        const next = olga.page.getByRole("button", { name: /^(Próximo|Concluir)$/ }).last();
        if (!(await next.isVisible().catch(() => false))) break;
        const label = (await next.innerText()).trim();
        await next.click();
        await olga.page.waitForTimeout(350);
        record(`Tour somente consulta sem rolagem (passo ${steps + 1}, ${width}px)`, (await overflow(olga.page)) <= 0);
        if (label === "Concluir") {
            steps++;
            break;
        }
    }
    record(`Tour de Etiquetas concluído pela operadora (${width}px)`, steps > 3, steps);
    record(`Tour não abriu cadastro para quem não pode cadastrar (${width}px)`,
        (await olga.page.getByRole("dialog", { name: "Cadastrar etiqueta" }).count()) === 0);
    await olga.page.keyboard.press("Escape");
}
const olgaHelp = await api(olga.context, B + "/ajuda");
record("Artigo de Etiquetas visível na ajuda da operadora", JSON.stringify(olgaHelp.body || {}).includes("etiquetas"), olgaHelp.status);

// Cargo de unidade lendo o catálogo de etiquetas (correção da E10).
const manager = await session("unidade@gartan.e10.test");
await sweep(manager.page, "gerente-unidade-auditoria", A + "/auditoria");
const managerTags = await api(manager.context, A + "/etiquetas");
record("Cargo de unidade sem a permissão continua sem Etiquetas", managerTags.status === 403, managerTags.status);
const olgaTags = await api(olga.context, B + "/etiquetas");
record("Cargo de unidade com a permissão lista Etiquetas (correção E10)", olgaTags.status === 200
    && (olgaTags.body?.auth?.permissions || []).includes("account.tags.list"), olgaTags.status);

// Plataforma e inspeção.
const platform = await session("platform@e10.test", "password");
await sweep(platform.page, "plataforma-contas", "/saas/contas");
await sweep(platform.page, "plataforma-auditoria", "/saas/auditoria");
const accountA = (await api(platform.context, "/saas/contas")).body.accounts.data.find((a) => a.name.includes("Gartan"));
const representable = await api(platform.context, `/saas/contas/${accountA.id}/inspecao/usuarios`);
const carlosOption = (representable.body.users || representable.body).find((u) => (u.email || "").startsWith("carlos@"));
const start = await api(platform.context, `/saas/contas/${accountA.id}/inspecao`, "POST", { user_id: carlosOption.id, mode: "read-only" });
record("Inspeção iniciada pelo navegador da plataforma", [200, 302].includes(start.status), start.status);
await sweep(platform.page, "inspecao-clientes", A + "/estabelecimento/todos/clientes");
const banner = await platform.page.locator(".account-inspection-banner").count();
await platform.page.setViewportSize({ width: 320, height: 780 });
await platform.page.screenshot({ path: `${out}/inspecao-aviso-320.png` });
record("Aviso de inspeção visível", banner > 0, banner);
await api(platform.context, "/inspecao/encerrar", "POST", {});

// Convite: identidade da A convidada pela B, aceite no navegador.
if (mail) {
    const bruno = await session("bruno@outra.e10.test");
    const email = `navegador-${Date.now() % 1000000}@gartan.e10.test`;
    await api(ana.context, A + "/usuarios", "POST", {
        name: "Navegador", email, mode: "manual", password, password_confirmation: password,
        role_id: state.roles.A["Somente Ajuda"], scope: "tenant",
    });
    await api(bruno.context, B + "/usuarios", "POST", {
        name: "Navegador", email, mode: "invite", role_id: state.roles.B["Operador B"], scope: "tenant",
    });
    const search = await (await fetch(`${mail}/api/v1/search?query=${encodeURIComponent(`to:"${email}"`)}`)).json();
    const message = await (await fetch(`${mail}/api/v1/message/${search.messages[0].ID}`)).json();
    const token = (message.Text + message.HTML).match(/\/convites\/([A-Za-z0-9]{64})/)?.[1];
    record("Convite entregue ao Mailpit isolado", Boolean(token));
    const guest = await session(email);
    await sweep(guest.page, "convite", "/convites/" + token);
    await guest.page.setViewportSize({ width: 390, height: 780 });
    await guest.page.goto(root + "/convites/" + token, { waitUntil: "domcontentloaded" });
    await settle(guest.page);
    await guest.page.getByRole("button", { name: "Salvar", exact: true }).click();
    await guest.page.locator(".notificationToast").first().waitFor({ timeout: 8000 }).catch(() => {});
    await guest.page.waitForTimeout(500);
    await guest.page.screenshot({ path: `${out}/convite-aceito-390.png` });
    record("Aceite leva à conta da B", guest.page.url().includes(B), guest.page.url());
    // O limitador real de leitura de convites (20 por minuto; abertura direta conta duas) segue ativo.
    await guest.page.waitForTimeout(61000);
    await sweep(guest.page, "convite-reutilizado", "/convites/" + token);
    record("Convite reutilizado informa indisponível", (await guest.page.getByText("não está disponível", { exact: false }).count()) > 0);
}

// Duas abas da mesma sessão: Conta A (administradora, várias unidades) e Conta B (operadora, uma unidade).
const tabs = await session("ana@gartan.e10.test");
const tabA = tabs.page;
const tabB = await tabs.context.newPage();
watch(tabB, "aba-b");
await tabA.goto(root + A + "/estabelecimento/todos/clientes?unidades=" + units.join(","), { waitUntil: "domcontentloaded" });
await tabB.goto(root + B + "/etiquetas", { waitUntil: "domcontentloaded" });
await settle(tabA);
await settle(tabB);
for (let round = 0; round < 3; round++) {
    await tabA.goto(root + A + "/etiquetas?search=Cliente", { waitUntil: "domcontentloaded" });
    await tabB.goto(root + B + "/etiquetas?search=E", { waitUntil: "domcontentloaded" });
    await settle(tabA);
    await settle(tabB);
    record(`Rodada ${round}: aba A continua na Conta A`, tabA.url().includes(A));
    record(`Rodada ${round}: aba B continua na Conta B`, tabB.url().includes(B));
    record(`Rodada ${round}: aba A pode cadastrar`, (await tabA.getByRole("button", { name: "Cadastrar etiqueta", exact: true }).count()) > 0);
    record(`Rodada ${round}: aba B não pode cadastrar`, (await tabB.getByRole("button", { name: "Cadastrar etiqueta", exact: true }).count()) === 0);
}
await tabA.getByRole("button", { name: "Cadastrar etiqueta", exact: true }).first().click();
await tabA.locator("input[name=name]").fill("Aba A " + (Date.now() % 100000));
await tabB.reload({ waitUntil: "domcontentloaded" });
await settle(tabB);
await tabA.getByRole("button", { name: "Salvar", exact: true }).click();
await tabA.getByRole("dialog", { name: "Cadastrar etiqueta" }).waitFor({ state: "hidden", timeout: 10000 }).catch(() => {});
record("Gravação da aba A depois de recarregar a aba B", tabA.url().includes(A));
await tabB.goBack({ waitUntil: "domcontentloaded" }).catch(() => {});
await settle(tabB);
record("Histórico da aba B permanece na Conta B", tabB.url().includes(B) || tabB.url().endsWith("/contas"), tabB.url());

// Revogação na B enquanto a aba A segue na A: sem troca silenciosa de conta.
const brunoTabs = await session("bruno@outra.e10.test");
const users = (await api(brunoTabs.context, B + "/usuarios")).body.users.data;
const anaRow = users.find((u) => u.email === "ana@gartan.e10.test");
await api(brunoTabs.context, B + `/usuarios/${anaRow.id}/suspender`, "PATCH", {});
await tabB.goto(root + B + "/etiquetas", { waitUntil: "domcontentloaded" });
await settle(tabB);
await tabB.screenshot({ path: `${out}/aba-b-revogada-1440.png` });
record("Aba B revogada não troca para a Conta A", tabB.url().includes(B) && !tabB.url().includes(A), tabB.url());
await tabA.goto(root + A + "/etiquetas", { waitUntil: "domcontentloaded" });
await settle(tabA);
record("Aba A segue operando após a revogação na B", (await tabA.getByRole("button", { name: "Cadastrar etiqueta", exact: true }).count()) > 0);
await api(brunoTabs.context, B + `/usuarios/${anaRow.id}/reativar`, "PATCH", {});

// Troca de unidades na aba A não altera a aba B.
await tabA.goto(root + A + "/estabelecimento/" + units[0] + "/clientes", { waitUntil: "domcontentloaded" });
await tabB.goto(root + B + "/etiquetas", { waitUntil: "domcontentloaded" });
await settle(tabA);
await settle(tabB);
record("Troca de unidade na aba A preserva a aba B", tabB.url().includes(B) && tabA.url().includes("/estabelecimento/" + units[0]));

record("Nenhum erro JavaScript", jsErrors.length === 0, jsErrors.slice(0, 5));
record("Nenhuma resposta 5xx", serverErrors.length === 0, serverErrors.slice(0, 5));

const failed = results.filter((r) => !r.passed);
writeFileSync(`${out}/resultado.json`, JSON.stringify({ checks: results.length, failed: failed.length, results, jsErrors, serverErrors }, null, 2));
console.log(JSON.stringify({ e10: "navegador", checks: results.length, failed: failed.length }));
await browser.close();
process.exit(failed.length ? 1 : 0);
