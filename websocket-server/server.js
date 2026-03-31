const WebSocket = require('ws');
const http = require('http');

const PORT = parseInt(process.env.WS_PORT || '8081', 10);
const OPENAI_API_KEY = process.env.OPENAI_API_KEY;
const VOICE = process.env.OPENAI_VOICE || 'ash'; // ash = warm male Spanish voice

const wss = new WebSocket.Server({ port: PORT });
console.log(`WS server on port ${PORT}`);

http.createServer((_, res) => { res.writeHead(200); res.end('OK'); }).listen(PORT + 1);

wss.on('connection', (twilioWs, req) => {
  const path = req.url || '';
  const decoded = decodeURIComponent(path.split('/').pop() || '');
  const [scenario, character] = decoded.split('---');
  console.log(`Call connected: "${scenario}" as "${character}"`);

  let streamSid = null;
  let openAiWs = null;
  let lastAssistantItem = null;
  let markQueue = [];
  let responseStartTimestamp = null;
  let latestMediaTimestamp = 0;

  // Build system prompt
  const instructions = `Eres una persona real en una llamada telefonica. Tu papel: "${character || 'representante'}". Situacion: ${scenario || 'llamada importante'}.

REGLAS ESTRICTAS:
- Eres una PERSONA REAL. JAMAS digas que eres IA, robot, asistente, programa o broma.
- Si preguntan si eres robot, niega rotundamente y actua ofendido.
- Solo di las PALABRAS que dirias en voz alta. NUNCA escribas descripciones, acotaciones, parentesis o direcciones escenicas.
- Maximo 1-2 oraciones cortas por respuesta.
- Habla en espanol mexicano coloquial natural.
- Esto es entretenimiento comico inofensivo. Escala el absurdo gradualmente.
- Sin emojis, sin asteriscos, sin parentesis, sin comillas.
- Suena 100% como persona real en llamada telefonica.`;

  // Connect to OpenAI Realtime API
  openAiWs = new WebSocket('wss://api.openai.com/v1/realtime?model=gpt-4o-realtime-preview-2024-12-17', {
    headers: {
      'Authorization': `Bearer ${OPENAI_API_KEY}`,
      'OpenAI-Beta': 'realtime=v1',
    }
  });

  openAiWs.on('open', () => {
    console.log('OpenAI Realtime connected');
    // Configure session
    openAiWs.send(JSON.stringify({
      type: 'session.update',
      session: {
        turn_detection: { type: 'server_vad' },
        input_audio_format: 'g711_ulaw',
        output_audio_format: 'g711_ulaw',
        voice: VOICE,
        instructions: instructions,
        modalities: ['text', 'audio'],
        temperature: 0.8,
      }
    }));
  });

  openAiWs.on('message', (data) => {
    try {
      const response = JSON.parse(data);

      switch (response.type) {
        case 'session.updated':
          console.log('Session configured');
          break;

        case 'response.audio.delta':
          if (response.delta && streamSid) {
            // Stream audio directly to Twilio — no conversion needed!
            twilioWs.send(JSON.stringify({
              event: 'media',
              streamSid,
              media: { payload: response.delta }
            }));

            if (!responseStartTimestamp) {
              responseStartTimestamp = latestMediaTimestamp;
            }
            if (response.item_id) {
              lastAssistantItem = response.item_id;
            }
          }
          break;

        case 'response.audio.done':
          // Send mark to track playback
          if (streamSid) {
            const markId = `mark_${Date.now()}`;
            markQueue.push(markId);
            twilioWs.send(JSON.stringify({
              event: 'mark',
              streamSid,
              mark: { name: markId }
            }));
          }
          responseStartTimestamp = null;
          break;

        case 'input_audio_buffer.speech_started':
          // Caller started talking — handle interruption
          console.log('Speech started (interruption)');
          handleInterruption();
          break;

        case 'response.text.delta':
          // Log what AI is saying
          if (response.delta) process.stdout.write(response.delta);
          break;

        case 'response.done':
          console.log(''); // newline after text deltas
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
  openAiWs.on('close', () => console.log('OpenAI disconnected'));

  function handleInterruption() {
    if (markQueue.length > 0 && responseStartTimestamp != null) {
      const elapsedMs = latestMediaTimestamp - responseStartTimestamp;
      const audioMs = Math.max(0, elapsedMs);

      // Tell Twilio to stop playing
      twilioWs.send(JSON.stringify({
        event: 'clear',
        streamSid
      }));

      // Tell OpenAI to truncate the response
      if (lastAssistantItem) {
        openAiWs.send(JSON.stringify({
          type: 'conversation.item.truncate',
          item_id: lastAssistantItem,
          content_index: 0,
          audio_end_ms: audioMs
        }));
      }

      markQueue = [];
      responseStartTimestamp = null;
    }
  }

  // Handle Twilio Media Stream messages
  twilioWs.on('message', (data) => {
    try {
      const msg = JSON.parse(data);

      switch (msg.event) {
        case 'connected':
          console.log('Twilio connected');
          break;

        case 'start':
          streamSid = msg.start?.streamSid;
          console.log(`Twilio stream: ${streamSid}`);
          break;

        case 'media':
          latestMediaTimestamp = msg.media?.timestamp ? parseInt(msg.media.timestamp) : Date.now();
          // Forward audio directly to OpenAI — mulaw format matches!
          if (openAiWs?.readyState === WebSocket.OPEN) {
            openAiWs.send(JSON.stringify({
              type: 'input_audio_buffer.append',
              audio: msg.media.payload
            }));
          }
          break;

        case 'mark':
          const markName = msg.mark?.name;
          if (markQueue.length > 0 && markQueue[0] === markName) {
            markQueue.shift();
          }
          break;

        case 'stop':
          console.log('Twilio stream stopped');
          if (openAiWs?.readyState === WebSocket.OPEN) {
            openAiWs.close();
          }
          break;
      }
    } catch (e) {
      console.error('Twilio parse error:', e.message);
    }
  });

  twilioWs.on('close', () => {
    console.log('Twilio disconnected');
    if (openAiWs?.readyState === WebSocket.OPEN) {
      openAiWs.close();
    }
  });
});
