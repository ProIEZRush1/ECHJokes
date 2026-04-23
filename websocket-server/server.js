const WebSocket = require('ws');
const http = require('http');
const https = require('https');

const PORT = parseInt(process.env.WS_PORT || '8081', 10);
const OPENAI_API_KEY = process.env.OPENAI_API_KEY;
const ELEVENLABS_API_KEY = process.env.ELEVENLABS_API_KEY || '';
const parseVoicePool = (raw, fallback) => {
  const ids = (raw || '').split(',').map(s => s.trim()).filter(Boolean);
  return ids.length ? ids : [fallback];
};
const ELEVENLABS_VOICES_MALE = parseVoicePool(
  process.env.ELEVENLABS_VOICES_MALE || process.env.ELEVENLABS_VOICE_MALE || process.env.ELEVENLABS_VOICE_ID,
  'iP95p4xoKVk53GoZ742B'
);
const ELEVENLABS_VOICES_FEMALE = parseVoicePool(
  process.env.ELEVENLABS_VOICES_FEMALE || process.env.ELEVENLABS_VOICE_FEMALE,
  'EXAVITQu4vr4xnSDxMaL'
);
console.log(`Voice pools: male=${ELEVENLABS_VOICES_MALE.length} female=${ELEVENLABS_VOICES_FEMALE.length}`);
const DEFAULT_VOICE = process.env.OPENAI_VOICE || 'ash';
const APP_INTERNAL_URL = process.env.APP_INTERNAL_URL || 'https://vacilada.com';

// Toggle: set to true to use ElevenLabs TTS, false for OpenAI native audio
const USE_ELEVENLABS = !!ELEVENLABS_API_KEY;
console.log(`TTS mode: ${USE_ELEVENLABS ? 'ElevenLabs' : 'OpenAI native'}`);

// Track active calls and their browser listeners
const activeCalls = new Map();

// HTTP server for health check
const server = http.createServer((req, res) => {
  if (req.url === '/health' || req.url === '/') { res.writeHead(200); res.end('OK'); }
  else { res.writeHead(404); res.end(); }
});

const wss = new WebSocket.Server({ noServer: true });
server.on('upgrade', (request, socket, head) => {
  wss.handleUpgrade(request, socket, head, (ws) => { wss.emit('connection', ws, request); });
});
server.listen(PORT, () => console.log(`WS server on port ${PORT}`));
http.createServer((_, res) => { res.writeHead(200); res.end('OK'); }).listen(PORT + 1);

function broadcastAudio(callSid, audioBase64, source) {
  const call = activeCalls.get(callSid);
  if (!call || call.listeners.size === 0) return;
  const msg = JSON.stringify({ type: 'audio', source, audio: audioBase64 });
  for (const listener of call.listeners) {
    if (listener.readyState === WebSocket.OPEN) listener.send(msg);
  }
}

function broadcastEvent(callSid, event, data) {
  const call = activeCalls.get(callSid);
  if (!call) return;
  const msg = JSON.stringify({ type: 'event', event, ...data });
  for (const listener of call.listeners) {
    if (listener.readyState === WebSocket.OPEN) listener.send(msg);
  }
}

function postCallAiFailed(callSid, reason) {
  if (!callSid) return;
  const data = JSON.stringify({ call_sid: callSid, reason: String(reason).slice(0, 500) });
  const url = new URL(`${APP_INTERNAL_URL}/api/call-ai-failed`);
  const isHttps = url.protocol === 'https:';
  const opts = {
    hostname: url.hostname,
    port: url.port || (isHttps ? 443 : 80),
    path: url.pathname,
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength(data) },
  };
  const req = (isHttps ? https : http).request(opts, (res) => {
    console.log(`[ai-failed] POST → ${res.statusCode} reason="${reason}"`);
  });
  req.on('error', (e) => console.error('[ai-failed] error:', e.message));
  req.write(data);
  req.end();
}

