#!/bin/bash
set -euo pipefail

# Usado exclusivamente pelo runner com PostgreSQL sintético e rede Docker interna.
prefix=$1
app="$prefix-app"
db="$prefix-db"

sql() {
    docker exec "$db" psql -XAt -v ON_ERROR_STOP=1 -U postgres -d e04 -c "$1"
}

account=$(docker exec "$app" php -r 'echo json_decode(file_get_contents("/tmp/prospera-e05-race.json"), true)["account"];')

race() {
    local operation=$1 lock first second blocked=0 first_status=0 second_status=0

    sql "begin; select id from clients where id=$account for update; select pg_sleep(3); commit;" >/dev/null &
    lock=$!
    for attempt in {1..50}; do
        [[ $(sql "select count(*) from pg_stat_activity where wait_event='PgSleep'") == 1 ]] && break
        sleep .05
    done

    docker exec "$app" php /performance/e05-client.php "$operation" 0 &
    first=$!
    docker exec "$app" php /performance/e05-client.php "$operation" 1 &
    second=$!

    for attempt in {1..20}; do
        blocked=$(sql "select count(*) from pg_stat_activity where wait_event_type='Lock' and query like '%clients%for update%'")
        [[ $blocked == 2 ]] && break
        sleep .05
    done

    wait "$lock"
    wait "$first" || first_status=$?
    wait "$second" || second_status=$?
    [[ $blocked == 2 ]]

    if [ "$operation" = grant ]; then
        [[ "$first_status,$second_status" == 0,22 || "$first_status,$second_status" == 22,0 ]]
        [[ $(sql "select count(*) from role_assignments where establishment_id=(select max(id) from establishments where account_id=$account)") == 1 ]]
    else
        [[ "$first_status,$second_status" == 0,3 || "$first_status,$second_status" == 3,0 ]]
        [[ $(sql "select count(*) from account_memberships m join role_assignments a on a.membership_id=m.id join roles r on r.id=a.role_id where m.account_id=$account and m.status='active' and a.scope='tenant' and r.system_key='administrator'") == 1 ]]
    fi
}

race grant
race suspend
