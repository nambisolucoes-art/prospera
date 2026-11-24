const { chromium } = await import(
    process.env.E09_PLAYWRIGHT ||
        "/tmp/prospera-e09-browser/node_modules/playwright/index.mjs"
);
import assert from "node:assert/strict";
const browser = await chromium.launch({ args: ["--no-sandbox"] });
const context = await browser.newContext();
const a = await context.newPage();
const errors = [];
a.on("pageerror", (e) => errors.push(e.message));
const root = process.env.E09_URL || "http://127.0.0.1:18099";
await a.goto(root + "/login", { waitUntil: "domcontentloaded" });
await a.locator("input[name=email]").fill("multi@e09.test");
await a.locator("input[name=password]").fill("password");
await a.locator("button[type=submit]").click();
await a.waitForURL("**/contas");
const b = await context.newPage();
b.on("pageerror", (e) => errors.push(e.message));
await a.goto(root + "/contas/laboratorio-conta-1/etiquetas", {
    waitUntil: "domcontentloaded",
});
await b.goto(root + "/contas/laboratorio-conta-2/etiquetas", {
    waitUntil: "domcontentloaded",
});
await a
    .getByRole("button", { name: "Cadastrar etiqueta", exact: true })
    .waitFor();
assert.equal(
    await b
        .getByRole("button", { name: "Cadastrar etiqueta", exact: true })
        .count(),
    0,
);
console.log(
    await a
        .getByRole("navigation", { name: "Paginação" })
        .getByRole("link")
        .evaluateAll((xs) =>
            xs.map((x) => ({
                label: x.getAttribute("aria-label"),
                href: x.href,
            })),
        ),
);
await a.getByRole("link", { name: "Próxima página", exact: true }).click();
await a.waitForURL((u) => u.searchParams.get("page") === "2");
await a.reload({ waitUntil: "domcontentloaded" });
await a
    .getByRole("button", { name: /Visualizar etiqueta/ })
    .first()
    .click();
await a.getByRole("dialog").waitFor();
await a.keyboard.press("Escape");
await a
    .getByRole("button", { name: "Cadastrar etiqueta", exact: true })
    .click();
await a.getByRole("dialog", { name: "Cadastrar etiqueta" }).waitFor();
await a.locator("input[name=name]").fill("Etiqueta pela aba A " + Date.now());
await a.getByRole("button", { name: "Salvar", exact: true }).click();
await a
    .getByRole("dialog", { name: "Cadastrar etiqueta" })
    .waitFor({ state: "hidden" });
await b.reload({ waitUntil: "domcontentloaded" });
assert.equal(
    await b
        .getByRole("button", { name: "Cadastrar etiqueta", exact: true })
        .count(),
    0,
);
await a.goto(root + "/contas/laboratorio-conta-1/organizacao", {
    waitUntil: "domcontentloaded",
});
await a.goBack({ waitUntil: "domcontentloaded" });
assert.match(a.url(), /contas\/laboratorio-conta-1\/etiquetas/);
assert.match(b.url(), /contas\/laboratorio-conta-2\/etiquetas/);
assert.deepEqual(errors, []);
console.log(
    JSON.stringify({
        pagination: true,
        preview: true,
        modal_save: true,
        reload: true,
        history: true,
        separate_tabs: true,
        errors,
    }),
);
await browser.close();
