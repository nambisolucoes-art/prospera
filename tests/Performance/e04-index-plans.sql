EXPLAIN (ANALYZE, BUFFERS) SELECT id, name, (SELECT count(*) FROM establishments e WHERE e.company_id=c.id)
FROM companies c WHERE c.account_id=5 AND c.active ORDER BY c.name, c.id LIMIT 25;
EXPLAIN (ANALYZE, BUFFERS) SELECT id, name FROM establishments WHERE account_id=5 AND company_id=16 AND active
ORDER BY name, id LIMIT 25;
BEGIN;
DROP INDEX establishments_company_id_active_name_index;
EXPLAIN (ANALYZE, BUFFERS) SELECT id, name FROM establishments WHERE account_id=5 AND company_id=16 AND active
ORDER BY name, id LIMIT 25;
ROLLBACK;
CREATE TEMP TABLE scale_units AS
SELECT (series * 100 + e.id)::bigint AS id, series * 5 + e.account_id AS account_id,
       series * 20 + e.company_id AS company_id, e.active, e.name
FROM establishments e CROSS JOIN generate_series(0, 999) series;
CREATE UNIQUE INDEX scale_units_account_code_proxy ON scale_units (account_id, id);
ANALYZE scale_units;
EXPLAIN (ANALYZE, BUFFERS) SELECT count(*) FROM scale_units WHERE company_id=16;
EXPLAIN (ANALYZE, BUFFERS) SELECT id, name FROM scale_units WHERE account_id=5 AND company_id=16 AND active ORDER BY name, id LIMIT 25;
CREATE INDEX scale_units_company_active_name ON scale_units (company_id, active, name);
ANALYZE scale_units;
EXPLAIN (ANALYZE, BUFFERS) SELECT count(*) FROM scale_units WHERE company_id=16;
EXPLAIN (ANALYZE, BUFFERS) SELECT id, name FROM scale_units WHERE account_id=5 AND company_id=16 AND active ORDER BY name, id LIMIT 25;
