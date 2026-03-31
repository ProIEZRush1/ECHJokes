const WebSocket = require('ws');
const https = require('https');
const { execSync } = require('child_process');
const fs = require('fs');

const PORT = parseInt(process.env.WS_PORT || '8081', 10);
const ANTHROPIC_KEY = process.env.ANTHROPIC_API_KEY;
const ELEVENLABS_KEY = process.env.ELEVENLABS_API_KEY;
const ELEVENLABS_VOICE = process.env.ELEVENLABS_VOICE_ID || 'iP95p4xoKVk53GoZ742B';

const wss = new WebSocket.Server({ port: PORT });
console.log(`WebSocket server on ws://0.0.0.0:${PORT}`);

const http = require('http');
http.createServer((_, res) => { res.writeHead(200); res.end('OK'); }).listen(PORT + 1);

wss.on('connection', (ws, req) => {
  const path = req.url || '';
  const decoded = decodeURIComponent(path.split('/').pop() || '');
  const [scenario, character] = decoded.split('---');
  console.log(`Connected: "${scenario}" as "${character}"`);

  let streamSid = null;
  let isPlaying = false;
  let listening = false;
  let conversation = [];
  let turnCount = 0;
  let mediaCount = 0;
  let silenceTimer = null;
  let lastMediaTime = 0;

  // After AI speaks, start listening for caller's response
  function startListening() {
    listening = true;
    mediaCount = 0;
    lastMediaTime = Date.now();
    console.log('Listening for speech...');

    // Safety timeout: if no response in 15s, generate one anyway
    silenceTimer = setTimeout(() => {
      if (listening && !isPlaying) {
        console.log('Silence timeout, responding...');
        handleSpeech('(silencio)');
      }
    }, 15000);
  }

  async function handleSpeech(text) {
    if (isPlaying) return;
    listening = false;
    if (silenceTimer) { clearTimeout(silenceTimer); silenceTimer = null; }

    conversation.push({ role: 'human', text });
    turnCount++;
    console.log(`Turn ${turnCount}: human="${text}"`);

    if (turnCount > 8) {
      await speakToStream(ws, streamSid, 'Bueno, le agradezco mucho su tiempo. Que tenga muy buen dia.');
      setTimeout(() => ws.close(), 5000);
      return;
    }

    const reply = await callClaude(conversation, scenario, character);
    conversation.push({ role: 'ai', text: reply });
    console.log(`AI: ${reply.substring(0, 80)}`);

    isPlaying = true;
    await speakToStream(ws, streamSid, reply);
  }

  ws.on('message', async (data) => {
    try {
      const msg = JSON.parse(data);

      switch (msg.event) {
        case 'connected':
          console.log('Twilio connected');
          break;

        case 'start':
          streamSid = msg.start?.streamSid;
          console.log(`Stream: ${streamSid}`);
          // Wait for caller to say "bueno?"
          listening = true;
          mediaCount = 0;
          // After 1.5s of audio, respond (they've said their greeting)
          silenceTimer = setTimeout(() => {
            if (listening && !isPlaying) {
              handleSpeech('Bueno?');
            }
          }, 1500);
          break;

        case 'media':
          mediaCount++;
          lastMediaTime = Date.now();

          if (listening && !isPlaying && mediaCount > 50) {
            // We've received ~1 second of audio after AI finished
            // Set a short timer - if no more audio for 1.5s, assume they stopped talking
            if (silenceTimer) clearTimeout(silenceTimer);
            silenceTimer = setTimeout(() => {
              if (listening && !isPlaying) {
                // Generate a contextual response based on conversation so far
                const responses = [
                  'Ah si?', 'Mmhmm', 'Ya veo', 'Entiendo',
                  'Ah bueno', 'Claro', 'Si si', 'Pues mire'
                ];
                const fakeTranscript = turnCount === 0 ? 'Bueno? Quien habla?' : responses[turnCount % responses.length];
                handleSpeech(fakeTranscript);
              }
            }, 2000);
          }
          break;

        case 'mark':
          isPlaying = false;
          mediaCount = 0;
          console.log('Playback done, starting to listen...');
          startListening();
          break;

        case 'stop':
          if (silenceTimer) clearTimeout(silenceTimer);
          console.log('Stream stopped');
          break;
      }
    } catch (e) {
      console.error('Error:', e.message);
    }
  });

  ws.on('close', () => {
    if (silenceTimer) clearTimeout(silenceTimer);
    console.log('Disconnected');
  });
});

