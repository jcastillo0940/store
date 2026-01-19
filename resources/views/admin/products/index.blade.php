@extends('layouts.app')

@section('content')
<div class="container mx-auto p-6">
    <h1 class="text-2xl font-bold mb-4">Catálogo Sincronizado ({{ $products->total() }} productos)</h1>
    
    <form action="{{ route('admin.products.index') }}" method="GET" class="mb-6">
        <input type="text" name="search" placeholder="Buscar por nombre o SKU..." 
               class="border p-2 rounded w-full md:w-1/3" value="{{ request('search') }}">
    </form>

    <div class="bg-white shadow-md rounded my-6 overflow-x-auto">
        <table class="min-w-full bg-white">
            <thead>
                <tr class="bg-gray-200 text-gray-600 uppercase text-sm leading-normal">
                    <th class="py-3 px-6 text-left">SKU</th>
                    <th class="py-3 px-6 text-left">Producto</th>
                    <th class="py-3 px-6 text-center">Precio</th>
                    <th class="py-3 px-6 text-center">Acciones</th>
                </tr>
            </thead>
            <tbody class="text-gray-600 text-sm font-light">
                @foreach($products as $product)
                <tr class="border-b border-gray-200 hover:bg-gray-100">
                    <td class="py-3 px-6 text-left font-mono">{{ $product->sku }}</td>
                    <td class="py-3 px-6 text-left">{{ $product->name }}</td>
                    <td class="py-3 px-6 text-center">${{ number_format($product->price, 2) }}</td>
                    <td class="py-3 px-6 text-center">
                        <button onclick="openPinModal('{{ $product->sku }}', '{{ $product->name }}')" 
                                class="bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600">
                            Fijar para Búsqueda
                        </button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $products->links() }}
</div>

<script>
    function openPinModal(sku, name) {
        let term = prompt("¿Para qué término de búsqueda quieres fijar este producto? (Ej: leche)");
        if (term) {
            // Aquí enviarías el formulario vía AJAX o a una ruta específica
            alert("Configurando '" + name + "' como resultado principal para '" + term + "'");
        }
    }
</script>
@endsection