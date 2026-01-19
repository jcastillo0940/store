<?php

use App\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/orders')->group(function () {
    // Paso 1: Recibir el texto y crear el pedido en estado 'pending'
    Route::post('/process-list', [OrderController::class, 'store']);
    
    // Paso 2: Obtener los detalles del pedido para que el usuario confirme (UX de Carrito)
    Route::get('/{order}', [OrderController::class, 'show']);
    
    // Paso 3: Confirmar ítems específicos
    Route::patch('/items/{item}/confirm', [OrderController::class, 'confirmItem']);
    
    // Paso 4: Finalizar y enviar a Magento
    Route::post('/{order}/checkout', [OrderController::class, 'checkout']);
});