# php-wiz

Control WiZ smart bulbs from PHP over your local network (UDP port 38899). No cloud, no API keys.

## Requirements

- PHP 8.1+
- In the WiZ app: **Settings → Security → Allow local communication** must be ON
- `ext-sockets` only if you use `Discovery`

## Install

From a local folder (until you publish it):

```json
{
    "repositories": [{ "type": "path", "url": "../php-wiz" }],
    "require": { "adao/php-wiz": "*" }
}
```

Then `composer update`. Once pushed to GitHub/Packagist: `composer require adao/php-wiz`.

## Usage

```php
use Adao\Wiz\WizBulb;

$bulb = new WizBulb('192.168.68.114');

$bulb->on(60, 3000);        // 60% brightness, 3000 K
$bulb->setBrightness(30);
$bulb->setTemperature(2700);
$bulb->toggle();
$bulb->off();

print_r($bulb->status());       // state, dimming, temp, rssi...
print_r($bulb->systemConfig()); // model, firmware, MAC...
```

Errors throw `Adao\Wiz\Exception\WizException`.

Discover bulbs on the LAN:

```php
use Adao\Wiz\Discovery;
print_r(Discovery::find()); // ['192.168.68.114' => [...], ...]
```

## CLI

```
vendor/bin/wiz discover
vendor/bin/wiz 192.168.68.114 on 60 3000
vendor/bin/wiz 192.168.68.114 off
WIZ_IP=192.168.68.114 vendor/bin/wiz toggle
```

## Notes

- Brightness is clamped to 10–100 %, temperature to 2200–6500 K; the bulb applies its own limits on top.
- Reserve the bulb's IP in your router so it doesn't change.
- `examples/web.php` has no authentication — don't expose it to the internet.
