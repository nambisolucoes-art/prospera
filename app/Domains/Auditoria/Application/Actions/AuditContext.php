<?php

namespace App\Domains\Auditoria\Application\Actions;

use App\Domains\Plataforma\Infrastructure\Session\AccountInspectionSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class AuditContext
{
    public ?string $operationId = null;

    public ?int $actorId = null;

    public string $actorName = 'Sistema';

    public ?int $representedUserId = null;

    public string $context = 'system';

    public function begin(Request $request): void
    {
        $this->operationId ??= (string) Str::uuid();
        $user = $request->user();
        $inspection = $request->hasSession() ? AccountInspectionSession::current($request->session()) : null;
        if ($inspection) {
            $this->actorId = $inspection->administratorId;
            $this->actorName = User::whereKey($inspection->administratorId)->value('name') ?? 'Administrador da plataforma';
            $this->representedUserId = $inspection->userId;
            $this->context = 'platform-account';
        } elseif ($user) {
            $this->actorId = $user->id;
            $this->actorName = $user->name;
            $this->context = $user->is_saas_admin ? 'platform' : 'account';
        }
    }

    public function clear(): void
    {
        $this->operationId = null;
        $this->actorId = null;
        $this->actorName = 'Sistema';
        $this->representedUserId = null;
        $this->context = 'system';
    }
}
