<?php

declare(strict_types=1);

use Elavora\Api\Extension\QueueWorker\TaskPayload;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Regras do TaskPayload: construtor, fromArray, tentativas e serialização.
 *
 * @see docs/USO.md#taskpayload
 */
final class TaskPayloadTest extends TestCase
{
    /** @see docs/USO.md#validação-do-construtor */
    public function testDefaults(): void
    {
        $payload = new TaskPayload('emails.send');

        self::assertSame('emails.send', $payload->task());
        self::assertSame([], $payload->data());
        self::assertSame(0, $payload->attempts());
        self::assertSame(3, $payload->maxAttempts());
    }

    /** @see docs/USO.md#taskpayload */
    public function testAcceptsNamedArguments(): void
    {
        $payload = new TaskPayload(task: 'a', data: ['k' => 1], attempts: 1, maxAttempts: 4);

        self::assertSame('a', $payload->task());
        self::assertSame(['k' => 1], $payload->data());
        self::assertSame(1, $payload->attempts());
        self::assertSame(4, $payload->maxAttempts());
    }

    /** @see docs/USO.md#taskpayload */
    public function testImplementsJsonSerializable(): void
    {
        self::assertInstanceOf(JsonSerializable::class, new TaskPayload('a'));
    }

    /** @see docs/USO.md#validação-do-construtor */
    public function testAcceptsNestedScalarData(): void
    {
        $data = ['x' => ['y' => [1, 2.5, true, null, 'z']]];

        self::assertSame($data, (new TaskPayload('a', $data))->data());
    }

    /** @see docs/USO.md#validação-do-construtor */
    public function testAttemptsMayReachMaxAttempts(): void
    {
        $payload = new TaskPayload('a', attempts: 3, maxAttempts: 3);

        self::assertSame(3, $payload->attempts());
        self::assertSame(3, $payload->maxAttempts());
    }

    /** @see docs/USO.md#validação-do-construtor */
    public function testAttemptsMayExceedMaxAttempts(): void
    {
        self::assertSame(5, (new TaskPayload('a', attempts: 5, maxAttempts: 3))->attempts());
    }

    /** @see docs/USO.md#validação-do-construtor */
    public function testTaskWithOnlySpacesIsAccepted(): void
    {
        self::assertSame(' ', (new TaskPayload(' '))->task());
    }

    /** @see docs/USO.md#validação-do-construtor */
    public function testDataKeysAreNotValidated(): void
    {
        $data = [0 => 'a', '' => 'b', 'chave com espaço' => 'c'];

        self::assertSame($data, (new TaskPayload('a', $data))->data());
    }

    /**
     * @return iterable<string, array{Closure(): TaskPayload, string}>
     */
    public static function invalidConstructorProvider(): iterable
    {
        yield 'task vazio' => [static fn(): TaskPayload => new TaskPayload(''), 'O nome da tarefa nao pode ser vazio.'];
        yield 'attempts negativo' => [static fn(): TaskPayload => new TaskPayload('a', attempts: -1), 'A quantidade de tentativas nao pode ser negativa.'];
        yield 'maxAttempts zero' => [static fn(): TaskPayload => new TaskPayload('a', maxAttempts: 0), 'A quantidade maxima de tentativas deve ser maior que zero.'];
        yield 'maxAttempts negativo' => [static fn(): TaskPayload => new TaskPayload('a', maxAttempts: -2), 'A quantidade maxima de tentativas deve ser maior que zero.'];
        yield 'objeto em data' => [static fn(): TaskPayload => new TaskPayload('a', ['obj' => new stdClass()]), 'Payload de tarefa aceita apenas valores escalares, nulos e arrays.'];
        yield 'recurso aninhado' => [static fn(): TaskPayload => new TaskPayload('a', ['lista' => [fopen('php://memory', 'r')]]), 'Payload de tarefa aceita apenas valores escalares, nulos e arrays.'];
        yield 'closure profunda' => [static fn(): TaskPayload => new TaskPayload('a', ['x' => ['y' => ['z' => static fn() => null]]]), 'Payload de tarefa aceita apenas valores escalares, nulos e arrays.'];
        yield 'ordem: task antes de attempts' => [static fn(): TaskPayload => new TaskPayload('', attempts: -1, maxAttempts: 0), 'O nome da tarefa nao pode ser vazio.'];
        yield 'ordem: attempts antes de maxAttempts' => [static fn(): TaskPayload => new TaskPayload('a', attempts: -1, maxAttempts: 0), 'A quantidade de tentativas nao pode ser negativa.'];
        yield 'ordem: maxAttempts antes de data' => [static fn(): TaskPayload => new TaskPayload('a', ['obj' => new stdClass()], maxAttempts: 0), 'A quantidade maxima de tentativas deve ser maior que zero.'];
    }

    /**
     * @param Closure(): TaskPayload $factory
     *
     * @see docs/USO.md#validação-do-construtor
     */
    #[DataProvider('invalidConstructorProvider')]
    public function testConstructorRejectsInvalidInput(Closure $factory, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        $factory();
    }

    /** @see docs/USO.md#fromarray */
    public function testFromArrayWithOnlyTaskUsesDefaults(): void
    {
        $payload = TaskPayload::fromArray(['task' => 'a']);

        self::assertSame('a', $payload->task());
        self::assertSame([], $payload->data());
        self::assertSame(0, $payload->attempts());
        self::assertSame(3, $payload->maxAttempts());
    }

