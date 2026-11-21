-- E08: completa audit_records até :target eventos sintéticos, espalhados por contas, empresas e unidades.
-- Uso: psql -v target=100000 -f e08-volume.sql. Somente no banco descartável.
\set ON_ERROR_STOP on

create temporary table e08_units as
select row_number() over (order by id) - 1 as n, id, company_id, account_id
from establishments;

select count(*) as units from e08_units \gset
select count(*) as have from audit_records where actor_name like 'Autor sintético %' \gset

-- 40% conta, 30% empresa, 30% unidade; 2% em inspeção; dois anos de histórico; 200 autores.
insert into audit_records (
    operation_id, actor_id, actor_name, account_id, context, represented_user_id,
    entity_type, entity_id, entity_name, event, changes, created_at, company_id, establishment_id
)
select
    gen_random_uuid(),
    1000000 + g % 200,
    'Autor sintético ' || (g % 200),
    u.account_id,
    case when g % 50 = 0 then 'platform-account' else 'account' end,
    case when g % 50 = 0 then 1000500 end,
    (array[
        'person', 'tag', 'role', 'account-membership', 'company',
        'customer', 'supplier', 'establishment', 'role-assignment', 'role-assignment'
    ])[g % 10 + 1],
    g,
    'Registro sintético ' || g,
    (array['created', 'updated', 'updated', 'updated', 'deleted'])[g % 5 + 1],
    '{"name":{"label":"Nome","before":"Antes","after":"Depois"}}',
    timestamp '2026-11-21 12:00' - ((g::bigint * 7919) % 63072000) * interval '1 second',
    case when g % 10 >= 4 then u.company_id end,
    case when g % 10 >= 7 then u.id end
from generate_series(:have + 1, :target) g
join e08_units u on u.n = (g * 31) % :units;

analyze audit_records;

select count(*) as audit_records,
       pg_size_pretty(pg_total_relation_size('audit_records')) as total_size,
       pg_size_pretty(pg_indexes_size('audit_records')) as index_size
from audit_records;
