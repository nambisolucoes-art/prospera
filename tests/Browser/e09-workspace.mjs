const { chromium } = await import(
    process.env.E09_PLAYWRIGHT ||
        "/tmp/prospera-e09-browser/node_modules/playwright/index.mjs"
);
import assert from "node:assert/strict";
const browser = await chromium.launch({ args: ["--no-sandbox"] });
const context = await browser.newContext();
const page = await context.newPage();
const errors = [];
page.on("pageerror", (e) => errors.push(e.message));
const root = process.env.E09_URL || "http://127.0.0.1:18099";
await page.goto(root + "/login", { waitUntil: "domcontentloaded" });
await page.locator("input[name=email]").fill("multi@e09.test");
await page.locator("input[name=password]").fill("password");
await page.locator("button[type=submit]").click();
await page.waitForURL("**/contas");
for (const width of [1440, 1024, 768, 390, 320]) {
    await page.setViewportSize({ width, height: 1000 });
    await page.goto(root + "/contas/laboratorio-conta-1/etiquetas", {
        waitUntil: "domcontentloaded",
    });
    await page.getByLabel(/^Menu de/).click();
    await page.getByText("Trocar de conta", { exact: true }).waitFor();
    await page.waitForTimeout(300);
    await page.screenshot({
        path: `/tmp/prospera-e09-evidence/avatar-${width}.png`,
        fullPage: true,
    });
    await page.keyboard.press("Escape");
    await page.getByLabel(/^Área de trabalho,/).click();
    await page
        .getByRole("dialog", { name: "Área de trabalho", exact: true })
        .waitFor();
    await page.waitForTimeout(300);
    await page.screenshot({
        path: `/tmp/prospera-e09-evidence/workspace-${width}.png`,
        fullPage: true,
    });
    assert.equal(
        await page.evaluate(
            () => document.documentElement.scrollWidth > innerWidth,
        ),
        false,
    );
    await page.keyboard.press("Escape");
    await page
        .getByRole("button", { name: "Tutorial desta tela", exact: true })
        .click();
    await page.waitForTimeout(300);
    await page.screenshot({
        path: `/tmp/prospera-e09-evidence/tour-${width}.png`,
        fullPage: true,
    });
    await page.keyboard.press("Escape");
}
await page.getByLabel(/^Área de trabalho,/).click();
await page
    .getByRole("checkbox", { name: "Selecionar todas", exact: true })
    .check();
await page.getByText("Abrir área de trabalho", { exact: false }).click();
await page.waitForURL((u) => u.pathname.includes("/estabelecimento/"));
const a = await context.request.get(
    root + "/contas/laboratorio-conta-1/area-de-trabalho/padrao",
    { headers: { Accept: "application/json" } },
);
const b = await context.request.get(
    root + "/contas/laboratorio-conta-2/area-de-trabalho/padrao",
    { headers: { Accept: "application/json" } },
);
console.log(
    JSON.stringify({
        widths: [1440, 1024, 768, 390, 320],
        workspace: await a.json(),
        other_account: await b.json(),
        url: page.url(),
        errors,
    }),
);
assert.deepEqual(errors, []);
await browser.close();
