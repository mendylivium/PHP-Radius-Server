<?php

/**
 * SocketServer - UDP + HTTP Socket Server with per-packet forking
 *
 * Features:
 *   - UDP listeners for Auth + Acct (non-blocking select loop)
 *   - TCP HTTP listener for CoA (with API key validation)
 *   - pcntl_fork() per packet for concurrency (like goroutines / tokio::spawn)
 *   - Child process reaping to prevent zombies
 *   - Configurable max workers to bound concurrency
 */
class SocketServer
{
    private array $udpSockets = [];
    private $coaSocket = null;
    private $onPacket;
    private $onCoaRequest;

    private int $maxAuthWorkers;
    private int $maxAcctWorkers;
    private int $activeChildren = 0;
    private bool $hasPcntl;

    public function __construct(array $config = [])
    {
        $this->maxAuthWorkers = $config['workers']['auth_workers'] ?? 50;
        $this->maxAcctWorkers = $config['workers']['acct_workers'] ?? 20;
        $this->hasPcntl = function_exists('pcntl_fork');

        $this->log('Server Initialized');

        if (PHP_MAJOR_VERSION < 8) {
            die("Minimum PHP version 8 required.\n");
        }

        if (!function_exists("socket_create")) {
            die("Sockets extension not available.\n");
        }

        if (!$this->hasPcntl) {
            $this->log("[WARN] pcntl not available - falling back to synchronous mode");
        }

        // Install SIGCHLD handler to reap child processes
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGCHLD, function () {
                while (pcntl_waitpid(-1, $status, WNOHANG) > 0) {
                    $this->activeChildren = max(0, $this->activeChildren - 1);
                }
            });
        }
    }

    public function log(string $str): void
    {
        $ts = date('Y-m-d\TH:i:s');
        echo "$ts $str\n";
    }

    public function addListener(string $hostIp, int $hostPort): void
    {
        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

        if (!$socket) {
            $this->log("Failed to create socket: " . socket_strerror(socket_last_error()));
            return;
        }

        if (!socket_bind($socket, $hostIp, $hostPort)) {
            $this->log("Failed to bind socket: " . socket_strerror(socket_last_error($socket)));
            return;
        }

        socket_set_nonblock($socket);
        $this->udpSockets[] = $socket;

        $this->log("UDP Listening on $hostIp:$hostPort");
    }

    /**
     * Add CoA HTTP listener (TCP)
     */
    public function addCoaListener(string $hostIp, int $hostPort): void
    {
        $this->coaSocket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        if (!$this->coaSocket) {
            $this->log("CoA: Failed to create TCP socket: " . socket_strerror(socket_last_error()));
            return;
        }

        socket_set_option($this->coaSocket, SOL_SOCKET, SO_REUSEADDR, 1);

        if (!socket_bind($this->coaSocket, $hostIp, $hostPort)) {
            $this->log("CoA: Failed to bind TCP socket: " . socket_strerror(socket_last_error($this->coaSocket)));
            return;
        }

        if (!socket_listen($this->coaSocket, 64)) {
            $this->log("CoA: Failed to listen: " . socket_strerror(socket_last_error($this->coaSocket)));
            return;
        }

        socket_set_nonblock($this->coaSocket);

        $this->log("CoA HTTP Listening on $hostIp:$hostPort");
    }

    public function on(string $eventName, callable $callback): void
    {
        match ($eventName) {
            'Packet'     => $this->onPacket = $callback,
            'CoaRequest' => $this->onCoaRequest = $callback,
            default      => $this->log("Unknown event: $eventName"),
        };
    }

    /**
     * Main event loop with select() on UDP + TCP sockets
     */
    public function run(): void
    {
        $maxWorkers = $this->maxAuthWorkers + $this->maxAcctWorkers;

        while (true) {
            // Dispatch pending signals (reap children)
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            // Build select arrays
            $read = $this->udpSockets;
            if ($this->coaSocket) {
                $read[] = $this->coaSocket;
            }
            $write = null;
            $except = null;

            $changed = @socket_select($read, $write, $except, 0, 200000); // 200ms timeout

            if ($changed === false) {
                $this->log('socket_select error: ' . socket_strerror(socket_last_error()));
                continue;
            }

            if ($changed === 0) continue;

            foreach ($read as $socket) {
                // CoA TCP socket - accept new connection
                if ($this->coaSocket && $socket === $this->coaSocket) {
                    $clientSocket = @socket_accept($this->coaSocket);
                    if ($clientSocket) {
                        $this->handleCoaConnection($clientSocket);
                    }
                    continue;
                }

                // UDP packet
                $packet = '';
                $remoteIp = '';
                $remotePort = 0;
                $result = @socket_recvfrom($socket, $packet, 65535, 0, $remoteIp, $remotePort);

                if ($result === false || strlen($packet) < 4) {
                    continue;
                }

                if (!is_callable($this->onPacket)) continue;

                $clientInfo = [
                    'address'       => $remoteIp,
                    'port'          => $remotePort,
                    'server_socket' => $socket,
                ];

                // Fork per packet (like goroutine / tokio::spawn)
                if ($this->hasPcntl && $this->activeChildren < $maxWorkers) {
                    $pid = pcntl_fork();

                    if ($pid === -1) {
                        // Fork failed - handle synchronously
                        $this->log("[WARN] Fork failed, handling synchronously");
                        call_user_func($this->onPacket, $this, $packet, $clientInfo);
                    } elseif ($pid === 0) {
                        // CHILD process
                        call_user_func($this->onPacket, $this, $packet, $clientInfo);
                        exit(0);
                    } else {
                        // PARENT - track child
                        $this->activeChildren++;
                    }
                } else {
                    // No pcntl or max workers reached - synchronous fallback
                    call_user_func($this->onPacket, $this, $packet, $clientInfo);
                }
            }
        }
    }

    /**
     * Handle incoming CoA HTTP connection
     */
    private function handleCoaConnection($clientSocket): void
    {
        socket_set_option($clientSocket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 5, 'usec' => 0]);

        $request = '';
        $tries = 0;

        while ($tries < 10) {
            $chunk = @socket_read($clientSocket, 32768 - strlen($request));
            if ($chunk === false || $chunk === '') break;
            $request .= $chunk;
            $tries++;

            // Check if we have complete headers + body
            $headerEnd = strpos($request, "\r\n\r\n");
            if ($headerEnd !== false) {
                if (preg_match('/Content-Length:\s*(\d+)/i', $request, $m)) {
                    $bodyStart = $headerEnd + 4;
                    $contentLen = (int)$m[1];
                    if (strlen($request) >= $bodyStart + $contentLen) break;
                } else {
                    break;
                }
            }
        }

        if (empty($request)) {
            socket_close($clientSocket);
            return;
        }

        // Parse HTTP request line
        $lines = explode("\r\n", $request);
        $requestLine = $lines[0] ?? '';

        if (!str_starts_with($requestLine, 'POST /coa')) {
            $this->sendHttpResponse($clientSocket, 404, 'Not Found',
                json_encode(['status' => 'error', 'message' => 'not found']));
            socket_close($clientSocket);
            return;
        }

        // Extract body
        $body = '';
        $bodyPos = strpos($request, "\r\n\r\n");
        if ($bodyPos !== false) {
            $body = substr($request, $bodyPos + 4);
        }

        // Extract headers for API key validation
        $headers = [];
        for ($i = 1; $i < count($lines); $i++) {
            if ($lines[$i] === '') break;
            $parts = explode(':', $lines[$i], 2);
            if (count($parts) === 2) {
                $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
        }

        if (is_callable($this->onCoaRequest)) {
            call_user_func($this->onCoaRequest, $this, $clientSocket, $body, $headers);
        } else {
            $this->sendHttpResponse($clientSocket, 501, 'Not Implemented',
                json_encode(['status' => 'error', 'message' => 'CoA not configured']));
            socket_close($clientSocket);
        }
    }

    /**
     * Send HTTP response on a TCP socket
     */
    public function sendHttpResponse($clientSocket, int $statusCode, string $statusText, string $jsonBody): void
    {
        $bodyLen = strlen($jsonBody);
        $header = "HTTP/1.1 $statusCode $statusText\r\n"
            . "Content-Type: application/json\r\n"
            . "Content-Length: $bodyLen\r\n"
            . "Connection: close\r\n"
            . "\r\n";

        @socket_write($clientSocket, $header . $jsonBody);
    }

    public function sendto($destinationIp, $destinationPort, $packets, $socket): bool
    {
        $result = @socket_sendto($socket, $packets, strlen($packets), 0, $destinationIp, $destinationPort);
        if ($result === false) {
            $this->log("socket_sendto error: " . socket_strerror(socket_last_error($socket)));
            return false;
        }
        return true;
    }

    public function close(): void
    {
        foreach ($this->udpSockets as $socket) {
            socket_close($socket);
        }
        if ($this->coaSocket) {
            socket_close($this->coaSocket);
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}