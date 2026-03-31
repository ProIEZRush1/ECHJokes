<?php

namespace App\Console\Commands;

use App\WebSockets\MediaStreamServer;
use Illuminate\Console\Command;
use Ratchet\RFC6455\Handshake\RequestVerifier;
use Ratchet\RFC6455\Handshake\ServerNegotiator;
use Ratchet\RFC6455\Messaging\CloseFrameChecker;
use Ratchet\RFC6455\Messaging\Frame;
use Ratchet\RFC6455\Messaging\MessageBuffer;
use React\Http\HttpServer;
use React\Http\Message\Response;
use React\Socket\SocketServer;
use React\Stream\ThroughStream;

class ServeMediaStreams extends Command
{
    protected $signature = 'echjokes:stream-server {--port=8081}';
    protected $description = 'Twilio Media Streams WebSocket server';

    public function handle(): int
    {
        $port = (int) $this->option('port');
        $this->info("Starting Media Stream server on port {$port}...");

        $handler = new MediaStreamServer();
        $negotiator = new ServerNegotiator(new RequestVerifier(), new \GuzzleHttp\Psr7\HttpFactory());

        $http = new HttpServer(function (\Psr\Http\Message\ServerRequestInterface $request) use ($handler, $negotiator) {
            $path = $request->getUri()->getPath();

            if ($path === '/health') {
                return new Response(200, ['Content-Type' => 'text/plain'], 'OK');
            }

            if (!str_starts_with($path, '/stream/')) {
                return new Response(404, ['Content-Type' => 'text/plain'], 'Not Found');
            }

            // WebSocket handshake using ratchet/rfc6455
            $negotiatorResponse = $negotiator->handshake($request);

            if ($negotiatorResponse->getStatusCode() !== 101) {
                return new Response(
                    $negotiatorResponse->getStatusCode(),
                    $negotiatorResponse->getHeaders(),
                    (string) $negotiatorResponse->getBody()
                );
            }

            $connId = uniqid('ws_');
            $stream = new ThroughStream();

            // Set up message parsing
            $messageBuffer = new MessageBuffer(
                new CloseFrameChecker(),
                function ($message) use ($handler, $connId) {
                    // Text message received
                    $handler->onTextMessage($connId, (string) $message);
                },
                null, // control frame handler
                true, // check for masking
                null  // sender callback
            );

            // Initialize connection in handler
            $handler->onOpen($connId, $stream, $path);

            $stream->on('data', function ($data) use ($messageBuffer) {
                $messageBuffer->onData($data);
            });

            $stream->on('close', function () use ($handler, $connId) {
                $handler->onConnectionClose($connId);
            });

            return new Response(
                101,
                $negotiatorResponse->getHeaders(),
                $stream
            );
        });

        $socket = new SocketServer("0.0.0.0:{$port}");
        $http->listen($socket);

        $this->info("WebSocket server running on ws://0.0.0.0:{$port}");

        return self::SUCCESS;
    }
}
