<?php

namespace App\Services\Privacy;

use Illuminate\Container\Attributes\Config;

/**
 * Replaces secrets with a keyed hash, so equal secrets stay recognisable without being revealed.
 */
final readonly class SecretMasker
{
    public function __construct(#[Config('app.key')] private string $key) {}

    /**
     * @param  array<mixed>  $data
     * @param  list<string>  $paths  dotted paths, with `*` matching every item of a list
     * @return array<mixed>
     */
    public function mask(array $data, array $paths): array
    {
        foreach ($paths as $path) {
            $data = $this->maskPath($data, explode('.', $path));
        }

        return $data;
    }

    /**
     * Mask the password of every `scheme://user:password@` URL in the strings of a value, keeping the user.
     */
    public function maskUrlCredentials(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map($this->maskUrlCredentials(...), $value);
        }

        if (! is_string($value)) {
            return $value;
        }

        return preg_replace_callback(
            '#(://[^/\s:@]+:)([^/\s@]+)@#',
            fn (array $matches): string => $matches[1].$this->maskValue($matches[2]).'@',
            $value,
        );
    }

    /**
     * @param  list<string>  $segments
     */
    private function maskPath(mixed $value, array $segments): mixed
    {
        if ($segments === []) {
            return $this->maskValue($value);
        }

        if (! is_array($value)) {
            return $value;
        }

        $segment = array_shift($segments);

        if ($segment === '*') {
            foreach ($value as $index => $item) {
                $value[$index] = $this->maskPath($item, $segments);
            }
        } elseif (array_key_exists($segment, $value)) {
            $value[$segment] = $this->maskPath($value[$segment], $segments);
        }

        return $value;
    }

    private function maskValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map($this->maskValue(...), $value);
        }

        if (! is_scalar($value)) {
            return $value;
        }

        return '[masked:hmac-sha256:'.substr(hash_hmac('sha256', (string) $value, $this->key), 0, 12).']';
    }
}
