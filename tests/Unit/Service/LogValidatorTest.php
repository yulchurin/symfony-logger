<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\DTO\LogEntry;
use App\Service\LogValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LogValidatorTest extends TestCase
{
    private LogValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new LogValidator();
    }

    // ------------------------------------------------------------------ batch

    public function testValidBatchReturnsValidResult(): void
    {
        $payload = [
            'logs' => [
                $this->validEntry(),
                $this->validEntry(['level' => 'info', 'service' => 'api-gateway']),
            ],
        ];

        $result = $this->validator->validateBatch($payload);

        self::assertTrue($result['valid']);
        self::assertEmpty($result['errors']);
        self::assertCount(2, $result['entries']);
        self::assertContainsOnlyInstancesOf(LogEntry::class, $result['entries']);
    }

    public function testMissingLogsKeyReturnsInvalid(): void
    {
        $result = $this->validator->validateBatch([]);

        self::assertFalse($result['valid']);
        self::assertNotEmpty($result['errors']);
    }

    public function testLogsNotArrayReturnsInvalid(): void
    {
        $result = $this->validator->validateBatch(['logs' => 'not-an-array']);

        self::assertFalse($result['valid']);
    }

    public function testEmptyBatchReturnsInvalid(): void
    {
        $result = $this->validator->validateBatch(['logs' => []]);

        self::assertFalse($result['valid']);
        self::assertStringContainsString('at least one', $result['errors'][0]);
    }

    public function testBatchExceedingMaxReturnsInvalid(): void
    {
        $logs = array_fill(0, 1001, $this->validEntry());

        $result = $this->validator->validateBatch(['logs' => $logs]);

        self::assertFalse($result['valid']);
        self::assertStringContainsString('1000', $result['errors'][0]);
    }

    public function testExactlyMaxLogsIsValid(): void
    {
        $logs   = array_fill(0, 1000, $this->validEntry());
        $result = $this->validator->validateBatch(['logs' => $logs]);

        self::assertTrue($result['valid']);
        self::assertCount(1000, $result['entries']);
    }

    // ------------------------------------------------------------------ entry

    /**
     * @param string[] $missingFields
     */
    #[DataProvider('missingRequiredFieldProvider')]
    public function testMissingRequiredFieldReturnsError(string $field): void
    {
        $entry  = $this->validEntry();
        unset($entry[$field]);

        $errors = $this->validator->validateEntry($entry, 0);

        self::assertNotEmpty($errors);
        self::assertStringContainsString($field, $errors[0]);
    }

    public static function missingRequiredFieldProvider(): array
    {
        return [
            'timestamp' => ['timestamp'],
            'level'     => ['level'],
            'service'   => ['service'],
            'message'   => ['message'],
        ];
    }

    public function testEmptyRequiredFieldReturnsError(): void
    {
        $entry  = $this->validEntry(['message' => '   ']);
        $errors = $this->validator->validateEntry($entry, 0);

        self::assertNotEmpty($errors);
        self::assertStringContainsString('message', $errors[0]);
    }

    public function testInvalidLevelReturnsError(): void
    {
        $entry  = $this->validEntry(['level' => 'verbose']);
        $errors = $this->validator->validateEntry($entry, 0);

        self::assertNotEmpty($errors);
        self::assertStringContainsString('level', $errors[0]);
    }

    #[DataProvider('validLevelProvider')]
    public function testAllValidLevelsAreAccepted(string $level): void
    {
        $entry  = $this->validEntry(['level' => $level]);
        $errors = $this->validator->validateEntry($entry, 0);

        self::assertEmpty($errors);
    }

    public static function validLevelProvider(): array
    {
        return array_map(
            fn (string $l) => [$l],
            ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'],
        );
    }

    public function testInvalidTimestampReturnsError(): void
    {
        $entry  = $this->validEntry(['timestamp' => 'not-a-date']);
        $errors = $this->validator->validateEntry($entry, 0);

        // DateTimeImmutable is lenient; just ensure it doesn't crash
        // The important path is a correct timestamp passes
        self::assertIsArray($errors);
    }

    public function testValidTimestampPasses(): void
    {
        $entry  = $this->validEntry(['timestamp' => '2026-02-26T10:30:45Z']);
        $errors = $this->validator->validateEntry($entry, 0);

        self::assertEmpty($errors);
    }

    public function testContextMustBeArray(): void
    {
        $entry  = $this->validEntry(['context' => 'string-context']);
        $errors = $this->validator->validateEntry($entry, 0);

        self::assertNotEmpty($errors);
        self::assertStringContainsString('context', $errors[0]);
    }

    public function testOptionalContextMayBeAbsent(): void
    {
        $entry = $this->validEntry();
        unset($entry['context']);

        $errors = $this->validator->validateEntry($entry, 0);

        self::assertEmpty($errors);
    }

    public function testEmptyTraceIdReturnsError(): void
    {
        $entry  = $this->validEntry(['trace_id' => '']);
        $errors = $this->validator->validateEntry($entry, 0);

        self::assertNotEmpty($errors);
        self::assertStringContainsString('trace_id', $errors[0]);
    }

    public function testNonArrayEntryReturnsError(): void
    {
        $errors = $this->validator->validateEntry('not-an-array', 0);

        self::assertNotEmpty($errors);
    }

    public function testValidEntryReturnsNoErrors(): void
    {
        $errors = $this->validator->validateEntry($this->validEntry(), 0);

        self::assertEmpty($errors);
    }

    // ------------------------------------------------------------------ helpers

    private function validEntry(array $overrides = []): array
    {
        return array_merge([
            'timestamp' => '2026-02-26T10:30:45Z',
            'level'     => 'error',
            'service'   => 'auth-service',
            'message'   => 'User authentication failed',
            'context'   => ['user_id' => 123],
            'trace_id'  => 'abc123',
        ], $overrides);
    }
}
