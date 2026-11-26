<?php

declare(strict_types=1);

namespace App\Domains\Institucional\UI\Controllers;

use App\Domains\Acessos\Application\Context\OrganizationContext;
use App\Domains\Institucional\Application\Actions\Hours\CreateHourExceptionAction;
use App\Domains\Institucional\Application\Actions\Hours\DeleteHourExceptionAction;
use App\Domains\Institucional\Application\Actions\Hours\SetEstablishmentTimezoneAction;
use App\Domains\Institucional\Application\Actions\Hours\UpdateHourExceptionAction;
use App\Domains\Institucional\Application\Data\Hours\CreateHourExceptionData;
use App\Domains\Institucional\Application\Data\Hours\HourExceptionImpactData;
use App\Domains\Institucional\Application\Data\Hours\SetEstablishmentTimezoneData;
use App\Domains\Institucional\Application\Data\Hours\UpdateHourExceptionData;
use App\Domains\Institucional\Application\Queries\Hours\FindHourExceptionQuery;
use App\Domains\Institucional\Application\Queries\Hours\HourExceptionImpactQuery;
use App\Domains\Institucional\Domain\ValueObjects\ProfileOwner;
use App\Support\ApiPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Exceções de horário e fuso do perfil institucional (S07). O dono vem da URL e é validado pelo contexto organizacional;
 * cada escrita é autorizada pela Action sobre o nó do dono, e o identificador de uma exceção de outro dono responde 404.
 */
final class InstitutionalHoursController
{
    public function store(
        Request $request,
        OrganizationContext $context,
        CreateHourExceptionAction $action,
    ): Response {
        $owner = $this->owner($context);
        Gate::authorize('account.institutional.view', $owner->node());

        $data = CreateHourExceptionData::fromInput($request->all());
        $action->execute($data, $owner);
        ApiPage::toast('Exceção de horário salva. A alteração já aparece nos sites publicados.');

        return response()->noContent();
    }

    public function update(
        Request $request,
        OrganizationContext $context,
        FindHourExceptionQuery $find,
        UpdateHourExceptionAction $action,
        string $excecao,
    ): Response {
        $owner = $this->owner($context);
        Gate::authorize('account.institutional.view', $owner->node());
        abort_unless(ctype_digit($excecao), 404);

        $exception = $find->execute($owner, (int) $excecao);
        abort_if($exception === null, 404);

        $data = UpdateHourExceptionData::fromInput($request->all());
        $action->execute($data, $exception);
        ApiPage::toast('Exceção de horário salva. A alteração já aparece nos sites publicados.');

        return response()->noContent();
    }

    public function destroy(
        OrganizationContext $context,
        FindHourExceptionQuery $find,
        DeleteHourExceptionAction $action,
        string $excecao,
    ): Response {
        $owner = $this->owner($context);
        Gate::authorize('account.institutional.view', $owner->node());
        abort_unless(ctype_digit($excecao), 404);

        $exception = $find->execute($owner, (int) $excecao);
        abort_if($exception === null, 404);

        $action->execute($exception);
        ApiPage::toast('Exceção de horário excluída.');

        return response()->noContent();
    }

    /** Resumo do alcance de uma exceção da empresa, recalculado no banco a cada pedido. */
    public function impact(
        Request $request,
        OrganizationContext $context,
        HourExceptionImpactQuery $impact,
    ): JsonResponse {
        $owner = $this->owner($context);
        abort_if($owner->isEstablishment(), 404);
        Gate::authorize('account.institutional.update', $owner->node());

        $data = HourExceptionImpactData::fromInput($request->query());

        return response()->json(
            $impact->execute($owner, $data->kind, $data->startsOn, $data->endsOn)->toArray(),
            headers: ['Cache-Control' => 'private, no-store'],
        );
    }

    public function setTimezone(
        Request $request,
        OrganizationContext $context,
        SetEstablishmentTimezoneAction $action,
    ): Response {
        $owner = $this->owner($context);
        Gate::authorize('account.institutional.view', $owner->node());

        $action->execute(SetEstablishmentTimezoneData::fromInput($request->all()), $owner);
        ApiPage::toast('Fuso horário salvo. Os horários passam a valer na hora local desse fuso.');

        return response()->noContent();
    }

    private function owner(OrganizationContext $context): ProfileOwner
    {
        $establishment = $context->establishment();

        if ($establishment !== null) {
            return ProfileOwner::establishment($context->accountId(), $establishment->company->id, $establishment->id);
        }

        $company = $context->company();
        abort_if($company === null, 404);

        return ProfileOwner::company($context->accountId(), $company->id);
    }
}
