<?php

namespace Database\Seeders;

use App\Models\Preset;
use Illuminate\Database\Seeder;

class PresetSeeder extends Seeder
{
    public function run(): void
    {
        $presets = [
            [
                'label' => 'Lavadora ruidosa',
                'emoji' => '🧺',
                'scenario' => 'Que llamen de la administracion del condominio diciendo que la lavadora hace demasiado ruido y que los vecinos se estan quejando. Que si no le baja van a tener que cobrarle una multa de $2,500 pesos. Escalar diciendo que ya van 3 reportes esta semana.',
                'character' => 'Administrador del condominio, serio pero educado',
                'voice' => 'ash',
                'category' => 'vecinos',
                'sort_order' => 1,
            ],
            [
                'label' => 'Cargo sospechoso',
                'emoji' => '💳',
                'scenario' => 'Que llamen del banco diciendo que detectaron un cargo sospechoso de $47,000 pesos en una tienda de disfraces para mascotas y que necesitan confirmar si fue el. Luego decir que tambien hay otro cargo de $12,000 en una tienda de pelucas.',
                'character' => 'Ejecutivo bancario, profesional y preocupado',
                'voice' => 'coral',
                'category' => 'banco',
                'sort_order' => 2,
            ],
            [
                'label' => 'Concurso de belleza',
                'emoji' => '🐕',
                'scenario' => 'Que llamen de una veterinaria diciendo que su perro fue nominado para un concurso de belleza canina y necesitan confirmar su asistencia para la pasarela. Pedir medidas del perro para el vestuario y preguntar si tiene experiencia en modelaje.',
                'character' => 'Coordinadora del concurso, muy entusiasmada',
                'voice' => 'coral',
                'category' => 'mascotas',
                'sort_order' => 3,
            ],
            [
                'label' => 'Premio viaje',
                'emoji' => '🏆',
                'scenario' => 'Que llamen diciendo que gano un viaje todo incluido a Cancun pero que para reclamarlo necesita contestar 3 preguntas de cultura general. Las preguntas van escalando en dificultad y absurdo.',
                'character' => 'Conductor de radio, muy animado y energetico',
                'voice' => 'ash',
                'category' => 'premios',
                'sort_order' => 4,
            ],
            [
                'label' => 'Pedido de pizzas',
                'emoji' => '🍕',
                'scenario' => 'Que llamen de una pizzeria diciendo que su pedido de 15 pizzas esta listo para entregar y que el total son $3,500 pesos. Insistir en que ellos hicieron el pedido y que el repartidor ya esta en camino.',
                'character' => 'Gerente de pizzeria, confundido pero firme',
                'voice' => 'ash',
                'category' => 'comida',
                'sort_order' => 5,
            ],
            [
                'label' => 'Gimnasio VIP',
                'emoji' => '💪',
                'scenario' => 'Que llamen informandole que fue seleccionado para una membresia VIP gratuita en un gimnasio exclusivo. Empieza normal pero las condiciones van siendo cada vez mas absurdas: acceso solo de 3:40am a 5:10am, un coach que lo observa sin hablar, cardio en silencio absoluto.',
                'character' => 'Ejecutiva de membresias VIP, muy profesional y elegante',
                'voice' => 'coral',
                'category' => 'premios',
                'sort_order' => 6,
            ],
            [
                'label' => 'Vecino fiestero',
                'emoji' => '🎉',
                'scenario' => 'Que llamen como vecino quejandose de que anoche hubo una fiesta muy ruidosa en su departamento (aunque no hubo ninguna). Describir con detalle la musica, los gritos y hasta que alguien avento un pastel por la ventana.',
                'character' => 'Vecino enojado del piso de arriba',
                'voice' => 'ash',
                'category' => 'vecinos',
                'sort_order' => 7,
            ],
            [
                'label' => 'Servicio de maquillaje',
                'emoji' => '💄',
                'scenario' => 'Una mujer llama preguntando si la persona maquilla porque tiene un evento importante. La conversacion empieza normal pero poco a poco empieza a pedir cosas absurdas: maquillaje que aguante llorar, verse natural pero no natural, un look que haga reaccionar a su ex.',
                'character' => 'Clienta interesada en maquillaje, amable pero indecisa',
                'voice' => 'coral',
                'category' => 'servicios',
                'sort_order' => 8,
            ],
        ];

        foreach ($presets as $preset) {
            Preset::updateOrCreate(
                ['label' => $preset['label']],
                $preset
            );
        }
    }
}
