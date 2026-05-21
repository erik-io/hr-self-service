<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AbsenceType;
use Illuminate\Database\Seeder;

class AbsenceTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            ['name' => 'Vacation', 'deducts_vacation_days' => true, 'requires_approval' => true],
            ['name' => 'Sick Leave', 'deducts_vacation_days' => false, 'requires_approval' => false],
            ['name' => 'Unpaid Leave', 'deducts_vacation_days' => false, 'requires_approval' => true],
            ['name' => 'Parental Leave', 'deducts_vacation_days' => false, 'requires_approval' => false],
        ];

        foreach ($types as $type) {
            AbsenceType::firstOrCreate(
                ['name' => $type['name']],
                [
                    'deducts_vacation_days' => $type['deducts_vacation_days'],
                    'requires_approval' => $type['requires_approval'],
                ],
            );
        }
    }
}
