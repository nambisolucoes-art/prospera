<?php

use App\Domains\Acessos\Application\Actions\DefaultRolesAction;
use App\Domains\Acessos\Application\Actions\RegisterAccountMembershipAction;
use App\Domains\Acessos\Domain\DTOs\AccessNode;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (getenv('DB_DATABASE') !== 'e09') {
    throw new RuntimeException('Somente laboratório');
}
foreach ([1, 2, 5] as $count) {
    foreach (range(1, 10) as $index) {
        $user = User::factory()->asAccountAdministrator(1)->create(['email' => "login-$count-$index@e09.test"]);
        foreach (range(2, $count) as $id) {
            if ($count === 1) {
                break;
            }
            [$role] = app(DefaultRolesAction::class)->execute($id);
            app(RegisterAccountMembershipAction::class)->execute($user, $role, AccessNode::tenant($id));
        }
    }
}
echo json_encode(['login_profiles' => [1, 2, 5], 'identities_per_profile' => 10]),PHP_EOL;
