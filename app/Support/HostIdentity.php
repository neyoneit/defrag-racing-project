<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Answers "are these two host strings the same machine?".
 *
 * One SFTP account is one VPS, so every server declared under it has to
 * live on the same box. People name a box either by its address or by a
 * domain that points at it, and the same person often uses both, so the
 * comparison cannot be a string comparison - it has to happen on the
 * addresses the name resolves to.
 */
class HostIdentity
{
    /** How long a resolved name is trusted. Long enough to survive a
     *  burst of additions, short enough that a moved box is noticed. */
    private const TTL = 600;

    /**
     * Every address the host points at. A literal address resolves to
     * itself. An empty array means the name could not be resolved at all,
     * which callers must treat as "unknown", never as "no match".
     */
    public static function addresses(string $host): array
    {
        $host = self::normalize($host);

        if ($host === '') {
            return [];
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        return Cache::remember('host-addresses:' . $host, self::TTL, function () use ($host) {
            $addresses = [];

            // AAAA is only asked for when there is no A record, so a
            // dual-stacked box is compared on its v4 address - the one
            // people actually type into the form.
            foreach ([DNS_A, DNS_AAAA] as $type) {
                foreach (@dns_get_record($host, $type) ?: [] as $record) {
                    $address = $record['ip'] ?? $record['ipv6'] ?? null;
                    if ($address) {
                        $addresses[] = strtolower($address);
                    }
                }

                if ($addresses) {
                    break;
                }
            }

            return array_values(array_unique($addresses));
        });
    }

    /**
     * Same machine? The same name always is, even when DNS is down. Two
     * different names are only the same machine when they resolve and
     * share an address - an unresolvable name is never claimed to match.
     */
    public static function sameMachine(string $a, string $b): bool
    {
        if (self::normalize($a) === self::normalize($b) && self::normalize($a) !== '') {
            return true;
        }

        $left  = self::addresses($a);
        $right = self::addresses($b);

        if (! $left || ! $right) {
            return false;
        }

        return (bool) array_intersect($left, $right);
    }

    /** True when the name resolves to nothing - a typo, or dead DNS. */
    public static function isUnresolvable(string $host): bool
    {
        return self::addresses($host) === [];
    }

    /**
     * Strip what people paste around a hostname: a scheme, a trailing
     * slash, a port, the brackets of an IPv6 literal.
     */
    public static function normalize(string $host): string
    {
        $host = strtolower(trim($host));
        $host = preg_replace('#^[a-z][a-z0-9+.-]*://#', '', $host);
        $host = trim($host, '/');

        // An IPv6 literal is written in brackets precisely so its own
        // colons cannot be read as a port, so unwrap it before anything
        // else touches the colons.
        if (preg_match('/^\[(.+)\](?::\d+)?$/', $host, $m)) {
            return $m[1];
        }

        // A single colon is a port; several mean a bare IPv6 literal,
        // which keeps every one of them.
        if (substr_count($host, ':') === 1) {
            $host = explode(':', $host)[0];
        }

        return $host;
    }
}
