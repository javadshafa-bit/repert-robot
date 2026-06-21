<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProvinceSeeder extends Seeder {
    public function run(): void {
        $provinces = [
            'آذربایجان شرقی','آذربایجان غربی','اردبیل','اصفهان','البرز',
            'ایلام','بوشهر','تهران','چهارمحال و بختیاری','خراسان جنوبی',
            'خراسان رضوی','خراسان شمالی','خوزستان','زنجان','سمنان',
            'سیستان و بلوچستان','فارس','قزوین','قم','کردستان',
            'کرمان','کرمانشاه','کهگیلویه و بویراحمد','گلستان','گیلان',
            'لرستان','مازندران','مرکزی','هرمزگان','همدان','یزد',
        ];
        foreach ($provinces as $name) {
            DB::table('provinces')->insert([
                'name'       => $name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}