<?php

use App\Services\Outbox\OutboxPublisher;
use Illuminate\Contracts\Console\Kernel;

// CI/local test worker. NEVER run against a real application database.
require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    if (isset($argv[1])) {
        file_put_contents($argv[1], 'ready');
    }

    $result = $app->make(OutboxPublisher::class)->publish(1);
    echo json_encode($result, JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    file_put_contents('php://stderr', $exception::class.': '.$exception->getMessage());
    exit(2);
}
