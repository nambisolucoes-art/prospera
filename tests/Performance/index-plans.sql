\set ON_ERROR_STOP on
-- Distribuição sintética com 10 mil contas, 30 mil cargos e 400 mil pessoas.
CREATE TEMP TABLE permission_role (role_id bigint, permission_id bigint, PRIMARY KEY (role_id, permission_id));
CREATE TEMP TABLE person_tag (person_id bigint, tag_id bigint, PRIMARY KEY (person_id, tag_id));
CREATE TEMP TABLE users (id bigint PRIMARY KEY, account_id bigint, role_id bigint, access_enabled boolean);
CREATE INDEX ON users (account_id);

INSERT INTO permission_role SELECT role, 1 + (role + n) % 64 FROM generate_series(1, 30000) role
CROSS JOIN generate_series(1, 10) n;
INSERT INTO person_tag SELECT person, 1 + (person + n * 400) % 10000 FROM generate_series(1, 400000) person
CROSS JOIN generate_series(1, 3) n;
INSERT INTO users SELECT id, (id - 1) / 40 + 1, ((id - 1) / 40) * 3 + 1 + id % 3, id % 5 <> 0
FROM generate_series(1, 400000) id;
ANALYZE;

SELECT 'before';
EXPLAIN (ANALYZE, BUFFERS) SELECT role_id FROM permission_role WHERE permission_id = 33;
EXPLAIN (ANALYZE, BUFFERS) SELECT permission_id FROM permission_role WHERE role_id = 29999;
EXPLAIN (ANALYZE, BUFFERS) SELECT count(*) FROM person_tag WHERE tag_id = 9999;
EXPLAIN (ANALYZE, BUFFERS) SELECT count(*) FROM users WHERE account_id = 9999 AND role_id = 29996 AND access_enabled;
EXPLAIN (ANALYZE, BUFFERS) SELECT id FROM users WHERE role_id = 29996 LIMIT 1;

CREATE INDEX ON permission_role (permission_id);
CREATE INDEX ON person_tag (tag_id);
CREATE INDEX ON users (role_id);
ANALYZE;

SELECT 'after';
EXPLAIN (ANALYZE, BUFFERS) SELECT role_id FROM permission_role WHERE permission_id = 33;
EXPLAIN (ANALYZE, BUFFERS) SELECT permission_id FROM permission_role WHERE role_id = 29999;
EXPLAIN (ANALYZE, BUFFERS) SELECT count(*) FROM person_tag WHERE tag_id = 9999;
EXPLAIN (ANALYZE, BUFFERS) SELECT count(*) FROM users WHERE account_id = 9999 AND role_id = 29996 AND access_enabled;
EXPLAIN (ANALYZE, BUFFERS) SELECT id FROM users WHERE role_id = 29996 LIMIT 1;

SELECT indexrelid::regclass AS index_name, pg_relation_size(indexrelid) AS bytes
FROM pg_index
WHERE indexrelid IN (
    'permission_role_permission_id_idx'::regclass, 'person_tag_tag_id_idx'::regclass, 'users_role_id_idx'::regclass
)
ORDER BY 1;
