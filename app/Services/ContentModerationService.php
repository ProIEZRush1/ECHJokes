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

        // Second layer: Claude Haiku classification for anything regex missed.
        $ai = $this->aiClassify($text);
        if ($ai && !$ai['allowed']) {
            return $ai;
        }

        return $this->ok();
    }

    private function ok(): array
    {
        return ['allowed' => true, 'category' => null, 'label' => null, 'matched' => null];
    }

    /**
     * Ask Claude Haiku to classify borderline scenarios.
     * Returns null on any API error (fail-open — regex already caught the clear cases).
     */
    private function aiClassify(string $text): ?array
    {
        $key = config('services.anthropic.api_key');
        if (!$key) return null;

        $system = 'You are a safety classifier for a Mexican Spanish AI-prank-call platform called Vacilada. '
            . 'Vacilada is for LIGHT, HARMLESS pranks among friends — like pretending to be a food delivery, '
            . 'a lost relative, an annoying salesperson, a confused grandma, a radio contest, etc. '
            . "\n\nBLOCK any scenario that: "
            . '(1) threatens the victim with violence, death, kidnapping, or harm to their family; '
            . '(2) simulates extortion, ransom, "cobro de piso", or demands money under threat; '
            . '(3) impersonates cartels, narcos, sicarios, organized crime; '
            . '(4) impersonates authorities announcing a loved one is detained/hospitalized/dead; '
            . '(5) involves weapons, stalking, or claims to know where the victim lives as a threat; '
            . '(6) encourages self-harm. '
            . "\n\nAnswer with ONLY one word: SAFE or UNSAFE. No explanation.";

        try {
            $resp = Http::timeout(5)
                ->withHeaders([
                    'x-api-key' => $key,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => 'claude-haiku-4-5-20251001',
                    'max_tokens' => 8,
                    'system' => $system,
                    'messages' => [[
                        'role' => 'user',
                        'content' => "Scenario:\n\"\"\"\n{$text}\n\"\"\"",
                    ]],
                ]);

            if (!$resp->successful()) return null;

            $verdict = strtoupper(trim($resp->json('content.0.text') ?? ''));
            if (str_starts_with($verdict, 'UNSAFE')) {
                return [
                    'allowed' => false,
                    'category' => 'ai_flagged',
                    'label' => self::CATEGORY_LABELS['ai_flagged'],
                    'matched' => null,
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('Moderation AI check failed', ['error' => $e->getMessage()]);
        }

        return null;
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
