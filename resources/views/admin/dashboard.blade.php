@extends('layouts.admin')

@section('title', 'Dashboard Resumen')

@section('content')
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
    
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex items-center gap-4 transition-transform hover:scale-105">
        <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-xl flex items-center justify-center text-2xl">📦</div>
        <div>
            <p class="text-sm text-gray-500 font-bold uppercase">Productos</p>
            <p class="text-2xl font-black text-gray-800">15,420</p>
        </div>
    </div>

    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex items-center gap-4 transition-transform hover:scale-105">
        <div class="w-12 h-12 bg-green-100 text-green-600 rounded-xl flex items-center justify-center text-2xl">🧠</div>
        <div>
            <p class="text-sm text-gray-500 font-bold uppercase">Curadurías</p>
            <p class="text-2xl font-black text-gray-800">1,240</p>
        </div>
    </div>

    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex items-center gap-4 transition-transform hover:scale-105">
        <div class="w-12 h-12 bg-orange-100 text-orange-600 rounded-xl flex items-center justify-center text-2xl">🛒</div>
        <div>
            <p class="text-sm text-gray-500 font-bold uppercase">Pedidos Hoy</p>
            <p class="text-2xl font-black text-gray-800">85</p>
        </div>
    </div>

    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex items-center gap-4 transition-transform hover:scale-105">
        <div class="w-12 h-12 bg-purple-100 text-purple-600 rounded-xl flex items-center justify-center text-2xl">👥</div>
        <div>
            <p class="text-sm text-gray-500 font-bold uppercase">Usuarios</p>
            <p class="text-2xl font-black text-gray-800">12</p>
        </div>
    </div>
</div>

<div class="mt-8 bg-green-700 rounded-3xl p-8 text-white shadow-lg overflow-hidden relative">
    <div class="relative z-10">
        <h2 class="text-3xl font-black">¡Hola, {{ explode(' ', Auth::user()->name)[0] }}! 👋</h2>
        <p class="mt-2 text-green-100 text-lg">Bienvenido al motor de VirziApp Quick Commerce. Desde aquí controlas la inteligencia de búsqueda.</p>
        <div class="mt-6 flex gap-4">
            <a href="{{ route('admin.curation.index') }}" class="bg-white text-green-700 px-6 py-3 rounded-xl font-bold hover:bg-green-50 transition">Entrenar Buscador</a>
            <a href="{{ route('admin.products.index') }}" class="bg-green-600 text-white px-6 py-3 rounded-xl font-bold hover:bg-green-500 transition border border-green-500">Ver Catálogo</a>
        </div>
    </div>
    <div class="absolute -bottom-12 -right-12 w-64 h-64 bg-green-600 rounded-full opacity-50"></div>
</div>
@endsection