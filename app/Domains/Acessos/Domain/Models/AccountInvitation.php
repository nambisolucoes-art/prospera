<?php

declare(strict_types=1);

namespace App\Domains\Acessos\Domain\Models;

use App\Domains\Acessos\Domain\Concerns\NamesAuditedRole;
use App\Domains\Acessos\Domain\DTOs\AccessNode;
use App\Domains\Auditoria\Domain\Concerns\AuditsChanges;
use App\Domains\Auditoria\Domain\Contracts\Auditable;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Convite de uma identidade existente para uma conta (E09). Não concede acesso: o vínculo só nasce
 * quando a própria identidade aceita, autenticada, dentro da validade e uma única vez.
 *
 * @property int $id
 * @property int $account_id
 * @property int $recipient_id
 * @property string $email
 * @property string $token_hash
 * @property int $role_id
 * @property string $scope
 * @property int|null $company_id
 * @property int|null $establishment_id
 * @property int|null $person_id
 * @property int|null $invited_by
 * @property int|null $accepted_by
 * @property string $status
 * @property CarbonInterface $expires_at
 * @property CarbonInterface|null $accepted_at
 * @property CarbonInterface|null $revoked_at
 */
final class AccountInvitation extends Model implements Auditable
{
    use AuditsChanges;
    use NamesAuditedRole {
        NamesAuditedRole::auditRelationNames insteadof AuditsChanges;
    }

    public static function auditType(): string
    {
        return 'account-invitation';
    }

    /** @return array<string,string> */
    public static function auditedFields(): array
    {
        return [
            'account_id' => 'Conta',
            'recipient_id' => 'Identidade destinatária',
            'email' => 'E-mail convidado',
            'role_id' => 'Cargo',
            'scope' => 'Escopo',
            'company_id' => 'Empresa',
            'establishment_id' => 'Estabelecimento',
            'person_id' => 'Pessoa',
            'invited_by' => 'Convidado por',
            'status' => 'Situação do convite',
            'expires_at' => 'Validade',
            'accepted_by' => 'Aceito por',
            'accepted_at' => 'Aceito em',
            'revoked_at' => 'Revogado em',
        ];
    }

    /** @return array<string,string> */
    public static function unauditedFields(): array
    {
        return ['token_hash' => 'Hash do token do convite, segredo que nunca entra no histórico.'];
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function issue(
        int $accountId,
        User $recipient,
        Role $role,
        AccessNode $node,
        ?int $personId,
        ?int $invitedBy,
        string $token,
        CarbonInterface $expiresAt,
    ): self {
        $invitation = new self;
        $invitation->account_id = $accountId;
        $invitation->recipient_id = $recipient->id;
        $invitation->email = mb_strtolower($recipient->email);
        $invitation->token_hash = self::hashToken($token);
        $invitation->role_id = $role->id;
        $invitation->scope = $node->type;
        $invitation->company_id = $node->companyId;
        $invitation->establishment_id = $node->establishmentId;
        $invitation->person_id = $personId;
        $invitation->invited_by = $invitedBy;
        $invitation->status = 'pending';
        $invitation->expires_at = $expiresAt;

        return $invitation;
    }

    public function node(): AccessNode
    {
        if ($this->scope === 'establishment' && $this->company_id !== null && $this->establishment_id !== null) {
            return AccessNode::establishment($this->account_id, $this->company_id, $this->establishment_id);
        }

        if ($this->scope === 'company' && $this->company_id !== null) {
            return AccessNode::company($this->account_id, $this->company_id);
        }

        return AccessNode::tenant($this->account_id);
    }

    public function isUsable(): bool
    {
        return $this->status === 'pending' && $this->expires_at->isFuture();
    }

    public function isFor(User $identity): bool
    {
        return $this->recipient_id === $identity->id
            && hash_equals($this->email, mb_strtolower($identity->email));
    }

    public function markAccepted(int $userId): void
    {
        $this->status = 'accepted';
        $this->accepted_by = $userId;
        $this->accepted_at = now();
    }

    public function markRevoked(): void
    {
        $this->status = 'revoked';
        $this->revoked_at = now();
    }

    /** @return BelongsTo<Client, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'account_id');
    }

    /** @return BelongsTo<Role, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'recipient_id' => 'integer',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
