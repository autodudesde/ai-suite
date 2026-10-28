<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Service\Http;

use AutoDudes\AiSuite\Exception\ResponseTooLargeException;
use AutoDudes\AiSuite\Exception\UnsafeUrlException;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use Psr\Http\Message\ResponseInterface;

class PublicUrlGuard
{
    /**
     * @var list<string>
     */
    private const BLOCKED_CIDRS = [
        '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16',
        '172.16.0.0/12', '192.0.0.0/24', '192.0.2.0/24', '192.168.0.0/16', '198.18.0.0/15',
        '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4', '255.255.255.255/32',
        '::1/128', '::/128', 'fc00::/7', 'fe80::/10', 'ff00::/8', '::ffff:0:0/96', '64:ff9b::/96',
    ];
    private ?ClientInterface $client = null;

    public function __construct(
        private readonly HostResolver $hostResolver = new HostResolver(),
        private readonly ?\Closure $transport = null,
    ) {}

    /**
     * @param list<string> $extraDenyList
     *
     * @return array{host: string, port: int, ips: list<string>}
     *
     * @throws UnsafeUrlException
     * @throws \InvalidArgumentException
     */
    public function check(string $url, array $extraDenyList = []): array
    {
        $parts = parse_url($url);
        if (false === $parts || empty($parts['scheme']) || empty($parts['host'])) {
            throw new \InvalidArgumentException('Invalid media URL.');
        }

        $scheme = strtolower((string) $parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new UnsafeUrlException(sprintf('Unsupported URL scheme "%s" — only http/https are allowed.', $scheme));
        }

        $hostName = trim((string) $parts['host'], '[]');

        [$denyHosts, $denyCidrs] = $this->splitDenyList($extraDenyList);
        if (in_array(strtolower($hostName), $denyHosts, true)) {
            throw new UnsafeUrlException('Target host is blocked by configuration.');
        }

        $ips = $this->hostResolver->resolve($hostName);
        if ([] === $ips) {
            throw new \InvalidArgumentException(sprintf('Could not resolve host "%s".', $hostName));
        }

        foreach ($ips as $ip) {
            $this->assertIpIsPublic($ip);
            foreach ($denyCidrs as $cidr) {
                if ($this->ipMatchesCidr($ip, $cidr)) {
                    throw new UnsafeUrlException('Target address is blocked by configuration.');
                }
            }
        }

        return [
            'host' => $hostName,
            'port' => isset($parts['port']) ? (int) $parts['port'] : ('https' === $scheme ? 443 : 80),
            'ips' => $ips,
        ];
    }

    /**
     * @param array{host: string, port: int, ips: list<string>} $target
     * @param array<string, mixed>                              $options
     *
     * @throws ResponseTooLargeException
     * @throws GuzzleException
     */
    public function fetch(string $url, array $target, array $options, int $maxBytes = 0): ResponseInterface
    {
        $options += $this->pinnedResolution($target);
        if ($maxBytes > 0) {
            $options['progress'] = static function (int $downloadTotal, int $downloaded) use ($maxBytes): void {
                if ($downloadTotal > $maxBytes || $downloaded > $maxBytes) {
                    throw new ResponseTooLargeException(sprintf('The response exceeds %d bytes.', $maxBytes));
                }
            };
        }

        try {
            return $this->client()->request('GET', $url, $options);
        } catch (\Throwable $exception) {
            for ($cause = $exception; null !== $cause; $cause = $cause->getPrevious()) {
                if ($cause instanceof ResponseTooLargeException) {
                    throw $cause;
                }
            }

            throw $exception;
        }
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function resolveRedirectTarget(string $base, string $location): string
    {
        $location = trim($location);
        if ('' === $location) {
            throw new \InvalidArgumentException('Redirect without a target location.');
        }

        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }

        $parts = parse_url($base);
        if (false === $parts || empty($parts['scheme']) || empty($parts['host'])) {
            throw new \InvalidArgumentException('Cannot resolve a relative redirect.');
        }

        $origin = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
        if (str_starts_with($location, '/')) {
            return $origin.$location;
        }

        $basePath = isset($parts['path']) ? (string) preg_replace('#/[^/]*$#', '/', $parts['path']) : '/';

        return $origin.$basePath.$location;
    }

