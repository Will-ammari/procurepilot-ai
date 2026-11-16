<?php

use App\Models\ApprovalStep;
use App\Models\User;
use App\Services\Procurement\ApprovalWorkflowService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

// Child process used only by MySqlApprovalConcurrencyTest. No public route.
// Run from the repository root so Laravel bootstraps the same code and database.

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    $step = ApprovalStep::query()->findOrFail((int) $argv[1]);
    $user = User::query()->findOrFail((int) $argv[2]);
    $action = (string) $argv[3];

    if (isset($argv[4])) {
        file_put_contents($argv[4], 'ready');
    }

    $service = $app->make(ApprovalWorkflowService::class);
    match ($action) {
        'approve' => $service->approve($step, $user),
        'reject' => $service->reject($step, $user, 'Competing decision'),
        default => throw new InvalidArgumentException('Unknown action'),
    };

    echo 'accepted';
} catch (ValidationException|AuthorizationException $exception) {
    echo 'denied';
} catch (Throwable $exception) {
    file_put_contents('php://stderr', $exception::class.': '.$exception->getMessage());
    exit(2);
}
