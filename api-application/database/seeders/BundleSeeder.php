<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BundleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        /**
         * Memanggil semua seeder yang biasa
         * dipanggil secara terurut sesuai
         * referensi atau ketergantungan
         * tertentu tanpa merusak data.
         * 
         */
        $this->call([
            JabatanSeeder::class,
            AdminSeeder::class,
            PosyanduSeeder::class,
                // FormatASeeder::class,
            BeratUmurSeeder::class,
            EdukasiSeeder::class,
            BeritaSeeder::class,
        ]);

    }
}
