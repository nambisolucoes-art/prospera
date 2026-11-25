<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\UI\Controllers;

use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Colaboradores\Application\Actions\Assignments\ChangeEmployeeAssignmentAction;
use App\Domains\Colaboradores\Application\Actions\Assignments\CreateEmployeeAssignmentAction;
use App\Domains\Colaboradores\Application\Actions\Assignments\EndEmployeeAssignmentAction;
use App\Domains\Colaboradores\Application\Actions\Assignments\SetPrimaryEmployeeAssignmentAction;
use App\Domains\Colaboradores\Application\Actions\Assignments\UpdateEmployeeAssignmentAction;
use App\Domains\Colaboradores\Application\Data\Assignments\ChangeEmployeeAssignmentData;
use App\Domains\Colaboradores\Application\Data\Assignments\CreateEmployeeAssignmentData;
use App\Domains\Colaboradores\Application\Data\Assignments\EndEmployeeAssignmentData;
use App\Domains\Colaboradores\Application\Data\Assignments\UpdateEmployeeAssignmentData;
use App\Domains\Colaboradores\Application\Queries\Assignments\ListAssignmentOptionsQuery;
use App\Domains\Colaboradores\Application\Queries\Assignments\ShowEmployeeAssignmentQuery;
use App\Domains\Colaboradores\Application\Queries\Employees\ShowEmployeeLinkQuery;
use App\Support\ApiPage;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Lotações de um vínculo de colaborador. O vínculo e a lotação são buscados dentro do escopo de visualização (404 fora
 * dele) e cada Action confere `account.employees.assign` sobre a unidade da lotação (403 sem a permissão).
 */
final class EmployeeAssignmentController
{
    public function store(
        Request $request,
        AccountContext $account,
        EffectivePermissions $permissions,
        ShowEmployeeLinkQuery $showLink,
        ListAssignmentOptionsQuery $options,
        CreateEmployeeAssignmentAction $action,
        int $personId,
        int $employeeId,
    ): Response {
        $user = $request->user();
        $accountId = $account->requireId();

        $viewable = $permissions->nodesFor($user, 'account.employees.view');
        abort_if($viewable->isEmpty(), 403);

        $link = $showLink->execute($accountId, $viewable, $personId, $employeeId);
        abort_if($link === null, 404);

        $assignable = $permissions->nodesFor($user, 'account.employees.assign');
        abort_if($assignable->isEmpty(), 403);

        $data = CreateEmployeeAssignmentData::fromInput(
            $request->all(),
            $options->execute($accountId, $link->company_id, $assignable),
        );

        $action->execute($data, $link);
        ApiPage::toast('Lotação cadastrada.');

        return response()->noContent();
    }

    public function update(
        Request $request,
        AccountContext $account,
        EffectivePermissions $permissions,
        ShowEmployeeLinkQuery $showLink,
        ShowEmployeeAssignmentQuery $showAssignment,
        ListAssignmentOptionsQuery $options,
        UpdateEmployeeAssignmentAction $action,
        int $personId,
        int $employeeId,
        int $assignmentId,
    ): Response {
        $user = $request->user();
        $accountId = $account->requireId();

        $viewable = $permissions->nodesFor($user, 'account.employees.view');
        abort_if($viewable->isEmpty(), 403);
        abort_if($showLink->execute($accountId, $viewable, $personId, $employeeId) === null, 404);

        $assignment = $showAssignment->execute($accountId, $viewable, $employeeId, $assignmentId);
        abort_if($assignment === null, 404);

        $assignable = $permissions->nodesFor($user, 'account.employees.assign');
        abort_if($assignable->isEmpty(), 403);

        $data = UpdateEmployeeAssignmentData::fromInput($request->all(), $options->execute(
            $accountId,
            $assignment->company_id,
            $assignable,
            array_filter([$assignment->department_id]),
            array_filter([$assignment->job_title_id]),
        ));

        $action->execute($data, $assignment);
        ApiPage::toast('Lotação salva.');

        return response()->noContent();
    }

    public function change(
        Request $request,
        AccountContext $account,
        EffectivePermissions $permissions,
        ShowEmployeeLinkQuery $showLink,
        ShowEmployeeAssignmentQuery $showAssignment,
        ListAssignmentOptionsQuery $options,
        ChangeEmployeeAssignmentAction $action,
        int $personId,
        int $employeeId,
        int $assignmentId,
    ): Response {
        $user = $request->user();
        $accountId = $account->requireId();

        $viewable = $permissions->nodesFor($user, 'account.employees.view');
        abort_if($viewable->isEmpty(), 403);
        abort_if($showLink->execute($accountId, $viewable, $personId, $employeeId) === null, 404);

        $assignment = $showAssignment->execute($accountId, $viewable, $employeeId, $assignmentId);
        abort_if($assignment === null, 404);

        $assignable = $permissions->nodesFor($user, 'account.employees.assign');
        abort_if($assignable->isEmpty(), 403);

        $data = ChangeEmployeeAssignmentData::fromInput($request->all(), $options->execute(
            $accountId,
            $assignment->company_id,
            $assignable,
            array_filter([$assignment->department_id]),
            array_filter([$assignment->job_title_id]),
        ));

        $action->execute($data, $assignment);
        ApiPage::toast('Mudança registrada.');

        return response()->noContent();
    }

    public function end(
        Request $request,
        AccountContext $account,
        EffectivePermissions $permissions,
        ShowEmployeeLinkQuery $showLink,
        ShowEmployeeAssignmentQuery $showAssignment,
        EndEmployeeAssignmentAction $action,
        int $personId,
        int $employeeId,
        int $assignmentId,
    ): Response {
        $user = $request->user();
        $accountId = $account->requireId();

        $viewable = $permissions->nodesFor($user, 'account.employees.view');
        abort_if($viewable->isEmpty(), 403);
        abort_if($showLink->execute($accountId, $viewable, $personId, $employeeId) === null, 404);

        $assignment = $showAssignment->execute($accountId, $viewable, $employeeId, $assignmentId);
        abort_if($assignment === null, 404);

        abort_if($permissions->nodesFor($user, 'account.employees.assign')->isEmpty(), 403);

        $action->execute(EndEmployeeAssignmentData::fromInput($request->all()), $assignment);
        ApiPage::toast('Lotação encerrada.');

        return response()->noContent();
    }

    public function primary(
        Request $request,
        AccountContext $account,
        EffectivePermissions $permissions,
        ShowEmployeeLinkQuery $showLink,
        ShowEmployeeAssignmentQuery $showAssignment,
        SetPrimaryEmployeeAssignmentAction $action,
        int $personId,
        int $employeeId,
        int $assignmentId,
    ): Response {
        $user = $request->user();
        $accountId = $account->requireId();

        $viewable = $permissions->nodesFor($user, 'account.employees.view');
        abort_if($viewable->isEmpty(), 403);
        abort_if($showLink->execute($accountId, $viewable, $personId, $employeeId) === null, 404);

        $assignment = $showAssignment->execute($accountId, $viewable, $employeeId, $assignmentId);
        abort_if($assignment === null, 404);

        abort_if($permissions->nodesFor($user, 'account.employees.assign')->isEmpty(), 403);

        $action->execute($assignment);
        ApiPage::toast('Lotação principal definida.');

        return response()->noContent();
    }
}
