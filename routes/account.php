<?php

use App\Domains\Acessos\UI\Controllers\AccessController;
use App\Domains\Acessos\UI\Controllers\AccountInvitationController;
use App\Domains\Acessos\UI\Controllers\AccountSelectionController;
use App\Domains\Ajuda\UI\Controllers\HelpController;
use App\Domains\Auditoria\UI\Controllers\AuditController;
use App\Domains\Colaboradores\UI\Controllers\DepartmentController;
use App\Domains\Colaboradores\UI\Controllers\EmployeeAssignmentController;
use App\Domains\Colaboradores\UI\Controllers\EmployeeController;
use App\Domains\Colaboradores\UI\Controllers\JobTitleController;
use App\Domains\Etiquetas\UI\Controllers\TagController;
use App\Domains\Institucional\UI\Controllers\InstitutionalHoursController;
use App\Domains\Institucional\UI\Controllers\InstitutionalProfileController;
use App\Domains\Midia\UI\Controllers\MediaController;
use App\Domains\Organizacao\Domain\ValueObjects\OrganizationCode;
use App\Domains\Organizacao\UI\Controllers\CompanyController;
use App\Domains\Organizacao\UI\Controllers\EstablishmentContextController;
use App\Domains\Organizacao\UI\Controllers\EstablishmentController;
use App\Domains\Organizacao\UI\Controllers\OrganizationController;
use App\Domains\Pessoas\UI\Controllers\CommercialRelationController;
use App\Domains\Plataforma\Domain\ValueObjects\AccountCode;
use App\Domains\Plataforma\UI\Controllers\AccountInspectionController;
use App\Domains\Plataforma\UI\Controllers\ClientController;
use App\Domains\Usuarios\UI\Controllers\Settings\ProfilePhotoController;
use App\Domains\Usuarios\UI\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// E09: a conta vem da URL e é validada pelo vínculo ativo em cada requisição (middleware account).
Route::middleware('auth')->group(function () {
    Route::post('/inspecao/encerrar', [AccountInspectionController::class, 'end'])
        ->block()
        ->name('inspection.end');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/contas', [AccountSelectionController::class, 'index'])
        ->name('accounts.select');

    Route::get('/convites/{token}', [AccountInvitationController::class, 'show'])
        ->where('token', '[A-Za-z0-9]{64}')
        ->middleware('throttle:account-invitations-view')
        ->name('invitations.show');

    Route::post('/convites/{token}', [AccountInvitationController::class, 'accept'])
        ->where('token', '[A-Za-z0-9]{64}')
        ->middleware('throttle:account-invitations-accept')
        ->name('invitations.accept');

    // Endereços anteriores à E09: só leitura segura, sem escolher uma conta ambígua pela pessoa.
    Route::get('/conta/{caminho?}', [AccountSelectionController::class, 'legacy'])
        ->where('caminho', '.*')
        ->name('accounts.legacy');
});

