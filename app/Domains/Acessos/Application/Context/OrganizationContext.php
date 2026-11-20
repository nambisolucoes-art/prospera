<?php

namespace App\Domains\Acessos\Application\Context;

use App\Domains\Acessos\Domain\DTOs\AccessNode;
use App\Domains\Acessos\Domain\DTOs\AuthorizedNodes;
use App\Domains\Auditoria\Application\Actions\AuditContext;
use App\Domains\Organizacao\Application\Queries\OrganizationContextQuery;
use App\Domains\Organizacao\Domain\DTOs\NavigableCompany;
use App\Domains\Organizacao\Domain\DTOs\NavigableEstablishment;
use App\Models\User;

/**
 * Contexto organizacional da requisição (E06), com binding scoped.
 *
 * A URL informa a empresa ou a unidade; o servidor valida o vínculo, o pertencimento ao tenant,
 * a cobertura por alguma atribuição e a situação. O contexto não concede permissões: cada operação
 * continua verificando a própria permissão sobre o nó.
 */
final class OrganizationContext
{
    public const CONSOLIDATED = 'todos';

    private string $mode = 'tenant';

    /** @var list<string> */
    private array $selectedCodes = [];

    private bool $consolidatedSupported = false;

    private ?NavigableCompany $company = null;

    private ?NavigableEstablishment $establishment = null;

    /** @var array<string, list<NavigableEstablishment>> */
    private array $navigable = [];

    public function __construct(
        private readonly AccountContext $accounts,
        private readonly EffectivePermissions $permissions,
        private readonly AuditContext $audit,
        private readonly OrganizationContextQuery $units,
    ) {}

    public function enterCompany(User $user, string $code, bool $history, bool $reading): void
    {
        $accountId = $this->accounts->requireId();

        // Código desconhecido, de outro tenant ou sem atribuição que o cubra responde igual.
        $company = $this->units->company($accountId, $code);
        abort_if($company === null, 404);

        $node = AccessNode::company($accountId, $company->id);
        abort_unless($this->permissions->navigableNodes($user)->covers($node), 404);

        $usable = $company->active || ($history && $reading);
        abort_unless($usable, 403, 'Esta empresa está inativa. Escolha outro estabelecimento no seletor.');

        $this->mode = 'company';
        $this->company = $company;
    }

    public function enterEstablishment(User $user, string $code, bool $history, bool $reading): void
    {
        $accountId = $this->accounts->requireId();

        $establishment = $this->units->establishment($accountId, $code);
        abort_if($establishment === null, 404);

        $node = AccessNode::establishment($accountId, $establishment->company->id, $establishment->id);
        abort_unless($this->permissions->navigableNodes($user)->covers($node), 404);

        $usable = ($establishment->active && $establishment->company->active) || ($history && $reading);
        abort_unless($usable, 403, 'Este estabelecimento está inativo. Escolha outro no seletor.');

        $this->mode = 'establishment';
        $this->company = $establishment->company;
        $this->establishment = $establishment;
    }

    /** @param list<string>|null $codes */
    public function enterConsolidated(User $user, ?array $codes): void
    {
        $this->accounts->requireId();

        $available = array_column($this->navigable($user), 'code');
        $this->selectedCodes = $codes ?? $available;
        abort_if($this->selectedCodes === [] || array_diff($this->selectedCodes, $available) !== [], 404);

        $this->mode = 'consolidated';
        $this->consolidatedSupported = true;
    }

    /** @return list<string> */
    public function defaultCodes(User $user): array
    {
        $available = array_column($this->navigable($user), 'code');

        if (count($available) <= 1) {
            return $available;
        }

        $preferred = $this->units->preferredCodes($this->accountId(), $user->id);

        return array_values(array_intersect($available, $preferred)) ?: [$available[0]];
    }

    /** @return list<string> */
    public function selectedCodes(): array
    {
        return $this->establishment === null ? $this->selectedCodes : [$this->establishment->code];
    }

    public function allowConsolidated(): void
    {
        $this->consolidatedSupported = true;
    }

    public function mode(): string
    {
        return $this->mode;
    }

    public function supportsConsolidated(): bool
    {
        return $this->consolidatedSupported;
    }

    public function accountId(): int
    {
        return $this->accounts->requireId();
    }

    public function membershipId(User $user): ?int
    {
        return $this->audit->membershipIdFor($user, $this->accounts->linkedId($user));
    }

    // Na inspeção, o autor real é o administrador SaaS; o guard contém o usuário representado.
    public function actorId(User $user): int
    {
        return $this->audit->actorId ?? $user->id;
    }

    public function company(): ?NavigableCompany
    {
        return $this->company;
    }

    public function establishment(): ?NavigableEstablishment
    {
        return $this->establishment;
    }

    public function node(): AccessNode
    {
        $accountId = $this->accountId();

        if ($this->establishment !== null) {
            return AccessNode::establishment($accountId, $this->establishment->company->id, $this->establishment->id);
        }

        if ($this->company !== null) {
            return AccessNode::company($accountId, $this->company->id);
        }

        return AccessNode::tenant($accountId);
    }

    /**
     * Descritor que a Query aplica no SQL para a permissão informada, já restrito ao contexto da URL.
     */
    public function scopeFor(User $user, string $ability): AuthorizedNodes
    {
        if ($this->mode === 'consolidated') {
            $permitted = $this->units->authorizedIds(
                $this->accountId(),
                $this->permissions->nodesFor($user, $ability),
                $this->selectedCodes,
            );

            return new AuthorizedNodes(establishments: $permitted);
        }

        if (! $user->can($ability, $this->node())) {
            return new AuthorizedNodes;
        }

        if ($this->establishment !== null) {
            return new AuthorizedNodes(establishments: [$this->establishment->id]);
        }

        if ($this->company !== null) {
            return new AuthorizedNodes(companies: [$this->company->id]);
        }

        return new AuthorizedNodes(tenant: true);
    }

    /**
     * Unidades ativas que o usuário pode abrir, para o seletor e o redirecionamento sem código.
     *
     * @return list<NavigableEstablishment>
     */
    public function navigable(User $user): array
    {
        $accountId = $this->accounts->activeAccount($user)?->id;

        if ($accountId === null) {
            return [];
        }

        $key = $user->id.':'.$accountId;

        return $this->navigable[$key] ??= $this->units->navigable(
            $accountId,
            $this->permissions->navigableNodes($user),
        );
    }
}
