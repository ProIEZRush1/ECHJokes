<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        // Cost per call: ~$14 MXN (2 min avg) + 30% margin = ~$18 MXN/call
        // Bulk discounts applied for higher tiers

        Plan::updateOrCreate(['slug' => 'single'], [
            'name' => 'Bromita',
            'description' => 'Una broma perfecta para probar',
            'price_mxn' => 29.00,
            'calls_included' => 1,
            'max_duration_minutes' => 3,
            'features' => [
                '1 llamada de broma',
                'Hasta 3 minutos',
                'Voz IA realista',
                'Grabacion incluida',
                'Escenario personalizado',
            ],
            'is_popular' => false,
            'is_active' => true,
            'sort_order' => 1,
            'stripe_price_id' => 'price_1THCi4IPT6obHC3cizff20qa',
        ]);

        Plan::updateOrCreate(['slug' => 'pack-5'], [
            'name' => 'Bromista',
            'description' => 'El pack ideal para divertirte con tus amigos',
            'price_mxn' => 99.00,
            'calls_included' => 5,
            'max_duration_minutes' => 5,
            'features' => [
                '5 llamadas de broma',
                'Hasta 5 minutos cada una',
                'Voz IA realista (hombre o mujer)',
                'Grabaciones incluidas',
                'Escenarios personalizados',
                'Prioridad en cola',
            ],
            'is_popular' => true,
            'is_active' => true,
            'sort_order' => 2,
            'stripe_price_id' => 'price_1THCi5IPT6obHC3c2D3GHcoP',
        ]);

        Plan::updateOrCreate(['slug' => 'pack-15'], [
            'name' => 'Comediante',
            'description' => 'Para los que no paran de bromear',
            'price_mxn' => 249.00,
            'calls_included' => 15,
            'max_duration_minutes' => 5,
            'features' => [
                '15 llamadas de broma',
                'Hasta 5 minutos cada una',
                'Voz IA realista (hombre o mujer)',
                'Grabaciones incluidas',
                'Escenarios personalizados',
                'Prioridad en cola',
                'Soporte prioritario',
            ],
            'is_popular' => false,
            'is_active' => true,
            'sort_order' => 3,
            'stripe_price_id' => 'price_1THCi6IPT6obHC3c9xdVjOZB',
        ]);
    }
}
