@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-[#f8fafd] pb-32">
    <div class="container mx-auto p-4 max-w-6xl">
        
        <header class="flex flex-col md:flex-row md:items-end justify-between mb-10 gap-4">
            <div>
                <nav class="flex items-center gap-2 text-[10px] font-black uppercase tracking-widest text-[#0f60e3] mb-2">
                    <span class="opacity-50">Lista</span>
                    <svg class="w-2 h-2" fill="currentColor" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3"/></svg>
                    <span>Verificación</span>
                </nav>
                <h1 class="text-4xl font-black text-gray-900 tracking-tighter leading-none">
                    Tu Pedido <span class="text-[#0f60e3]">Inteligente</span>
                </h1>
                <p class="text-gray-500 font-medium mt-2 max-w-md">
                    Confirmación de productos y optimización de pesos por unidad.
                </p>
            </div>
            <div class="hidden md:block">
                <span class="bg-[#dfecff] text-[#0f60e3] text-xs font-black px-5 py-2.5 rounded-2xl shadow-sm">
                    PASO 2 DE 2
                </span>
            </div>
        </header>

        <form action="{{ route('orders.checkout', $order->id) }}" method="POST" id="checkoutForm">
            @csrf
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">
                
                <div class="lg:col-span-2 space-y-6" id="itemsContainer">
                    @foreach($order->items as $item)
                    <div id="row-{{ $item->id }}" 
                         class="group relative bg-white rounded-[2.5rem] p-6 shadow-sm border-2 border-transparent hover:border-[#0f60e3]/10 hover:shadow-xl transition-all duration-300 item-row">
                        
                        @if($item->has_alternatives)
                        <div class="absolute -top-3 left-10 z-10">
                            <span class="bg-[#ffd100] text-gray-900 text-[10px] font-black px-4 py-1.5 rounded-full shadow-md uppercase tracking-wider flex items-center gap-2">
                                <span class="w-2 h-2 bg-gray-900 rounded-full animate-ping"></span> 
                                Opción Recomendada
                            </span>
                        </div>
                        @endif

                        <div class="flex flex-col sm:flex-row items-center gap-8">
                            <div class="flex items-center gap-6 w-full sm:w-auto">
                                <label class="relative flex items-center cursor-pointer">
                                    <input type="checkbox" name="items[{{ $item->id }}][confirmed]" checked 
                                           class="peer sr-only item-checkbox" 
                                           onchange="calculateTotal()">
                                    <div class="w-10 h-10 bg-gray-100 border-2 border-gray-200 rounded-full peer-checked:bg-[#0f60e3] peer-checked:border-[#0f60e3] transition-all flex items-center justify-center">
                                        <svg class="w-6 h-6 text-white opacity-0 peer-checked:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M5 13l4 4L19 7"/></svg>
                                    </div>
                                </label>
                                
                                <div class="w-28 h-28 bg-[#f8fafd] rounded-[2.5rem] overflow-hidden border border-gray-100 p-3 flex-shrink-0">
                                    <img id="img-{{ $item->id }}" 
                                         src="{{ $item->image_url ?? 'https://via.placeholder.com/150' }}" 
                                         class="w-full h-full object-contain mix-blend-multiply">
                                </div>
                            </div>

                            <div class="flex-1 text-center sm:text-left">
                                <h3 id="name-{{ $item->id }}" class="font-black text-2xl text-gray-900 leading-tight uppercase tracking-tighter mb-2">
                                    {{ $item->name }}
                                </h3>
                                <div class="flex flex-wrap justify-center sm:justify-start items-center gap-3">
                                    <span class="text-[11px] font-bold text-gray-400 bg-gray-50 px-3 py-1 rounded-xl">
                                        "{{ $item->search_term_origin }}"
                                    </span>
                                    <span class="text-[#0f60e3] font-black text-lg">
                                        $<span id="price-{{ $item->id }}" class="item-price">{{ number_format($item->price, 2, '.', '') }}</span>
                                        @if($item->is_by_weight)
                                        <span class="text-xs font-medium">/kg</span>
                                        @endif
                                    </span>
                                </div>

                                @if($item->has_alternatives)
                                <button type="button" 
                                        id="btn-alt-{{ $item->id }}"
                                        onclick="openAlternatives('{{ $item->sku }}', '{{ $item->id }}', '{{ $item->name }}')"
                                        class="mt-5 inline-flex items-center gap-2 text-[10px] font-black text-[#0f60e3] bg-[#dfecff] hover:bg-[#0f60e3] hover:text-white px-5 py-2.5 rounded-2xl transition-all uppercase tracking-widest">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                    Reemplazar Marca
                                </button>
                                @endif
                            </div>

                            <div class="w-full sm:w-36 bg-[#f8fafd] rounded-[2.5rem] p-5 flex flex-col items-center shadow-inner">
                                <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 text-center">Cant. Final</span>
                                <input type="number" 
                                       name="items[{{ $item->id }}][qty]" 
                                       value="{{ $item->is_by_weight ? number_format($item->quantity, 3, '.', '') : intval($item->quantity) }}" 
                                       step="{{ $item->is_by_weight ? '0.001' : '1' }}" 
                                       min="{{ $item->is_by_weight ? '0.001' : '1' }}" 
                                       class="bg-transparent border-none text-center font-black text-gray-900 w-full focus:ring-0 p-0 text-2xl item-qty"
                                       onchange="calculateTotal()">
                                <span class="text-[9px] font-bold text-[#0f60e3] uppercase mt-2">
                                    {{ $item->is_by_weight ? 'KILOGRAMOS' : 'UNIDADES' }}
                                </span>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>

                <div class="lg:col-span-1">
                    <div class="sticky top-10 bg-gray-900 rounded-[3.5rem] p-10 text-white shadow-2xl shadow-[#0f60e3]/30">
                        <h4 class="text-xs font-black text-[#ffd100] uppercase tracking-[0.3em] mb-8">Resumen Final</h4>
                        
                        <div class="space-y-5 mb-10">
                            <div class="flex justify-between items-center">
                                <span class="text-gray-400 text-xs font-bold uppercase">Subtotal</span>
                                <span class="text-lg font-black">$<span id="subTotal">0.00</span></span>
                            </div>
                            <div class="flex justify-between items-center border-t border-white/10 pt-5">
                                <span class="text-gray-400 text-xs font-bold uppercase tracking-widest">Impuestos</span>
                                <span class="text-gray-500 italic text-[10px]">Cálculo en caja</span>
                            </div>
                        </div>

                        <div class="flex flex-col gap-2 mb-10">
                            <span class="text-[11px] font-black text-white/30 uppercase tracking-widest">Total Estimado</span>
                            <div class="text-6xl font-black text-white tracking-tighter">
                                $<span id="grandTotal">0.00</span>
                            </div>
                        </div>

                        <button type="submit" class="group w-full bg-[#0f60e3] hover:bg-[#ffd100] hover:text-gray-900 text-white font-black py-7 rounded-[2.2rem] shadow-xl transition-all duration-500 flex items-center justify-center gap-4">
                            <span class="text-xl">ENVIAR PEDIDO</span>
                            <svg class="w-7 h-7 transform group-hover:translate-x-2 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                        </button>
                    </div>
                </div>

            </div>
        </form>
    </div>
</div>

<div id="modalAlternatives" class="fixed inset-0 bg-gray-900/90 hidden items-center justify-center z-50 p-4 backdrop-blur-2xl transition-all duration-500">
    <div class="bg-white rounded-[4rem] max-w-2xl w-full overflow-hidden shadow-2xl transform transition-transform flex flex-col max-h-[85vh]">
        <div class="p-12 border-b border-gray-100 flex flex-col gap-8">
            <div class="flex justify-between items-start">
                <h3 class="font-black text-4xl text-gray-900 tracking-tighter leading-none">
                    Opciones de<br><span id="modalProductName" class="text-[#0f60e3]"></span>
                </h3>
                <button onclick="closeModal()" class="w-14 h-14 bg-gray-100 rounded-full flex items-center justify-center text-3xl font-light hover:bg-gray-200 transition-colors">&times;</button>
            </div>
            <div class="relative">
                <input type="text" id="altSearchInput" onkeyup="filterAlternatives()" 
                       placeholder="Buscar marca o tipo..." 
                       class="w-full pl-16 pr-10 py-6 bg-[#f8fafd] border-none rounded-[2.5rem] focus:ring-4 focus:ring-[#0f60e3]/10 font-bold text-gray-800 text-lg">
                <svg class="absolute left-6 top-6 w-7 h-7 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
        </div>
        <div id="alternativesList" class="p-10 space-y-6 overflow-y-auto flex-1 bg-[#f8fafd]/50"></div>
    </div>
</div>

<script>
let currentAlternatives = [];
let activeItemId = null;

document.addEventListener('DOMContentLoaded', calculateTotal);

function calculateTotal() {
    let total = 0;
    document.querySelectorAll('.item-row').forEach(row => {
        const checkbox = row.querySelector('.item-checkbox');
        if (checkbox.checked) {
            const price = parseFloat(row.querySelector('.item-price').innerText);
            const qty = parseFloat(row.querySelector('.item-qty').value);
            total += price * qty;
        }
    });
    document.getElementById('grandTotal').innerText = total.toFixed(2);
    document.getElementById('subTotal').innerText = total.toFixed(2);
}

function openAlternatives(sku, itemId, productName) {
    activeItemId = itemId;
    document.getElementById('modalProductName').innerText = productName;
    document.getElementById('altSearchInput').value = '';
    const list = document.getElementById('alternativesList');
    list.innerHTML = `<div class="flex flex-col items-center py-20"><div class="w-16 h-16 border-8 border-[#0f60e3] border-t-transparent rounded-full animate-spin"></div></div>`;
    document.getElementById('modalAlternatives').classList.replace('hidden', 'flex');
    fetch(`/api/products/alternatives/${sku}`)
        .then(res => {
            if (!res.ok) throw new Error('Error al cargar alternativas');
            return res.json();
        })
        .then(products => {
            currentAlternatives = products;
            renderAlternatives(products);
        })
        .catch(error => {
            console.error('Error:', error);
            list.innerHTML = '<p class="text-center font-bold text-red-500">Error al cargar alternativas. Por favor intenta de nuevo.</p>';
        });
}

function renderAlternatives(products) {
    const list = document.getElementById('alternativesList');
    list.innerHTML = products.length === 0 ? '<p class="text-center font-bold text-gray-400">Sin opciones similares.</p>' : '';
    products.forEach(p => {
        const imageUrl = p.image_path ? `https://mcstaging.supercarnes.com/media/catalog/product${p.image_path}` : 'https://via.placeholder.com/150';
        list.innerHTML += `
            <div class="flex items-center gap-6 p-6 bg-white rounded-[2.5rem] border-2 border-transparent hover:border-[#0f60e3] shadow-sm transition-all group">
                <img src="${imageUrl}" class="w-20 h-20 object-contain rounded-3xl bg-gray-50 p-2">
                <div class="flex-1">
                    <p class="font-black text-lg text-gray-900 uppercase tracking-tighter mb-1">${p.name}</p>
                    <p class="text-[#0f60e3] font-black text-xl">$${parseFloat(p.price).toFixed(2)}</p>
                </div>
                <button type="button" onclick="replaceItem(${activeItemId}, '${p.sku}', this)" 
                        class="bg-[#0f60e3] text-white px-8 py-4 rounded-[1.5rem] font-black text-xs shadow-lg hover:scale-105 active:scale-95 transition-all uppercase">
                    ELEGIR
                </button>
            </div>
        `;
    });
}

function replaceItem(itemId, newSku, btn) {
    btn.disabled = true;
    btn.innerText = "⏳";
    fetch('{{ route("orders.replace_item") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({ order_item_id: itemId, new_sku: newSku })
    })
    .then(res => {
        if (!res.ok) throw new Error('Error al reemplazar el item');
        return res.json();
    })
    .then(data => {
        if (data.status === 'success') {
            document.getElementById(`name-${itemId}`).innerText = data.new_name;
            document.getElementById(`price-${itemId}`).innerText = data.new_price;
            if(data.new_image) document.getElementById(`img-${itemId}`).src = data.new_image;
            closeModal();
            calculateTotal();
            const row = document.getElementById(`row-${itemId}`);
            row.classList.add('ring-4', 'ring-[#ffd100]');
            setTimeout(() => row.classList.remove('ring-4', 'ring-[#ffd100]'), 1000);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        btn.disabled = false;
        btn.innerText = "ELEGIR";
        alert('Error al reemplazar el producto. Por favor intenta de nuevo.');
    });
}

function filterAlternatives() {
    const query = document.getElementById('altSearchInput').value.toLowerCase();
    renderAlternatives(currentAlternatives.filter(p => p.name.toLowerCase().includes(query)));
}

function closeModal() {
    document.getElementById('modalAlternatives').classList.replace('flex', 'hidden');
}
</script>

<style>
    body { -webkit-font-smoothing: antialiased; scroll-behavior: smooth; }
    input[type=number]::-webkit-inner-spin-button, input[type=number]::-webkit-outer-spin-button { opacity: 1; }
    .item-row { animation: slideIn 0.6s cubic-bezier(0.23, 1, 0.32, 1) backwards; }
    @keyframes slideIn { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
</style>
@endsection