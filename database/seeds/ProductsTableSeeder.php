<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ProductsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('products')->insert([
            'name' => 'Magical iPhone Case',
            'price' => 20.99,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);

        DB::table('products')->insert([
            'name' => 'Invisible Drone',
            'price' => 120,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);

        DB::table('products')->insert([
            'name' => 'Floating Keyboard',
            'price' => 70.49,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);
    }
}
