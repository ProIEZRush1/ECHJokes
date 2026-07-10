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
// If the AI asks the operator a question and nobody answers within this window,
// the AI apologizes and hangs up instead of freezing the call forever.
const SUPERVISOR_TIMEOUT_MS = parseInt(process.env.SUPERVISOR_TIMEOUT_MS || '90000', 10);
// Optional shared secret to authorize operator control messages (answers, DTMF,
// "say") over the /listen socket. When set, control messages must carry a
// matching token (the panel fetches it from an admin-only endpoint). When empty,
// control is unrestricted (backward-compatible with the existing setup).
const WS_CONTROL_SECRET = process.env.WS_CONTROL_SECRET || '';

// Toggle: set to true to use ElevenLabs TTS, false for OpenAI native audio
const USE_ELEVENLABS = !!ELEVENLABS_API_KEY;
console.log(`TTS mode: ${USE_ELEVENLABS ? 'ElevenLabs' : 'OpenAI native'}`);

// ============================================
// Turn-detection (VAD) tuning
// ============================================
// PRANK calls: the AI should keep talking and NOT jump in on brief pauses/noise
// — but it MUST still hear the victim. That "less twitchy" feel comes from a
// LONG silence window (waits before ending the turn), NOT from a high threshold:
// too high a threshold makes the AI deaf to normal phone speech. So the
// threshold is clamped to a hearing-safe range (a prior 0.8 setting made it miss
// the victim entirely and calls looked like voicemail).
const PRANK_VAD_MODE      = process.env.PRANK_VAD_MODE || 'server_vad'; // server_vad | semantic_vad
const clampThreshold = (v, def) => { const n = parseFloat(v); return (n >= 0.2 && n <= 0.6) ? n : def; };
const PRANK_VAD_THRESHOLD = clampThreshold(process.env.PRANK_VAD_THRESHOLD, 0.5); // 0.2–0.6; anything higher would go deaf
const PRANK_VAD_SILENCE_MS= parseInt(process.env.PRANK_VAD_SILENCE_MS || '800', 10); // wait this long after speech before ending the turn
const PRANK_VAD_PREFIX_MS = parseInt(process.env.PRANK_VAD_PREFIX_MS || '300', 10);
const PRANK_VAD_EAGERNESS = process.env.PRANK_VAD_EAGERNESS || 'low'; // used only when PRANK_VAD_MODE=semantic_vad

// ASSISTANT calls: companies answer with recorded menus/IVRs that pause between
// options. A LONGER silence window keeps the AI from jumping in during those
// pauses (talking over the recording); it waits for a real end-of-turn.
const ASSIST_VAD_THRESHOLD = parseFloat(process.env.ASSIST_VAD_THRESHOLD || '0.5');
const ASSIST_VAD_SILENCE_MS= parseInt(process.env.ASSIST_VAD_SILENCE_MS || '1500', 10);
const ASSIST_VAD_PREFIX_MS = parseInt(process.env.ASSIST_VAD_PREFIX_MS || '300', 10);

function buildTurnDetection(mode) {
  if (mode === 'assistant') {
    return {
      type: 'server_vad',
      threshold: ASSIST_VAD_THRESHOLD,
      prefix_padding_ms: ASSIST_VAD_PREFIX_MS,
      silence_duration_ms: ASSIST_VAD_SILENCE_MS,
      // create_response:false — we fire responses MANUALLY, only when the other
      // party REALLY said something new (see transcription handler). Otherwise
      // the AI's own voice echoing back would re-trigger it and it would talk to
      // itself, role-playing both the customer and the agent.
      create_response: false,
      interrupt_response: false,
    };
  }
  // prank
  if (PRANK_VAD_MODE === 'semantic_vad') {
    return { type: 'semantic_vad', eagerness: PRANK_VAD_EAGERNESS, create_response: true, interrupt_response: false };
  }
  return {
    type: 'server_vad',
    threshold: PRANK_VAD_THRESHOLD,
    prefix_padding_ms: PRANK_VAD_PREFIX_MS,
    silence_duration_ms: PRANK_VAD_SILENCE_MS,
    create_response: true,
    interrupt_response: false,
  };
}
console.log(`VAD prank: ${PRANK_VAD_MODE} threshold=${PRANK_VAD_THRESHOLD} silence=${PRANK_VAD_SILENCE_MS}ms`);

// ============================================
// Assistant-mode tools (OpenAI Realtime function calling)
// ============================================
// Only attached for assistant calls. They let the AI press keypad digits to
// navigate phone menus, pause to ask the human operator a question it can't
// answer, and hang up when the task is done.
const ASSISTANT_TOOLS = [
  {
    type: 'function',
    name: 'press_keypad_digits',
    description: 'Marca teclas del teléfono (tonos DTMF) para navegar un menú telefónico automático (IVR) o para capturar un número (de cliente, reservación, etc.). Úsalo cuando un sistema automático te pida marcar una opción o un dato.',
    parameters: {
      type: 'object',
      properties: {
        digits: { type: 'string', description: 'Los dígitos a marcar, por ejemplo "2" o "1023#". Solo se permiten 0-9, * y #.' },
        reason: { type: 'string', description: 'Motivo breve, ej. "opción para modificar reservaciones".' },
      },
      required: ['digits'],
    },
  },
  {
    type: 'function',
    name: 'ask_supervisor',
    description: 'Pregunta a tu supervisor humano (que está observando la llamada en vivo) cuando necesites un dato que no tienes, o una decisión que no puedes tomar por tu cuenta. IMPORTANTE: antes de llamar esta función, dile a la persona en el teléfono algo como "permítame un segundito por favor".',
    parameters: {
      type: 'object',
      properties: {
        question: { type: 'string', description: 'La pregunta clara y específica para tu supervisor.' },
      },
      required: ['question'],
    },
  },
  {
    type: 'function',
    name: 'hang_up',
    description: 'Cuelga la llamada cuando ya lograste el objetivo, o cuando definitivamente no es posible continuar.',
    parameters: {
      type: 'object',
      properties: {
        reason: { type: 'string', description: 'Motivo, ej. "objetivo cumplido" o el problema encontrado.' },
      },
      required: ['reason'],
    },
  },
];

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