    /**
     * @param array{host: string, port: int, ips: list<string>} $target
     *
     * @return array<string, mixed>
     */
    private function pinnedResolution(array $target): array
    {
        if (!defined('CURLOPT_RESOLVE') || false !== filter_var($target['host'], FILTER_VALIDATE_IP)) {
            return [];
        }

        $addresses = array_map(
            static fn (string $ip): string => str_contains($ip, ':') ? '['.$ip.']' : $ip,
            $target['ips'],
        );

        return ['curl' => [CURLOPT_RESOLVE => [sprintf('%s:%d:%s', $target['host'], $target['port'], implode(',', $addresses))]]];
    }

    private function client(): ClientInterface
    {
        if (null === $this->client) {
            // Guzzle hands a 'stream' request to its stream handler, which refuses the pinning cURL option
            $stack = HandlerStack::create($this->transport ?? new CurlHandler());
            $configured = $GLOBALS['TYPO3_CONF_VARS']['HTTP']['handler'] ?? [];
            if (is_array($configured)) {
                foreach ($configured as $name => $handler) {
                    if (is_callable($handler)) {
                        $stack->push($handler, (string) $name);
                    }
                }
            }
            $this->client = new Client(['handler' => $stack] + $this->httpDefaults());
        }

        return $this->client;
    }

    /**
     * @return array<string, mixed>
     */
    private function httpDefaults(): array
    {
        $defaults = $GLOBALS['TYPO3_CONF_VARS']['HTTP'] ?? [];
        if (!is_array($defaults)) {
            return [];
        }
        if (isset($defaults['verify'])) {
            $defaults['verify'] = filter_var($defaults['verify'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $defaults['verify'];
        }
        unset($defaults['handler'], $defaults['allowed_hosts']);

        return $defaults;
    }

    /**
     * @param list<string> $entries
     *
     * @return array{0: list<string>, 1: list<string>}
     */
    private function splitDenyList(array $entries): array
    {
        $hosts = [];
        $cidrs = [];
        foreach ($entries as $entry) {
            $entry = trim($entry);
            if ('' === $entry) {
                continue;
            }
            if (str_contains($entry, '/') || false !== filter_var($entry, FILTER_VALIDATE_IP)) {
                $cidrs[] = $entry;
            } else {
                $hosts[] = strtolower($entry);
            }
        }

        return [$hosts, $cidrs];
    }

    private function assertIpIsPublic(string $ip): void
    {
        if (false === filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new \InvalidArgumentException(sprintf('Invalid target IP address "%s".', $ip));
        }

        foreach (self::BLOCKED_CIDRS as $cidr) {
            if ($this->ipMatchesCidr($ip, $cidr)) {
                throw new UnsafeUrlException(sprintf('Target address %s is not allowed (private, loopback, link-local or reserved range).', $ip));
            }
        }

        if (false === filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw new UnsafeUrlException(sprintf('Target address %s is not allowed (private or reserved range).', $ip));
        }
    }

    private function ipMatchesCidr(string $ip, string $cidr): bool
    {
        if (!str_contains($cidr, '/')) {
            $left = @inet_pton($ip);
            $right = @inet_pton($cidr);

            return false !== $left && false !== $right && $left === $right;
        }

        [$subnet, $maskLen] = explode('/', $cidr, 2);
        $ipBin = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);
        if (false === $ipBin || false === $subnetBin || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        $maskLen = (int) $maskLen;
        $fullBytes = intdiv($maskLen, 8);
        $remainderBits = $maskLen % 8;

        if ($fullBytes > 0 && substr($ipBin, 0, $fullBytes) !== substr($subnetBin, 0, $fullBytes)) {
            return false;
        }

        if ($remainderBits > 0) {
            $mask = 0xFF << (8 - $remainderBits) & 0xFF;

            return (ord($ipBin[$fullBytes]) & $mask) === (ord($subnetBin[$fullBytes]) & $mask);
        }

        return true;
    }
}
