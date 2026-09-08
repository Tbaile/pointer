<?php

namespace App\Support\Remote;

use RuntimeException;

/**
 * Connection settings for the support server that brokers access to targets.
 */
final readonly class SupportServerConfig
{
    public function __construct(
        public string $host,
        public string $user,
        public ?string $identityFile,
        public int $timeout,
        public int $maxOutputBytes,
    ) {}

    /**
     * Build the configuration from the "pointer" config file.
     *
     * @throws RuntimeException when the support server host is not configured
     */
    public static function fromConfig(): self
    {
        $host = config('pointer.support.host');

        if (! is_string($host) || $host === '') {
            throw new RuntimeException(
                'No support server configured. Set POINTER_SUPPORT_HOST in your environment.',
            );
        }

        return new self(
            host: $host,
            user: (string) config('pointer.support.user'),
            identityFile: self::identityFile(config('pointer.support.identity_file')),
            timeout: (int) config('pointer.execution.timeout'),
            maxOutputBytes: (int) config('pointer.execution.max_output_bytes'),
        );
    }

    /**
     * Resolve the configured identity against the storage path, so a relative
     * value such as "app/private/pointer" keeps working whatever the working
     * directory of the process happens to be. Absolute paths are left alone,
     * for deployments that keep the key outside the application.
     */
    private static function identityFile(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return str_starts_with($value, '/') ? $value : storage_path($value);
    }
}
