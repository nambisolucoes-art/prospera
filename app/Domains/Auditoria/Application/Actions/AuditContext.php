<?php

namespace App\Domains\Auditoria\Application\Actions;

use App\Domains\Acessos\Domain\Models\AccountMembership;
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

    /** @var array<string,int|null> */
    private array $memberships = [];

    public function membershipIdFor(?User $identity, ?int $accountId): ?int
    {
        if ($identity === null || $identity->is_saas_admin || $accountId === null) {
            return null;
        }

        $key = $identity->id.':'.$accountId;

        if (! array_key_exists($key, $this->memberships)) {
            $this->memberships[$key] = AccountMembership::where('account_id', $accountId)
                ->where('user_id', $identity->id)
                ->first(['id'])?->id;
        }

        return $this->memberships[$key];
    }

    public function begin(Request $request): void
    {
        $this->operationId ??= (string) Str::uuid();
        $user = $request->user();
        $inspection = $request->hasSession() ? AccountInspectionSession::current($request->session()) : null;

        if ($inspection) {
            $administratorName = User::whereKey($inspection->administratorId)->value('name');

            $this->actorId = $inspection->administratorId;
            $this->actorName = $administratorName ?? 'Administrador da plataforma';
            $this->representedUserId = $inspection->userId;
            $this->memberships[$inspection->userId.':'.$inspection->accountId] = $inspection->membershipId;
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
        $this->memberships = [];
        $this->context = 'system';
    }
}
