<?php

namespace App\Services;

use App\Exceptions\UnsafeUrlException;

class UrlSafetyService
{
    public function assertSafe(string $url): string
    {
        $parts = parse_url(trim($url));
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));
        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            throw new UnsafeUrlException('Only public HTTP and HTTPS URLs are allowed.');
        }
        if ($host === 'localhost' || str_ends_with($host, '.localhost')) {
            throw new UnsafeUrlException('Local and private network URLs are not allowed.');
        }

        $addresses = $this->resolveAddresses($host);
        if ($addresses === []) {
            throw new UnsafeUrlException('The website hostname could not be resolved.');
        }
        foreach ($addresses as $address) {
            if (! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new UnsafeUrlException('Local, private, reserved, and internal network addresses are not allowed.');
            }
        }

        $path = $parts['path'] ?? '/';
        $normalized = $scheme.'://'.$host;
        if (isset($parts['port'])) {
            $normalized .= ':'.(int) $parts['port'];
        }
        $normalized .= $path === '' ? '/' : $path;
        if (isset($parts['query']) && $parts['query'] !== '') {
            $normalized .= '?'.$parts['query'];
        }

        return $normalized;
    }

    /** @return list<string> */
    public function publicAddresses(string $url): array
    {
        $safe = $this->assertSafe($url);

        return $this->resolveAddresses((string) parse_url($safe, PHP_URL_HOST));
    }

    /** @return list<string> */
    protected function resolveAddresses(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }
        $addresses = gethostbynamel($host) ?: [];
        if (function_exists('dns_get_record')) {
            foreach (dns_get_record($host, DNS_AAAA) ?: [] as $record) {
                if (isset($record['ipv6'])) {
                    $addresses[] = $record['ipv6'];
                }
            }
        }

        return array_values(array_unique($addresses));
    }
}