function postTranscript(callSid, role, text) {
  if (!callSid) { console.log(`[transcript] skip: no callSid (role=${role} text="${text?.slice(0,30)}")`); return; }
  if (!text) return;
  const data = JSON.stringify({ call_sid: callSid, role, text });
  const url = new URL(`${APP_INTERNAL_URL}/api/call-transcript`);
  const isHttps = url.protocol === 'https:';
  const opts = {
    hostname: url.hostname,
    port: url.port || (isHttps ? 443 : 80),
    path: url.pathname,
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength(data) },
  };
  const req = (isHttps ? https : http).request(opts, (res) => {
    console.log(`[transcript] POST ${role} len=${text.length} → ${res.statusCode}`);
  });
  req.on('error', (e) => console.error('[transcript] error:', e.message));
  req.write(data);
  req.end();
}

// ============================================
// Mulaw audio utilities (module level)
// ============================================
const MULAW_DECODE = new Int16Array(256);
for (let i = 0; i < 256; i++) {
  let mu = ~i & 0xFF;
  let sign = (mu & 0x80) ? -1 : 1;
  mu = mu & 0x7F;
  let exponent = (mu >> 4) & 0x07;
  let mantissa = mu & 0x0F;
  let sample = (mantissa << (exponent + 3)) + (1 << (exponent + 3)) - 132;
  MULAW_DECODE[i] = sign * Math.min(sample, 32767);
}

function linearToMulaw(sample) {
  const BIAS = 0x84, MAX = 32635;
  const sign = (sample >> 8) & 0x80;
  if (sign) sample = -sample;
  if (sample > MAX) sample = MAX;
  sample += BIAS;
  let exponent = 7;
  for (let expMask = 0x4000; (sample & expMask) === 0 && exponent > 0; exponent--, expMask >>= 1) {}
  const mantissa = (sample >> (exponent + 3)) & 0x0F;
  return ~(sign | (exponent << 4) | mantissa) & 0xFF;
}

const AMBIENCE_SR = 8000;
const AMBIENCE_LEN = AMBIENCE_SR * 15;
const ambienceLoop = new Int16Array(AMBIENCE_LEN);
(function generateAmbienceLoop() {
  let lp1 = 0, lp2 = 0, lp3 = 0;
  const BAND_CENTER = 350, BAND_Q = 6;
  let bp = 0, bpPrev = 0;
  for (let i = 0; i < AMBIENCE_LEN; i++) {
    const white = (Math.random() - 0.5) * 2400;
    lp1 = lp1 * 0.9 + white * 0.1;
    lp2 = lp2 * 0.88 + lp1 * 0.12;
    lp3 = lp3 * 0.85 + lp2 * 0.15;
    const envelope = 0.45 + 0.55 * (0.5 + 0.5 * Math.sin(2 * Math.PI * i * 0.2 / AMBIENCE_SR));
    const rumble = Math.sin(2 * Math.PI * i * 90 / AMBIENCE_SR) * 35;
    const hum = Math.sin(2 * Math.PI * i * 120 / AMBIENCE_SR) * 18;
    let s = lp3 * envelope + rumble + hum;
    const click = Math.random() < 0.00008 ? (Math.random() - 0.5) * 800 : 0;
    s += click;
    ambienceLoop[i] = Math.max(-32767, Math.min(32767, Math.round(s)));
  }
  console.log(`Ambience loop generated: ${AMBIENCE_LEN} samples (${AMBIENCE_LEN/AMBIENCE_SR}s)`);
})();

function createAmbienceProfile() {
  return {
    pos: Math.floor(Math.random() * AMBIENCE_LEN),
    gain: 0.004 + Math.random() * 0.008,
  };
}

const VOICE_GAIN = 2.4;
const CLIP_KNEE = 16000;
function softClip(x) {
  const a = Math.abs(x);
  if (a <= CLIP_KNEE) return x;
  const sign = x < 0 ? -1 : 1;
  const headroom = 32767 - CLIP_KNEE;
  return sign * (CLIP_KNEE + headroom * Math.tanh((a - CLIP_KNEE) / headroom));
}
function mixAmbience(mulawFrame, p) {
  const buf = Buffer.from(mulawFrame);
  for (let i = 0; i < buf.length; i++) {
    const voice = softClip(MULAW_DECODE[buf[i]] * VOICE_GAIN);
    const amb = ambienceLoop[p.pos] * p.gain;
    p.pos = (p.pos + 1) % AMBIENCE_LEN;
    const sample = Math.max(-32767, Math.min(32767, voice + amb));
    buf[i] = linearToMulaw(sample);
  }
  return buf.toString('base64');
}

