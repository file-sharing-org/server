<?php

namespace Database\Seeders;

use App\Models\Group;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class GroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $everyone = new Group();
        $everyone->name = 'everyone';
        $everyone->creator = 'seeder';
        $everyone->save();

        $admins = new Group();
        $admins->name = 'admins';
        $admins->creator = 'seeder';
        $admins->save();
    }
}
