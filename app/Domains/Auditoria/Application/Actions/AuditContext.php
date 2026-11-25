<?php

declare(strict_types=1);

namespace App\Domains\Auditoria\Application\Actions;

use App\Domains\Acessos\Domain\Models\AccountMembership;
use App\Domains\Plataforma\Domain\DTOs\AccountInspectionDTO;
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

    // Nome do representado no momento da operação, para o histórico continuar legível depois de mudanças.
    public ?string $representedUserName = null;

    public ?string $inspectionId = null;

    public string $context = 'system';

    /** @var array<string,int|null> */
    private array $memberships = [];

    public function membershipIdFor(?User $identity, ?int $accountId): ?int
    {
        if ($accountId === null) {
            return null;
        }

        if ($this->representedUserId !== null) {
            return $this->memberships[$this->representedUserId.':'.$accountId] ?? null;
        }

        if ($identity === null || $identity->is_saas_admin) {
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

            $representedName = $user?->id === $inspection->userId ? $user->name : null;
            $this->identifyInspection($inspection, $administratorName, $representedName);
        } elseif ($user) {
            $this->actorId = $user->id;
            $this->actorName = $user->name;
            $this->context = $user->is_saas_admin ? 'platform' : 'account';
        }
    }

    public function identifyInspection(
        AccountInspectionDTO $inspection,
        ?string $administratorName,
        ?string $representedName,
    ): void {
        $this->actorId = $inspection->administratorId;
        $this->actorName = $administratorName ?? 'Administrador da plataforma';
        $this->representedUserId = $inspection->userId;
        $this->representedUserName = $representedName;
        $this->inspectionId = $inspection->id;
        $this->memberships[$inspection->userId.':'.$inspection->accountId] = $inspection->membershipId;
        $this->context = 'platform-account';
    }

    public function clear(): void
    {
        $this->operationId = null;
        $this->actorId = null;
        $this->actorName = 'Sistema';
        $this->representedUserId = null;
        $this->representedUserName = null;
        $this->inspectionId = null;
        $this->memberships = [];
        $this->context = 'system';
    }
}
