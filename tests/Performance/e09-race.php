<?php

use App\Domains\Acessos\Application\Actions\AcceptAccountInvitationAction;
use App\Domains\Acessos\Application\Actions\CreateAccountInvitationAction;
use App\Domains\Acessos\Application\Actions\DefaultRolesAction;
use App\Domains\Acessos\Application\Actions\RegisterAccountMembershipAction;
use App\Domains\Acessos\Application\Actions\SaveRoleAction;
use App\Domains\Acessos\Application\Actions\SetMembershipStatusAction;
use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Acessos\Application\Data\RoleAvailabilityData;
use App\Domains\Acessos\Application\Data\SaveRoleData;
use App\Domains\Acessos\Domain\DTOs\AccessNode;
use App\Domains\Acessos\Domain\Models\Permission;
use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Plataforma\Application\Actions\SaveAccountAction;
use App\Domains\Plataforma\Application\Data\SaveAccountData;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (getenv('DB_DATABASE') !== 'e09') {
    throw new RuntimeException('Somente o laboratório E09.');
}
$data = json_decode(file_get_contents('/tmp/e09-fixtures.json'), true, flags: JSON_THROW_ON_ERROR);
try {
    if ($argv[1] === 'setup') {
        $user = User::factory()->create(['email' => 'emitter@e09.test']);
        $account = Client::whereKey($data['accounts'][2]['id'])->firstOrFail();
        [$role] = app(DefaultRolesAction::class)->execute($account->id);
        app(RegisterAccountMembershipAction::class)->execute(
            $user, $role, AccessNode::tenant($account->id),
        );
        User::factory()->create(['email' => 'concurrent@e09.test']);
        echo json_encode(['result' => 'setup']), PHP_EOL;
    } elseif ($argv[1] === 'permission-setup') {
        Role::create([
            'account_id' => $data['accounts'][2]['id'], 'name' => 'Permissões concorrentes',
        ]);
        echo json_encode(['result' => 'permission-setup']), PHP_EOL;
    } elseif ($argv[1] === 'permission') {
        $account = Client::whereKey($data['accounts'][2]['id'])->firstOrFail();
        $user = User::where('email', 'emitter@e09.test')->sole();
        auth()->login($user);
        app(AccountContext::class)->enter($user, $account->code);
        $role = Role::where('account_id', $account->id)
            ->where('name', 'Permissões concorrentes')->sole();
        $slug = $argv[2] === '1' ? 'account.tags.list' : 'account.tags.create';
        $permission = Permission::where('slug', $slug)->sole();
        app(SaveRoleAction::class)->execute(
            $account->id, $role,
            SaveRoleData::fromInput([
                'name' => $role->name, 'permission_ids' => [$permission->id],
            ], $account->id, $role->id),
            RoleAvailabilityData::fromInput([], $account->id),
        );
        echo json_encode(['result' => 'permission-saved', 'permission' => $slug]), PHP_EOL;
    } elseif ($argv[1] === 'admin-setup') {
        $account = Client::whereKey($data['accounts'][4]['id'])->firstOrFail();
        $other = User::factory()->asAccountAdministrator($account->id)->create(['email' => 'second-admin@e09.test']);
        echo json_encode(['result' => 'admin-setup', 'second' => $other->id]), PHP_EOL;
    } elseif ($argv[1] === 'suspend-admin') {
        $account = Client::whereKey($data['accounts'][4]['id'])->firstOrFail();
        $user = User::where('email', $argv[2] === '1' ? 'five@e09.test' : 'second-admin@e09.test')->sole();
        auth()->login($user);
        app(AccountContext::class)->enter($user, $account->code);
        try {
            app(SetMembershipStatusAction::class)->execute(
                $account->id, $user->memberships()->where('account_id', $account->id)->sole()->id, false,
            );
            echo json_encode(['result' => 'suspended']), PHP_EOL;
        } catch (ValidationException) {
            echo json_encode(['result' => 'last-admin-protected']), PHP_EOL;
        }
    } elseif ($argv[1] === 'code') {
        $document = str_pad((string) (700000000 + (int) $argv[2]), 9, '0', STR_PAD_LEFT);
        foreach ([10, 11] as $weight) {
            $sum = 0;
            foreach (str_split($document) as $digit) {
                $sum += (int) $digit * $weight;
                $weight--;
            }
            $remainder = ($sum * 10) % 11;
            $document .= $remainder === 10 ? '0' : (string) $remainder;
        }
        $data = SaveAccountData::fromInput([
            'name' => 'Colisão Concorrente',
            'person_type' => 'individual',
            'document' => $document,
            'details' => ['phone' => '77999990000'],
            'administrator' => [
                'name' => 'Administrador sintético',
                'email' => 'final-code-'.$argv[2].'@e09.test',
                'mode' => 'manual',
                'password' => 'Synthetic-password-123!',
                'password_confirmation' => 'Synthetic-password-123!',
            ],
        ]);
        $message = app(SaveAccountAction::class)->execute(
            new Client, $data,
        );
        echo json_encode(['result' => 'created', 'message' => $message]), PHP_EOL;
    } elseif ($argv[1] === 'accept') {
        $user = User::whereKey($data['users']['recipient'])->firstOrFail();
        auth()->login($user);
        $account = app(AcceptAccountInvitationAction::class)->execute($user, $data['invitations']['valid']);
        echo json_encode(['result' => 'accepted', 'account' => $account->id]), PHP_EOL;
    } elseif ($argv[1] === 'issue') {
        $user = User::where('email', 'emitter@e09.test')->firstOrFail();
        auth()->login($user);
        $account = Client::whereKey($data['accounts'][2]['id'])->firstOrFail();
        [$role] = app(DefaultRolesAction::class)->execute($account->id);
        // O emissor tem autoridade sintética nesta conta para a prova de emissão concorrente.
        app(AccountContext::class)->enter($user, $account->code);
        app(CreateAccountInvitationAction::class)->execute(
            $account, 'concurrent@e09.test', $role->id, AccessNode::tenant($account->id), null,
        );
        echo json_encode(['result' => 'issued']), PHP_EOL;
    }
} catch (HttpExceptionInterface $exception) {
    echo json_encode(['result' => 'denied', 'status' => $exception->getStatusCode()]), PHP_EOL;
}