Route::middleware(['auth', 'verified', 'account'])
    ->prefix('contas/{conta}')
    ->where(['conta' => AccountCode::ROUTE_PATTERN])
    ->name('account.')
    ->group(function () {
        Route::get('/', [ClientController::class, 'accountDashboard'])
            ->name('dashboard');

        Route::get('organizacao', OrganizationController::class)
            ->name('organization');

        Route::get('editar', [ClientController::class, 'editTenant'])
            ->name('edit')
            ->middleware('can:account.account-data.update');

        Route::patch('/', [ClientController::class, 'updateTenant'])
            ->name('update')
            ->middleware('can:account.account-data.update');

        // Central de Ajuda com os artigos permitidos nesta conta; /ajuda segue para a identidade.
        Route::get('ajuda', [HelpController::class, 'index'])
            ->middleware('can:self.help.list')
            ->name('help.index');

        Route::get('ajuda/{slug}', [HelpController::class, 'show'])
            ->where('slug', '[a-z0-9-]+')
            ->middleware('can:self.help.view')
            ->name('help.show');

        Route::get('auditoria', [AuditController::class, 'index'])
            ->name('audit.index');

        Route::get('auditoria/{recordId}/visualizar', [AuditController::class, 'show'])
            ->whereNumber('recordId')
            ->name('audit.show');

        Route::prefix('usuarios')->name('users.')->group(function () {
            Route::get('/', [UserController::class, 'tenantIndex'])
                ->name('index');

            Route::get('cadastrar', [UserController::class, 'create'])
                ->name('create');

            Route::get('pessoas', [UserController::class, 'peopleForAccess'])->name('people');

            Route::get('{userId}/foto/{version}', [ProfilePhotoController::class, 'showDirectory'])
                ->whereNumber('userId')
                ->whereUuid('version')
                ->name('photo');

            Route::post('/', [UserController::class, 'store'])
                ->name('store');

            Route::get('{membershipId}/atribuicoes', [UserController::class, 'assignments'])
                ->whereNumber('membershipId')
                ->name('assignments');

            Route::patch('{membershipId}/atribuicoes', [UserController::class, 'saveAssignments'])
                ->whereNumber('membershipId')
                ->name('assignments.update');

            Route::patch('{membershipId}/suspender', [UserController::class, 'suspend'])
                ->whereNumber('membershipId')
                ->middleware('can:account.users.revoke')
                ->name('suspend');

            Route::patch('{membershipId}/reativar', [UserController::class, 'reactivate'])
                ->whereNumber('membershipId')
                ->middleware('can:account.users.reactivate')
                ->name('reactivate');

            Route::post('{membershipId}/recuperacao', [UserController::class, 'sendPasswordReset'])
                ->whereNumber('membershipId')
                ->middleware(['can:account.users.send-password-reset', 'throttle:5,1'])
                ->name('send-password-reset');

            Route::post('{membershipId}/convite', [UserController::class, 'resendInvitation'])
                ->whereNumber('membershipId')
                ->middleware(['can:account.users.invite', 'throttle:5,1'])
                ->name('invite');

            Route::patch('convites/{invitationId}/revogar', [AccountInvitationController::class, 'revoke'])
                ->whereNumber('invitationId')
                ->middleware('can:account.users.revoke-invitation')
                ->name('invitations.revoke');
        });

        Route::name('directory.')->group(function () {
            $tags = TagController::class;

            // Listar e visualizar valem para qualquer atribuição com a permissão (8.2.1): conferido no controller.
            Route::get('etiquetas', [$tags, 'index'])
                ->name('tags.index');

            Route::get('etiquetas/cadastrar', [$tags, 'form'])
                ->name('tags.create')
                ->middleware('can:account.tags.create');

            Route::get('etiquetas/{tagId}/visualizar', [$tags, 'preview'])
                ->whereNumber('tagId')
                ->name('tags.view');

            Route::get('etiquetas/{tagId}/editar', [$tags, 'form'])
                ->whereNumber('tagId')
                ->name('tags.edit')
                ->middleware('can:account.tags.update');

            Route::post('etiquetas', [$tags, 'store'])
                ->name('tags.store')
                ->middleware('can:account.tags.create');

            Route::delete('etiquetas/{tagId}', [$tags, 'destroy'])
                ->whereNumber('tagId')
                ->name('tags.destroy')
                ->middleware('can:account.tags.delete');

            Route::patch('etiquetas/{tagId}', [$tags, 'update'])
                ->whereNumber('tagId')
                ->name('tags.update')
                ->middleware('can:account.tags.update');
        });

        Route::name('access.')->group(function () {
            $access = AccessController::class;

            Route::get('cargos', [$access, 'roles'])
                ->name('roles.index');

            Route::get('cargos/cadastrar', [$access, 'roleForm'])
                ->middleware('can:account.roles.create')
                ->name('roles.create');

            Route::get('cargos/{roleId}/editar', [$access, 'roleForm'])
                ->whereNumber('roleId')
                ->middleware('can:account.roles.update')
                ->name('roles.edit');

            Route::get('cargos/{roleId}/visualizar', [$access, 'rolePreview'])
                ->whereNumber('roleId')
                ->name('roles.view');

            Route::post('cargos', [$access, 'storeRole'])
                ->middleware('can:account.roles.create')
                ->name('roles.store');

            Route::get('cargos/{roleId}/impacto-abrangencia', [AccessController::class, 'roleImpact'])
                ->whereNumber('roleId')
                ->middleware('can:account.roles.update')
                ->name('roles.availability-impact');

            Route::patch('cargos/{roleId}', [$access, 'updateRole'])
                ->whereNumber('roleId')
                ->middleware('can:account.roles.update')
                ->name('roles.update');
        });

        Route::name('companies.')->group(function () {
            Route::get('empresas', [CompanyController::class, 'index'])
                ->name('index');

            Route::get('empresas/cadastrar', [CompanyController::class, 'form'])
                ->name('create')
                ->middleware('can:account.companies.create');

            Route::get('empresas/{companyId}/editar', [CompanyController::class, 'form'])
                ->whereNumber('companyId')
                ->name('edit');

            Route::get('empresas/{companyId}/visualizar', [CompanyController::class, 'preview'])
                ->whereNumber('companyId')
                ->name('view');

            Route::post('empresas', [CompanyController::class, 'store'])
                ->name('store')
                ->middleware('can:account.companies.create');

            Route::patch('empresas/{companyId}', [CompanyController::class, 'update'])
                ->whereNumber('companyId')
                ->name('update');
        });

        Route::name('establishments.')->group(function () {
            Route::get('estabelecimentos', [EstablishmentController::class, 'index'])
                ->name('index');

            Route::get('estabelecimentos/cadastrar', [EstablishmentController::class, 'form'])
                ->name('create');

            Route::get('estabelecimentos/{establishmentId}/editar', [EstablishmentController::class, 'form'])
                ->whereNumber('establishmentId')
                ->name('edit');

            Route::get('estabelecimentos/{establishmentId}/visualizar', [EstablishmentController::class, 'preview'])
                ->whereNumber('establishmentId')
                ->name('view');

            Route::post('estabelecimentos', [EstablishmentController::class, 'store'])
                ->name('store');

            Route::patch('estabelecimentos/{establishmentId}', [EstablishmentController::class, 'update'])
                ->whereNumber('establishmentId')
                ->name('update');

            Route::post('estabelecimentos/{establishmentId}/principal', [EstablishmentController::class, 'promote'])
                ->whereNumber('establishmentId')
                ->name('promote');
        });

        // Rotas com contexto organizacional na URL (E06, DA-28), dentro da conta da URL (E09).
        Route::get('estabelecimento', [EstablishmentContextController::class, 'enter'])
            ->name('establishment.enter');

        Route::get('area-de-trabalho/padrao', [EstablishmentContextController::class, 'showDefault'])
            ->name('workspace.show-default');

        Route::put('area-de-trabalho/padrao', [EstablishmentContextController::class, 'saveDefault'])
            ->name('workspace.default');

        // S03: departamentos e funções na mesma tela para todas as empresas do acesso. A autorização de cada
        // operação é conferida no servidor sobre a empresa do registro ou as empresas escolhidas no cadastro.
        foreach ([
            'departamentos' => [DepartmentController::class, 'departments'],
            'funcoes' => [JobTitleController::class, 'job-titles'],
        ] as $segment => [$controller, $name]) {
            Route::get($segment, [$controller, 'index'])
                ->name($name.'.index');

            Route::get($segment.'/{itemId}/visualizar', [$controller, 'show'])
                ->whereNumber('itemId')
                ->name($name.'.view');

            Route::post($segment, [$controller, 'store'])
                ->name($name.'.store');

            Route::patch($segment.'/{itemId}', [$controller, 'update'])
                ->whereNumber('itemId')
                ->name($name.'.update');
        }

        // S06: o perfil institucional pertence a uma empresa ou a um estabelecimento. A lista leva a cada dono.
        Route::get('institucional', [InstitutionalProfileController::class, 'index'])
            ->name('institutional.index');

        // S08: entrada da biblioteca de mídia: as empresas e unidades que o acesso alcança.
        Route::get('midia', [MediaController::class, 'owners'])->name('media.owners');

        // S04: colaboradores de todas as empresas do acesso, numa só tela, com uma linha por pessoa. Cada operação
        // confere a permissão sobre a empresa do vínculo ou sobre as empresas escolhidas no cadastro.
        Route::get('colaboradores', [EmployeeController::class, 'index'])
            ->name('employees.index');

        Route::get('colaboradores/cadastrar', [EmployeeController::class, 'create'])
            ->name('employees.create');

        Route::post('colaboradores/pessoa', [EmployeeController::class, 'findPerson'])
            ->name('employees.find-person');

        Route::post('colaboradores', [EmployeeController::class, 'store'])
            ->name('employees.store');

        Route::get('colaboradores/{personId}/visualizar', [EmployeeController::class, 'show'])
            ->whereNumber('personId')
            ->name('employees.view');

        Route::get('colaboradores/{personId}/editar', [EmployeeController::class, 'edit'])
            ->whereNumber('personId')
            ->name('employees.edit');

        Route::patch('colaboradores/{personId}', [EmployeeController::class, 'updatePerson'])
            ->whereNumber('personId')
            ->name('employees.update-person');

        Route::post('colaboradores/{personId}/vinculos', [EmployeeController::class, 'addLinks'])
            ->whereNumber('personId')
            ->name('employees.add-links');

        Route::patch('colaboradores/{personId}/vinculos/{employeeId}', [EmployeeController::class, 'updateLink'])
            ->whereNumber('personId')
            ->whereNumber('employeeId')
            ->name('employees.update-link');

        // S05: lotações do vínculo. A unidade vem do corpo e é validada no servidor contra o escopo de `assign`.
        $assignmentUrl = 'colaboradores/{personId}/vinculos/{employeeId}/lotacoes';

        Route::post($assignmentUrl, [EmployeeAssignmentController::class, 'store'])
            ->whereNumber(['personId', 'employeeId'])
            ->name('employees.assignments.store');

        foreach ([
            ['patch', '', 'update', 'update'],
            ['post', '/trocar', 'change', 'change'],
            ['patch', '/encerrar', 'end', 'end'],
            ['patch', '/principal', 'primary', 'primary'],
        ] as [$method, $suffix, $action, $name]) {
            Route::$method($assignmentUrl.'/{assignmentId}'.$suffix, [EmployeeAssignmentController::class, $action])
                ->whereNumber(['personId', 'employeeId', 'assignmentId'])
                ->name('employees.assignments.'.$name);
        }

        Route::prefix('estabelecimento/{estabelecimento}')
            ->where(['estabelecimento' => OrganizationCode::ROUTE_PATTERN])
            ->middleware('organization:establishment,consolidated')
            ->name('establishment.')
            ->group(function () {
                Route::get('/', [EstablishmentContextController::class, 'home'])
                    ->name('home');

                // E07: relações comerciais das empresas das unidades da URL, sem repetir a empresa.
                Route::get('clientes', [CommercialRelationController::class, 'index'])
                    ->defaults('role', 'customer')
                    ->name('customers.index');

                Route::get('fornecedores', [CommercialRelationController::class, 'index'])
                    ->defaults('role', 'supplier')
                    ->name('suppliers.index');

                Route::get('clientes/cadastrar', [CommercialRelationController::class, 'create'])
                    ->defaults('role', 'customer')
                    ->name('customers.create');

                Route::get('fornecedores/cadastrar', [CommercialRelationController::class, 'create'])
                    ->defaults('role', 'supplier')
                    ->name('suppliers.create');

                foreach (['clientes' => 'customer', 'fornecedores' => 'supplier'] as $segment => $role) {
                    Route::get($segment.'/{companyCode}/{relationId}/editar', [CommercialRelationController::class, 'edit'])
                        ->where('companyCode', OrganizationCode::ROUTE_PATTERN)
                        ->whereNumber('relationId')
                        ->defaults('role', $role)
                        ->name(($role === 'customer' ? 'customers' : 'suppliers').'.edit');
                }

                // S06: perfil institucional do estabelecimento. O consolidado `todos` nunca escreve nem lê aqui.
                Route::get('institucional', [InstitutionalProfileController::class, 'show'])->name('institutional.show');
                Route::get('institucional/impacto', [InstitutionalProfileController::class, 'impact'])
                    ->name('institutional.impact');
                Route::patch('institucional/secoes/{secao}', [InstitutionalProfileController::class, 'saveSection'])
                    ->name('institutional.section');
                Route::post('institucional/publicar', [InstitutionalProfileController::class, 'publish'])
                    ->name('institutional.publish');
                Route::patch('institucional/endereco-publico', [InstitutionalProfileController::class, 'setAddress'])
                    ->name('institutional.address');
                // S07: exceções de horário e fuso da unidade.
                Route::post('institucional/excecoes', [InstitutionalHoursController::class, 'store'])
                    ->name('institutional.exceptions.store');
                Route::patch('institucional/excecoes/{excecao}', [InstitutionalHoursController::class, 'update'])
                    ->name('institutional.exceptions.update');
                Route::delete('institucional/excecoes/{excecao}', [InstitutionalHoursController::class, 'destroy'])
                    ->name('institutional.exceptions.destroy');
                Route::patch('institucional/fuso', [InstitutionalHoursController::class, 'setTimezone'])
                    ->name('institutional.timezone');
                // S08: biblioteca de mídia. Os arquivos só saem por estas rotas autenticadas e autorizadas.
                Route::get('midia', [MediaController::class, 'index'])->name('media.index');
                Route::post('midia', [MediaController::class, 'store'])->name('media.store');
                Route::get('midia/{midia}', [MediaController::class, 'show'])->name('media.show');
                Route::patch('midia/{midia}', [MediaController::class, 'update'])->name('media.update');
                Route::delete('midia/{midia}', [MediaController::class, 'destroy'])->name('media.destroy');
                Route::get('midia/{midia}/variantes/{variante}', [MediaController::class, 'variant'])->name('media.variant');
                Route::get('midia/{midia}/original', [MediaController::class, 'original'])->name('media.original');
            });

        // E07: escritas comerciais nomeiam a empresa responsável; nunca há empresa escolhida pelo sistema.
        Route::prefix('empresa/{empresa}')
            ->where(['empresa' => OrganizationCode::ROUTE_PATTERN])
            ->middleware('organization:company')
            ->name('company.')
            ->group(function () {
                foreach (['clientes' => 'customer', 'fornecedores' => 'supplier'] as $segment => $role) {
                    $name = $role === 'customer' ? 'customers.' : 'suppliers.';

                    Route::get($segment.'/{relationId}/visualizar', [CommercialRelationController::class, 'show'])
                        ->whereNumber('relationId')
                        ->defaults('role', $role)
                        ->name($name.'view');

                    Route::post($segment.'/pessoa', [CommercialRelationController::class, 'findPerson'])
                        ->defaults('role', $role)
                        ->name($name.'find-person');

                    Route::post($segment, [CommercialRelationController::class, 'store'])
                        ->defaults('role', $role)
                        ->name($name.'store');

                    Route::patch($segment.'/{relationId}', [CommercialRelationController::class, 'update'])
                        ->whereNumber('relationId')
                        ->defaults('role', $role)
                        ->name($name.'update');

                    Route::patch($segment.'/{relationId}/ativar', [CommercialRelationController::class, 'activate'])
                        ->whereNumber('relationId')
                        ->defaults('role', $role)
                        ->name($name.'activate');

                    Route::patch($segment.'/{relationId}/inativar', [CommercialRelationController::class, 'deactivate'])
                        ->whereNumber('relationId')
                        ->defaults('role', $role)
                        ->name($name.'deactivate');
                }

                // S06: perfil institucional da empresa.
                Route::get('institucional', [InstitutionalProfileController::class, 'show'])->name('institutional.show');
                Route::get('institucional/impacto', [InstitutionalProfileController::class, 'impact'])
                    ->name('institutional.impact');
                Route::patch('institucional/secoes/{secao}', [InstitutionalProfileController::class, 'saveSection'])
                    ->name('institutional.section');
                Route::post('institucional/publicar', [InstitutionalProfileController::class, 'publish'])
                    ->name('institutional.publish');
                // S07: exceções de horário da empresa e o resumo do alcance sobre unidades com horário próprio.
                Route::post('institucional/excecoes', [InstitutionalHoursController::class, 'store'])
                    ->name('institutional.exceptions.store');
                Route::patch('institucional/excecoes/{excecao}', [InstitutionalHoursController::class, 'update'])
                    ->name('institutional.exceptions.update');
                Route::delete('institucional/excecoes/{excecao}', [InstitutionalHoursController::class, 'destroy'])
                    ->name('institutional.exceptions.destroy');
                Route::get('institucional/excecoes/impacto', [InstitutionalHoursController::class, 'impact'])
                    ->name('institutional.exceptions.impact');
                // S08: biblioteca de mídia. Os arquivos só saem por estas rotas autenticadas e autorizadas.
                Route::get('midia', [MediaController::class, 'index'])->name('media.index');
                Route::post('midia', [MediaController::class, 'store'])->name('media.store');
                Route::get('midia/{midia}', [MediaController::class, 'show'])->name('media.show');
                Route::patch('midia/{midia}', [MediaController::class, 'update'])->name('media.update');
                Route::delete('midia/{midia}', [MediaController::class, 'destroy'])->name('media.destroy');
                Route::get('midia/{midia}/variantes/{variante}', [MediaController::class, 'variant'])->name('media.variant');
                Route::get('midia/{midia}/original', [MediaController::class, 'original'])->name('media.original');
            });
    });
