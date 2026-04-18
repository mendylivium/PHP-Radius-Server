# PHP Radius Server

A simple **RADIUS server written in PHP** that uses raw UDP sockets to handle authentication and accounting requests — no external extensions or frameworks required (just PHP 8+ with sockets enabled).

## Features

- Pure PHP implementation of the RADIUS protocol (RFC 2865 / RFC 2866)
- Handles **Access-Request**, **Accounting-Start**, **Accounting-Interim**, and **Accounting-Stop** events
- Supports PAP and CHAP authentication
- Loadable RADIUS dictionaries (standard + vendor-specific like Mikrotik, WISPr, etc.)
- Event-driven callback system — define your own logic per request type
- Named constants for all RADIUS packet codes (`RadiusPacketCode::ACCESS_ACCEPT`, etc.)

## Requirements

- PHP 8.0 or higher
- PHP Sockets extension (`php-sockets`)

## Installation

```bash
git clone https://github.com/mendylivium/PHP-Radius-Server.git
cd PHP-Radius-Server
```

## Quick Start

```bash
php server.php
```

The server will listen on:
- **Port 1812** — Authentication (Access-Request)
- **Port 1813** — Accounting (Start / Interim / Stop)

## Configuration

### Server Settings (`RadiusCore.php`)

```php
var $radiusIp       = '0.0.0.0';       // IP to bind (0.0.0.0 = all interfaces)
var $radiusAuthPort = 1812;             // Authentication port
var $radiusAcctPort = 1813;             // Accounting port
var $radiusSecret   = 'mendify@2023';   // Shared secret between NAS and server
```

You can also pass these as constructor arguments:

```php
$radius = new RadiusCore('0.0.0.0', 1812, 1813, 'your-secret');
```

### Loading Dictionaries

Dictionaries map vendor-specific attribute IDs to human-readable names. Load them before registering event handlers:

```php
$radius->load_dictionary('dictionary.mikrotik');
$radius->load_dictionary('dictionary.wispr');
```

Dictionary files are located in the `dictionary/` folder.

## Response Code Constants

Instead of using raw numbers, the project provides `RadiusPacketCode` constants for readability:

| Constant | Code | Description |
|---|---|---|
| `RadiusPacketCode::ACCESS_REQUEST` | 1 | Sent by NAS to request authentication |
| `RadiusPacketCode::ACCESS_ACCEPT` | 2 | Authentication successful |
| `RadiusPacketCode::ACCESS_REJECT` | 3 | Authentication failed / denied |
| `RadiusPacketCode::ACCOUNTING_REQUEST` | 4 | Sent by NAS for accounting |
| `RadiusPacketCode::ACCOUNTING_RESPONSE` | 5 | Server acknowledges accounting request |
| `RadiusPacketCode::ACCESS_CHALLENGE` | 11 | Additional info needed (e.g., MFA) |

## Event Handlers (`server.php`)

Register callbacks for each RADIUS event type. Each callback receives the decoded `$attributes` array from the NAS and must return an array of `[response_code, [attributes]]`.

### Access-Request

Triggered when a NAS sends an authentication request (e.g., PPPoE login, hotspot login).

```php
$radius->on("access-request", function($attributes) {
    // $attributes contains: User-Name, User-Password, NAS-IP-Address, etc.

    // Accept the user with rate limiting and a session timeout
    return [RadiusPacketCode::ACCESS_ACCEPT, [
        'Mikrotik-Rate-Limit' => '5m/5m',
        'Session-Timeout'     => 3600,
        'Reply-Message'       => 'Welcome!'
    ]];

    // Or reject:
    // return [RadiusPacketCode::ACCESS_REJECT, [
    //     'Reply-Message' => 'Invalid credentials'
    // ]];
});
```

### Accounting-Start

Triggered when a user session begins.

```php
$radius->on("accounting-start", function($attributes) {
    // Log session start, store session info, etc.
    return [RadiusPacketCode::ACCOUNTING_RESPONSE, [
        'Reply-Message' => 'Accounting Start Received'
    ]];
});
```

### Accounting-Interim

Triggered periodically during an active session (interim updates).

```php
$radius->on("accounting-interim", function($attributes) {
    // Update session stats (bytes in/out, session time, etc.)
    return [RadiusPacketCode::ACCOUNTING_RESPONSE, [
        'Reply-Message' => 'Accounting Interim Received'
    ]];
});
```

### Accounting-Stop

Triggered when a user session ends.

```php
$radius->on("accounting-stop", function($attributes) {
    // Finalize session, log totals, etc.
    return [RadiusPacketCode::ACCOUNTING_RESPONSE, [
        'Reply-Message' => 'Accounting Stop Received'
    ]];
});
```

## Available Attributes

The `$attributes` array passed to your callbacks contains all decoded RADIUS attributes from the incoming request. Common attributes include:

| Attribute | Description |
|---|---|
| `User-Name` | Username of the connecting client |
| `User-Password` | Decoded PAP password |
| `NAS-IP-Address` | IP address of the NAS device |
| `NAS-Port` | Physical port on the NAS |
| `Calling-Station-Id` | MAC address of the client |
| `Called-Station-Id` | MAC address of the NAS |
| `Framed-IP-Address` | IP assigned to the client |
| `Acct-Status-Type` | Accounting event type (Start, Stop, Interim-Update) |
| `Acct-Session-Id` | Unique session identifier |
| `Acct-Session-Time` | Duration of session in seconds |
| `Acct-Input-Octets` | Bytes received by the client |
| `Acct-Output-Octets` | Bytes sent by the client |

Vendor-specific attributes (e.g., `Mikrotik-Rate-Limit`) are available when the corresponding dictionary is loaded.

## Project Structure

```
PHP-Radius-Server/
 server.php              # Entry point — define your event handlers here
 RadiusCore.php          # Core RADIUS protocol logic (encode/decode/events)
 RadiusPacketCode.php    # Named constants for RADIUS packet codes
 SocketServer.php        # UDP socket server (non-blocking, select-based)
 SystemCore.php          # System info utilities (CPU, memory, disk)
 dictionary/             # RADIUS dictionary files
   ├── dictionary          # Main dictionary
   ├── dictionary.mikrotik # Mikrotik vendor attributes
   ├── dictionary.wispr    # WISPr vendor attributes
   └── ...                 # Other vendor dictionaries
 README.md
```

## License

This project is open source. See the repository for license details.

## Credits

- [CodFrm/php-radius](https://github.com/CodFrm/php-radius)
- [phpipam/phpipam](https://github.com/phpipam/phpipam)
- [dr4g0nsr/radius-server](https://github.com/dr4g0nsr/radius-server)
