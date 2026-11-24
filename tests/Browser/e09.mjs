const { chromium } = await import(
    process.env.E09_PLAYWRIGHT ||
        "/tmp/prospera-e09-browser/node_modules/playwright/index.mjs"
);
import assert from "node:assert/strict";
const root = process.env.E09_URL || "http://127.0.0.1:18099";
const browser = await chromium.launch({ args: ["--no-sandbox"] });
const evidence = [];
const errors = [];
async function session(email) {
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
async function api(context, path, method = "GET", data) {
    const cookies = await context.cookies();
    const xsrf = decodeURIComponent(
        cookies.find((c) => c.name === "XSRF-TOKEN")?.value || "",
    );
    const response = await context.request.fetch(root + path, {
        method,
        data,
        headers: { Accept: "application/json", "X-XSRF-TOKEN": xsrf },
        maxRedirects: 0,
    });
    const text = await response.text();
    let body;
    try {
        body = JSON.parse(text);
    } catch {
        body = text.slice(0, 200);
    }
    return { status: response.status(), body };
}
const { context, page } = await session("multi@e09.test");
assert.equal(new URL(page.url()).pathname, "/contas");
const a = "/contas/laboratorio-conta-1",
    b = "/contas/laboratorio-conta-2";
const second = await context.newPage();
second.on("pageerror", (e) => errors.push(e.message));
for (let round = 0; round < 4; round++) {
    for (const [tab, path, allowed] of [
        [page, a, true],
        [second, b, false],
    ]) {
        await tab.goto(root + path + "/etiquetas", {
            waitUntil: "domcontentloaded",
        });
        await tab.waitForTimeout(250);
        const result = await api(
            context,
            path + "/etiquetas?search=teste&page=2",
        );
        assert.equal(result.status, 200);
        assert.equal(result.body.account_context.base, path);
        assert.equal(
            result.body.auth.permissions.includes("account.tags.create"),
            allowed,
        );
        const write = await api(context, path + "/etiquetas", "POST", {
            name: `E09 ${Date.now()} round ${round}`,
            module: "customers",
            color: "#3563e9",
            active: true,
        });
        assert.equal(write.status, allowed ? 200 : 403);
        const denied = await api(context, path + "/usuarios");
        assert.equal(denied.status, allowed ? 200 : 403);
        const units = await api(context, path);
        assert.equal(units.status, 200);
        evidence.push({
            round,
            path,
            read: result.status,
            write: write.status,
            permissions: result.body.auth.permissions.length,
        });
    }
}
for (const width of [1440, 1024, 768, 390, 320]) {
    await page.setViewportSize({ width, height: 1000 });
    for (const path of [
        "/contas",
        a + "/etiquetas",
        a + "/organizacao",
        "/ajuda",
    ]) {
        await page.goto(root + path, { waitUntil: "domcontentloaded" });
        await page.waitForTimeout(600);
        assert.equal(
            await page.evaluate(
                () => document.documentElement.scrollWidth > innerWidth,
            ),
            false,
            `overflow ${width} ${path}`,
        );
        await page.screenshot({
            path: `/tmp/prospera-e09-evidence/visual-${width}-${path.split("/").at(-1)}.png`,
            fullPage: true,
        });
        evidence.push({ width, path, overflow: false });
    }
}
for (const [email, count] of [
    ["single@e09.test", 1],
    ["five@e09.test", 5],
    ["none@e09.test", 0],
    ["suspended@e09.test", 0],
]) {
    const s = await session(email);
    const result = await api(s.context, "/contas");
    if (count === 1) {
        assert.match(result.body.redirect, /contas\/laboratorio-conta-1/);
    } else {
        assert.equal(result.status, 200);
        assert.equal(
            result.body.accounts.filter((a) => a.status === "active").length,
            count,
        );
    }
    evidence.push({ email, count, url: s.page.url() });
    await s.context.close();
}
for (const [state, token] of [
    ["expired", "2"],
    ["revoked", "3"],
]) {
    const r = await session(
        state === "expired" ? "expired@e09.test" : "recipient@e09.test",
    );
    const path = "/convites/" + token.repeat(64);
    const result = await api(r.context, path);
    assert.equal(result.body.invitation, null);
    for (const width of [1440, 1024, 768, 390, 320]) {
        await r.page.setViewportSize({ width, height: 1000 });
        await r.page.goto(root + path, { waitUntil: "domcontentloaded" });
        await r.page.waitForTimeout(400);
        await r.page.screenshot({
            path: `/tmp/prospera-e09-evidence/invite-${state}-${width}.png`,
            fullPage: true,
        });
    }
    evidence.push({ state, available: false });
}
assert.deepEqual(errors, []);
console.log(JSON.stringify({ evidence, errors }, null, 2));
await browser.close();
