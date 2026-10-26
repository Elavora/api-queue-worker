<?php

declare(strict_types=1);

/*
 * Roda o QueueWorkerCommand com uma tarefa que sempre falha e maxAttempts 1.
 * Usado pelo QueueWorkerCommandTest para ler o STDERR e o código de saída.
 *
 * @see docs/USO.md#loop
 */

use Elavora\Api\Extension\QueueWorker\QueueWorker;
use Elavora\Api\Extension\QueueWorker\QueueWorkerCommand;
use Elavora\Api\Extension\QueueWorker\TaskPayload;
use Elavora\Api\Extension\QueueWorker\TaskRegistry;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require dirname(__DIR__) . '/Support/Doubles.php';

$queue = new FakeQueue();
$queue->push('default', (new TaskPayload('always.fail', maxAttempts: 1))->toArray());

$command = new QueueWorkerCommand(new QueueWorker($queue, (new TaskRegistry())->add('always.fail', new FailingTaskHandler())));

exit($command->run(['worker.php', '--sleep=0']));
