<?php

namespace App\Enums;

enum JokeCallStatus: string
{
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case GeneratingJoke = 'generating_joke';
    case GeneratingAudio = 'generating_audio';
    case QueuedForCall = 'queued_for_call';
    case Calling = 'calling';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Failed = 'failed';
    case Voicemail = 'voicemail';
    case Refunded = 'refunded';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Failed, self::Voicemail, self::Refunded]);
    }

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Esperando pago',
            self::Paid => 'Pago confirmado',
            self::GeneratingJoke => 'Creando guion',
            self::GeneratingAudio => 'Preparando voz',
            self::QueuedForCall => 'En cola',
            self::Calling => 'Llamando...',
            self::InProgress => 'En llamada',
            self::Completed => 'Completado',
            self::Failed => 'Fallido',
            self::Voicemail => 'Buzon de voz',
            self::Refunded => 'Reembolsado',
        };
    }
}
