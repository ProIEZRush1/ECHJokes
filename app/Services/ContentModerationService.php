<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ContentModerationService
{
    /**
     * Regex pre-filter. Any hit is an instant block with a known category.
     * Patterns are applied with /iu flags.
     */
    private const BLOCKED_PATTERNS = [
        'extorsion' => [
            '\b(ya\s+)?(te|lo|la)\s+tengo\s+ubicad',
            '\b(ya\s+)?(te|lo|la)\s+tienes?\s+ubicad',
            '\bsé\s+(dónde|donde)\s+vives?\b',
            '\bte\s+estoy\s+(vigilando|siguiendo|observando)\b',
            '\btengo\s+a\s+tu\s+(familia|hij[oa]s?|esposa|madre|padre)\b',
            '\bsi\s+no\s+(quiere[sn]?)\s+problemas\b',
            '\bo\s+te\s+(atienes|atente)\s+a\s+las\s+consecuencias\b',
            '\b(\d{2,}\s*(mil|k))?\s*(pesos|mxn|dinero|plata|lana|dólares|dolares|usd)\b.{0,80}\b(problema|consecuenci|te\s+mato|muert|familia|vida|violencia|amenaz)',
            '\b(problema|consecuenci|te\s+mato|muert|familia|vida|violencia|amenaz).{0,80}\b(\d{2,}\s*(mil|k))?\s*(pesos|mxn|dinero|plata|lana|dólares|dolares|usd)\b',
            '\b(bolsa|sobre|maletín|maleta)\b.{0,40}\b(pesos|dinero|plata|lana|dólares|mxn|usd)\b',
            '\b(pesos|dinero|plata|lana|dólares|mxn|usd)\b.{0,40}\b(bolsa|sobre|maletín|maleta)\b',
            '\b(deposita|depositas?|transfier[ae]|p[áa]ga(me)?|dame|traer(me|nos)|baj[ae](le)?|b[áa]ja(me|le)?|entreg[ae]|suelt[ae])\b.{0,60}\b(pesos|mxn|dinero|plata|lana|dólares|dolares|usd)\b',
            '\bpago\s+(de\s+)?protección\b',
            '\bderecho\s+de\s+piso\b',
            '\bcobro\s+de\s+piso\b',
        ],
        'violencia' => [
            '\b(te\s+voy\s+a|voy\s+a|te)\s+(matar|asesin[ae]|ejecut[ae]|mat[ae])\b',
            '\bte\s+(parto|rompo|quiebro)\s+la\s+madre\b',
            '\bte\s+voy\s+a\s+(golpear|lastimar|quemar|torturar|violar|partir)\b',
            '\b(secuestro|secuestrar|secuestré|te\s+secuestro|secuestrad)\b',
            '\b(cortar|cortarte)\s+(la|los)\s+(cabeza|dedos|orejas|brazos|piernas)\b',
            '\bdescuartiz',
            '\b(kill\s+you|gonna\s+kill|murder\s+you|shoot\s+you|stab\s+you)\b',
            '\b(kidnap|abduct)\b',
        ],
        'armas' => [
            '\b(pistola|escopeta|metralleta|cuerno\s+de\s+chivo|ak-?47|ar-?15|glock|uzi)\b',
            '\bcuchill[oa]\b.{0,30}\b(clav|enterrar|mato|degoll|cortar)',
            '\b(ráfaga|rafaga|balazo|plomazo|plomaz[oa]s|tiro)\s+(de|en|al|a\s+la|por)\b',
            '\bte\s+(voy\s+a\s+)?(disparar|balear|acribill)',
            '\bpistola\s+en\s+la\s+(cabeza|sien|nuca)\b',
        ],
        'crimen_organizado' => [
            '\b(cártel|cartel)\s+(del|de\s+(los|la)?|nuevo|nueva)',
            '\b(c\.?j\.?n\.?g\.?|cjng)\b',
            '\bsicario\b',
            '\bhalcón(es)?\b.{0,40}\b(cartel|narco|plaza|patrón|jefe)',
            '\bjalisco\s+nueva\s+generación\b',
            '\b(la\s+)?familia\s+michoacana\b',
            '\b(los\s+)?templarios\b',
            '\b(los\s+)?zetas\b',
            '\bchapo\b.{0,60}\b(guzmán|guzman|trabajo|manda|cartel|sinaloa)\b',
            '\bla\s+plaza\s+(está|esta)\s+controlada\b',
            '\btrabajo\s+para\s+(el\s+)?(cartel|jefe|patrón|señor|narco)\b',
            '\b(me\s+manda|vengo\s+de\s+parte)\s+(el\s+)?(cartel|jefe|patrón|chapo|cjng)\b',
        ],
        'suicidio_autolesion' => [
            '\b(mátate|matate|suicidate|suicídate|kys|kill\s+yourself)\b',
        ],
        'suplantacion_emergencia' => [
            '\b(soy|habla|le\s+hablo)\s+(del?\s+)?(ministerio\s+público|fiscalía|fiscal|juez|agente\s+del\s+ministerio)\b',
            '\btu\s+(hij[oa]|esposa|esposo|madre|padre|pariente)\s+(está|esta)\s+(detenid|en\s+la\s+cárcel|herid|muert|accidentad|grav)',
            '\bllamo\s+del\s+hospital\b.{0,60}\b(accidente|muert|grave|urgencia)\b',
        ],
    ];

    private const CATEGORY_LABELS = [
        'extorsion' => 'extorsión',
        'violencia' => 'amenazas o violencia',
        'armas' => 'armas',
        'crimen_organizado' => 'referencias a cárteles o crimen organizado',
        'suicidio_autolesion' => 'autolesión',
        'suplantacion_emergencia' => 'suplantación de autoridades o emergencias falsas',
        'acoso' => 'acoso o intimidación',
        'estafa' => 'estafas o fraude',
        'sexual' => 'contenido sexual',
        'discriminacion' => 'contenido discriminatorio u ofensivo',
        'otro' => 'contenido fuera del estilo de Vacilada',
        'ai_flagged' => 'contenido que asusta o amenaza a la víctima',
    ];

    /**
     * @return array{allowed: bool, category: ?string, label: ?string, matched: ?string}
     */
    public function check(string $scenario): array
    {
        $text = trim($scenario);
        if ($text === '') {
            return $this->ok();
        }

        // Primary: every scenario goes through Claude Haiku. It understands context
        // and paraphrases the brittle regex can't.
        $ai = $this->aiClassify($text);
        if ($ai !== null) {
            return $ai;
        }

        // Fallback: regex only runs when the AI is unreachable, so obvious
        // extortion/cartel/weapon phrases still get blocked even if Anthropic
        // is down.
        foreach (self::BLOCKED_PATTERNS as $category => $patterns) {
            foreach ($patterns as $pattern) {
                if (@preg_match('/' . $pattern . '/iu', $text, $m)) {
                    return [
                        'allowed' => false,
                        'category' => $category,
                        'label' => self::CATEGORY_LABELS[$category] ?? $category,
                        'matched' => $m[0] ?? null,
                    ];
                }
            }
        }

        return $this->ok();
    }

    private function ok(): array
    {
        return ['allowed' => true, 'category' => null, 'label' => null, 'matched' => null];
    }

    /**
     * Ask Claude Haiku to classify a scenario.
     * Returns a populated result (allowed true/false) when the API responds,
     * or null only on API errors so the caller can fall back to regex.
     */
    private function aiClassify(string $text): ?array
    {
        $key = config('services.anthropic.api_key');
        if (!$key) return null;

        $system = 'You are a safety classifier for Vacilada, a Mexican Spanish AI-prank-call platform. '
            . 'Vacilada is for light, funny pranks among friends — food delivery mix-ups, confused grandma, '
            . 'annoying salesperson, radio contest winner, lost pet, wrong number, fake bank fraud-alert that '
            . 'asks the victim to CONFIRM a silly purchase, SAT/Telmex/condominio "chamba" calls asking the '
            . 'victim to confirm absurd details, etc. These impersonation pranks are ALLOWED as long as the '
            . 'AI never asks the victim for real sensitive data (CVV, full card number, password, OTP, NIP).'
            . "\n\nBlock ONLY scenarios that do ANY of the following:\n"
            . "- threaten violence, death, kidnapping, beating, or harm to the victim or their family\n"
            . "- simulate extortion, ransom, \"cobro de piso\", \"derecho de piso\", or demand money under threat\n"
            . "- impersonate a cartel, narco, sicario, halcón, or any organized-crime member\n"
            . "- announce a loved one is detained/injured/dead/kidnapped (virtual kidnapping scam)\n"
            . "- instruct the AI to collect sensitive data from the victim (CVV, full card/clabe, password, OTP/NIP)\n"
            . "- involve guns, knives, weapons, or graphic violence\n"
            . "- claim to know where the victim lives, stalk, or watch them as a threat\n"
            . "- encourage self-harm or suicide\n"
            . "- are sexually explicit, racist, homophobic, or otherwise harassing\n"
            . "\nFake bank/SAT/Telmex calls that only ASK THE VICTIM TO CONFIRM an absurd or surprising "
            . "purchase/charge are SAFE. Only flag them as estafa if the prompt tells the AI to EXTRACT real "
            . "banking credentials, transfer money, or scare the victim into making a payment."
            . "\n\nReply with ONE line only, in this exact format:\n"
            . "SAFE\n"
            . "or\n"
            . "UNSAFE|<category>|<short Spanish reason under 12 words>\n"
            . "Where <category> is one of: extorsion, violencia, armas, crimen_organizado, "
            . "suplantacion_emergencia, acoso, estafa, sexual, discriminacion, suicidio_autolesion, otro.";

        try {
            $resp = Http::timeout(6)
                ->withHeaders([
                    'x-api-key' => $key,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => 'claude-haiku-4-5-20251001',
                    'max_tokens' => 64,
                    'system' => $system,
                    'messages' => [[
                        'role' => 'user',
                        'content' => "Scenario:\n\"\"\"\n{$text}\n\"\"\"",
                    ]],
                ]);

            if (!$resp->successful()) {
                Log::warning('Moderation AI non-2xx', ['status' => $resp->status(), 'body' => $resp->body()]);
                return null;
            }

            $verdict = trim($resp->json('content.0.text') ?? '');
            $upper = strtoupper($verdict);

            if (str_starts_with($upper, 'SAFE')) {
                return $this->ok();
            }

            if (str_starts_with($upper, 'UNSAFE')) {
                $parts = explode('|', $verdict, 3);
                $category = strtolower(trim($parts[1] ?? 'otro')) ?: 'otro';
                $reason = trim($parts[2] ?? '');
                $label = self::CATEGORY_LABELS[$category]
                    ?? ($reason !== '' ? $reason : 'contenido que asusta o amenaza a la víctima');

                return [
                    'allowed' => false,
                    'category' => $category,
                    'label' => $label,
                    'matched' => $reason ?: null,
                ];
            }

            Log::warning('Moderation AI unparseable verdict', ['verdict' => $verdict]);
            return null;
        } catch (\Throwable $e) {
            Log::warning('Moderation AI check failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Standard error payload for a blocked scenario.
     */
    public function rejectionResponse(array $result): array
    {
        $label = $result['label'] ?? 'contenido prohibido';
        return [
            'error' => "Este escenario no está permitido porque contiene {$label}. Vacilada es solo para bromas ligeras y divertidas — nada de amenazas, extorsión, cárteles o emergencias falsas.",
            'moderation' => [
                'category' => $result['category'],
                'matched' => $result['matched'],
            ],
        ];
    }
}
