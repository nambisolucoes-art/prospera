const { chromium } = await import(
    process.env.E09_PLAYWRIGHT ||
        "/tmp/prospera-e09-browser/node_modules/playwright/index.mjs"
);
import assert from "node:assert/strict";
const browser = await chromium.launch({ args: ["--no-sandbox"] });
const results = {};
const root = process.env.E09_URL || "http://127.0.0.1:18099";
for (const count of [1, 2, 5]) {
    const rows = [];
    for (let index = 1; index <= 10; index++) {
        const context = await browser.newContext();
        const request = context.request;
        await request.get(root + "/login");
        const csrf = decodeURIComponent(
            (await context.cookies()).find((c) => c.name === "XSRF-TOKEN")
                .value,
        );
        const start = performance.now();
        const response = await request.post(root + "/login", {
            headers: { Accept: "application/json", "X-XSRF-TOKEN": csrf },
            data: {
                email: `login-${count}-${index}@e09.test`,
                password: "password",
            },
            maxRedirects: 0,
        });
        assert.equal(response.status(), 200);
        const login_ms = performance.now() - start;
        const login_metrics = JSON.parse(response.headers()["x-e03-metrics"]);
        let body = await response.json();
        let steps = 1;
        while (body.redirect) {
            const next = await request.get(body.redirect, {
                headers: { Accept: "application/json" },
                maxRedirects: 0,
            });
            assert.equal(next.status(), 200);
            body = await next.json();
            steps++;
            assert.ok(steps < 8);
        }
        if (count === 1) {
            assert.equal(body.account_context.code, "laboratorio-conta-1");
        } else {
            assert.equal(
                body.accounts.filter((a) => a.status === "active").length,
                count,
            );
        }
        rows.push({
            login_ms,
            flow_ms: performance.now() - start,
            steps,
            duplicate_sql: login_metrics.duplicate_sql,
            queries: login_metrics.queries,
            duplicates: login_metrics.duplicates,
            peak_delta_bytes: login_metrics.peak_delta_bytes,
        });
        await context.close();
    }
    const percentile = (key, p) =>
        rows.map((x) => x[key]).sort((a, b) => a - b)[
            Math.ceil(rows.length * p) - 1
        ];
    results[count] = {
        samples: rows.length,
        duplicate_sql: [...new Set(rows.flatMap((r) => r.duplicate_sql))],
        login_median_ms: percentile("login_ms", 0.5),
        login_p95_ms: percentile("login_ms", 0.95),
        flow_median_ms: percentile("flow_ms", 0.5),
        flow_p95_ms: percentile("flow_ms", 0.95),
        queries: [...new Set(rows.map((r) => r.queries))],
        duplicates_max: Math.max(...rows.map((r) => r.duplicates)),
        peak_delta_bytes_max: Math.max(...rows.map((r) => r.peak_delta_bytes)),
        steps: [...new Set(rows.map((r) => r.steps))],
    };
}
console.log(JSON.stringify({ login: results, unexpected: 0 }));
await browser.close();
