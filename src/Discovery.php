<?php

declare(strict_types=1);

namespace Adao\Wiz;

use Adao\Wiz\Exception\WizException;

/**
 * Finds WiZ bulbs on the local network via UDP broadcast.
 * Requires the PHP "sockets" extension.
 */
class Discovery
{
    /**
     * @return array<string, array<string, mixed>> Map of IP => getPilot result
     */
    public static function find(string $broadcast = '255.255.255.255', float $wait = 2.0): array
    {
        if (!function_exists('socket_create')) {
            throw new WizException('Discovery needs the PHP "sockets" extension (ext-sockets).');
        }

        $sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if ($sock === false) {
            throw new WizException('Could not create UDP socket');
        }
        socket_set_option($sock, SOL_SOCKET, SO_BROADCAST, 1);
        socket_set_option($sock, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 0, 'usec' => 200000]);

        $msg = json_encode(['method' => 'getPilot', 'params' => new \stdClass()], JSON_THROW_ON_ERROR);
        socket_sendto($sock, $msg, strlen($msg), 0, $broadcast, WizBulb::PORT);

        $found = [];
        $deadline = microtime(true) + $wait;
        while (microtime(true) < $deadline) {
            $buf = '';
            $ip = '';
            $port = 0;
            $len = @socket_recvfrom($sock, $buf, 4096, 0, $ip, $port);
            if ($len === false || $len === 0) {
                continue;
            }
            $data = json_decode($buf, true);
            if (is_array($data) && isset($data['result'])) {
                $found[$ip] = $data['result'];
            }
        }
        socket_close($sock);

        return $found;
    }
}
