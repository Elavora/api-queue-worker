<?php

declare(strict_types=1);

use Elavora\Api\Extension\QueueWorker\QueueWorker;
use Elavora\Api\Extension\QueueWorker\QueueWorkerCommand;
use Elavora\Api\Extension\QueueWorker\TaskPayload;
use Elavora\Api\Extension\QueueWorker\TaskRegistry;
use Elavora\Api\Framework\Contracts\Queue;
use PHPUnit\Framework\TestCase;

final class QueueWorkerDocumentationTest extends TestCase
{
    public function testDocumentedRetryAndCommandFlowExecutes(): void
    {
        $queue = new DocumentationWorkerQueue();
        $runs = 0;
        $registry = (new TaskRegistry())->add(
            'reports.generate',
            static function (TaskPayload $payload) use (&$runs): void {
                $runs++;
                if ($runs === 1) {
                    throw new RuntimeException('Servico temporariamente indisponivel.');
                }
            }
        );
        $worker = new QueueWorker($queue, $registry);
        $queue->push(
            'reports',
            (new TaskPayload('reports.generate', ['report_id' => 42], maxAttempts: 3))->toArray()
        );

        $retry = $worker->workOnce('reports');
        self::assertTrue($retry->isRetried());
        self::assertSame(1, $retry->payload()?->attempts());

        $output = [];
        $command = new QueueWorkerCommand(
            worker: $worker,
            output: static function (string $message) use (&$output): void {
                $output[] = $message;
            }
        );

        self::assertSame(
            QueueWorkerCommand::EXIT_SUCCESS,
            $command->run(['worker.php', '--queue=reports', '--max-jobs=1', '--sleep=0'])
        );
        self::assertSame(["Tarefa processada na fila reports.\n"], $output);
        self::assertSame(2, $runs);
    }
}

final class DocumentationWorkerQueue implements Queue
{
    /** @var array<string, list<array<string, mixed>>> */
    private array $items = [];

    public function push(string $queue, array $payload): void
    {
        $this->items[$queue][] = $payload;
    }

    public function pop(string $queue): ?array
    {
        if (($this->items[$queue] ?? []) === []) {
            return null;
        }

        return array_shift($this->items[$queue]);
    }
}
