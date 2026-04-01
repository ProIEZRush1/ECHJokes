const WebSocket = require('ws');
const http = require('http');
const https = require('https');

const PORT = parseInt(process.env.WS_PORT || '8081', 10);
const OPENAI_API_KEY = process.env.OPENAI_API_KEY;
const ELEVENLABS_API_KEY = process.env.ELEVENLABS_API_KEY || '';
const ELEVENLABS_VOICE_ID = process.env.ELEVENLABS_VOICE_ID || 'iP95p4xoKVk53GoZ742B';
const DEFAULT_VOICE = process.env.OPENAI_VOICE || 'ash';
const APP_INTERNAL_URL = 'http://app:8000';

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

function postTranscript(callSid, role, text) {
  if (!callSid || !text) return;
  const data = JSON.stringify({ call_sid: callSid, role, text });
  const url = new URL(`${APP_INTERNAL_URL}/api/call-transcript`);
  const opts = { hostname: url.hostname, port: url.port || 80, path: url.pathname, method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength(data) } };
  const req = http.request(opts, () => {});
  req.on('error', () => {});
  req.write(data);
  req.end();
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
    voice_settings: { stability: 0.5, similarity_boost: 0.75 }
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
    const FRAME_SIZE = 160; // 160 bytes mulaw = 20ms at 8kHz

    res.on('data', (chunk) => {
      totalBytes += chunk.length;
      buffer = Buffer.concat([buffer, chunk]);

      // Send in Twilio-sized mulaw frames
      while (buffer.length >= FRAME_SIZE) {
        const frame = buffer.subarray(0, FRAME_SIZE);
        buffer = buffer.subarray(FRAME_SIZE);
        callback(frame.toString('base64'), false);
      }
    });
    res.on('end', () => {
      if (buffer.length > 0) {
        callback(buffer.toString('base64'), false);
      }
      console.log(`ElevenLabs TTS done: ${totalBytes} mulaw bytes`);
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
  console.log(`Call connected: scenario="${scenario}" character="${character}" voice=${voice} tts=${USE_ELEVENLABS ? 'ElevenLabs' : 'OpenAI'}`);

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

  const instructions = `Estas en una llamada telefonica. TU eres quien LLAMO. La persona que contesta es a quien llamaste.

TU PERSONAJE:
${character || 'Una persona que llama por un asunto importante'}

PERSONA A QUIEN LLAMAS:
${victimName ? `Se llama ${victimName}. Usa su nombre cuando le hables.` : 'No sabes su nombre, pregunta por "el encargado" o "la persona responsable".'}

SITUACION / CONTEXTO DE LA LLAMADA:
${scenario || 'Llamada importante que debes llevar a cabo'}

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

  openAiWs = new WebSocket('wss://api.openai.com/v1/realtime?model=gpt-4o-realtime-preview-2024-12-17', {
    headers: { 'Authorization': `Bearer ${OPENAI_API_KEY}`, 'OpenAI-Beta': 'realtime=v1' }
  });

  openAiWs.on('open', () => {
    console.log('OpenAI Realtime connected');
    openAiWs.send(JSON.stringify({
      type: 'session.update',
      session: {
        turn_detection: { type: 'server_vad', threshold: 0.5, silence_duration_ms: 500 },
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
          console.log('Speech started (interruption)');
          handleInterruption();
          if (callSid) broadcastEvent(callSid, 'speech_started');
          break;

        // === Text output (used by both modes, but ElevenLabs sends to TTS) ===
        case 'response.text.delta':
        case 'response.audio_transcript.delta':
          if (response.delta) {
            process.stdout.write(response.delta);
            currentAiText += response.delta;
          }
          break;

        case 'response.done':
          console.log('');
          if (currentAiText.trim()) {
            const text = currentAiText.trim();
            console.log(`[AI]: ${text}`);
            postTranscript(callSid, 'ai', text);
            if (callSid) broadcastEvent(callSid, 'ai_text', { text });

            // === ELEVENLABS TTS: convert text to speech ===
            if (USE_ELEVENLABS && streamSid) {
              isSpeaking = true;
              responseStartTimestamp = latestMediaTimestamp;
              let framesSent = 0;
              elevenLabsTTS(text, ELEVENLABS_VOICE_ID, (audioBase64, done) => {
                if (audioBase64 && streamSid && twilioWs.readyState === WebSocket.OPEN) {
                  try {
                    twilioWs.send(JSON.stringify({ event: 'media', streamSid, media: { payload: audioBase64 } }));
                    framesSent++;
                    if (callSid) broadcastAudio(callSid, audioBase64, 'ai');
                  } catch(e) { console.error('Twilio send error:', e.message); }
                }
                if (done) {
                  console.log(`ElevenLabs: sent ${framesSent} frames to Twilio`);
                  isSpeaking = false;
                  if (streamSid) {
                    const markId = `mark_${Date.now()}`;
                    markQueue.push(markId);
                    twilioWs.send(JSON.stringify({ event: 'mark', streamSid, mark: { name: markId } }));
                  }
                  responseStartTimestamp = null;
                }
              });
            }
          }
          currentAiText = '';
          break;

        case 'conversation.item.input_audio_transcription.completed':
          if (response.transcript) {
            const t = response.transcript.trim();
            const hallucinations = ['thank you', 'thanks for watching', 'thank you for listening', 'thanks for listening', 'you', 'the end', 'subtitles by', 'amara.org'];
            if (t && !hallucinations.includes(t.toLowerCase())) {
              console.log(`[Human]: ${t}`);
              postTranscript(callSid, 'human', t);
              if (callSid) broadcastEvent(callSid, 'human_text', { text: t });
            }
          }
          break;

        case 'error':
          console.error('OpenAI error:', response.error?.message || JSON.stringify(response));
          break;
      }
    } catch (e) {
      console.error('Parse error:', e.message);
    }
  });

  openAiWs.on('error', (e) => console.error('OpenAI WS error:', e.message));
  openAiWs.on('close', () => {
    console.log('OpenAI disconnected');
    if (callSid) { broadcastEvent(callSid, 'call_ended'); activeCalls.delete(callSid); }
  });

  function maybeStartGreeting() {
    if (!sessionReady || !streamReady) return;
    console.log('Both ready — AI says Hola first');
    openAiWs.send(JSON.stringify({
      type: 'conversation.item.create',
      item: { type: 'message', role: 'user',
        content: [{ type: 'input_text', text: `[La persona contesto el telefono y dijo "bueno"]. Responde con "Bueno, buenas tardes"${victimName ? ` y pregunta "se encuentra ${victimName}?"` : ' y presentate segun tu personaje'}. Sigue el protocolo mexicano de llamada telefonica.` }] }
    }));
    openAiWs.send(JSON.stringify({ type: 'response.create' }));
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
    if (openAiWs?.readyState === WebSocket.OPEN) openAiWs.close();
  });
}
