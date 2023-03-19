<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $everyone = Group::where('name', 'everyone')->first();
        $admins = Group::where('name', 'admins')->first();

        $admin = new User();
        $admin->is_admin = true;
        $admin->name = 'admin1';
        $admin->email = 'admin1@gmail.com';
        $admin->password = Hash::make('admin1');
        $admin->save();

        $moderator = new User();
        $moderator->is_moderator = true;
        $moderator->name = 'moderator1';
        $moderator->email = 'moderator1@gmail.com';
        $moderator->password = Hash::make('moderator1');
        $moderator->save();

        $user1 = new User();
        $user1->name = 'user1';
        $user1->email = 'user1@gmail.com';
        $user1->password = Hash::make('user1');
        $user1->save();

        $user2 = new User();
        $user2->name = 'user2';
        $user2->email = 'user2@gmail.com';
        $user2->password = Hash::make('user2');
        $user2->save();

        $user3 = new User();
        $user3->name = 'user3';
        $user3->email = 'user3@gmail.com';
        $user3->password = Hash::make('user3');
        $user3->save();

        $user4 = new User();
        $user4->name = 'user4';
        $user4->email = 'user4@gmail.com';
        $user4->password = Hash::make('user4');
        $user4->save();

        $user5 = new User();
        $user5->name = 'user5';
        $user5->email = 'user5@gmail.com';
        $user5->password = Hash::make('user5');
        $user5->save();

        $admin->groups()->attach($admins);
        $moderator->groups()->attach($admins);

        $admin->groups()->attach($everyone);
        $moderator->groups()->attach($everyone);
        $user1->groups()->attach($everyone);
        $user2->groups()->attach($everyone);
        $user3->groups()->attach($everyone);
        $user4->groups()->attach($everyone);
        $user5->groups()->attach($everyone);
    }
}
