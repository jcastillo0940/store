<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Super Administrador (Acceso Total)
        User::create([
            'name' => 'Admin Virzi',
            'email' => 'admin@virzi.com',
            'password' => Hash::make('admin123'),
            'role' => User::ROLE_ADMIN,
        ]);

        // 2. Gerente (Acceso a configuración)
        User::create([
            'name' => 'Gerente Aguadulce',
            'email' => 'gerente@virzi.com',
            'password' => Hash::make('gerente123'),
            'role' => User::ROLE_GERENTE,
        ]);

        // 3. Shopper (Acceso a dashboard de pedidos)
        User::create([
            'name' => 'Shopper Juan',
            'email' => 'shopper@virzi.com',
            'password' => Hash::make('shopper123'),
            'role' => User::ROLE_SHOPPER,
        ]);

        // 4. Cliente de prueba
        User::create([
            'name' => 'Cliente Prueba',
            'email' => 'cliente@gmail.com',
            'password' => Hash::make('cliente123'),
            'role' => User::ROLE_CLIENTE,
        ]);
    }
}