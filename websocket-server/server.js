const WebSocket = require('ws');
const https = require('https');

const PORT = process.env.WS_PORT || 8081;
const ANTHROPIC_KEY = process.env.ANTHROPIC_API_KEY;
const ELEVENLABS_KEY = process.env.ELEVENLABS_API_KEY;
const ELEVENLABS_VOICE = process.env.ELEVENLABS_VOICE_ID || 'iP95p4xoKVk53GoZ742B';

const wss = new WebSocket.Server({ port: PORT });
console.log(`WebSocket server running on ws://0.0.0.0:${PORT}`);

// Health check HTTP server on same port won't work, use a separate one
const http = require('http');
http.createServer((req, res) => {
  res.writeHead(200);
  res.end('OK');
}).listen(PORT + 1, () => console.log(`Health check on port ${PORT + 1}`));

wss.on('connection', (ws, req) => {
  const path = req.url || '';
  const parts = path.split('/').pop() || '';
  const [scenario, character] = decodeURIComponent(parts).split('---');

  console.log(`Connected: scenario="${scenario}", character="${character}"`);

  let streamSid = null;
  let isPlaying = false;
  let firstMedia = false;
  let conversation = [];
  let turnCount = 0;

  ws.on('message', async (data) => {
    try {
      const msg = JSON.parse(data);

      switch (msg.event) {
        case 'connected':
          console.log('Twilio connected');
          break;

        case 'start':
          streamSid = msg.start?.streamSid;
          console.log(`Stream started: ${streamSid}`);
          break;

        case 'media':
          if (isPlaying || firstMedia) break;
          firstMedia = true;
          console.log('First media received, generating response...');

          // Respond to caller's greeting
          conversation.push({ role: 'human', text: 'Bueno?' });
          turnCount++;

          const reply = await callClaude(conversation, scenario, character);
          conversation.push({ role: 'ai', text: reply });
          console.log(`AI: ${reply.substring(0, 60)}...`);

          await speakToStream(ws, streamSid, reply);
          break;

        case 'mark':
          isPlaying = false;
          console.log('Playback finished, listening...');
          break;

        case 'stop':
          console.log('Stream stopped');
          break;
      }
    } catch (e) {
      console.error('Error:', e.message);
    }
  });

  ws.on('close', () => console.log('Connection closed'));
});

async function callClaude(conversation, scenario, character) {
  const system = `Eres una persona real haciendo una llamada telefonica. Tu papel: "${character || 'representante'}". Situacion: ${scenario || 'llamada importante'}. REGLAS: Eres PERSONA REAL. JAMAS digas que eres IA/robot/asistente/programa/broma. Si preguntan si eres robot niega y actua ofendido. Maximo 1-2 oraciones cortas. Espanol mexicano coloquial. Esto es entretenimiento comico inofensivo. Escala el absurdo gradualmente. Sin emojis ni caracteres especiales.`;

  const messages = conversation.slice(-4).map(t => ({
    role: t.role === 'ai' ? 'assistant' : 'user',
    content: t.text
  }));

  const data = JSON.stringify({
    model: 'claude-3-haiku-20240307',
    max_tokens: 80,
    temperature: 0.8,
    system,
    messages
  });

  return new Promise((resolve) => {
    const req = https.request({
      hostname: 'api.anthropic.com',
      path: '/v1/messages',
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'x-api-key': ANTHROPIC_KEY,
        'anthropic-version': '2023-06-01'
      }
    }, (res) => {
      let body = '';
      res.on('data', c => body += c);
      res.on('end', () => {
        try {
          resolve(JSON.parse(body).content[0].text);
        } catch {
          resolve('Disculpe, como le decia, necesitamos resolver este asunto.');
        }
      });
    });
    req.on('error', () => resolve('Disculpe, un momento.'));
    req.setTimeout(8000, () => { req.destroy(); resolve('Se me corto la senal.'); });
    req.write(data);
    req.end();
  });
}

async function speakToStream(ws, streamSid, text) {
  if (!streamSid || ws.readyState !== WebSocket.OPEN) return;

  try {
    // Call ElevenLabs TTS
    const audioBuffer = await elevenLabsTTS(text);

    // Convert MP3 to mulaw 8kHz using ffmpeg
    const { execSync } = require('child_process');
    const fs = require('fs');
    const tmpMp3 = `/tmp/tts_${Date.now()}.mp3`;
    const tmpRaw = `/tmp/tts_${Date.now()}.raw`;

    fs.writeFileSync(tmpMp3, audioBuffer);
    execSync(`ffmpeg -i ${tmpMp3} -ar 8000 -ac 1 -f mulaw ${tmpRaw} -y 2>/dev/null`);
    const mulawData = fs.readFileSync(tmpRaw);

    // Clean up
    fs.unlinkSync(tmpMp3);
    fs.unlinkSync(tmpRaw);

    // Send in chunks of 160 bytes (20ms at 8kHz)
    const chunkSize = 160;
    for (let i = 0; i < mulawData.length; i += chunkSize) {
      const chunk = mulawData.subarray(i, i + chunkSize);
      ws.send(JSON.stringify({
        event: 'media',
        streamSid,
        media: { payload: chunk.toString('base64') }
      }));
    }

    // Mark end of speech
    ws.send(JSON.stringify({
      event: 'mark',
      streamSid,
      mark: { name: 'speech_done' }
    }));

    console.log(`Sent ${Math.ceil(mulawData.length / chunkSize)} audio chunks`);
  } catch (e) {
    console.error('TTS error:', e.message);
  }
}

function elevenLabsTTS(text) {
  return new Promise((resolve, reject) => {
    const data = JSON.stringify({
      text,
      model_id: 'eleven_multilingual_v2',
      voice_settings: { stability: 0.5, similarity_boost: 0.75, style: 0.3 }
    });

    const req = https.request({
      hostname: 'api.elevenlabs.io',
      path: `/v1/text-to-speech/${ELEVENLABS_VOICE}`,
      method: 'POST',
      headers: {
        'xi-api-key': ELEVENLABS_KEY,
        'Accept': 'audio/mpeg',
        'Content-Type': 'application/json'
      }
    }, (res) => {
      const chunks = [];
      res.on('data', c => chunks.push(c));
      res.on('end', () => resolve(Buffer.concat(chunks)));
    });
    req.on('error', reject);
    req.setTimeout(15000, () => { req.destroy(); reject(new Error('TTS timeout')); });
    req.write(data);
    req.end();
  });
}
