<?php

namespace App\Console\Commands;

use App\WebSockets\TwilioMediaStreamHandler;
use Illuminate\Console\Command;
use Ratchet\RFC6455\Handshake\ServerNegotiator;
use React\EventLoop\Loop;
use React\Http\HttpServer;
use React\Http\Message\Response;
use React\Socket\SocketServer;

class ServeMediaStreams extends Command
{
    protected $signature = 'echjokes:stream-server {--port=8081 : Port to listen on}';
    protected $description = 'Start the Twilio Media Streams WebSocket server';

    public function handle(): int
    {
        $port = (int) $this->option('port');

        $this->info("Starting Media Stream WebSocket server on port {$port}...");

        $handler = new TwilioMediaStreamHandler();

        $http = new HttpServer(function (\Psr\Http\Message\ServerRequestInterface $request) use ($handler) {
            $path = $request->getUri()->getPath();

            // Health check endpoint
            if ($path === '/health') {
                return new Response(200, ['Content-Type' => 'text/plain'], 'OK');
            }

            // WebSocket upgrade for /stream/{session_id}
            if (str_starts_with($path, '/stream/')) {
                return $handler->handleUpgrade($request);
            }

            return new Response(404, ['Content-Type' => 'text/plain'], 'Not Found');
        });

        $socket = new SocketServer("0.0.0.0:{$port}");
        $http->listen($socket);

        $this->info("Media Stream server running on ws://0.0.0.0:{$port}");

        Loop::run();

        return self::SUCCESS;
    }
}
