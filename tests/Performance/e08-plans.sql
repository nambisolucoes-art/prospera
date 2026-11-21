-- E08: planos das consultas da auditoria. Variáveis: account, company, unit, units30 (lista separada por vírgula).
\set ON_ERROR_STOP on
\pset pager off

\echo '== índices'
select indexname, pg_size_pretty(pg_relation_size(indexname::regclass)) as size
from pg_indexes where tablename = 'audit_records' order by indexname;

\echo '== 1. conta inteira: página inicial'
explain (analyze, buffers, costs off)
select * from audit_records where account_id = :account order by created_at desc, id desc limit 20;

\echo '== 2. conta inteira: contagem'
explain (analyze, buffers, costs off)
select count(*) from audit_records where account_id = :account;

\echo '== 3. conta inteira: página profunda (offset 9980)'
explain (analyze, buffers, costs off)
select * from audit_records where account_id = :account order by created_at desc, id desc limit 20 offset 9980;

\echo '== 4. administrador de empresa: página'
explain (analyze, buffers, costs off)
select * from audit_records where account_id = :account and (company_id in (:company) or 0 = 1)
order by created_at desc, id desc limit 20;

\echo '== 5. administrador de empresa: contagem'
explain (analyze, buffers, costs off)
select count(*) from audit_records where account_id = :account and (company_id in (:company) or 0 = 1);

\echo '== 6. 30 atribuições de unidade: página'
explain (analyze, buffers, costs off)
select * from audit_records where account_id = :account and (0 = 1 or establishment_id in (:units30))
order by created_at desc, id desc limit 20;

\echo '== 7. 30 atribuições de unidade: contagem'
explain (analyze, buffers, costs off)
select count(*) from audit_records where account_id = :account and (0 = 1 or establishment_id in (:units30));

\echo '== 8. filtro de unidade dentro da conta: página'
explain (analyze, buffers, costs off)
select * from audit_records where account_id = :account and establishment_id = :unit
order by created_at desc, id desc limit 20;

\echo '== 9. período antes da E08 (cast para data)'
explain (analyze, buffers, costs off)
select count(*) from audit_records where account_id = :account
and created_at::date >= '2026-10-01' and created_at::date <= '2026-10-31';

\echo '== 10. período depois da E08 (intervalo semiaberto)'
explain (analyze, buffers, costs off)
select count(*) from audit_records where account_id = :account
and created_at >= '2026-10-01 00:00:00' and created_at < '2026-11-01 00:00:00';

\echo '== 11. autor: contagem'
explain (analyze, buffers, costs off)
select count(*) from audit_records where account_id = :account and actor_id = 1000007;

\echo '== 12. busca sem distinção de maiúsculas'
explain (analyze, buffers, costs off)
select count(*) from audit_records where account_id = :account
and (entity_name ilike '%Registro sintético 12%' or actor_name ilike '%Registro sintético 12%');

\echo '== 13. opções: autores'
explain (analyze, buffers, costs off)
select distinct actor_id, actor_name from audit_records where account_id = :account and actor_id is not null
order by actor_name;

\echo '== 14. opções: tipos e ações'
explain (analyze, buffers, costs off)
select distinct entity_type from audit_records where account_id = :account;

\echo '== 15. plataforma (PD-02): página'
explain (analyze, buffers, costs off)
select * from audit_records where (account_id is null or entity_type = 'account' or context = 'platform')
order by created_at desc, id desc limit 20;

\echo '== 16. plataforma (PD-02): contagem'
explain (analyze, buffers, costs off)
select count(*) from audit_records where (account_id is null or entity_type = 'account' or context = 'platform');
\echo '== 13b. opções: autores por varredura solta (E08)'
explain (analyze, buffers, costs off)
with recursive actors (id) as (
    select min(actor_id) from audit_records where account_id = :account
    union all
    select (select min(actor_id) from audit_records where account_id = :account and actor_id > actors.id)
    from actors where actors.id is not null
)
select actors.id, (select actor_name from audit_records where account_id = :account and actor_id = actors.id
    order by created_at desc limit 1) as name
from actors where actors.id is not null;

\echo '== 14b. opções: tipos e ações por varredura solta (E08)'
explain (analyze, buffers, costs off)
with recursive types (type) as (
    select min(entity_type) from audit_records where account_id = :account
    union all
    select (select min(entity_type) from audit_records where account_id = :account and entity_type > types.type)
    from types where types.type is not null
),
pairs (type, event) as (
    select types.type, (select min(event) from audit_records where account_id = :account and entity_type = types.type)
    from types where types.type is not null
    union all
    select pairs.type, (select min(event) from audit_records
        where account_id = :account and entity_type = pairs.type and event > pairs.event)
    from pairs where pairs.event is not null
)
select type, event from pairs where event is not null;
