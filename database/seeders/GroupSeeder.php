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
        $group1 = new Group();
        $group1->name = 'group1';
        $group1->save();

        $group2 = new Group();
        $group2->name = 'group2';
        $group2->save();

        $group3 = new Group();
        $group3->name = 'group3';
        $group3->save();
    }
}
