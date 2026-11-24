<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (getenv('DB_DATABASE') !== 'e09') {
    throw new RuntimeException('Somente laboratório.');
}
$tests = [
    'immutable' => ["update clients set code='outro-codigo' where id=1", '23514'],
    'unique' => ["insert into clients(name,code,active) select 'Duplicado',code,true from clients where id=1", '23505'],
    'reserved' => ["insert into clients(name,code,active) values('Reservado','todos',true)", '23514'],
    'invalid' => ["insert into clients(name,code,active) values('Inválido','INVALIDO',true)", '23514'],
    'recipient-match' => [
        'insert into account_invitations(account_id,recipient_id,email,token_hash,role_id,scope,expires_at,'
        .'status,accepted_at,accepted_by) '
        ."select 1,1,'invalid@e09.test',repeat('c',64),id,'tenant',now()+interval '1 day',"
        ."'accepted',now(),2 from roles where account_id=1 limit 1",
        '23514',
    ],
    'cross-account-role' => [
        'insert into account_invitations(account_id,recipient_id,email,token_hash,role_id,scope,expires_at) '
        ."select 1,1,'invalid@e09.test',repeat('f',64),id,'tenant',now()+interval '1 day' "
        .'from roles where account_id=2 limit 1',
        '23503',
    ],
];
$results = [];
foreach ($tests as $label => [$sql, $expected]) {
    try {
        DB::transaction(fn () => DB::statement($sql));
        throw new RuntimeException('Constraint não aplicada: '.$label);
    } catch (QueryException $exception) {
        if ($exception->getCode() !== $expected) {
            throw $exception;
        }
        $results[$label] = $exception->getCode();
    }
}
echo json_encode(['constraints' => $results]), PHP_EOL;
