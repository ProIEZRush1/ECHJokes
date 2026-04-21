<?php

namespace App\Console\Commands;

use App\WebSockets\MediaStreamServer;
use Illuminate\Console\Command;
use React\Socket\ConnectionInterface;
use React\Socket\SocketServer;

class ServeMediaStreams extends Command
{
    protected $signature = 'vacilada:stream-server {--port=8081}';
    protected $description = 'Twilio Media Streams WebSocket server (raw TCP)';

    public function handle(): int
    {
        $port = (int) $this->option('port');
        $this->info("Starting Media Stream server on port {$port}...");

        $handler = new MediaStreamServer();

        $socket = new SocketServer("0.0.0.0:{$port}");

        $socket->on('connection', function (ConnectionInterface $conn) use ($handler) {
            $buffer = '';
            $upgraded = false;
            $connId = uniqid('ws_');
            $path = '';

            $conn->on('data', function ($data) use ($conn, &$buffer, &$upgraded, &$connId, &$path, $handler) {
                if (!$upgraded) {
                    $buffer .= $data;

                    // Wait for complete HTTP headers
                    if (strpos($buffer, "\r\n\r\n") === false) {
                        return;
                    }

                    // Parse HTTP request
                    $lines = explode("\r\n", $buffer);
                    $firstLine = $lines[0]; // GET /stream/xxx HTTP/1.1
                    preg_match('/GET (.+) HTTP/', $firstLine, $matches);
                    $path = $matches[1] ?? '/';

                    // Health check
                    if ($path === '/health') {
                        $conn->write("HTTP/1.1 200 OK\r\nContent-Type: text/plain\r\nContent-Length: 2\r\n\r\nOK");
                        $conn->end();
                        return;
                    }

                    // Extract WebSocket key
                    $key = '';
                    foreach ($lines as $line) {
                        if (stripos($line, 'Sec-WebSocket-Key:') === 0) {
                            $key = trim(substr($line, 18));
                        }
                    }

                    if (empty($key)) {
                        $conn->write("HTTP/1.1 400 Bad Request\r\n\r\n");
                        $conn->end();
                        return;
                    }

                    // WebSocket handshake
                    $accept = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-5AB5DC11E65B', true));
                    $conn->write(
                        "HTTP/1.1 101 Switching Protocols\r\n" .
                        "Upgrade: websocket\r\n" .
                        "Connection: Upgrade\r\n" .
                        "Sec-WebSocket-Accept: {$accept}\r\n" .
                        "\r\n"
                    );

                    $upgraded = true;
                    $buffer = '';

                    // Notify handler
                    $handler->onOpen($connId, $conn, $path);
                    return;
                }

                // WebSocket frame parsing
                $buffer .= $data;

                while (strlen($buffer) >= 2) {
                    $byte1 = ord($buffer[0]);
                    $byte2 = ord($buffer[1]);
                    $opcode = $byte1 & 0x0F;
                    $masked = ($byte2 & 0x80) !== 0;
                    $payloadLen = $byte2 & 0x7F;

                    $offset = 2;
                    if ($payloadLen === 126) {
                        if (strlen($buffer) < 4) return;
                        $payloadLen = unpack('n', substr($buffer, 2, 2))[1];
                        $offset = 4;
                    } elseif ($payloadLen === 127) {
                        if (strlen($buffer) < 10) return;
                        $payloadLen = unpack('J', substr($buffer, 2, 8))[1];
                        $offset = 10;
                    }

                    $maskLen = $masked ? 4 : 0;
                    $totalLen = $offset + $maskLen + $payloadLen;

                    if (strlen($buffer) < $totalLen) return;

                    $maskKey = $masked ? substr($buffer, $offset, 4) : '';
                    $payload = substr($buffer, $offset + $maskLen, $payloadLen);

                    if ($masked) {
                        for ($i = 0; $i < $payloadLen; $i++) {
                            $payload[$i] = $payload[$i] ^ $maskKey[$i % 4];
                        }
                    }

                    $buffer = substr($buffer, $totalLen);

                    if ($opcode === 0x1) { // Text frame
                        $handler->onTextMessage($connId, $payload);
                    } elseif ($opcode === 0x8) { // Close
                        $handler->onConnectionClose($connId);
                        $conn->end();
                        return;
                    } elseif ($opcode === 0x9) { // Ping
                        // Send pong
                        $pong = chr(0x8A) . chr(strlen($payload)) . $payload;
                        $conn->write($pong);
                    }
                }
            });

            $conn->on('close', function () use (&$connId, &$upgraded, $handler) {
                if ($upgraded) {
                    $handler->onConnectionClose($connId);
                }
            });
        });

        $this->info("WebSocket server running on ws://0.0.0.0:{$port}");

        return self::SUCCESS;
    }
}
