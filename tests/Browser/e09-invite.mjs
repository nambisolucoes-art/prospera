const { chromium } = await import(
    process.env.E09_PLAYWRIGHT ||
        "/tmp/prospera-e09-browser/node_modules/playwright/index.mjs"
);
import { readFileSync } from "node:fs";
import assert from "node:assert/strict";
const browser = await chromium.launch({ args: ["--no-sandbox"] });
const context = await browser.newContext();
const page = await context.newPage();
const errors = [];
page.on("pageerror", (e) => errors.push(e.message));
const root = process.env.E09_URL || "http://127.0.0.1:18099";
const expected = process.env.E09_ACCOUNT || "laboratorio-conta-2";
await page.goto(root + "/login", { waitUntil: "domcontentloaded" });
await page.locator("input[name=email]").fill("recipient@e09.test");
await page.locator("input[name=password]").fill("password");
await page.locator("button[type=submit]").click();
await page.waitForURL((u) => !u.pathname.includes("login"));
const token = readFileSync(
    process.env.E09_TOKEN_FILE || "/tmp/prospera-e09-browser/invite.txt",
    "utf8",
).trim();
for (const width of [1440, 1024, 768, 390, 320]) {
    await page.setViewportSize({ width, height: 1000 });
    await page.goto(root + "/convites/" + token, {
        waitUntil: "domcontentloaded",
    });
    try {
        await page
            .getByRole("button", { name: "Salvar", exact: true })
            .waitFor({ timeout: 15000 });
    } catch (error) {
        // Registra o estado real da tela antes de falhar, para não mascarar a causa.
        await page.screenshot({
            path: `/tmp/prospera-e09-evidence/invite-failure-${width}.png`,
            fullPage: true,
        });
        console.error(page.url(), (await page.locator("body").innerText()).slice(0, 600));
        throw error;
    }
    assert.equal(
        await page.evaluate(
            () => document.documentElement.scrollWidth > innerWidth,
        ),
        false,
    );
    await page.screenshot({
        path: `/tmp/prospera-e09-evidence/invite-valid-${width}.png`,
        fullPage: true,
    });
}
await page.getByRole("button", { name: "Salvar", exact: true }).click();
await page.waitForURL((u) => u.pathname.includes("/contas/" + expected));
// O aviso entra deslizando; depois de assentado, precisa caber na largura e sair sozinho.
const toast = page.locator(".notificationToast").first();
await toast.getByText("Convite aceito", { exact: false }).waitFor();
await page.waitForTimeout(600);
const box = await toast.boundingBox();
assert.ok(box && box.x >= 0 && box.x + box.width <= 320);
await toast.waitFor({ state: "detached", timeout: 10000 });
await page.getByLabel(/^Menu de/).click();
await page.getByText("Trocar de conta", { exact: true }).waitFor();
await page.screenshot({
    path: "/tmp/prospera-e09-evidence/avatar-320.png",
    fullPage: true,
});
await page.getByText("Trocar de conta", { exact: true }).click();
await page.waitForURL("**/contas");
await page.goto(root + "/convites/" + token, { waitUntil: "domcontentloaded" });
await page
    .getByText("Este convite não está disponível.", { exact: false })
    .waitFor();
assert.deepEqual(errors, []);
console.log(
    JSON.stringify({
        valid_invite_widths: [1440, 1024, 768, 390, 320],
        accepted: true,
        reuse_unavailable: true,
        avatar_switch: true,
        errors,
    }),
);
await browser.close();