async function callClaude(conversation, scenario, character) {
  const system = `Eres una persona real en una llamada telefonica. Tu papel: "${character || 'representante'}". Situacion: ${scenario || 'llamada'}. REGLAS ESTRICTAS: 1) Solo escribe las PALABRAS que dirias en voz alta. NUNCA escribas descripciones, acotaciones, parentesis, ni direcciones escénicas como "contesta con voz irritada" o "(suspira)". 2) Eres PERSONA REAL, JAMAS menciones IA/robot/asistente/programa. 3) Maximo 1-2 oraciones MUY cortas. 4) Espanol mexicano coloquial natural. 5) Entretenimiento comico inofensivo, escala el absurdo poco a poco. 6) Sin emojis, sin asteriscos, sin parentesis, sin comillas.`;

  const messages = conversation.slice(-4).map(t => ({
    role: t.role === 'ai' ? 'assistant' : 'user',
    content: t.text
  }));

  return new Promise((resolve) => {
    const req = https.request({
      hostname: 'api.anthropic.com', path: '/v1/messages', method: 'POST',
      headers: { 'Content-Type': 'application/json', 'x-api-key': ANTHROPIC_KEY, 'anthropic-version': '2023-06-01' }
    }, (res) => {
      let body = '';
      res.on('data', c => body += c);
      res.on('end', () => {
        try {
          let text = JSON.parse(body).content[0].text;
          // Strip stage directions like *text*, (text), [text]
          text = text.replace(/\*[^*]+\*/g, '').replace(/\([^)]+\)/g, '').replace(/\[[^\]]+\]/g, '').trim();
          if (!text) text = 'Disculpe, como le decia.';
          resolve(text);
        }
        catch { resolve('Disculpe, como le decia, necesitamos resolver este asunto.'); }
      });
    });
    req.on('error', () => resolve('Disculpe, un momento.'));
    req.setTimeout(8000, () => { req.destroy(); resolve('Se me corto la senal.'); });
    req.write(JSON.stringify({ model: 'claude-3-haiku-20240307', max_tokens: 80, temperature: 0.8, system, messages }));
    req.end();
  });
}

async function speakToStream(ws, streamSid, text) {
  if (!streamSid || ws.readyState !== WebSocket.OPEN) return;
  try {
    const audio = await elevenLabsTTS(text);
    const tmpMp3 = `/tmp/tts_${Date.now()}.mp3`;
    const tmpRaw = `/tmp/tts_${Date.now()}.raw`;
    fs.writeFileSync(tmpMp3, audio);
    execSync(`ffmpeg -i ${tmpMp3} -ar 8000 -ac 1 -f mulaw ${tmpRaw} -y 2>/dev/null`);
    const mulaw = fs.readFileSync(tmpRaw);
    fs.unlinkSync(tmpMp3); fs.unlinkSync(tmpRaw);

    for (let i = 0; i < mulaw.length; i += 160) {
      ws.send(JSON.stringify({ event: 'media', streamSid, media: { payload: mulaw.subarray(i, i + 160).toString('base64') } }));
    }
    ws.send(JSON.stringify({ event: 'mark', streamSid, mark: { name: 'done' } }));
    console.log(`Spoke: ${Math.ceil(mulaw.length / 160)} chunks`);
  } catch (e) {
    console.error('TTS error:', e.message);
  }
}

function elevenLabsTTS(text) {
  return new Promise((resolve, reject) => {
    const req = https.request({
      hostname: 'api.elevenlabs.io', path: `/v1/text-to-speech/${ELEVENLABS_VOICE}`, method: 'POST',
      headers: { 'xi-api-key': ELEVENLABS_KEY, 'Accept': 'audio/mpeg', 'Content-Type': 'application/json' }
    }, (res) => {
      const chunks = [];
      res.on('data', c => chunks.push(c));
      res.on('end', () => resolve(Buffer.concat(chunks)));
    });
    req.on('error', reject);
    req.setTimeout(15000, () => { req.destroy(); reject(new Error('timeout')); });
    req.write(JSON.stringify({ text, model_id: 'eleven_multilingual_v2', voice_settings: { stability: 0.5, similarity_boost: 0.75, style: 0.3 } }));
    req.end();
  });
}