// ============================================
// PCM 16-bit signed to G.711 mulaw conversion
// ============================================
function pcmToMulaw(pcmBuffer) {
  const mulaw = Buffer.alloc(pcmBuffer.length / 2);
  for (let i = 0; i < mulaw.length; i++) {
    let sample = pcmBuffer.readInt16LE(i * 2);
    // Clamp
    const BIAS = 0x84;
    const MAX = 32635;
    const sign = (sample >> 8) & 0x80;
    if (sign) sample = -sample;
    if (sample > MAX) sample = MAX;
    sample += BIAS;
    let exponent = 7;
    for (let expMask = 0x4000; (sample & expMask) === 0 && exponent > 0; exponent--, expMask >>= 1) {}
    const mantissa = (sample >> (exponent + 3)) & 0x0F;
    mulaw[i] = ~(sign | (exponent << 4) | mantissa) & 0xFF;
  }
  return mulaw;
}

// ============================================
// ElevenLabs TTS: stream text -> mulaw audio
// ============================================
function elevenLabsTTS(text, voiceId, callback) {
  const postData = JSON.stringify({
    text: text,
    model_id: 'eleven_turbo_v2_5',
    voice_settings: { stability: 0.7, similarity_boost: 0.92, style: 0.12, use_speaker_boost: false, speed: 1.1 }
  });

  const options = {
    hostname: 'api.elevenlabs.io',
    path: `/v1/text-to-speech/${voiceId}/stream?output_format=ulaw_8000`,
    method: 'POST',
    headers: {
      'xi-api-key': ELEVENLABS_API_KEY,
      'Content-Type': 'application/json',
      'Content-Length': Buffer.byteLength(postData),
    }
  };

  const req = https.request(options, (res) => {
    console.log(`ElevenLabs response: ${res.statusCode}`);
    if (res.statusCode !== 200) {
      let body = '';
      res.on('data', (c) => body += c);
      res.on('end', () => { console.error('ElevenLabs error body:', body); callback(null, true); });
      return;
    }
    let totalBytes = 0;
    let buffer = Buffer.alloc(0);
    const FRAME_SIZE = 160;

    res.on('data', (chunk) => {
      totalBytes += chunk.length;
      buffer = Buffer.concat([buffer, chunk]);
      while (buffer.length >= FRAME_SIZE) {
        const frame = buffer.subarray(0, FRAME_SIZE);
        buffer = buffer.subarray(FRAME_SIZE);
        callback(frame.toString('base64'), false);
      }
    });
    res.on('end', () => {
      if (buffer.length > 0) callback(buffer.toString('base64'), false);
      console.log(`ElevenLabs TTS done: ${totalBytes} bytes`);
      callback(null, true);
    });
  });

  req.on('error', (e) => {
    console.error('ElevenLabs TTS error:', e.message);
    callback(null, true);
  });

  req.write(postData);
  req.end();
}

wss.on('connection', (ws, req) => {
  const url = req.url || '';

  // Browser listener
  if (url.startsWith('/listen/')) {
    const callSid = url.split('/listen/')[1];
    console.log(`Browser listener connected for call: ${callSid}`);
    let call = activeCalls.get(callSid);
    if (!call) { call = { listeners: new Set() }; activeCalls.set(callSid, call); }
    call.listeners.add(ws);
    ws.send(JSON.stringify({ type: 'event', event: 'connected', callSid }));
    ws.on('close', () => { call.listeners.delete(ws); });
    return;
  }

  handleTwilioStream(ws, req);
});

