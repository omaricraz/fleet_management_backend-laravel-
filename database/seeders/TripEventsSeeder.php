<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TripEventsSeeder extends Seeder
{
    public function run(): void
    {
        $events = [];

        $eventTypes = [
            'active',
            'closed',
            'sale',
        ];

        for ($i = 1; $i <= 300; $i++) {

            $quantity = rand(1, 100);

            $amount = rand(100, 5000);

            $eventType = $eventTypes[array_rand($eventTypes)];

            $events[] = [

                'trip_id' => rand(1004, 1100),

                'user_id' => rand(1, 10),

                'tenant_id' => 1,

                'quantity' => number_format($quantity, 2, '.', ''),

                'amount' => number_format($amount, 2, '.', ''),

                'product_id' => rand(4, 13),

                'metadata' => json_encode([
                    'source' => 'seeder',
                    'reference' => 'EVT-' . rand(1000, 9999),
                    'notes' => 'Generated test event',
                ]),

                'description' => 'Seeder generated trip event record',

                'created_at' => now()->subDays(rand(0, 30)),

                'event_type' => $eventType,
            ];
        }

        DB::table('trip_events')->insert($events);
    }
}
