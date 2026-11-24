const { chromium } = await import(
    process.env.E09_PLAYWRIGHT ||
        "/tmp/prospera-e09-browser/node_modules/playwright/index.mjs"
);
import assert from "node:assert/strict";

// Estados-limite da E09: cargo só com a Ajuda e limite de leitura de convites atingido.
// Requer e09-ui-fixtures.php (help-only@e09.test). Não usa token real: um token inexistente basta.
const root = process.env.E09_URL || "http://127.0.0.1:18099";
const account = process.env.E09_ACCOUNT || "laboratorio-conta-1";
const widths = [1440, 1024, 768, 390, 320];
const browser = await chromium.launch({ args: ["--no-sandbox"] });
const errors = [];
const result = { help_only: [], rate_limit: [] };

async function signIn(email) {
    const context = await browser.newContext();
    const page = await context.newPage();
    page.on("pageerror", (e) => errors.push(e.message));
    await page.goto(root + "/login", { waitUntil: "domcontentloaded" });
    await page.locator("input[name=email]").fill(email);
    await page.locator("input[name=password]").fill("password");
    await page.locator("button[type=submit]").click();
    await page.waitForURL((u) => !u.pathname.includes("login"));
    return { context, page };
}

async function noOverflow(page) {
    return page.evaluate(() => document.documentElement.scrollWidth <= innerWidth);
}

const help = await signIn("help-only@e09.test");
for (const width of widths) {
    await help.page.setViewportSize({ width, height: 900 });
    await help.page.goto(`${root}/contas/${account}`, { waitUntil: "domcontentloaded" });
    await help.page.waitForURL(`**/contas/${account}/ajuda`);
    await help.page.getByRole("heading", { name: /Central de Ajuda/ }).first().waitFor();
    assert.equal(await noOverflow(help.page), true);
    await help.page.screenshot({ path: `/tmp/prospera-e09-evidence/help-only-${width}.png`, fullPage: true });
    result.help_only.push({ width, url: new URL(help.page.url()).pathname });
}
await help.context.close();

const limited = await signIn("none@e09.test");
const path = `${root}/convites/${"x".repeat(64)}`;
for (let view = 0; view < 12; view++) {
    await limited.page.goto(path, { waitUntil: "domcontentloaded" });
}
for (const width of widths) {
    await limited.page.setViewportSize({ width, height: 900 });
    const response = await limited.page.goto(path, { waitUntil: "domcontentloaded" });
    assert.equal(response.status(), 429);
    await limited.page.getByText("Muitas tentativas em pouco tempo", { exact: false }).first().waitFor();
    const text = await limited.page.locator("body").innerText();
    assert.equal(/Too Many/i.test(text), false);
    assert.equal(await noOverflow(limited.page), true);
    await limited.page.screenshot({ path: `/tmp/prospera-e09-evidence/rate-limit-${width}.png`, fullPage: true });
    result.rate_limit.push({ width, status: 429 });
}
await limited.context.close();

assert.deepEqual(errors, []);
console.log(JSON.stringify({ ...result, errors }));
await browser.close();
