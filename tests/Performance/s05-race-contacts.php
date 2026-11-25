<?php

use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Colaboradores\Application\Actions\Assignments\SetPrimaryEmployeeAssignmentAction;
use App\Domains\Colaboradores\Application\Actions\Assignments\UpdateEmployeeAssignmentAction;
use App\Domains\Colaboradores\Application\Data\Assignments\AssignmentOptions;
use App\Domains\Colaboradores\Application\Data\Assignments\UpdateEmployeeAssignmentData;
use App\Domains\Colaboradores\Domain\Models\EmployeeAssignment;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;

require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (getenv('DB_DATABASE') !== 's04') {
    throw new RuntimeException('Somente o laboratório S04.');
}

// Uso: php s05-race-contacts.php contacts <id> <email> | primary <id>
[$scenario, $id, $email] = [$argv[1], (int) $argv[2], $argv[3] ?? ''];

$user = User::where('email', 'admin@s04.test')->first();
Auth::login($user);
$app->make(AccountContext::class)->enter($user, 'grupo-gartan');
$assignment = EmployeeAssignment::findOrFail($id);

try {
    if ($scenario === 'contacts') {
        $data = UpdateEmployeeAssignmentData::fromInput([
            'starts_on' => substr($assignment->starts_on, 0, 10),
            'contact_mode' => 'override',
            'work_email' => $email,
        ], new AssignmentOptions([], [], [], []));
        app(UpdateEmployeeAssignmentAction::class)->execute($data, $assignment);
    } else {
        app(SetPrimaryEmployeeAssignmentAction::class)->execute($assignment);
    }

    echo "OK\n";
} catch (Throwable $e) {
    echo get_class($e), ': ', substr($e->getMessage(), 0, 90), "\n";
}
