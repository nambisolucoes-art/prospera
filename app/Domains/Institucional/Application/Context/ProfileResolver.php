<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Application\Context;

use App\Domains\Institucional\Domain\Enums\ProfileSectionType;
use App\Domains\Institucional\Domain\Enums\ProfileState;
use App\Domains\Institucional\Domain\ValueObjects\ProfileOwner;
use App\Domains\Institucional\Domain\ValueObjects\ResolvedSection;
use Illuminate\Support\Facades\DB;

/**
 * Única porta de leitura do valor efetivo das seções institucionais (seção 09.3 do plano 002).
 *
 * Binding `scoped`: a memória vive só dentro de uma requisição. Todas as seções de um dono saem de uma única consulta
 * por empresa e unidade, e não há cache entre requisições. Telas, Queries públicas e o diretório nunca leem
 * `profile_sections` diretamente; um teste arquitetural vigia isso.
 *
 * Ordem para uma unidade: linha própria (`override` ou `disabled`); se `inherit` ou ausente, a linha da empresa; se
 * ausente, o padrão da plataforma. No estado `draft` a herança lê o valor de trabalho da empresa, e no `published`
 * lê apenas o publicado: o público nunca enxerga rascunho.
 */
final class ProfileResolver
{
    private const COLUMNS = ['section', 'establishment_id', 'mode', 'value', 'published_mode', 'published_value'];

    /** @var array<string, array{company: array<string, \stdClass>, establishment: array<string, \stdClass>}> */
    private array $loaded = [];

    public function resolve(ProfileOwner $owner, ProfileSectionType $type, ProfileState $state): ResolvedSection
    {
        $rows = $this->rows($owner);

        $own = $owner->isEstablishment() ? $this->pair($rows['establishment'][$type->value] ?? null, $state) : null;
        $company = $this->pair($rows['company'][$type->value] ?? null, $state);

        if ($owner->isEstablishment()) {
            if ($own !== null && $own['mode'] === 'override') {
                return new ResolvedSection($own['value'], 'establishment', 'override', null);
            }

            if ($own !== null && $own['mode'] === 'disabled') {
                return new ResolvedSection(null, 'establishment', 'disabled', null);
            }

            // A galeria é local por natureza: sem linha própria não há nada a herdar.
            if ($type->isEstablishmentOnly()) {
                return new ResolvedSection($type->emptyValue(), 'platform', 'inherit', 'platform');
            }

            return $this->fromCompany($company, $type, 'inherit', true);
        }

        return $this->fromCompany($company, $type, $company === null ? 'default' : 'override', false);
    }

    /** @return array<string, ResolvedSection> */
    public function resolveAll(ProfileOwner $owner, ProfileState $state): array
    {
        $result = [];

        foreach (ProfileSectionType::cases() as $type) {
            $result[$type->value] = $this->resolve($owner, $type, $state);
        }

        return $result;
    }

    /**
     * Carrega numa única consulta as seções da empresa e de várias unidades, para resolver muitas unidades sem N+1.
     * Depois dela, `resolve` de cada dono já encontra os dados na memória.
     *
     * @param  list<int>  $establishmentIds
     */
    public function warm(int $accountId, int $companyId, array $establishmentIds): void
    {
        $company = [];
        $units = [];

        $query = DB::table('profile_sections')
            ->where('account_id', $accountId)
            ->where('company_id', $companyId)
            ->where(fn ($scope) => $scope->whereNull('establishment_id')
                ->orWhereIn('establishment_id', $establishmentIds));

        foreach ($query->get(self::COLUMNS) as $row) {
            if ($row->establishment_id === null) {
                $company[$row->section] = $row;

                continue;
            }

            $units[(int) $row->establishment_id][$row->section] = $row;
        }

        $this->loaded[ProfileOwner::company($accountId, $companyId)->key()] = ['company' => $company, 'establishment' => []];

        foreach ($establishmentIds as $id) {
            $owner = ProfileOwner::establishment($accountId, $companyId, $id);
            $this->loaded[$owner->key()] = ['company' => $company, 'establishment' => $units[$id] ?? []];
        }
    }

    /**
     * Descarta a memória depois de uma gravação na mesma requisição. Gravar a empresa também invalida as unidades,
     * que guardam uma cópia das seções da empresa; gravar uma unidade invalida só ela.
     */
    public function forget(?ProfileOwner $owner = null): void
    {
        if ($owner === null) {
            $this->loaded = [];

            return;
        }

        if ($owner->isEstablishment()) {
            unset($this->loaded[$owner->key()]);

            return;
        }

        $prefix = $owner->accountId.':'.$owner->companyId.':';

        foreach (array_keys($this->loaded) as $key) {
            if (str_starts_with($key, $prefix)) {
                unset($this->loaded[$key]);
            }
        }
    }

    /** @param array{mode: string, value: array<string,mixed>|null}|null $company */
    private function fromCompany(?array $company, ProfileSectionType $type, string $mode, bool $inherited): ResolvedSection
    {
        if ($company !== null && $company['mode'] === 'override') {
            return new ResolvedSection($company['value'], 'company', $mode, $inherited ? 'company' : null);
        }

        return new ResolvedSection($type->defaultValue(), 'platform', $mode, $inherited ? 'platform' : null);
    }

    /**
     * O par de modo e valor do estado pedido. No publicado, `published_mode` nulo significa que nada foi publicado.
     *
     * @return array{mode: string, value: array<string,mixed>|null}|null
     */
    private function pair(?\stdClass $row, ProfileState $state): ?array
    {
        if ($row === null) {
            return null;
        }

        $mode = $state === ProfileState::Draft ? $row->mode : $row->published_mode;

        if ($mode === null) {
            return null;
        }

        $raw = $state === ProfileState::Draft ? $row->value : $row->published_value;

        return ['mode' => (string) $mode, 'value' => $raw === null ? null : json_decode((string) $raw, true)];
    }

    /** @return array{company: array<string, \stdClass>, establishment: array<string, \stdClass>} */
    private function rows(ProfileOwner $owner): array
    {
        $key = $owner->key();

        if (isset($this->loaded[$key])) {
            return $this->loaded[$key];
        }

        $query = DB::table('profile_sections')
            ->where('account_id', $owner->accountId)
            ->where('company_id', $owner->companyId)
            ->where(function ($scope) use ($owner): void {
                $scope->whereNull('establishment_id');

                if ($owner->establishmentId !== null) {
                    $scope->orWhere('establishment_id', $owner->establishmentId);
                }
            });

        $rows = ['company' => [], 'establishment' => []];

        foreach ($query->get(self::COLUMNS) as $row) {
            $rows[$row->establishment_id === null ? 'company' : 'establishment'][$row->section] = $row;
        }

        return $this->loaded[$key] = $rows;
    }
}
