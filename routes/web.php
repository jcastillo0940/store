<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\AuthController;

/*
|--------------------------------------------------------------------------
| Rutas Públicas (Clientes)
|--------------------------------------------------------------------------
*/

// La página de inicio carga el formulario para escribir la lista
Route::get('/', function () {
    return view('orders.create'); // Esta es la vista con el textarea
})->name('home');

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

/*
|--------------------------------------------------------------------------
| Motor de Pedidos
|--------------------------------------------------------------------------
*/
Route::prefix('orders')->group(function () {
    // 1. Procesa el texto (Ya existe en tu controlador)
    Route::post('/process', [OrderController::class, 'store'])->name('orders.process');
    
    // 2. Muestra la revisión (Tu controlador busca 'orders.confirm')
    Route::get('/{order}/confirm', [OrderController::class, 'show'])->name('orders.confirm');
    
    // 3. Finaliza (Ya existe en tu controlador)
    Route::post('/{order}/checkout', [OrderController::class, 'checkout'])->name('orders.checkout');
});

/*
|--------------------------------------------------------------------------
| API y Administración (Se mantienen igual)
|--------------------------------------------------------------------------
*/
Route::prefix('api')->group(function () {
    Route::get('/products/alternatives/{sku}', [ProductController::class, 'getAlternatives']);
    Route::post('/orders/replace-item', [OrderController::class, 'replaceItem'])->name('orders.replace_item');
});

Route::middleware(['auth', 'role:super_admin,gerente'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'index'])->name('admin.dashboard');
    Route::get('/products', [ProductController::class, 'index'])->name('admin.products.index');
    Route::get('/curation', [ProductController::class, 'curationView'])->name('admin.curation.index');
    Route::get('/search-products', [ProductController::class, 'searchProducts'])->name('admin.products.search');
    Route::post('/save-order', [ProductController::class, 'saveOrder'])->name('admin.save.order');
});

Route::middleware(['auth', 'role:shopper,super_admin'])->prefix('shopper')->group(function () {
    Route::get('/dashboard', [OrderController::class, 'dashboard'])->name('shopper.dashboard');
});