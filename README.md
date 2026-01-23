# PHP Radius Server

A simple **RADIUS server written in PHP** that uses sockets to handle authentication and accounting requests.

This server is designed as a lightweight, pure-PHP implementation of the RADIUS protocol, making it easy to experiment with and extend for custom use cases.

## Installation

**Clone the repo**

```bash
git clone https://github.com/mendylivium/PHP-Radius-Server.git
cd PHP-Radius-Server
```

---

## Usage

```bash
php server.php
```

---

## Configuration / Customization
At a minimum you’ll want to modify:
- Listening host/port
- Shared secret(s)
- Authentication logic

**Inside RadiusCore.php**
```php
var $radiusIp       =   '0.0.0.0'; //Ip to Listen (0.0.0.0 means all IP)
var $radiusAuthPort =   1812; // Authentication Port
var $radiusAcctPort =   1813; // Accounting Port
var $radiusSecret   =   'mendify@2023'; // Radius Secret
```

**Inside server.php**
```php
$radius->on("accounting-start", function($attrbutes) {

    return [5,[
        'Reply-Message'         =>  'Accounting Start Recieved'
    ]];
});

$radius->on("accounting-interim", function($attrbutes) {

    echo "Interim Update\r\n";

    return [3,[
        'Reply-Message'         =>  'Accounting Interim Recieved in Server'
    ]];
});

$radius->on("accounting-stop", function($attrbutes) {

    return [5,[
        'Reply-Message'         =>  'Accounting Stop Recieved'
    ]];
});
```

