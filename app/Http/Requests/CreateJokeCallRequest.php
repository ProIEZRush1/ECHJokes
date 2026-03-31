<?php

namespace App\Http\Requests;

use App\Models\JokeCall;
use App\Enums\JokeCallStatus;
use Illuminate\Foundation\Http\FormRequest;

class CreateJokeCallRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone_number' => [
                'required',
                'string',
                'regex:/^[1-9]\d{9}$/',
            ],
            'scenario' => [
                'required',
                'string',
                'min:10',
                'max:500',
            ],
            'delivery_type' => [
                'sometimes',
                'string',
                'in:call,whatsapp',
            ],
            'is_gift' => [
                'sometimes',
                'boolean',
            ],
            'recipient_phone' => [
                'required_if:is_gift,true',
                'nullable',
                'string',
                'regex:/^[1-9]\d{9}$/',
            ],
            'sender_name' => [
                'required_if:is_gift,true',
                'nullable',
                'string',
                'max:50',
            ],
            'gift_message' => [
                'nullable',
                'string',
                'max:100',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'phone_number.regex' => 'El numero debe ser de 10 digitos (sin +52).',
            'recipient_phone.regex' => 'El numero del destinatario debe ser de 10 digitos.',
            'scenario.required' => 'Describe la situacion para la broma.',
            'scenario.min' => 'La descripcion debe tener al menos 10 caracteres.',
            'scenario.max' => 'La descripcion no puede exceder 500 caracteres.',
            'sender_name.required_if' => 'Indica tu nombre para la dedicatoria.',
            'recipient_phone.required_if' => 'Indica el numero de tu amigo.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $phone = $this->e164PhoneNumber();

            $recentCalls = JokeCall::where('phone_number', $phone)
                ->where('created_at', '>=', now()->subDay())
                ->whereNotIn('status', [JokeCallStatus::Failed->value, JokeCallStatus::Refunded->value])
                ->count();

            if ($recentCalls >= 3) {
                $validator->errors()->add(
                    'phone_number',
                    'Este numero ya recibio 3 bromas hoy. Manana mas!'
                );
            }

            if ($this->is_gift && $this->recipient_phone) {
                $recipientPhone = '+52' . preg_replace('/\D/', '', $this->recipient_phone);
                $recentRecipient = JokeCall::where('recipient_phone', $recipientPhone)
                    ->where('created_at', '>=', now()->subDay())
                    ->whereNotIn('status', [JokeCallStatus::Failed->value, JokeCallStatus::Refunded->value])
                    ->count();

                if ($recentRecipient >= 3) {
                    $validator->errors()->add('recipient_phone', 'Este numero ya recibio 3 bromas hoy.');
                }
            }
        });
    }

    public function e164PhoneNumber(): string
    {
        return '+52' . preg_replace('/\D/', '', $this->phone_number);
    }

    public function e164RecipientPhone(): ?string
    {
        if (! $this->recipient_phone) return null;
        return '+52' . preg_replace('/\D/', '', $this->recipient_phone);
    }
}
