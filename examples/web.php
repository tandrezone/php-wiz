<?php
/**
 * Minimal HTTP endpoint. Keep it on your LAN only — there is no authentication.
 *
 *   web.php?action=status
 *   web.php?action=on&dim=60&temp=3000
 *   web.php?action=off | toggle
 *   web.php?action=dim&value=40
 *   web.php?action=temp&value=2700
 */

require __DIR__ . '/../vendor/autoload.php';

use Adao\Wiz\WizBulb;

header('Content-Type: application/json');

$bulb = new WizBulb('192.168.68.114');
$int = static fn (string $k): ?int => isset($_GET[$k]) ? (int) $_GET[$k] : null;

try {
    $result = match ($_GET['action'] ?? 'status') {
        'status' => $bulb->status(),
        'on'     => $bulb->on($int('dim'), $int('temp')),
        'off'    => $bulb->off(),
        'toggle' => $bulb->toggle(),
        'dim'    => $bulb->setBrightness($int('value') ?? 100),
        'temp'   => $bulb->setTemperature($int('value') ?? 2700),
        default  => throw new InvalidArgumentException('Unknown action'),
    };
    echo json_encode(['ok' => true, 'result' => $result]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