function handleTwilioStream(twilioWs, req) {
  const pathParts = (req.url || '').split('/');
  const encoded = pathParts[pathParts.length - 1] || '';
  let scenario = '', character = '', voice = DEFAULT_VOICE, victimName = '';
  try {
    const params = JSON.parse(Buffer.from(encoded, 'base64').toString('utf8'));
    scenario = params.s || '';
    character = params.c || '';
    voice = params.v || DEFAULT_VOICE;
    victimName = params.n || '';
  } catch (e) {
    console.log('Could not decode params, using defaults');
  }
  console.log(`Call connected: scenario="${scenario}" character="${character}" voice=${voice} victim="${victimName || '(none)'}" tts=${USE_ELEVENLABS ? 'ElevenLabs' : 'OpenAI'}`);

  let streamSid = null;
  let callSid = null;
  let openAiWs = null;

  let lastAssistantItem = null;
  let markQueue = [];
  let responseStartTimestamp = null;
  let latestMediaTimestamp = 0;
  let sessionReady = false;
  let streamReady = false;
  let currentAiText = '';
  let isSpeaking = false; // Track if ElevenLabs is currently speaking
  let ttsSessionId = 0; // Increment to invalidate in-flight TTS callbacks
  // Sentence-level streaming to ElevenLabs
  let streamedOffset = 0;          // chars of currentAiText already dispatched to TTS
  let ttsQueue = [];               // pending sentence fragments for current response
  let ttsActive = false;           // true while an ElevenLabs request is in-flight
  let responseActive = false;      // true between response start and response.done

  // Generate a random Mexican name for the AI caller
  const maleNames = ['Carlos','Miguel','Roberto','Fernando','Alejandro','Ricardo','Eduardo','Jorge','Luis','Daniel','Raul','Sergio','Arturo','Oscar','Hector','Manuel','Pablo','Diego','Ivan','Andres'];
  const femaleNames = ['Maria','Sofia','Andrea','Daniela','Valeria','Fernanda','Gabriela','Alejandra','Patricia','Carmen','Laura','Monica','Claudia','Leticia','Veronica','Diana','Karla','Adriana','Lorena','Sandra'];
  const lastNames = ['Garcia','Hernandez','Lopez','Martinez','Gonzalez','Rodriguez','Perez','Sanchez','Ramirez','Torres','Flores','Rivera','Morales','Cruz','Reyes','Gutierrez','Ortiz','Mendoza','Castillo','Jimenez'];
  const isFemaleVoice = ['coral','sage','shimmer'].includes(voice);
  const voicePool = isFemaleVoice ? ELEVENLABS_VOICES_FEMALE : ELEVENLABS_VOICES_MALE;
  const elevenLabsVoiceId = voicePool[Math.floor(Math.random() * voicePool.length)];
  const firstName = isFemaleVoice
    ? femaleNames[Math.floor(Math.random() * femaleNames.length)]
    : maleNames[Math.floor(Math.random() * maleNames.length)];
  const lastName = lastNames[Math.floor(Math.random() * lastNames.length)];
  const callerName = `${firstName} ${lastName}`;
  console.log(`AI caller name: ${callerName} (voice=${voice} gender=${isFemaleVoice ? 'F' : 'M'} elevenLabs=${elevenLabsVoiceId})`);

  const ambience = createAmbienceProfile();
  console.log(`Ambience: gain=${ambience.gain.toFixed(2)} offset=${ambience.pos}`);

  const instructions = `Estas en una llamada telefonica. TU eres quien LLAMO. La persona que contesta es a quien llamaste.

TU NOMBRE Y GENERO: Te llamas ${callerName}. Eres ${isFemaleVoice ? 'MUJER' : 'HOMBRE'} — tu voz es de ${isFemaleVoice ? 'mujer' : 'hombre'}, NUNCA uses un nombre del otro género ni pretendas ser del otro género. Cuando te pregunten "de parte de quien" o te pidan tu nombre, di "${callerName}" (${isFemaleVoice ? 'nombre femenino' : 'nombre masculino'}). SIEMPRE usa este nombre exacto, aunque el escenario o la víctima sugiera otro. Todas tus referencias a ti mismo deben estar en ${isFemaleVoice ? 'femenino (ej. "estoy segura", "soy yo")' : 'masculino (ej. "estoy seguro", "soy yo")'}.

TU PERSONAJE:
${character || 'Una persona que llama por un asunto importante'}

PERSONA A QUIEN LLAMAS:
${victimName ? `⚠️ SE LLAMA: ${victimName} ⚠️
En tu PRIMER saludo DEBES preguntar "¿se encuentra ${victimName}?" o "¿hablo con ${victimName}?".
SIEMPRE dirígete a la persona por su nombre (${victimName}) durante toda la conversación: "Mire ${victimName}...", "Oiga ${victimName}...", "Como le decía ${victimName}...".
NO PREGUNTES por "el encargado" ni por otra persona — sabes exactamente a quién llamas.` : 'NO SABES su nombre. NUNCA inventes un nombre. Simplemente pregunta "hablo con el encargado?" o "buenas tardes, le hablo porque..." sin mencionar ningun nombre.'}

SITUACION / CONTEXTO DE LA LLAMADA:
${scenario || 'Llamada importante que debes llevar a cabo'}

ATENCION — SABER A QUIEN LE HABLAN:
- A veces escucharas voces de fondo, personas hablando entre ellas, ruido. ESO NO TE LO ESTAN HABLANDO A TI.
- Solo responde cuando la persona se dirija claramente a ti (responde tu pregunta, te hace una pregunta, o continua la conversacion que tu iniciaste).
- Si escuchas voces pero no parecen dirigidas a ti, NO RESPONDAS. Solo di "aja", "mhm", "si?" o quedate callado esperando.
- Como un humano real: tu esperas pacientemente cuando la otra persona esta distraida.

COMO HABLAR — ESTO ES LO MAS IMPORTANTE. SUENA 100% HUMANO:
- SIEMPRE empieza tus respuestas con una muletilla o sonido natural ANTES del contenido: "Mmm...", "Este...", "Ay mira...", "No pos...", "Aja, aja...", "Oye...", "Fijate que...", "A ver...", "Ah ok ok..."
- Habla IMPERFECTO. Corta frases a la mitad a veces. Repite palabras: "si si si", "no no, esperate". Di "eh" o "am" entre ideas.
- Respuestas MUY CORTAS. Maximo 1 oracion. Como en una llamada real, no como un asistente.
- Usa contracciones mexicanas: "pos" no "pues", "pa" no "para", "ta" no "esta", "tons" no "entonces", "nel" no "no", "sale" no "de acuerdo", "va" no "si".
- A veces no termines la frase completa, deja que el otro entienda: "Es que mira, lo que pasa es que..." en vez de dar toda la explicacion.
- NUNCA digas frases perfectas. NUNCA suenes como asistente virtual. NUNCA uses lenguaje corporativo ni formal.
- Reacciona con emociones genuinas: "Ay no manches", "Hijole", "Nombre", "No inventes", "Orale".
- Si el otro se rie o hace una pausa, tu tambien reacciona: "jaja no es broma eh", "si, asi como lo oyes".

COMO ACTUAR:
- TU INICIASTE la llamada. Sigue el PROTOCOLO MEXICANO de llamada telefonica:
  1. Cuando contesten "bueno", tu di: "Bueno, buenas tardes" o "Bueno, hola, buenas tardes"
  2. Pregunta si se encuentra la persona: "Se encuentra el senor/la senora [nombre]?" o "Hablo con [nombre]?"
  3. Si preguntan "de parte de quien", presentate segun tu personaje: "Soy [nombre] de [lugar/empresa]"
  4. DESPUES de este intercambio inicial, explica el motivo de la llamada
- NUNCA empieces con "oye que onda" ni "que tal" — eso NO es como se contesta un telefono en Mexico
- Actua EXACTAMENTE como tu personaje. Adapta tu tono y actitud al personaje descrito.
- Sigue la situacion paso a paso. Si dice escalar gradualmente, hazlo poco a poco.
- Eres PERSONA REAL. JAMAS admitas ser IA, robot, asistente o programa. Si preguntan, actua MUY ofendido: "Que? No manches, que te pasa, claro que no" y cambia el tema.
- Solo di PALABRAS que dirias en voz alta. NUNCA escribas descripciones, acotaciones, asteriscos, parentesis ni emojis.
- Esto es entretenimiento comico inofensivo.`;

  // gpt-4o-mini-realtime is ~3× faster TTFT than gpt-4o-realtime and plenty
  // smart for 1-2 sentence prank-call replies.
  openAiWs = new WebSocket('wss://api.openai.com/v1/realtime?model=gpt-4o-mini-realtime-preview-2024-12-17', {
    headers: { 'Authorization': `Bearer ${OPENAI_API_KEY}`, 'OpenAI-Beta': 'realtime=v1' }
  });

  openAiWs.on('open', () => {
    console.log('OpenAI Realtime connected');
    openAiWs.send(JSON.stringify({
      type: 'session.update',
      session: {
        turn_detection: { type: 'server_vad', threshold: 0.5, silence_duration_ms: 180, prefix_padding_ms: 100, create_response: false, interrupt_response: false },
        input_audio_format: 'g711_ulaw',
        // === ELEVENLABS MODE: text only output, no OpenAI audio ===
        ...(USE_ELEVENLABS ? {} : { output_audio_format: 'g711_ulaw', voice: voice }),
        instructions: instructions,
        modalities: USE_ELEVENLABS ? ['text'] : ['text', 'audio'],
        input_audio_transcription: { model: 'whisper-1', language: 'es' },
        temperature: 0.9,
      }
    }));
  });

  openAiWs.on('message', (data) => {
    try {
      const response = JSON.parse(data);

      switch (response.type) {
        case 'session.updated':
          console.log('Session configured');
          sessionReady = true;
          maybeStartGreeting();
          break;

        // === OpenAI native audio (only when USE_ELEVENLABS is false) ===
        case 'response.audio.delta':
          if (!USE_ELEVENLABS && response.delta && streamSid) {
            twilioWs.send(JSON.stringify({ event: 'media', streamSid, media: { payload: response.delta } }));
            if (callSid) broadcastAudio(callSid, response.delta, 'ai');
            if (!responseStartTimestamp) responseStartTimestamp = latestMediaTimestamp;
            if (response.item_id) lastAssistantItem = response.item_id;
          }
          break;

        case 'response.audio.done':
          if (!USE_ELEVENLABS && streamSid) {
            const markId = `mark_${Date.now()}`;
            markQueue.push(markId);
            twilioWs.send(JSON.stringify({ event: 'mark', streamSid, mark: { name: markId } }));
          }
          responseStartTimestamp = null;
          break;

        case 'input_audio_buffer.speech_started':
          console.log('Speech started (waiting for transcript before interrupting)');
          if (callSid) broadcastEvent(callSid, 'speech_started');
          break;

        case 'input_audio_buffer.committed':
          // VAD just committed the user's turn. Trigger response immediately so
          // we don't wait for Whisper transcription to come back before starting
          // Claude — saves 500-1500ms per turn. Guard against firing while a
          // prior response is still generating (OpenAI rejects with "active
          // response in progress"). responseActive covers the text-gen phase,
          // isSpeaking covers the ElevenLabs TTS phase.
          if (openAiWs?.readyState === WebSocket.OPEN && !responseActive && !isSpeaking) {
            openAiWs.send(JSON.stringify({ type: 'response.create' }));
          }
          break;

        // === Text output (used by both modes, but ElevenLabs sends to TTS) ===
        case 'response.text.delta':
        case 'response.audio_transcript.delta':
          if (response.delta) {
            process.stdout.write(response.delta);
            if (USE_ELEVENLABS && !responseActive) {
              // First delta of a new response — set up session.
              if (isSpeaking && streamSid) {
                try { twilioWs.send(JSON.stringify({ event: 'clear', streamSid })); } catch(e) {}
              }
              ttsSessionId++;
              streamedOffset = 0;
              currentAiText = '';
              ttsQueue = [];
              isSpeaking = true;
              responseActive = true;
              responseStartTimestamp = latestMediaTimestamp;
            }
            currentAiText += response.delta;
            if (USE_ELEVENLABS) flushSentences(ttsSessionId);
          }
          break;

        case 'response.done':
          console.log('');
          responseActive = false;
          if (currentAiText.trim()) {
            const text = currentAiText.trim();
            console.log(`[AI]: ${text}`);
            postTranscript(callSid, 'ai', text);
            if (callSid) broadcastEvent(callSid, 'ai_text', { text });

            if (USE_ELEVENLABS && streamSid) {
              // Flush any trailing fragment that didn't end with a sentence terminator.
              const tail = currentAiText.slice(streamedOffset).trim();
              if (tail) { ttsQueue.push(tail); streamedOffset = currentAiText.length; }
              drainTtsQueue(ttsSessionId);
            }
          } else {
            // No text produced — make sure we don't leave isSpeaking stuck.
            if (!ttsActive && ttsQueue.length === 0) isSpeaking = false;
          }
          currentAiText = '';
          break;

        case 'conversation.item.input_audio_transcription.completed':
          if (response.transcript) {
            const t = response.transcript.trim();
            if (t) {
              console.log(`[Human]: ${t}`);
              postTranscript(callSid, 'human', t);
              if (callSid) broadcastEvent(callSid, 'human_text', { text: t });
              // Response already triggered on input_audio_buffer.committed —
              // don't fire again here.
            }
          }
          break;

        case 'error':
          console.error('OpenAI error:', response.error?.message || JSON.stringify(response));
          // Recoverable: "active response in progress" — the existing response
          // will finish fine. Don't kill the call, just log it.
          if (callSid && !/active response in progress/i.test(response.error?.message || '')) {
            postCallAiFailed(callSid, response.error?.message || 'openai_error');
          }
          break;
      }
    } catch (e) {
      console.error('Parse error:', e.message);
    }
  });

  openAiWs.on('error', (e) => {
    console.error('OpenAI WS error:', e.message);
    if (callSid) postCallAiFailed(callSid, e.message || 'openai_ws_error');
  });
  openAiWs.on('close', () => {
    console.log('OpenAI disconnected');
    stopAmbienceStream();
    if (callSid) { broadcastEvent(callSid, 'call_ended'); activeCalls.delete(callSid); }
  });

  function maybeStartGreeting() {
    if (!sessionReady || !streamReady) return;
    console.log('Both ready — AI says Hola first');
    openAiWs.send(JSON.stringify({
      type: 'conversation.item.create',
      item: { type: 'message', role: 'user',
        content: [{ type: 'input_text', text: `[La persona contesto el telefono y dijo "bueno"]. Responde con "Bueno, buenas tardes"${victimName ? `. DEBES preguntar textualmente "¿se encuentra ${victimName}?" — NUNCA preguntes por "el encargado" ni por otra persona` : ' y presentate segun tu personaje'}. Sigue el protocolo mexicano de llamada telefonica.` }] }
    }));
    openAiWs.send(JSON.stringify({ type: 'response.create' }));
  }

  // Look for sentence terminators past streamedOffset and enqueue finished
  // sentences for TTS immediately, so ElevenLabs starts speaking the first
  // sentence while Claude is still streaming the rest.
  function flushSentences(sessionId) {
    if (sessionId !== ttsSessionId) return;
    const sentenceEnd = /[.!?…]+[\s)"'"]*|\n+/g;
    sentenceEnd.lastIndex = streamedOffset;
    let m;
    let progressed = false;
    while ((m = sentenceEnd.exec(currentAiText)) !== null) {
      const end = m.index + m[0].length;
      // Need enough lead-in to be worth a TTS round-trip (avoid "Ok." etc.).
      if (end - streamedOffset < 12) continue;
      const fragment = currentAiText.slice(streamedOffset, end).trim();
      if (fragment) {
        ttsQueue.push(fragment);
        streamedOffset = end;
        progressed = true;
      }
    }
    if (progressed) drainTtsQueue(sessionId);
  }

  function drainTtsQueue(sessionId) {
    if (sessionId !== ttsSessionId) return;
    if (ttsActive || ttsQueue.length === 0 || !streamSid) return;
    ttsActive = true;
    const text = ttsQueue.shift();
    const mySessionId = ttsSessionId;
    elevenLabsTTS(text, elevenLabsVoiceId, (audioBase64, done) => {
      if (mySessionId !== ttsSessionId) {
        if (done) { ttsActive = false; }
        return;
      }
      if (audioBase64 && streamSid && twilioWs.readyState === WebSocket.OPEN) {
        try {
          const mulawFrame = Buffer.from(audioBase64, 'base64');
          const mixedPayload = mixAmbience(mulawFrame, ambience);
          twilioWs.send(JSON.stringify({ event: 'media', streamSid, media: { payload: mixedPayload } }));
          if (callSid) broadcastAudio(callSid, mixedPayload, 'ai');
        } catch(e) { console.error('Twilio send error:', e.message); }
      }
      if (done) {
        ttsActive = false;
        if (ttsQueue.length > 0) {
          drainTtsQueue(mySessionId);
        } else if (!responseActive) {
          // Queue empty AND no more text coming — this response is fully spoken.
          if (streamSid) {
            const markId = `mark_${Date.now()}`;
            markQueue.push(markId);
            twilioWs.send(JSON.stringify({ event: 'mark', streamSid, mark: { name: markId } }));
          }
          isSpeaking = false;
          responseStartTimestamp = null;
        }
        // else: more text still streaming from OpenAI — wait for next delta/flushSentences
      }
    });
  }

  function handleInterruption() {
    if (USE_ELEVENLABS) {
      // For ElevenLabs: just clear the Twilio buffer
      if (streamSid) {
        twilioWs.send(JSON.stringify({ event: 'clear', streamSid }));
      }
      isSpeaking = false;
      markQueue = [];
      responseStartTimestamp = null;
    } else {
      // OpenAI native: truncate the response
      if (markQueue.length > 0 && responseStartTimestamp != null) {
        const audioMs = Math.max(0, latestMediaTimestamp - responseStartTimestamp);
        twilioWs.send(JSON.stringify({ event: 'clear', streamSid }));
        if (lastAssistantItem) {
          openAiWs.send(JSON.stringify({
            type: 'conversation.item.truncate',
            item_id: lastAssistantItem, content_index: 0, audio_end_ms: audioMs
          }));
        }
        markQueue = [];
        responseStartTimestamp = null;
      }
    }
  }

  twilioWs.on('message', (data) => {
    try {
      const msg = JSON.parse(data);
      switch (msg.event) {
        case 'connected':
          console.log('Twilio connected');
          break;
        case 'start':
          streamSid = msg.start?.streamSid;
          callSid = msg.start?.callSid || null;
          console.log(`Twilio stream: ${streamSid} call: ${callSid}`);
          if (callSid && !activeCalls.has(callSid)) activeCalls.set(callSid, { listeners: new Set() });
          streamReady = true;
          startAmbienceStream();
          maybeStartGreeting();
          break;
        case 'media':
          latestMediaTimestamp = msg.media?.timestamp ? parseInt(msg.media.timestamp) : Date.now();
          // Don't send audio to OpenAI while ElevenLabs is speaking (prevents echo/feedback loop)
          if (openAiWs?.readyState === WebSocket.OPEN && !(USE_ELEVENLABS && isSpeaking)) {
            openAiWs.send(JSON.stringify({ type: 'input_audio_buffer.append', audio: msg.media.payload }));
          }
          if (callSid) broadcastAudio(callSid, msg.media.payload, 'human');
          break;
        case 'mark':
          if (markQueue.length > 0 && markQueue[0] === msg.mark?.name) markQueue.shift();
          break;
        case 'stop':
          console.log('Twilio stream stopped');
          if (openAiWs?.readyState === WebSocket.OPEN) openAiWs.close();
          break;
      }
    } catch (e) {
      console.error('Twilio parse error:', e.message);
    }
  });

  twilioWs.on('close', () => {
    console.log('Twilio disconnected');
    stopAmbienceStream();
    if (openAiWs?.readyState === WebSocket.OPEN) openAiWs.close();
  });

  let ambienceInterval = null;
  let ambienceFrameCount = 0;
  const STANDALONE_AMB_GAIN = 0.3;
  function startAmbienceStream() {
    if (ambienceInterval) return;
    ambienceFrameCount = 0;
    console.log('Ambience stream started');
    ambienceInterval = setInterval(() => {
      if (!streamSid || twilioWs.readyState !== WebSocket.OPEN || isSpeaking) return;
      const frame = Buffer.alloc(160);
      for (let i = 0; i < 160; i++) {
        const amb = ambienceLoop[ambience.pos] * ambience.gain * STANDALONE_AMB_GAIN;
        ambience.pos = (ambience.pos + 1) % AMBIENCE_LEN;
        frame[i] = linearToMulaw(Math.max(-32767, Math.min(32767, amb)));
      }
      try {
        twilioWs.send(JSON.stringify({ event: 'media', streamSid, media: { payload: frame.toString('base64') } }));
        ambienceFrameCount++;
        if (ambienceFrameCount % 250 === 0) console.log(`Ambience: ${ambienceFrameCount} standalone frames sent (${(ambienceFrameCount/50).toFixed(1)}s)`);
      } catch (e) { /* ignored */ }
    }, 20);
  }
  function stopAmbienceStream() {
    if (ambienceInterval) { clearInterval(ambienceInterval); ambienceInterval = null; console.log(`Ambience stream stopped (${ambienceFrameCount} standalone frames total)`); }
  }
}