    /** @see docs/USO.md#fromarray */
    public function testFromArrayPreservesValuesAndIgnoresExtraKeys(): void
    {
        $payload = TaskPayload::fromArray([
            'task' => 'a',
            'data' => ['k' => 1],
            'attempts' => 2,
            'max_attempts' => 5,
            'extra' => true,
        ]);

        self::assertSame('a', $payload->task());
        self::assertSame(['k' => 1], $payload->data());
        self::assertSame(2, $payload->attempts());
        self::assertSame(5, $payload->maxAttempts());
        self::assertArrayNotHasKey('extra', $payload->toArray());
    }

    /** @see docs/USO.md#fromarray */
    public function testFromArrayTreatsNullAsMissing(): void
    {
        $payload = TaskPayload::fromArray(['task' => 'a', 'data' => null, 'attempts' => null, 'max_attempts' => null]);

        self::assertSame([], $payload->data());
        self::assertSame(0, $payload->attempts());
        self::assertSame(3, $payload->maxAttempts());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidFromArrayProvider(): iterable
    {
        $task = 'Payload de tarefa deve conter task como string.';
        $data = 'Payload de tarefa deve conter data como array.';
        $attempts = 'Payload de tarefa deve conter attempts como inteiro.';
        $max = 'Payload de tarefa deve conter max_attempts como inteiro.';

        yield 'vazio' => [[], $task];
        yield 'task inteiro' => [['task' => 10], $task];
        yield 'task null' => [['task' => null], $task];
        yield 'data string' => [['task' => 'a', 'data' => 'x'], $data];
        yield 'attempts string' => [['task' => 'a', 'attempts' => '1'], $attempts];
        yield 'attempts string numerica 2' => [['task' => 'a', 'attempts' => '2'], $attempts];
        yield 'attempts float' => [['task' => 'a', 'attempts' => 2.0], $attempts];
        yield 'max_attempts float' => [['task' => 'a', 'max_attempts' => 2.0], $max];
        yield 'ordem: task antes de data' => [['task' => 10, 'data' => 'x'], $task];
        yield 'ordem: data antes de attempts' => [['task' => 'a', 'data' => 'x', 'attempts' => '1'], $data];
        yield 'ordem: attempts antes de max_attempts' => [['task' => 'a', 'attempts' => '1', 'max_attempts' => 2.0], $attempts];
        yield 'regra do construtor: task vazio' => [['task' => ''], 'O nome da tarefa nao pode ser vazio.'];
        yield 'regra do construtor: max_attempts zero' => [['task' => 'a', 'max_attempts' => 0], 'A quantidade maxima de tentativas deve ser maior que zero.'];
        yield 'regra do construtor: attempts negativo' => [['task' => 'a', 'attempts' => -1], 'A quantidade de tentativas nao pode ser negativa.'];
    }

    /**
     * @param array<string, mixed> $input
     *
     * @see docs/USO.md#fromarray
     */
    #[DataProvider('invalidFromArrayProvider')]
    public function testFromArrayRejectsInvalidInput(array $input, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        TaskPayload::fromArray($input);
    }

    /** @see docs/USO.md#fromarray */
    public function testFromArrayRoundTrip(): void
    {
        $original = new TaskPayload('emails.send', ['email' => 'ana@exemplo.com'], 2, 4);
        $copy = TaskPayload::fromArray($original->toArray());

        self::assertSame($original->task(), $copy->task());
        self::assertSame($original->data(), $copy->data());
        self::assertSame($original->attempts(), $copy->attempts());
        self::assertSame($original->maxAttempts(), $copy->maxAttempts());
    }

    /** @see docs/USO.md#tentativas */
    public function testWithRecordedFailureReturnsNewInstanceAndKeepsOriginal(): void
    {
        $original = new TaskPayload('a', ['k' => 1], 1, 4);
        $failed = $original->withRecordedFailure();

        self::assertNotSame($original, $failed);
        self::assertSame(2, $failed->attempts());
        self::assertSame('a', $failed->task());
        self::assertSame(['k' => 1], $failed->data());
        self::assertSame(4, $failed->maxAttempts());
        self::assertSame(1, $original->attempts());
    }

    /**
     * @return iterable<string, array{int, int, bool}>
     */
    public static function canRetryProvider(): iterable
    {
        yield '0 de 3' => [0, 3, true];
        yield '2 de 3' => [2, 3, true];
        yield '3 de 3' => [3, 3, false];
        yield '5 de 3' => [5, 3, false];
    }

    /** @see docs/USO.md#tentativas */
    #[DataProvider('canRetryProvider')]
    public function testCanRetry(int $attempts, int $maxAttempts, bool $expected): void
    {
        self::assertSame($expected, (new TaskPayload('a', attempts: $attempts, maxAttempts: $maxAttempts))->canRetry());
    }

    /** @see docs/USO.md#serialização */
    public function testToArrayKeysAndOrder(): void
    {
        $payload = new TaskPayload('emails.send', ['email' => 'ana@exemplo.com']);

        self::assertSame(
            ['task' => 'emails.send', 'data' => ['email' => 'ana@exemplo.com'], 'attempts' => 0, 'max_attempts' => 3],
            $payload->toArray(),
        );
    }

    /** @see docs/USO.md#serialização */
    public function testJsonSerializeEqualsToArray(): void
    {
        $payload = new TaskPayload('a', ['k' => [1, 2]], 1, 2);

        self::assertSame($payload->toArray(), $payload->jsonSerialize());
    }

    /** @see docs/USO.md#serialização */
    public function testJsonEncode(): void
    {
        self::assertSame(
            '{"task":"a","data":{"k":1},"attempts":0,"max_attempts":3}',
            json_encode(new TaskPayload('a', ['k' => 1]), JSON_THROW_ON_ERROR),
        );
    }
}
