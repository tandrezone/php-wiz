<?php

declare(strict_types=1);

namespace Adao\Wiz;

use Adao\Wiz\Exception\WizException;
use stdClass;

/**
 * Controls a single WiZ bulb over its local UDP API (port 38899).
 * Requires "Allow local communication" to be enabled in the WiZ app.
 */
class WizBulb
{
    public const PORT = 38899;
    public const MIN_BRIGHTNESS = 10;
    public const MAX_BRIGHTNESS = 100;
    public const MIN_TEMP = 2200;
    public const MAX_TEMP = 6500;

    public function __construct(
        private readonly string $ip,
        private readonly int $port = self::PORT,
        private readonly float $timeout = 2.0,
        private readonly int $retries = 3,
    ) {
    }

    public function getIp(): string
    {
        return $this->ip;
    }

    /**
     * Sends a raw request and returns the decoded response.
     *
     * @param array<string, mixed>|null $params
     * @return array<string, mixed>
     */
    public function request(string $method, ?array $params = null): array
    {
        $sock = @stream_socket_client(
            "udp://{$this->ip}:{$this->port}",
            $errno,
            $errstr,
            $this->timeout
        );
        if ($sock === false) {
            throw new WizException("Socket error: {$errstr} ({$errno})");
        }

        $sec = (int) floor($this->timeout);
        stream_set_timeout($sock, $sec, (int) (($this->timeout - $sec) * 1_000_000));

        $payload = json_encode(
            ['method' => $method, 'params' => $params ?: new stdClass()],
            JSON_THROW_ON_ERROR
        );
        $response = '';

        // UDP can drop packets, so retry
        for ($i = 0; $i < max(1, $this->retries); $i++) {
            fwrite($sock, $payload);
            $response = fread($sock, 4096);
            if ($response !== false && $response !== '') {
                break;
            }
        }
        fclose($sock);

        if ($response === false || $response === '') {
            throw new WizException("No response from bulb at {$this->ip}");
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new WizException("Invalid response from bulb: {$response}");
        }
        if (isset($data['error'])) {
            throw new WizException('Bulb error: ' . json_encode($data['error']));
        }

        return $data;
    }

    /** @return array<string, mixed> Current state (state, dimming, temp, sceneId, rssi...) */
    public function status(): array
    {
        return $this->request('getPilot')['result'] ?? [];
    }

    /** @return array<string, mixed> Model, firmware, MAC, home ID... */
    public function systemConfig(): array
    {
        return $this->request('getSystemConfig')['result'] ?? [];
    }

    public function isOn(): bool
    {
        return !empty($this->status()['state']);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function setPilot(array $params): array
    {
        return $this->request('setPilot', $params);
    }

    public function on(?int $brightness = null, ?int $temperature = null): array
    {
        $params = ['state' => true];
        if ($brightness !== null) {
            $params['dimming'] = self::clampBrightness($brightness);
        }
        if ($temperature !== null) {
            $params['temp'] = self::clampTemperature($temperature);
        }
        return $this->setPilot($params);
    }

    public function off(): array
    {
        return $this->setPilot(['state' => false]);
    }

    public function toggle(): array
    {
        return $this->isOn() ? $this->off() : $this->on();
    }

    public function setBrightness(int $percent): array
    {
        return $this->setPilot(['state' => true, 'dimming' => self::clampBrightness($percent)]);
    }

    public function setTemperature(int $kelvin): array
    {
        return $this->setPilot(['state' => true, 'temp' => self::clampTemperature($kelvin)]);
    }

    /** Activates a built-in WiZ scene by ID (support depends on the bulb). */
    public function setScene(int $sceneId, ?int $brightness = null): array
    {
        $params = ['state' => true, 'sceneId' => $sceneId];
        if ($brightness !== null) {
            $params['dimming'] = self::clampBrightness($brightness);
        }
        return $this->setPilot($params);
    }

    public static function clampBrightness(int $value): int
    {
        return max(self::MIN_BRIGHTNESS, min(self::MAX_BRIGHTNESS, $value));
    }

    public static function clampTemperature(int $value): int
    {
        return max(self::MIN_TEMP, min(self::MAX_TEMP, $value));
    }
}
