<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SearchCuration;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    /**
     * Muestra la vista principal del Dashboard con estadísticas reales.
     */
    public function index()
    {
        $stats = [
            'products_count'  => Product::count(),
            'curations_count' => SearchCuration::count(),
            'orders_today'    => Order::whereDate('created_at', today())->count(),
            'users_count'     => User::count(),
        ];

        return view('admin.dashboard', compact('stats'));
    }
}