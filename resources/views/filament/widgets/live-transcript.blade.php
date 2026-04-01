<x-filament-widgets::widget wire:poll.2s>
    <x-filament::section>
        <x-slot name="heading">
            @if($this->isLive())
                <div style="display:flex;align-items:center;gap:10px;">
                    <span style="position:relative;display:inline-flex;width:10px;height:10px;">
                        <span style="position:absolute;display:inline-flex;width:100%;height:100%;border-radius:50%;background:#f87171;opacity:0.75;animation:ping 1s cubic-bezier(0,0,0.2,1) infinite;"></span>
                        <span style="position:relative;display:inline-flex;width:10px;height:10px;border-radius:50%;background:#ef4444;"></span>
                    </span>
                    <span>Live Call</span>
                </div>
                <style>@keyframes ping{75%,100%{transform:scale(2);opacity:0;}}</style>
            @else
                Transcript
            @endif
        </x-slot>

        @if($this->isLive() && $this->record->twilio_call_sid)
            <div style="margin-bottom:16px;padding:12px;background:#fef2f2;border:1px solid #fecaca;border-radius:12px;">
                <button id="echj-btn" type="button"
                    style="font-size:14px;padding:8px 24px;border-radius:24px;background:#ef4444;color:#fff;font-weight:700;border:none;cursor:pointer;">
                    Listen Live
                </button>
                <span id="echj-status" style="font-size:12px;color:#666;margin-left:8px;"></span>
            </div>
        @endif

        @php $transcript = $this->getTranscript(); @endphp

        @if(count($transcript) > 0)
            <div style="display:flex;flex-direction:column;gap:12px;max-height:500px;overflow-y:auto;scroll-behavior:smooth;" id="echj-transcript">
                @foreach($transcript as $line)
                    <div style="display:flex;gap:10px;{{ $line['role'] !== 'ai' ? 'flex-direction:row-reverse;' : '' }}">
                        <div style="flex-shrink:0;width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;
                            {{ $line['role'] === 'ai' ? 'background:#dcfce7;color:#166534;' : 'background:#dbeafe;color:#1e40af;' }}">
                            {{ $line['role'] === 'ai' ? 'AI' : 'H' }}
                        </div>
                        <div style="max-width:80%;border-radius:16px;padding:8px 14px;font-size:14px;line-height:1.5;
                            {{ $line['role'] === 'ai'
                                ? 'background:#f0fdf4;color:#14532d;border-top-left-radius:4px;'
                                : 'background:#eff6ff;color:#1e3a5f;border-top-right-radius:4px;' }}">
                            <div>{{ $line['text'] }}</div>
                            <div style="font-size:10px;opacity:0.4;margin-top:2px;">{{ $line['at'] ?? '' }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
            <script>requestAnimationFrame(()=>{const b=document.getElementById('echj-transcript');if(b)b.scrollTop=b.scrollHeight;});</script>
        @else
            <div style="text-align:center;padding:24px 0;color:#9ca3af;font-size:14px;">
                @if($this->isLive())
                    Waiting for conversation...
                @else
                    No transcript available
                @endif
            </div>
        @endif
    </x-filament::section>

    @if($this->isLive() && $this->record->twilio_call_sid)
    <script>
    // Re-runs on every Livewire poll — re-attaches onclick each time
    (function() {
        const callSid = @js($this->record->twilio_call_sid);
        const wsUrl = 'wss://ws.echjokes.overcloud.us:8443/listen/' + callSid;

        // Initialize mulaw table once
        if (!window.__echjT) {
            window.__echjT = new Float32Array(256);
            for (let i = 0; i < 256; i++) {
                let mu = ~i & 0xFF, sign = (mu & 0x80) ? -1 : 1;
                mu &= 0x7F;
                let e = (mu >> 4) & 7, m = mu & 0xF;
                let s = (m << (e + 3)) + (1 << (e + 3)) - 132;
                window.__echjT[i] = sign * Math.min(s, 32767) / 32768;
            }
        }

        // Persistent state
        if (!window.__echjState) {
            window.__echjState = { ws: null, audioCtx: null, listening: false, sched: 0 };
        }
        const S = window.__echjState;

        const btn = document.getElementById('echj-btn');
        const status = document.getElementById('echj-status');
        if (!btn) return;

        // Restore visual state
        if (S.listening) {
            btn.textContent = 'Listening...';
            btn.style.background = '#22c55e';
            if (status) status.textContent = 'Connected';
        }

        btn.onclick = function() {
            if (S.listening) {
                S.listening = false;
                try { if (S.ws) S.ws.close(); } catch(e) {} S.ws = null;
                try { if (S.audioCtx) S.audioCtx.close(); } catch(e) {} S.audioCtx = null;
                btn.textContent = 'Listen Live';
                btn.style.background = '#ef4444';
                if (status) status.textContent = '';
                return;
            }

            S.audioCtx = new (window.AudioContext || window.webkitAudioContext)({ sampleRate: 8000 });
            S.ws = new WebSocket(wsUrl);
            S.sched = 0;
            btn.textContent = 'Connecting...';
            btn.style.background = '#888';
            if (status) status.textContent = '';

            S.ws.onopen = () => {
                S.listening = true;
                btn.textContent = 'Listening...';
                btn.style.background = '#22c55e';
                if (status) status.textContent = 'Connected';
                console.log('Listen WS connected');
            };

            S.ws.onmessage = (ev) => {
                try {
                    const msg = JSON.parse(ev.data);
                    if (msg.type === 'audio' && msg.audio && S.audioCtx) {
                        const bin = atob(msg.audio);
                        const bytes = new Uint8Array(bin.length);
                        for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
                        const pcm = new Float32Array(bytes.length);
                        for (let i = 0; i < bytes.length; i++) pcm[i] = window.__echjT[bytes[i]];
                        const buf = S.audioCtx.createBuffer(1, pcm.length, 8000);
                        buf.getChannelData(0).set(pcm);
                        const src = S.audioCtx.createBufferSource();
                        src.buffer = buf;
                        src.connect(S.audioCtx.destination);
                        const now = S.audioCtx.currentTime;
                        if (S.sched < now) S.sched = now;
                        src.start(S.sched);
                        S.sched += buf.duration;
                    } else if (msg.type === 'event' && msg.event === 'call_ended') {
                        if (status) status.textContent = 'Call ended';
                        btn.textContent = 'Call Ended';
                        btn.style.background = '#888';
                        S.listening = false;
                        try { S.ws.close(); } catch(e) {}
                    }
                } catch(e) { console.error('Audio error:', e); }
            };

            S.ws.onerror = (e) => {
                console.error('Listen WS error:', e);
                if (status) status.textContent = 'Connection error';
                btn.textContent = 'Listen Live';
                btn.style.background = '#ef4444';
                S.listening = false;
            };

            S.ws.onclose = () => {
                if (S.listening) {
                    S.listening = false;
                    btn.textContent = 'Listen Live';
                    btn.style.background = '#ef4444';
                    if (status) status.textContent = 'Disconnected';
                }
            };
        };
    })();
    </script>
    @endif
</x-filament-widgets::widget>
