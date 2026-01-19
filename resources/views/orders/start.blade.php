@extends('layouts.app') {{-- O el layout que uses --}}

@section('content')
<div class="max-w-3xl mx-auto pt-12">
    <div class="text-center mb-10">
        <h1 class="text-4xl font-black text-gray-900 mb-2">Haz tu lista de súper</h1>
        <p class="text-gray-600 text-lg">Escribe los productos que necesitas y nosotros los buscamos por ti.</p>
    </div>

    <div class="bg-white rounded-2xl p-8 shadow-xl border border-gray-100">
        <form action="{{ route('orders.process') }}" method="POST">
            @csrf
            <div class="mb-6">
                <label for="list_text" class="block text-sm font-medium text-gray-700 mb-2">Tu lista:</label>
                <textarea 
                    name="list_text" 
                    id="list_text" 
                    rows="10" 
                    class="w-full p-4 border-gray-200 rounded-xl focus:ring-blue-500 focus:border-blue-500 shadow-sm"
                    placeholder="Ejemplo:&#10;2 galones de leche de vaca&#10;1 paquete de pan integral&#10;3 libras de arroz..."
                    required></textarea>
            </div>

            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 rounded-xl transition-all shadow-lg">
                Procesar mi lista
            </button>
        </form>
    </div>
</div>
@endsection