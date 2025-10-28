<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserRolesSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $roles = [
            'superadmin' => 'Super Admin',
            'admin' => 'Admin',
            'specialist' => 'Specialist',
            'client' => 'Client',
        ];

        foreach ($roles as $slug => $name) {
           $role = UserRole::firstOrCreate(
                ['slug' => $slug],
                ['name' => $name]
            );
           if($slug == 'superadmin'){
               User::create([
                   'user_role_id' => $role->id,
                   'name' => 'Super Admin',
                   'email' => 'super@admin.com',
                   'password' => bcrypt(env('SUPER_ADMIN_PASSWORD', 'password')),
               ]);
           }
        }
    }
}
