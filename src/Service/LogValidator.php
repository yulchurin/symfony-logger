<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\LogEntry;

class LogValidator
{
    private const array REQUIRED_FIELDS = ['timestamp', 'level', 'service', 'message'];

    private const array VALID_LEVELS = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'];

    private const int MAX_LOGS_PER_BATCH = 1000;

    /**
     * Validate the entire batch payload.
     *
     * @return array{valid: bool, errors: string[], entries: LogEntry[]}
     */
    public function validateBatch(array $payload): array
    {
        $errors  = [];
        $entries = [];

        if (!isset($payload['logs']) || !is_array($payload['logs'])) {
            return [
                'valid'   => false,
                'errors'  => ['Field "logs" is required and must be an array.'],
                'entries' => [],
            ];
        }

        $logs = $payload['logs'];

        if (count($logs) === 0) {
            return [
                'valid'   => false,
                'errors'  => ['Batch must contain at least one log entry.'],
                'entries' => [],
            ];
        }

        if (count($logs) > self::MAX_LOGS_PER_BATCH) {
            return [
                'valid'   => false,
                'errors'  => [sprintf('Batch exceeds maximum of %d log entries.', self::MAX_LOGS_PER_BATCH)],
                'entries' => [],
            ];
        }

        foreach ($logs as $index => $log) {
            $entryErrors = $this->validateEntry($log, $index);

            if (!empty($entryErrors)) {
                $errors = array_merge($errors, $entryErrors);
            } else {
                $entries[] = LogEntry::fromArray($log);
            }
        }

        return [
            'valid'   => empty($errors),
            'errors'  => $errors,
            'entries' => $entries,
        ];
    }

    /**
     * Validate a single log entry.
     *
     * @return array<string>
     */
    public function validateEntry(mixed $entry, int $index): array
    {
        $errors = [];

        if (!is_array($entry)) {
            return [sprintf('Log entry at index %d must be an object.', $index)];
        }

        foreach (self::REQUIRED_FIELDS as $field) {
            if (!array_key_exists($field, $entry)) {
                $errors[] = sprintf('Log entry[%d]: missing required field "%s".', $index, $field);
            } elseif (!is_string($entry[$field]) || trim($entry[$field]) === '') {
                $errors[] = sprintf('Log entry[%d]: field "%s" must be a non-empty string.', $index, $field);
            }
        }

        if (isset($entry['level']) && is_string($entry['level'])) {
            if (!in_array(strtolower($entry['level']), self::VALID_LEVELS, true)) {
                $errors[] = sprintf(
                    'Log entry[%d]: invalid level "%s". Allowed: %s.',
                    $index,
                    $entry['level'],
                    implode(', ', self::VALID_LEVELS),
                );
            }
        }

        if (isset($entry['timestamp']) && is_string($entry['timestamp'])) {
            if (!$this->isValidTimestamp($entry['timestamp'])) {
                $errors[] = sprintf(
                    'Log entry[%d]: "timestamp" must be a valid ISO 8601 datetime (e.g. 2026-02-26T10:30:45Z).',
                    $index,
                );
            }
        }

        if (isset($entry['context']) && !is_array($entry['context'])) {
            $errors[] = sprintf('Log entry[%d]: "context" must be an object/array.', $index);
        }

        if (isset($entry['trace_id']) && (!is_string($entry['trace_id']) || trim($entry['trace_id']) === '')) {
            $errors[] = sprintf('Log entry[%d]: "trace_id" must be a non-empty string when provided.', $index);
        }

        return $errors;
    }

    private function isValidTimestamp(string $timestamp): bool
    {
        try {
            new \DateTimeImmutable($timestamp);
            return true;
        } catch (\Exception) {
            return false;
        }
    }
}
