<?php

namespace App\Domains\Pessoas\Application\Actions;

use App\Domains\Auditoria\Application\Actions\AuditContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DirectoryAudit
{
    /** @param array<string,mixed> $changes */
    public static function record(int $accountId, string $event, string $type, int $id, array $changes): void
    {
        DB::table('directory_audit_events')->insert(['operation_id' => app(AuditContext::class)->operationId ?? (string) Str::uuid(), 'actor_id' => app(AuditContext::class)->actorId ?? auth()->id(), 'account_id' => $accountId, 'event' => $event, 'entity_type' => $type, 'entity_id' => $id, 'changes' => json_encode($changes, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }
}
