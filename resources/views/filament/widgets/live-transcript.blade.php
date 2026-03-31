<x-filament-widgets::widget wire:poll.2s>
    <x-filament::section>
        <x-slot name="heading">
            @if($this->isLive())
                <div class="flex items-center gap-3">
                    <span class="relative flex h-3 w-3">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-red-500"></span>
                    </span>
                    <span>Live Call</span>

                    @if($this->record->twilio_call_sid)
                        <button
                            id="listen-btn"
                            onclick="window._toggleListen()"
                            type="button"
                            class="ml-4 inline-flex items-center gap-2 px-4 py-1.5 rounded-full text-sm font-semibold transition-all
                                   bg-red-500 text-white hover:bg-red-600 shadow-lg shadow-red-500/25">
                            <svg id="listen-icon-play" class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/></svg>
                            <span id="listen-label">Listen Live</span>
                        </button>
                        <span id="listen-status" class="text-xs text-gray-400"></span>
                        <div id="audio-bars" class="hidden flex items-end gap-0.5 h-4 ml-2">
                            <div class="w-1 bg-green-500 rounded-full animate-pulse" style="height:40%;animation-delay:0s"></div>
                            <div class="w-1 bg-green-500 rounded-full animate-pulse" style="height:70%;animation-delay:0.15s"></div>
                            <div class="w-1 bg-green-500 rounded-full animate-pulse" style="height:50%;animation-delay:0.3s"></div>
                            <div class="w-1 bg-green-500 rounded-full animate-pulse" style="height:90%;animation-delay:0.1s"></div>
                            <div class="w-1 bg-green-500 rounded-full animate-pulse" style="height:30%;animation-delay:0.25s"></div>
                        </div>
                    @endif
                </div>
            @else
                Transcript
            @endif
        </x-slot>

        @if($this->isLive() && $this->record->twilio_call_sid)
            <script>
            (function() {
                if (window._listenInitialized) return;
                window._listenInitialized = true;

                let ws = null;
                let audioCtx = null;
                let listening = false;
                const callSid = @js($this->record->twilio_call_sid);
                const wsUrl = 'wss://ws.echjokes.overcloud.us:8443/listen/' + callSid;

                const MULAW_TABLE = new Float32Array(256);
                for (let i = 0; i < 256; i++) {
                    let mu = ~i & 0xFF;
                    let sign = (mu & 0x80) ? -1 : 1;
                    mu = mu & 0x7F;
                    let exponent = (mu >> 4) & 0x07;
                    let mantissa = mu & 0x0F;
                    let sample = (mantissa << (exponent + 3)) + (1 << (exponent + 3)) - 132;
                    if (sample > 32767) sample = 32767;
                    MULAW_TABLE[i] = sign * sample / 32768.0;
                }

                window._toggleListen = function() {
                    listening ? stopListening() : startListening();
                };

                function startListening() {
                    audioCtx = new (window.AudioContext || window.webkitAudioContext)({ sampleRate: 8000 });
                    ws = new WebSocket(wsUrl);

                    const label = document.getElementById('listen-label');
                    const status = document.getElementById('listen-status');
                    const btn = document.getElementById('listen-btn');
                    const bars = document.getElementById('audio-bars');

                    if (label) label.textContent = 'Connecting...';

                    ws.onopen = () => {
                        listening = true;
                        if (label) label.textContent = 'Stop';
                        if (status) status.textContent = '';
                        if (btn) { btn.classList.remove('bg-red-500', 'hover:bg-red-600', 'shadow-red-500/25'); btn.classList.add('bg-gray-600', 'hover:bg-gray-700', 'shadow-gray-600/25'); }
                        if (bars) bars.classList.remove('hidden');
                    };

                    let scheduledTime = 0;
                    ws.onmessage = (event) => {
                        try {
                            const msg = JSON.parse(event.data);
                            if (msg.type === 'audio' && msg.audio && audioCtx) {
                                const binary = atob(msg.audio);
                                const bytes = new Uint8Array(binary.length);
                                for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
                                const pcm = new Float32Array(bytes.length);
                                for (let i = 0; i < bytes.length; i++) pcm[i] = MULAW_TABLE[bytes[i]];

                                const buffer = audioCtx.createBuffer(1, pcm.length, 8000);
                                buffer.getChannelData(0).set(pcm);
                                const source = audioCtx.createBufferSource();
                                source.buffer = buffer;
                                source.connect(audioCtx.destination);

                                const now = audioCtx.currentTime;
                                if (scheduledTime < now) scheduledTime = now;
                                source.start(scheduledTime);
                                scheduledTime += buffer.duration;
                            } else if (msg.type === 'event' && msg.event === 'call_ended') {
                                if (status) status.textContent = 'Call ended';
                                stopListening();
                            }
                        } catch(e) { console.error('Listen error:', e); }
                    };

                    ws.onclose = () => { if (listening) stopListening(); };
                    ws.onerror = () => { if (status) status.textContent = 'Error'; stopListening(); };
                }

                function stopListening() {
                    listening = false;
                    if (ws) { try { ws.close(); } catch(e) {} ws = null; }
                    if (audioCtx) { try { audioCtx.close(); } catch(e) {} audioCtx = null; }
                    const label = document.getElementById('listen-label');
                    const btn = document.getElementById('listen-btn');
                    const bars = document.getElementById('audio-bars');
                    if (label) label.textContent = 'Listen Live';
                    if (btn) { btn.classList.remove('bg-gray-600', 'hover:bg-gray-700', 'shadow-gray-600/25'); btn.classList.add('bg-red-500', 'hover:bg-red-600', 'shadow-red-500/25'); }
                    if (bars) bars.classList.add('hidden');
                }
            })();
            </script>
        @endif

        {{-- Transcript --}}
        @php $transcript = $this->getTranscript(); @endphp

        @if(count($transcript) > 0)
            <div class="space-y-3 max-h-[500px] overflow-y-auto scroll-smooth" id="transcript-box">
                @foreach($transcript as $line)
                    <div class="flex gap-3 {{ $line['role'] === 'ai' ? '' : 'flex-row-reverse' }}">
                        <div class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold
                            {{ $line['role'] === 'ai' ? 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300' : 'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300' }}">
                            {{ $line['role'] === 'ai' ? 'AI' : '??' }}
                        </div>
                        <div class="max-w-[80%] rounded-2xl px-4 py-2.5 text-sm leading-relaxed
                            {{ $line['role'] === 'ai'
                                ? 'bg-green-50 dark:bg-green-900/20 text-green-900 dark:text-green-100 rounded-tl-sm'
                                : 'bg-blue-50 dark:bg-blue-900/20 text-blue-900 dark:text-blue-100 rounded-tr-sm' }}">
                            <p>{{ $line['text'] }}</p>
                            <span class="text-[10px] opacity-40 mt-1 block">{{ $line['at'] ?? '' }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
            <script>
                requestAnimationFrame(() => {
                    const box = document.getElementById('transcript-box');
                    if (box) box.scrollTop = box.scrollHeight;
                });
            </script>
        @else
            <div class="text-center py-8">
                @if($this->isLive())
                    <div class="flex items-center justify-center gap-2 text-gray-400">
                        <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span class="text-sm">Waiting for conversation...</span>
                    </div>
                @else
                    <p class="text-sm text-gray-400 italic">No transcript available</p>
                @endif
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
