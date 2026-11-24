import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
const { chromium } = await import(
    process.env.E09_PLAYWRIGHT ||
        "/tmp/prospera-e09-browser/node_modules/playwright/index.mjs"
);
const root = process.env.E09_URL || "http://127.0.0.1:18100";
const data = JSON.parse(
    readFileSync(
        process.env.E09_SAMPLES_FILE ||
            "/tmp/prospera-e09-browser/accept-samples.json",
        "utf8",
    ),
);
const browser = await chromium.launch({ args: ["--no-sandbox"] });
const rows = [];
for (const recipient of data) {
    const context = await browser.newContext();
    const client = context.request;
    await client.get(root + "/login");
    const cookie = async () =>
        decodeURIComponent(
            (await context.cookies()).find((c) => c.name === "XSRF-TOKEN")
                .value,
        );
    const login = await client.post(root + "/login", {
        headers: { Accept: "application/json", "X-XSRF-TOKEN": await cookie() },
        data: { email: recipient.email, password: "password" },
        maxRedirects: 0,
    });
    assert.equal(login.status(), 200);
    const start = performance.now();
    const accept = await client.post(root + "/convites/" + recipient.token, {
        headers: { Accept: "application/json", "X-XSRF-TOKEN": await cookie() },
        maxRedirects: 0,
    });
    assert.equal(accept.status(), 200);
    assert.match(
        (await accept.json()).redirect,
        /\/contas\/laboratorio-conta-2$/,
    );
    rows.push({
        ms: performance.now() - start,
        ...JSON.parse(accept.headers()["x-e03-metrics"]),
    });
    await context.close();
}
const times = rows.map((r) => r.ms).sort((a, b) => a - b);
console.log(
    JSON.stringify({
        boundary: "POST autenticado de aceite, HTTP, FrankenPHP e PostgreSQL",
        samples: rows.length,
        median_ms: times[4],
        p95_ms: times[9],
        queries: [...new Set(rows.map((r) => r.queries))],
        duplicates_max: Math.max(...rows.map((r) => r.duplicates)),
        duplicate_sql: [...new Set(rows.flatMap((r) => r.duplicate_sql))],
        peak_delta_bytes_max: Math.max(...rows.map((r) => r.peak_delta_bytes)),
        unexpected: 0,
    }),
);
await browser.close();
