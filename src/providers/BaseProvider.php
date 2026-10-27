<?php

declare(strict_types=1);

namespace rapttor\payment\providers;

use yii\base\BaseObject;

/** Shared plumbing: config properties, a small curl client and constant-time comparison. */
abstract class BaseProvider extends BaseObject implements ProviderInterface
{
    /** "test" uses the provider's sandbox, "live" the real thing. */
    public string $mode = 'test';
    /** Owning app slug (set by {@see \rapttor\payment\Payments::$app}); empty = untagged. */
    public string $app = '';

    /** `custom_data` tag for everything this app creates at the provider. @return array<string,string> */
    protected function appTag(): array
    {
        return $this->app === '' ? [] : ['app' => $this->app];
    }

    /** Catalog code with the app prefix, e.g. `cetirizida-pro`. */
    protected function prefixed(string $code): string
    {
        return $this->app === '' ? $code : $this->app . '-' . $code;
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function dashboardLinks(): array
    {
        return [];
    }

    public function apiLinks(): array
    {
        return [];
    }

    public function requiredSettings(): array
    {
        return [];
    }

    public function isLive(): bool
    {
        return $this->mode === 'live';
    }

    /**
     * @param array<string,string> $headers "Name: value" pairs keyed by name
     * @param string|array<string,mixed>|null $body array is sent as given by the caller's encoding choice: strings are sent raw
     * @return array{status:int, body:array<string,mixed>, raw:string}
     */
    protected function http(string $method, string $url, array $headers = [], string|null $body = null): array
    {
        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $lines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException($this->id() . ' request failed: ' . $err);
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        $decoded = json_decode((string) $raw, true);

        return ['status' => $status, 'body' => is_array($decoded) ? $decoded : [], 'raw' => (string) $raw];
    }

    /** @param array{status:int, body:array<string,mixed>, raw:string} $response */
    protected function ensureOk(array $response, string $what): array
    {
        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new \RuntimeException(sprintf('%s: %s failed (HTTP %d): %s', $this->id(), $what, $response['status'], mb_substr($response['raw'], 0, 500)));
        }

        return $response['body'];
    }

    protected function equals(string $a, string $b): bool
    {
        return hash_equals($a, $b);
    }
}
