<?php

namespace App\Support\Http;

use DomainException;

/**
 * The guard on every address the platform fetches on someone's say-so (supplier sources, brand websites). An administrator types the URL, so it is
 * treated as hostile: only http and https, no credentials in the URL, and the host must resolve to a
 * public address -- never loopback, a private range, link-local (cloud metadata) or reserved space.
 * It is checked when the source is saved and again right before every fetch, because DNS can change
 * between the two. Redirects are not followed by the fetcher, so a public URL cannot bounce inward.
 */
class SafeUrl
{
    /** @var callable(string): list<string> */
    private $resolver;

    public function __construct(?callable $resolver = null)
    {
        $this->resolver = $resolver ?? static function (string $host): array {
            $ips = gethostbynamel($host) ?: [];
            foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $r) {
                if (! empty($r['ipv6'])) {
                    $ips[] = $r['ipv6'];
                }
            }

            return $ips;
        };
    }

    /** @throws DomainException when the URL may not be fetched */
    public function check(string $url): string
    {
        $url = trim($url);
        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            throw new DomainException('Give a full http or https address.');
        }
        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            throw new DomainException('Only http and https sources can be fetched.');
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new DomainException('Put credentials in the source authentication, not in the address.');
        }
        if (isset($parts['port']) && ! in_array((int) $parts['port'], [80, 443, 8080, 8443], true)) {
            throw new DomainException('Sources are fetched on the standard web ports only.');
        }
        $host = strtolower(trim($parts['host'], '[]'));
        if ($host === 'localhost' || preg_match('/\.(localhost|local|internal|lan|home|corp)$/', $host)) {
            throw new DomainException('That address points inside a private network.');
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : ($this->resolver)($host);
        if ($ips === []) {
            throw new DomainException("The host {$host} could not be resolved.");
        }
        foreach ($ips as $ip) {
            if (! self::isPublic($ip)) {
                throw new DomainException('That address points inside a private network.');
            }
        }

        return $url;
    }

    public static function isPublic(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }
        // Ranges PHP's flags let through: carrier-grade NAT, benchmarking, IPv4-mapped and unique-local IPv6.
        foreach (['100.64.0.0/10', '198.18.0.0/15', '192.0.0.0/24', '0.0.0.0/8'] as $cidr) {
            if (self::inCidr($ip, $cidr)) {
                return false;
            }
        }

        return ! preg_match('/^(::ffff:|fc|fd|fe80)/i', $ip);
    }

    private static function inCidr(string $ip, string $cidr): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }
        [$net, $bits] = explode('/', $cidr);
        $mask = -1 << (32 - (int) $bits);

        return (ip2long($ip) & $mask) === (ip2long($net) & $mask);
    }
}
