<?php

namespace App\Services\Webhooks;

use App\Enums\Mode;
use App\Exceptions\GatewayException;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Validates merchant webhook URLs to prevent SSRF into internal networks.
 * The check runs both when a URL is saved and right before each delivery
 * (DNS answers can change between the two).
 */
class WebhookUrlGuard
{
    private const PRIVATE_RANGES = [
        '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16', '172.16.0.0/12',
        '192.0.0.0/24', '192.168.0.0/16', '198.18.0.0/15', '224.0.0.0/4', '240.0.0.0/4',
        '::1/128', 'fc00::/7', 'fe80::/10', '::/128', '::ffff:0:0/96',
    ];

    public function assertAllowed(string $url, Mode $mode): void
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = $parts['host'] ?? null;

        if (! in_array($scheme, ['http', 'https'], true) || ! $host) {
            throw new GatewayException('invalid_webhook_url', 'Webhook URL must be an absolute http(s) URL.');
        }

        if ($mode === Mode::Live && config('gateway.webhooks.require_https_in_live') && $scheme !== 'https') {
            throw new GatewayException('invalid_webhook_url', 'Live webhook URLs must use HTTPS.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new GatewayException('invalid_webhook_url', 'Webhook URLs must not contain credentials.');
        }

        if (! config('gateway.webhooks.block_private_ips')) {
            return;
        }

        $host = trim($host, '[]');
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->resolve($host);

        if ($ips === []) {
            throw new GatewayException('invalid_webhook_url', 'Webhook host could not be resolved.');
        }

        foreach ($ips as $ip) {
            if (IpUtils::checkIp($ip, self::PRIVATE_RANGES)) {
                throw new GatewayException('invalid_webhook_url', 'Webhook URLs must point to a public internet address.');
            }
        }
    }

    /** @return list<string> */
    protected function resolve(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
        $ips = [];
        foreach ($records as $r) {
            if (isset($r['ip'])) {
                $ips[] = $r['ip'];
            } elseif (isset($r['ipv6'])) {
                $ips[] = $r['ipv6'];
            }
        }

        return $ips;
    }
}