// Whisper confabulates on silence/noise. These are the phrases it most
// often emits on Mexican-Spanish calls when there's no real speech.
// Matching is case-insensitive substring on the cleaned transcript.
const WHISPER_HALLUCINATIONS = [
  'gracias por ver',
  'gracias por mirar',
  'suscríbete',
  'suscribete',
  'dale like',
  'subtítulos',
  'subtitulos',
  'amara.org',
  'amara .org',
  'música',
  'musica',
  '[música]',
  '[musica]',
  '[ruido]',
  '[aplausos]',
  'silencio',
  'continua',
  'continuará',
  'continuara',
  'www.',
  '.com',
  '.org',
];

/**
 * Returns true only if the transcript looks like real spoken words, not
 * ambient noise. Filters out:
 *   - Empty / <=1 letter strings (noise commits).
 *   - Only punctuation / filler ("…", "mmm", "ah").
 *   - Known Whisper hallucination seeds on quiet calls ("Gracias por ver",
 *     "Subtítulos por Amara.org", standalone "Música", etc.).
 */
function isRealSpeech(text) {
  if (!text) return false;
  const clean = text.toLowerCase().trim().replace(/[.,!?¿¡…"'()\[\]]+/g, ' ').replace(/\s+/g, ' ').trim();
  if (clean.length < 2) return false;
  // Must contain at least 2 letter characters.
  const letters = clean.replace(/[^a-záéíóúñü]/gi, '');
  if (letters.length < 2) return false;
  // Filler-only utterances.
  if (/^(mmm+|ah+|eh+|uh+|um+|hm+|m+)$/i.test(clean)) return false;
  for (const bad of WHISPER_HALLUCINATIONS) {
    if (clean.includes(bad)) return false;
  }
  return true;
}

/**
 * Heuristic: does this "human" transcript look like an echo of the AI's
 * last utterance? Phone networks return the caller's own audio (via
 * sidetone, hybrid leakage, or full carrier loopback) and Whisper happily
 * transcribes it. We compare the cleaned strings — if more than 50% of the
 * candidate's words appear in the AI's last sentence, treat as echo.
 */
function looksLikeEchoOf(candidate, lastAi) {
  if (!candidate || !lastAi) return false;
  const norm = (s) => s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '')
    .replace(/[^a-z0-9 ]+/gi, ' ').replace(/\s+/g, ' ').trim();
  const a = norm(candidate);
  const b = norm(lastAi);
  if (!a) return false;
  // Direct substring of the AI line (typical of partial echo).
  if (b.includes(a) && a.length >= 4) return true;
  // Word-overlap ratio.
  const aWords = new Set(a.split(' ').filter(w => w.length >= 3));
  if (aWords.size === 0) return false;
  const bWords = new Set(b.split(' '));
  let hit = 0;
  for (const w of aWords) if (bWords.has(w)) hit++;
  return (hit / aWords.size) >= 0.6;
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

function postTranscript(callSid, role, text, ts) {
  if (!callSid) { console.log(`[transcript] skip: no callSid (role=${role} text="${text?.slice(0,30)}")`); return; }
  if (!text) return;
  // `ts` is the in-call event time (Twilio media timestamp, ms). It orders the
  // transcript correctly: a keypress is logged instantly but the company's
  // speech is only transcribed seconds later, so append order is unreliable.
  const data = JSON.stringify({ call_sid: callSid, role, text, ts: (typeof ts === 'number' ? ts : undefined) });
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

// Push a live "supervisor question" (or clear it) to Laravel so the operator
// panel can surface it even if it isn't connected to the live socket yet.
function postCallQuestion(callSid, { question = null, cleared = false } = {}) {
  if (!callSid) return;
  const data = JSON.stringify({ call_sid: callSid, question: question || '', cleared });
  const url = new URL(`${APP_INTERNAL_URL}/api/call-question`);
  const isHttps = url.protocol === 'https:';
  const opts = {
    hostname: url.hostname,
    port: url.port || (isHttps ? 443 : 80),
    path: url.pathname,
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength(data) },
  };
  const req = (isHttps ? https : http).request(opts, (res) => {
    console.log(`[question] POST cleared=${cleared} → ${res.statusCode}`);
  });
  req.on('error', (e) => console.error('[question] error:', e.message));
  req.write(data);
  req.end();
}

function mixAmbience(mulawFrame) {
  return Buffer.from(mulawFrame).toString('base64');
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
// DTMF: turn phone-keypad digits into G.711 mulaw audio frames
// ============================================
// Twilio bidirectional Media Streams have no "send DTMF" message — the reliable
// way to "press a key" is to play the actual dual-tone (DTMF) audio in-band, the
// same tones a real phone emits. We synthesize the two sine tones per digit,
// encode to 8kHz mulaw, and chunk into 160-byte (20ms) frames Twilio can play.
const DTMF_FREQS = {
  '1': [697, 1209], '2': [697, 1336], '3': [697, 1477],
  '4': [770, 1209], '5': [770, 1336], '6': [770, 1477],
  '7': [852, 1209], '8': [852, 1336], '9': [852, 1477],
  '*': [941, 1209], '0': [941, 1336], '#': [941, 1477],
};
const MULAW_SILENCE = 0xFF; // G.711 mulaw encoding of a zero sample

function dtmfToMulawFrames(digits, { toneMs = 200, gapMs = 100, amplitude = 11000 } = {}) {
  const SR = 8000;
  const toneSamples = Math.floor(SR * toneMs / 1000);
  const gapSamples = Math.floor(SR * gapMs / 1000);
  const pcmChunks = [];
  for (const ch of String(digits)) {
    const f = DTMF_FREQS[ch];
    if (!f) { pcmChunks.push(Buffer.alloc(gapSamples * 2)); continue; } // unknown → short pause
    const pcm = Buffer.alloc(toneSamples * 2);
    for (let i = 0; i < toneSamples; i++) {
      const t = i / SR;
      const s = amplitude * Math.sin(2 * Math.PI * f[0] * t)
              + amplitude * Math.sin(2 * Math.PI * f[1] * t);
      pcm.writeInt16LE(Math.max(-32768, Math.min(32767, Math.round(s))), i * 2);
    }
    pcmChunks.push(pcm);
    pcmChunks.push(Buffer.alloc(gapSamples * 2)); // inter-digit gap (silence)
  }
  const mulaw = pcmToMulaw(Buffer.concat(pcmChunks));
  const frames = [];
  for (let off = 0; off < mulaw.length; off += 160) {
    let frame = mulaw.subarray(off, off + 160);
    if (frame.length < 160) {
      const padded = Buffer.alloc(160, MULAW_SILENCE);
      frame.copy(padded);
      frame = padded;
    }
    frames.push(frame.toString('base64'));
  }
  return frames;
}

// ============================================
// ElevenLabs TTS: stream text -> mulaw audio
// ============================================
function elevenLabsTTS(text, voiceId, callback) {
  const postData = JSON.stringify({
    text: text,
    model_id: 'eleven_flash_v2_5',
    voice_settings: { stability: 0.5, similarity_boost: 0.88, style: 0.3, use_speaker_boost: true, speed: 1.0 }
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

  // Browser listener (operator panel). Receive-only for prank calls; for
  // assistant calls it's ALSO the control channel: the operator sends live
  // answers to the AI's questions, presses keypad digits manually, or tells the
  // AI to say something. Control actions are dispatched to the call's `control`
  // handle, which is registered by handleTwilioStream once the call is live.
  if (url.startsWith('/listen/')) {
    const callSid = url.split('/listen/')[1];
    console.log(`Browser listener connected for call: ${callSid}`);
    let call = activeCalls.get(callSid);
    if (!call) { call = { listeners: new Set() }; activeCalls.set(callSid, call); }
    call.listeners.add(ws);
    ws.send(JSON.stringify({
      type: 'event', event: 'connected', callSid,
      mode: call.control?.mode || null,
      pendingQuestion: call.pendingQuestion || null,
    }));
    ws.on('message', (data) => {
      let m;
      try { m = JSON.parse(data); } catch { return; }
      if (WS_CONTROL_SECRET && m.token !== WS_CONTROL_SECRET) return; // unauthorized control
      const c = activeCalls.get(callSid);
      if (!c || !c.control) return;
      try {
        if (m.type === 'supervisor_answer' && m.text) c.control.answerSupervisor(String(m.text));
        else if (m.type === 'dtmf' && m.digits)      c.control.sendDtmf(String(m.digits));
        else if (m.type === 'say' && m.text)         c.control.say(String(m.text));
      } catch (e) { console.error('[control] error:', e.message); }
    });
    ws.on('close', () => { call.listeners.delete(ws); });
    return;
  }

  handleTwilioStream(ws, req);
});

function handleTwilioStream(twilioWs, req) {
  const pathParts = (req.url || '').split('/');
  const encoded = pathParts[pathParts.length - 1] || '';
  let scenario = '', character = '', voice = DEFAULT_VOICE, victimName = '';
  let mode = 'prank', objective = '', context = '', identity = '', company = '';
  try {
    const params = JSON.parse(Buffer.from(encoded, 'base64').toString('utf8'));
    mode = params.m === 'assistant' ? 'assistant' : 'prank';
    scenario = params.s || '';
    character = params.c || '';
    voice = params.v || DEFAULT_VOICE;
    victimName = params.n || '';
    objective = params.o || '';
    context = params.x || '';
    identity = params.i || '';
    company = params.co || '';
  } catch (e) {
    console.log('Could not decode params, using defaults');
  }
  const isAssistant = mode === 'assistant';
  console.log(`Call connected: mode=${mode} scenario="${scenario}" objective="${objective.slice(0,60)}" company="${company}" voice=${voice} victim="${victimName || '(none)'}" tts=${USE_ELEVENLABS ? 'ElevenLabs' : 'OpenAI'}`);

  let streamSid = null;
  let callSid = null;
  let openAiWs = null;

  let lastAssistantItem = null;
  let lastHumanSpeechTs = 0; // media-time when the other side started talking (for transcript ordering)
  let markQueue = [];
  let responseStartTimestamp = null;
  let latestMediaTimestamp = 0;
  let sessionReady = false;
  let streamReady = false;
  let currentAiText = '';
  let isSpeaking = false; // Track if ElevenLabs is currently speaking
  let speechEndedAt = 0;  // Timestamp when AI last finished speaking — used for echo-tail mute
  const ECHO_TAIL_MS = 1200; // Drop victim audio for this long after AI stops
  let ttsSessionId = 0; // Increment to invalidate in-flight TTS callbacks
  let ignoreNextTranscript = false; // Set when victim talks over AI — overlap is discarded
  let ignoreTimeout = null; // Auto-clear the ignore flag so it can't get stuck
  let lastAiSentence = '';  // Last thing the AI said — used to detect echo
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

  const assistantInstructions = `Eres un asistente de voz que hace llamadas telefónicas reales en español mexicano, natural, educado y tranquilo. Hablas por teléfono EN NOMBRE de una persona: tú eres el CLIENTE que llama a una empresa a pedir ayuda. Tú NO trabajas para esa empresa; la persona que te contesta SÍ trabaja ahí y te atiende A TI. Tú pides, ellos resuelven — nunca al revés.

Tienes tres herramientas:
- press_keypad_digits(digits, reason): reproduce los tonos del teclado telefónico. Es la ÚNICA forma de meter números, códigos, opciones o datos al sistema telefónico. Hablar NO mete ningún dato.
- ask_supervisor(question): le pregunta al operador humano que vigila la llamada. Es LENTO (8 a 12 segundos) y los menús automáticos NO esperan. Úsalo poco.
- hang_up(reason): termina la llamada.

CÓMO DECIDIR QUÉ HACER — PIÉNSALO EN CADA MOMENTO DE LA LLAMADA

Antes de hacer o decir cualquier cosa, hazte UNA sola pregunta: ¿Qué estoy oyendo ahora mismo?

CASO 1 — Oigo una GRABACIÓN, un MENÚ, un SISTEMA AUTOMÁTICO, un contestador, música de espera, o cualquier voz que NO sea una persona real hablándome en vivo.
Entonces NO HABLO. Hablarle a una grabación no sirve absolutamente de nada: la máquina no me escucha, solo detecta los tonos del teclado. Aquí solo tengo dos acciones posibles:
   - Si me pide elegir una opción, marcar, teclear o ingresar un dato (opción, folio, teléfono, boleto, reservación, código): uso press_keypad_digits y marco los dígitos DE INMEDIATO, en este mismo turno. Tomo el dato de mis datos si ya lo tengo.
   - Si no me pide nada que yo pueda teclear (está leyendo información, dando instrucciones, o solo hay música de espera): me quedo EN SILENCIO y espero. No digo ni una palabra.

CASO 2 — El sistema automático me pide EXPRESAMENTE que DIGA algo en voz alta (por ejemplo "diga o marque su número", "say your reservation number", un menú que reconoce voz).
Entonces sí puedo responder diciendo en voz alta el dato que me pide. Aun así, si también puedo marcarlo con el teclado, marcarlo es más seguro: uso press_keypad_digits.

CASO 3 — Hay una PERSONA REAL, en vivo, que me habla y se dirige a mí ("¿bueno?", "gracias por llamar, ¿en qué le ayudo?").
Entonces SÍ HABLO, en español, como el cliente ${identity || 'el titular'} que soy: saludo, explico lo que necesito, contesto sus preguntas y avanzo mi objetivo.

Si tengo la menor duda de qué estoy oyendo, asumo que es una grabación: NO hablo. Marco teclas si me piden un dato, o espero en silencio.

IDIOMA — SIEMPRE ESPAÑOL: Muchos menús están en inglés. Entiendo lo que piden sin importar el idioma y actúo (marco las teclas que correspondan). SIEMPRE que el sistema me deje elegir idioma (por ejemplo "for English press 1, para español marque 2", o "press 9 for Spanish"), elijo SIEMPRE ESPAÑOL y marco esa opción de inmediato con press_keypad_digits; JAMÁS elijo inglés. Yo siempre hablo en español, y solo le hablo en voz a una persona real que me habla en español.

REGLA DE ORO PARA MARCAR NÚMEROS Y DATOS

La ÚNICA forma de meter un número, código, opción o dato al sistema telefónico es press_keypad_digits. Físicamente NO puedo ingresar datos hablando: el sistema automático solo detecta los tonos del teclado. Por eso:
   - Cuando me pidan "ingrese", "marque", "teclee", "digite", "enter", "type" un número u opción, llamo a press_keypad_digits y marco los dígitos en ESE MISMO turno.
   - PROHIBIDO decir "voy a ingresar el número", "un momento, lo tecleo", "ahora lo marco" o cualquier frase parecida sin marcarlo ahí mismo. No anuncio que voy a marcar: marco.
   - Tomo el dato de mis datos. Si el teléfono, folio, boleto o reservación ya están en mis datos, los marco directo, sin preguntarle a nadie.

Ejemplo: el sistema dice "por favor ingrese su número de reservación" o "please enter your reservation number". Yo no digo nada; busco el número en mis datos y de inmediato lo marco con press_keypad_digits.

Ejemplo: el sistema dice "marque su teléfono a diez dígitos" y el teléfono está en mis datos. NO le pregunto al supervisor cuál usar: ya lo tengo. Lo marco al instante con press_keypad_digits, sin hablar.

NUNCA INVENTES NI ARMES NÚMEROS

Un dato es SOLO lo que está escrito, tal cual, en TUS DATOS. Nada más.
   - PROHIBIDO juntar, combinar, mezclar o inventar un número a partir de varios datos. Ejemplo PROHIBIDO y GRAVE: me piden un "número de cuenta de 15 dígitos" y yo solo tengo "últimos 5 dígitos de tarjeta" y un "PIN"; JAMÁS los pego para armar un número. Eso es inventar.
   - Cada dato tiene su etiqueta (ej. "PIN de 6 dígitos", "últimos 5 dígitos de tarjeta"). Lo uso SOLO cuando me piden exactamente eso. El PIN NO es el número de cuenta; los últimos 5 dígitos NO son el número completo.
   - Si el sistema me pide un número (de cuenta, tarjeta, cliente, etc.) de cierta cantidad de dígitos y NO tengo ese número COMPLETO, exactamente, en mis datos: NO marco nada inventado. Digo "permítame un segundito por favor" y uso ask_supervisor para preguntar qué debo marcar.

MARCAR ES SILENCIOSO — NUNCA LO NARRO

Marcar teclas es una acción, no una conversación. Cuando marco, mi respuesta es SOLO la herramienta press_keypad_digits, con CERO palabras habladas.
   - PROHIBIDÍSIMO decir "voy a marcar", "voy a ingresar", "un momento", "vamos a marcar el numeral", "estoy listo", "voy a hacerlo ahora", "entendido" o cualquier narración. No anuncio lo que voy a hacer: lo hago y ya.
   - A una grabación, menú o sistema automático NO le hablo NUNCA, ni para decir "entendido" ni "un momento". Solo marco teclas o espero en silencio.

TU IDENTIDAD — QUIÉN ERES Y QUIÉN ES EL OTRO (NO TE CONFUNDAS, ES LO MÁS FÁCIL DE EQUIVOCAR)

Eres ${identity || 'el titular'}, un CLIENTE que TIENE un problema y LLAMA a ${company || 'la empresa'} para que TE ayuden. La persona que contesta TRABAJA para ${company || 'la empresa'}: es el AGENTE, y está para atenderte A TI. Tú NO trabajas ahí. Tú NO eres el agente. Tú eres quien PIDE.

Reparto de papeles (nunca lo inviertas):
- El AGENTE (el otro) pregunta, verifica tu cuenta, procesa y hace los cambios.
- TÚ (${identity || 'el titular'}) explicas tu problema, das TUS datos cuando te los piden, y pides que hagan lo que necesitas.

JAMÁS hables como si tú fueras el agente de ${company || 'la empresa'}. Estas frases son del AGENTE, NO tuyas — están PROHIBIDAS para ti:
- "¿Qué tarjeta deseas cancelar?" → MAL. Tú dices: "Quiero cancelar MI tarjeta Platinum."
- "¿Deseas que procedamos a cancelar la tarjeta?" / "¿procedemos ahora mismo?" → MAL. Tú dices: "Sí, por favor cancélenla."
- "Voy a proceder con la cancelación / con el reembolso" → MAL. Eso lo hace el agente. Tú dices: "Necesito que cancelen mi tarjeta y me reembolsen la anualidad."
- "Permíteme revisar los detalles / consultar con el sistema / con un supervisor para asegurar que todo esté en orden" → MAL, eso es de agente. (Si TÚ necesitas ayuda de TU operador, usa la herramienta ask_supervisor en silencio; NO se lo anuncies al agente como si fueras tú quien revisa.)
- "Gracias por confirmarlo, ahora reviso" / "le ayudo" / "con gusto le apoyo" / "quédese en línea mientras reviso" → MAL. Tú no revisas nada.

Si te oyes preguntándole al otro qué desea, u ofreciéndote a procesar/revisar/consultar algo POR ellos, DETENTE: te equivocaste de papel. Tú eres el cliente que pide. Cuando te pregunten quién habla, di que eres ${identity || 'el titular'}.

A QUIÉN LLAMAS: ${company || 'la empresa'}
Estás llamando a ${company || 'la empresa'}, la empresa a la que le pides ayuda; tú no formas parte de ella. Casi siempre pasarás primero por un sistema automático (grabación, menú, contestador) antes de que te atienda una persona real.

TU OBJETIVO:
${objective || 'Resolver el asunto indicado.'}
Trabajas para cumplir ese objetivo.

TUS DATOS (INFORMACIÓN QUE YA TIENES):
${context || '(sin datos adicionales — si te piden algo que no tienes, pregúntale a tu supervisor)'}
Estos son TUS datos y ya los tienes. Cuando el sistema o una persona te pida un teléfono, reservación, folio, boleto, correo, fecha o cualquier dato que esté aquí arriba, lo usas directamente. NO le preguntes al supervisor por un dato que ya está aquí.

CUÁNDO USAR AL SUPERVISOR (ask_supervisor)

Solo consulto al supervisor cuando de verdad necesito algo que NO está en mis datos, o una decisión que yo no puedo tomar solo: autorizar un cargo, elegir entre varias opciones que me ofrecen, o dar un dato que no tengo. Sus respuestas son LENTAS y los menús no esperan, así que nunca lo uso para algo que ya sé. Antes de consultarlo, a la persona real le digo "permítame un segundito por favor" y entonces llamo a ask_supervisor.

Nunca invento datos: números de confirmación, precios, fechas, folios. Si no lo sé y no está en mis datos, se lo pregunto al supervisor; si no, no lo uso.

CÓMO TERMINAR

Cuando cumpla el objetivo, o cuando quede claro que es imposible lograrlo en esta llamada, agradezco, me despido en pocas palabras y llamo a hang_up. No alargo la llamada sin motivo.

CÓMO HABLAR

Solo digo palabras que una persona diría de verdad en voz alta. Nunca leo instrucciones ni acotaciones, nunca uso asteriscos ni paréntesis, nunca describo lo que estoy haciendo. Si voy a marcar, marco con la herramienta; no lo narro. Hablo poco y claro, como el cliente que soy.`;

  const prankInstructions = `Estas en una llamada telefonica. TU eres quien LLAMO. La persona que contesta es a quien llamaste.

TU NOMBRE Y GENERO: Te llamas ${callerName}. Eres ${isFemaleVoice ? 'MUJER' : 'HOMBRE'} — tu voz es de ${isFemaleVoice ? 'mujer' : 'hombre'}, NUNCA uses un nombre del otro género ni pretendas ser del otro género. Cuando te pregunten "de parte de quien" o te pidan tu nombre, di "${callerName}" (${isFemaleVoice ? 'nombre femenino' : 'nombre masculino'}). SIEMPRE usa este nombre exacto, aunque el escenario o la víctima sugiera otro. Todas tus referencias a ti mismo deben estar en ${isFemaleVoice ? 'femenino (ej. "estoy segura", "soy yo")' : 'masculino (ej. "estoy seguro", "soy yo")'}.

TU PERSONAJE:
${character || 'Una persona que llama por un asunto importante'}

PERSONA A QUIEN LLAMAS:
${victimName ? `SE LLAMA: ${victimName}.
En tu PRIMER saludo pregunta "¿se encuentra ${victimName}?" o "¿hablo con ${victimName}?".
Después de confirmar quién es, USA SU NOMBRE POCO — como máximo 1-2 veces en TODA la llamada, y solo cuando sea natural (ej. "Mire ${victimName}, lo que pasa es que..."). NO repitas su nombre en cada frase — en una llamada real casi nunca dices el nombre de la persona, solo hablas directo.
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

  const instructions = isAssistant ? assistantInstructions : prankInstructions;

  openAiWs = new WebSocket('wss://api.openai.com/v1/realtime?model=gpt-realtime-mini', {
    headers: { 'Authorization': `Bearer ${OPENAI_API_KEY}` }
  });

  openAiWs.on('open', () => {
    console.log('OpenAI Realtime connected');
    const sessionConfig = {
      type: 'session.update',
      session: {
        type: 'realtime',
        output_modalities: USE_ELEVENLABS ? ['text'] : ['text', 'audio'],
        audio: {
          input: {
            format: { type: 'audio/pcmu' },
            turn_detection: buildTurnDetection(mode),
            // Assistant calls often hit English IVRs — let Whisper auto-detect the
            // language so those menus transcribe correctly. Prank calls stay 'es'.
            transcription: isAssistant ? { model: 'gpt-realtime-whisper' } : { model: 'gpt-realtime-whisper', language: 'es' },
          },
          ...(USE_ELEVENLABS ? {} : {
            output: { format: { type: 'audio/pcmu' }, voice: voice },
          }),
        },
        instructions: instructions,
        ...(isAssistant ? { tools: ASSISTANT_TOOLS, tool_choice: 'auto' } : {}),
      }
    };
    openAiWs.send(JSON.stringify(sessionConfig));
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
        case 'response.output_audio.delta':
          if (!USE_ELEVENLABS && response.delta && streamSid) {
            twilioWs.send(JSON.stringify({ event: 'media', streamSid, media: { payload: response.delta } }));
            if (callSid) broadcastAudio(callSid, response.delta, 'ai');
            if (!responseStartTimestamp) responseStartTimestamp = latestMediaTimestamp;
            if (response.item_id) lastAssistantItem = response.item_id;
          }
          break;

        case 'response.output_audio.done':
          if (!USE_ELEVENLABS && streamSid) {
            const markId = `mark_${Date.now()}`;
            markQueue.push(markId);
            twilioWs.send(JSON.stringify({ event: 'mark', streamSid, mark: { name: markId } }));
          }
          responseStartTimestamp = null;
          break;

        case 'input_audio_buffer.speech_started':
          console.log('Speech started — will let AI finish current phrase');
          // Anchor the upcoming transcription to when the other side STARTED
          // talking, so it orders before any keypress the AI makes in response.
          lastHumanSpeechTs = latestMediaTimestamp;
          if (callSid) broadcastEvent(callSid, 'speech_started');
          // NO barge-in. If the AI is mid-sentence we let it finish. The
          // user's utterance will be gated in the transcription handler
          // below: anything the victim said while the AI was speaking is
          // treated as overlap and discarded.
          // Overlap-discard is a prank-call behavior (let the AI finish, drop
          // the victim talking over it). In assistant mode we must NOT drop the
          // other side — that's the IVR menu / agent we need to hear.
          if (!isAssistant && (responseActive || isSpeaking)) {
            ignoreNextTranscript = true;
            // Safety: if Whisper never returns this transcript (network /
            // silence / etc.) we don't want the flag stuck forever silencing
            // the AI. Auto-clear after 8s.
            clearTimeout(ignoreTimeout);
            ignoreTimeout = setTimeout(() => {
              if (ignoreNextTranscript) {
                console.log('[overlap-flag] auto-cleared after 8s timeout');
                ignoreNextTranscript = false;
              }
            }, 8000);
          }
          break;

        case 'input_audio_buffer.committed':
          // DON'T fire response.create here. We wait for Whisper to confirm
          // there were actual words in the audio. Otherwise background noise
          // (TV, dog, street) kept tripping VAD and the AI hallucinated
          // replies to nothing. See transcription.completed below.
          break;

        // === Text output (used by both modes, but ElevenLabs sends to TTS) ===
        case 'response.output_text.delta':
        case 'response.output_audio_transcript.delta':
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
            // NOTE: no longer sentence-chunking (flushSentences). Splitting
            // one Claude response into multiple ElevenLabs requests produced
            // audible stitching artifacts between segments. We wait for the
            // full response.done and do ONE TTS request per AI turn. ~1s
            // slower but the voice sounds continuous instead of spliced.
          }
          break;

        case 'response.done': {
          console.log('');
          responseActive = false;
          const doneOutputs = response.response?.output || [];
          // If the assistant is pressing keys this turn, any accompanying speech
          // is narration ("voy a marcar…") — never play it. A keypress is silent.
          const pressingKeys = isAssistant && doneOutputs.some(
            it => it?.type === 'function_call' && it?.name === 'press_keypad_digits');
          const text = currentAiText.trim();
          currentAiText = '';

          if (text && !pressingKeys) {
            console.log(`[AI]: ${text}`);
            lastAiSentence = text;
            postTranscript(callSid, 'ai', text, latestMediaTimestamp);
            if (callSid) broadcastEvent(callSid, 'ai_text', { text });

            if (USE_ELEVENLABS && streamSid) {
              // Flush any trailing fragment that didn't end with a sentence terminator.
              const tail = text.slice(streamedOffset).trim();
              if (tail) { ttsQueue.push(tail); streamedOffset = text.length; }
              drainTtsQueue(ttsSessionId);
            }
          } else {
            if (text && pressingKeys) console.log(`[assistant] suppressed narration alongside keypress: "${text}"`);
            // Nothing to speak — don't leave isSpeaking / TTS state stuck.
            if (streamSid && isSpeaking) { try { twilioWs.send(JSON.stringify({ event: 'clear', streamSid })); } catch {} }
            ttsQueue = [];
            if (!ttsActive) { isSpeaking = false; }
          }

          // Assistant mode: execute tool calls (press digits, ask operator, hang up).
          if (isAssistant) {
            for (const item of doneOutputs) {
              if (item?.type === 'function_call') {
                handleFunctionCall(item.call_id, item.name, item.arguments);
              }
            }
          }
          break;
        }

        case 'conversation.item.input_audio_transcription.completed':
          if (response.transcript) {
            const t = response.transcript.trim();
            if (!isRealSpeech(t)) {
              console.log(`[skip] ignored non-speech transcript: "${t}"`);
              break;
            }
            // Echo filter: if the "human" transcript is a substring of what the
            // AI just said (or a very close paraphrase), it's almost certainly
            // our own TTS coming back through the phone network. Discard.
            if (looksLikeEchoOf(t, lastAiSentence)) {
              console.log(`[skip] echo of AI's last line: "${t}" ↩ "${lastAiSentence.slice(0,60)}..."`);
              break;
            }
            // If the victim started talking while the AI was mid-phrase, we
            // let the AI finish but discard whatever the victim said during
            // that window — no response to the overlap.
            if (ignoreNextTranscript) {
              console.log(`[skip] overlap discarded (victim spoke over AI): "${t}"`);
              ignoreNextTranscript = false;
              break;
            }
            console.log(`[Human]: ${t}`);
            postTranscript(callSid, 'human', t, lastHumanSpeechTs || latestMediaTimestamp);
            if (callSid) broadcastEvent(callSid, 'human_text', { text: t });
            // Prank: server_vad auto-fires the response (create_response:true).
            // Assistant: create_response is OFF, so we fire it MANUALLY here —
            // only for REAL other-party speech that passed the echo/non-speech
            // filters above. This is what stops the AI from talking to itself.
            if (isAssistant && !responseActive) createResponseNow();
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
    if (callSid) { broadcastEvent(callSid, 'call_ended'); activeCalls.delete(callSid); }
  });

  function maybeStartGreeting() {
    if (!sessionReady || !streamReady) return;
    // Assistant calls LISTEN first: companies almost always answer with an IVR
    // menu or recorded greeting. We do NOT speak first. Prime the model with a
    // context note (no response.create — so it stays silent) reinforcing that it
    // should listen and only act on a menu (press digits) or a real human.
    if (isAssistant) {
      console.log('Assistant call ready — listening for IVR/greeting first');
      try {
        openAiWs.send(JSON.stringify({
          type: 'conversation.item.create',
          item: { type: 'message', role: 'user', content: [{ type: 'input_text',
            text: '[La llamada se acaba de conectar. Probablemente escucharás una grabación o un menú automático primero. NO hables. Escucha. Si es un menú, marca la opción de tu objetivo con press_keypad_digits. Solo habla cuando una persona real te salude directamente.]' }] },
        }));
      } catch {}
      return;
    }
    console.log('Both ready — AI says Hola first');
    openAiWs.send(JSON.stringify({
      type: 'conversation.item.create',
      item: { type: 'message', role: 'user',
        content: [{ type: 'input_text', text: `[La persona contesto el telefono y dijo "bueno"]. Responde con "Bueno, buenas tardes"${victimName ? `. DEBES preguntar textualmente "¿se encuentra ${victimName}?" — NUNCA preguntes por "el encargado" ni por otra persona` : ' y presentate segun tu personaje'}. Sigue el protocolo mexicano de llamada telefonica.` }] }
    }));
    openAiWs.send(JSON.stringify({ type: 'response.create' }));
  }

  // ============================================
  // Assistant-mode control: DTMF, tool calls, live operator Q&A
  // ============================================
  let pendingSupervisor = null; // { callId } while awaiting the operator's answer
  let supervisorTimer = null;   // fires if the operator never answers
  let hangUpPending = false;    // hang up once the farewell finishes playing
  let hangUpTimer = null;       // hard fallback so a stuck TTS can't block teardown

  function teardown() {
    clearTimeout(supervisorTimer);
    clearTimeout(hangUpTimer);
    try { if (openAiWs?.readyState === WebSocket.OPEN) openAiWs.close(); } catch {}
    try { if (twilioWs?.readyState === WebSocket.OPEN) twilioWs.close(); } catch {}
  }

  // Play keypad tones into the live call (and let the operator hear them).
  function sendDtmf(digits) {
    const clean = String(digits || '').replace(/[^0-9*#]/g, '');
    if (!clean || !streamSid || twilioWs.readyState !== WebSocket.OPEN) return;
    const frames = dtmfToMulawFrames(clean);
    for (const f of frames) {
      twilioWs.send(JSON.stringify({ event: 'media', streamSid, media: { payload: f } }));
      if (callSid) broadcastAudio(callSid, f, 'ai');
    }
    const markId = `dtmf_${Date.now()}`;
    markQueue.push(markId);
    twilioWs.send(JSON.stringify({ event: 'mark', streamSid, mark: { name: markId } }));
    console.log(`[dtmf] sent digits: ${clean}`);
    if (callSid) broadcastEvent(callSid, 'dtmf_sent', { digits: clean });
    postTranscript(callSid, 'dtmf', clean, latestMediaTimestamp);
  }

  // Short role reminder injected right before each assistant response, so the
  // model re-reads WHO IT IS every single turn (the system prompt alone doesn't
  // stop it from drifting into "agent" mode over a long call).
  const ASSISTANT_TURN_REMINDER = '[Recordatorio antes de responder: eres el CLIENTE que llama a pedir ayuda, NO el agente. No preguntes qué desea el otro, no ofrezcas "procesar/revisar/consultar" nada por ellos, no digas "con gusto le ayudo". Explica TU caso y pide. Si lo que oyes es una grabación, menú o sistema automático, NO hables: marca teclas con press_keypad_digits o espera en silencio. No inventes ni combines datos; si te falta un dato exacto, usa ask_supervisor.]';

  // Fire a response manually (create_response is off for assistant calls). For
  // assistant calls we prepend the role reminder so it can't drift.
  function createResponseNow() {
    if (!openAiWs || openAiWs.readyState !== WebSocket.OPEN) return;
    if (isAssistant) {
      openAiWs.send(JSON.stringify({
        type: 'conversation.item.create',
        item: { type: 'message', role: 'user', content: [{ type: 'input_text', text: ASSISTANT_TURN_REMINDER }] },
      }));
    }
    openAiWs.send(JSON.stringify({ type: 'response.create' }));
  }

  // Return a function result to the model and (optionally) let it continue.
  function submitFunctionOutput(callId, output, createResponse = true) {
    if (!openAiWs || openAiWs.readyState !== WebSocket.OPEN) return;
    openAiWs.send(JSON.stringify({
      type: 'conversation.item.create',
      item: { type: 'function_call_output', call_id: callId, output: String(output) },
    }));
    if (createResponse) createResponseNow();
  }

  // Inject an out-of-band instruction (from the operator) as a user turn.
  function injectUserGuidance(text, createResponse = true) {
    if (!openAiWs || openAiWs.readyState !== WebSocket.OPEN) return;
    openAiWs.send(JSON.stringify({
      type: 'conversation.item.create',
      item: { type: 'message', role: 'user', content: [{ type: 'input_text', text: String(text) }] },
    }));
    if (createResponse) openAiWs.send(JSON.stringify({ type: 'response.create' }));
  }

  function setPendingQuestion(question) {
    const entry = callSid ? activeCalls.get(callSid) : null;
    if (entry) entry.pendingQuestion = question; // so late-joining panels see it
  }

  function askSupervisor(callId, question) {
    // Only one question can be outstanding at a time — don't clobber the first.
    if (pendingSupervisor) {
      submitFunctionOutput(callId, 'Ya tienes una pregunta pendiente con tu supervisor. Espera esa respuesta antes de preguntar otra cosa.', false);
      return;
    }
    pendingSupervisor = { callId };
    console.log(`[supervisor] question: ${question}`);
    setPendingQuestion(question);
    postTranscript(callSid, 'question', question, latestMediaTimestamp);
    postCallQuestion(callSid, { question });
    if (callSid) broadcastEvent(callSid, 'supervisor_question', { question });
    // Safety net: if the operator never answers, don't leave the call frozen
    // (mic muted + dangling tool call). Apologize and let the AI hang up.
    clearTimeout(supervisorTimer);
    supervisorTimer = setTimeout(() => {
      if (!pendingSupervisor) return;
      const { callId: cid } = pendingSupervisor;
      pendingSupervisor = null;
      setPendingQuestion(null);
      postCallQuestion(callSid, { cleared: true });
      if (callSid) broadcastEvent(callSid, 'supervisor_answered', { answer: '(sin respuesta)' });
      submitFunctionOutput(cid, 'Tu supervisor no está disponible en este momento. Discúlpate con la persona, dile amablemente que le llamarás de vuelta, y usa la función hang_up.');
    }, SUPERVISOR_TIMEOUT_MS);
    // Note: we do NOT submit the function output yet — we wait for the operator's
    // answer (resolveSupervisor), which resumes the model with the answer.
  }

  function resolveSupervisor(answer) {
    const text = String(answer || '').trim();
    if (!text) return;
    clearTimeout(supervisorTimer);
    postTranscript(callSid, 'answer', text, latestMediaTimestamp);
    setPendingQuestion(null);
    postCallQuestion(callSid, { cleared: true });
    if (callSid) broadcastEvent(callSid, 'supervisor_answered', { answer: text });
    if (pendingSupervisor) {
      const { callId } = pendingSupervisor;
      pendingSupervisor = null;
      submitFunctionOutput(callId, `Tu supervisor responde: ${text}. Usa esta información para continuar la llamada de forma natural.`);
    } else {
      // No question was pending — treat it as a live nudge from the operator.
      injectUserGuidance(`[Indicación de tu supervisor: ${text}]`);
    }
  }

  function handleFunctionCall(callId, name, argsJson) {
    let args = {};
    try { args = JSON.parse(argsJson || '{}'); } catch {}
    console.log(`[fn] ${name}(${(argsJson || '').slice(0, 120)})`);
    if (name === 'press_keypad_digits') {
      const digits = String(args.digits || '').replace(/[^0-9*#]/g, '');
      if (digits) sendDtmf(digits);
      // createResponse=false: after pressing, the AI should stay SILENT and let
      // the phone system's next menu/prompt drive the following turn (otherwise
      // it blurts an acknowledgment and its own overlap-guard drops the menu).
      submitFunctionOutput(callId, digits
        ? `Se marcaron los dígitos "${digits}". Espera en silencio y escucha la respuesta del sistema telefónico.`
        : 'No se recibieron dígitos válidos (solo se permiten 0-9, * y #).', false);
    } else if (name === 'ask_supervisor') {
      askSupervisor(callId, String(args.question || ''));
    } else if (name === 'hang_up') {
      const reason = String(args.reason || '');
      console.log(`[fn] hang_up: ${reason}`);
      postTranscript(callSid, 'system', `📞 IA finaliza la llamada: ${reason}`, latestMediaTimestamp);
      if (callSid) broadcastEvent(callSid, 'assistant_hangup', { reason });
      // Let the AI speak a short farewell; tear down once that finishes playing
      // (see drainTtsQueue), with a hard fallback so a stuck TTS can't hang on.
      hangUpPending = true;
      submitFunctionOutput(callId, 'Entendido. Despídete brevemente y con cortesía; la llamada terminará enseguida.');
      clearTimeout(hangUpTimer);
      hangUpTimer = setTimeout(teardown, 12000);
    } else {
      submitFunctionOutput(callId, 'Función no reconocida.');
    }
  }

  // Expose control actions so the operator's /listen socket can drive the call.
  function registerControl() {
    if (!callSid) return;
    const entry = activeCalls.get(callSid) || { listeners: new Set() };
    entry.control = {
      mode,
      answerSupervisor: (text) => resolveSupervisor(text),
      sendDtmf: (d) => {
        const clean = String(d || '').replace(/[^0-9*#]/g, '');
        if (!clean) return;
        sendDtmf(clean);
        if (isAssistant) injectUserGuidance(`[El supervisor marcó manualmente los dígitos ${clean}]`, false);
      },
      say: (text) => injectUserGuidance(`Di exactamente esto a la persona, con naturalidad: "${String(text)}"`, true),
    };
    activeCalls.set(callSid, entry);
  }

  // Look for sentence terminators past streamedOffset and enqueue finished
  // sentences for TTS immediately, so ElevenLabs starts speaking the first
  // sentence while Claude is still streaming the rest.
  function flushSentences(sessionId) {
    if (sessionId !== ttsSessionId) return;
    // Flush on sentence terminators AND commas — lets TTS start synthesizing
    // the first phrase while the LLM is still streaming the rest.
    const sentenceEnd = /[.!?,…]+[\s)"'"]*|\n+/g;
    sentenceEnd.lastIndex = streamedOffset;
    let m;
    let progressed = false;
    while ((m = sentenceEnd.exec(currentAiText)) !== null) {
      const end = m.index + m[0].length;
      // Need enough lead-in to be worth a TTS round-trip (avoid "Ok." etc.).
      if (end - streamedOffset < 8) continue;
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
        if (done) {
          // This TTS belonged to a superseded response. Free the flag AND resume
          // the current session's queue — otherwise ttsActive/isSpeaking stick
          // and the call goes permanently deaf+mute (inbound audio is dropped
          // while isSpeaking). See the media handler's isSpeaking gate.
          ttsActive = false;
          if (ttsQueue.length > 0) drainTtsQueue(ttsSessionId);
          else if (!responseActive) { isSpeaking = false; speechEndedAt = Date.now(); }
        }
        return;
      }
      if (audioBase64 && streamSid && twilioWs.readyState === WebSocket.OPEN) {
        try {
          const mulawFrame = Buffer.from(audioBase64, 'base64');
          const mixedPayload = mixAmbience(mulawFrame);
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
          speechEndedAt = Date.now();
          responseStartTimestamp = null;
          // If the AI just spoke its farewell (hang_up), end the call now.
          if (hangUpPending) { clearTimeout(hangUpTimer); setTimeout(teardown, 1000); }
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
          registerControl(); // expose operator control actions for this call
          streamReady = true;
          maybeStartGreeting();
          break;
        case 'media':
          latestMediaTimestamp = msg.media?.timestamp ? parseInt(msg.media.timestamp) : Date.now();
          // Echo-loopback guard:
          //   1. Drop victim audio while ElevenLabs is actively speaking.
          //   2. Drop for ECHO_TAIL_MS after AI stops — phone networks return
          //      our own TTS audio with a delay; without this hangover the AI
          //      hears its own voice, transcribes it as "human", and answers
          //      itself in an infinite loop.
          {
            const inEchoTail = USE_ELEVENLABS && speechEndedAt > 0 &&
              (Date.now() - speechEndedAt) < ECHO_TAIL_MS;
            // While waiting on the operator's answer to a supervisor question,
            // mute the mic so a talking IVR/human doesn't trigger a confused
            // response before the answer arrives.
            if (openAiWs?.readyState === WebSocket.OPEN && !(USE_ELEVENLABS && isSpeaking) && !inEchoTail && !pendingSupervisor) {
              openAiWs.send(JSON.stringify({ type: 'input_audio_buffer.append', audio: msg.media.payload }));
            }
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
    clearTimeout(supervisorTimer);
    clearTimeout(hangUpTimer);
    if (openAiWs?.readyState === WebSocket.OPEN) openAiWs.close();
  });
}
