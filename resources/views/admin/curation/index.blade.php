@extends('layouts.admin')

@section('title', 'Entrenador de Inteligencia')

@section('content')
<div class="max-w-6xl mx-auto">
    <div class="mb-8">
        <h2 class="text-3xl font-black text-gray-800">Cerebro de Búsqueda</h2>
        <p class="text-gray-500 font-medium">Define el orden exacto. El #1 es el PIN y los siguientes aparecerán en ese mismo orden en el pop-up de reemplazos.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        <div class="lg:col-span-5 space-y-6">
            <div class="bg-white p-8 rounded-3xl shadow-sm border border-gray-100">
                <div class="mb-6">
                    <label class="block text-sm font-black text-gray-700 uppercase tracking-wider mb-2">1. Término a Entrenar</label>
                    <div class="flex gap-2">
                        <input type="text" id="searchTerm" 
                            class="w-full border-2 border-gray-100 rounded-2xl p-4 focus:border-green-500 focus:ring-4 focus:ring-green-50 transition-all outline-none font-bold text-lg" 
                            placeholder="Ej: Leche..."
                            onkeypress="if(event.key === 'Enter') searchForCuration()">
                        <button onclick="searchForCuration()" 
                            class="bg-gray-900 text-white px-6 rounded-2xl font-bold hover:bg-black transition-all active:scale-95">
                            🔍
                        </button>
                    </div>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-black text-gray-700 uppercase tracking-wider mb-2">Sinónimos</label>
                    <textarea id="synonyms" rows="2" 
                        class="w-full border-2 border-gray-100 rounded-2xl p-4 focus:border-green-500 transition-all outline-none text-sm font-medium" 
                        placeholder="lacteos, blanca..."></textarea>
                </div>
            </div>

            <div class="bg-green-700 p-6 rounded-3xl text-white shadow-lg relative overflow-hidden">
                <h4 class="font-black text-lg mb-2">Guía de Prioridad</h4>
                <p class="text-sm text-green-100 mb-4 font-medium">El orden que establezcas aquí será el orden numérico que verá el cliente al buscar alternativas.</p>
                <div class="space-y-2">
                    <div class="flex items-center gap-3 bg-green-600/50 p-2 rounded-lg text-xs font-bold">
                        <span class="w-5 h-5 bg-white text-green-700 rounded-full flex items-center justify-center">1</span> Producto Principal (PIN)
                    </div>
                    <div class="flex items-center gap-3 bg-green-600/50 p-2 rounded-lg text-xs font-bold">
                        <span class="w-5 h-5 bg-white text-green-700 rounded-full flex items-center justify-center text-[10px]">2-10</span> Orden de reemplazos
                    </div>
                </div>
            </div>
        </div>

        <div class="lg:col-span-7">
            <div class="bg-white p-8 rounded-3xl shadow-sm border border-gray-100 min-h-[550px] flex flex-col">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="font-black text-gray-800 uppercase tracking-widest text-sm">2. Jerarquía de Productos</h3>
                    <span id="badgeStatus" class="hidden px-3 py-1 rounded-full text-[10px] font-black uppercase italic"></span>
                </div>

                <ul id="sortableList" class="space-y-3 flex-1">
                    <div id="placeholder" class="h-full flex flex-col items-center justify-center py-20 text-center text-gray-400">
                        <span class="text-6xl mb-4">🧪</span>
                        <p class="font-bold">Busca un término para cargar la inteligencia</p>
                    </div>
                </ul>

                <div id="footerActions" class="hidden mt-8 border-t pt-6">
                    <button onclick="saveOrder()" id="btnSave" 
                        class="w-full bg-green-600 text-white py-5 rounded-2xl font-black text-xl shadow-xl shadow-green-100 hover:bg-green-700 transition-all active:scale-95 flex items-center justify-center gap-3">
                        💾 Guardar Entrenamiento
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
let sortableInstance;

function searchForCuration() {
    const term = document.getElementById('searchTerm').value;
    const list = document.getElementById('sortableList');
    const badge = document.getElementById('badgeStatus');
    const synonymsInput = document.getElementById('synonyms');
    
    if(!term) return;

    list.style.opacity = '0.5';

    fetch(`/admin/search-products?term=${term}`)
        .then(res => res.json())
        .then(data => {
            list.style.opacity = '1';
            list.innerHTML = '';
            
            // Cargar datos existentes si los hay
            if(data.curation) {
                synonymsInput.value = data.curation.synonyms || '';
                badge.innerText = "Editando Entrenamiento";
                badge.className = "bg-blue-100 text-blue-600 px-3 py-1 rounded-full text-[10px] font-black uppercase italic block";
            } else {
                synonymsInput.value = '';
                badge.innerText = "Nuevo Entrenamiento";
                badge.className = "bg-green-100 text-green-600 px-3 py-1 rounded-full text-[10px] font-black uppercase italic block";
            }

            data.products.forEach((p, index) => {
                const pos = index + 1;
                const isFirst = index === 0;
                list.innerHTML += `
                    <li class="group bg-white border-2 ${isFirst ? 'border-green-500 bg-green-50 shadow-md' : 'border-gray-50'} p-4 rounded-2xl flex justify-between items-center cursor-move transition-all" data-sku="${p.sku}">
                        <div class="flex items-center gap-4">
                            <div class="number-badge w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center font-black text-gray-400 group-hover:bg-green-100 group-hover:text-green-600 transition-all">
                                ${pos}
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <p class="font-black text-gray-800">${p.name}</p>
                                    ${isFirst ? '<span class="bg-green-600 text-[9px] text-white px-2 py-0.5 rounded-full font-black uppercase">PIN</span>' : ''}
                                </div>
                                <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">${p.sku}</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="font-black text-green-700">$${parseFloat(p.price).toFixed(2)}</p>
                        </div>
                    </li>
                `;
            });

            document.getElementById('footerActions').classList.remove('hidden');
            
            if(sortableInstance) sortableInstance.destroy();
            sortableInstance = new Sortable(list, { 
                animation: 250,
                ghostClass: 'bg-green-100',
                onEnd: () => refreshUI()
            });
        });
}

function refreshUI() {
    const items = document.querySelectorAll('#sortableList li');
    items.forEach((li, index) => {
        const badge = li.querySelector('.number-badge');
        badge.innerText = index + 1;
        
        if(index === 0) {
            li.classList.add('border-green-500', 'bg-green-50', 'shadow-md');
            badge.classList.add('bg-green-100', 'text-green-600');
            if(!li.querySelector('.bg-green-600')) {
                li.querySelector('.font-black').insertAdjacentHTML('afterend', '<span class="bg-green-600 text-[9px] text-white px-2 py-0.5 rounded-full font-black uppercase ml-2">PIN</span>');
            }
        } else {
            li.classList.remove('border-green-500', 'bg-green-50', 'shadow-md');
            badge.classList.remove('bg-green-100', 'text-green-600');
            const pinLabel = li.querySelector('.bg-green-600');
            if(pinLabel) pinLabel.remove();
        }
    });
}

function saveOrder() {
    const btn = document.getElementById('btnSave');
    const skus = Array.from(document.querySelectorAll('#sortableList li')).map(li => li.dataset.sku);
    const term = document.getElementById('searchTerm').value;
    const synonyms = document.getElementById('synonyms').value;

    btn.innerText = "Guardando...";
    btn.disabled = true;

    fetch('{{ route("admin.save.order") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({ search_term: term, skus: skus, synonyms: synonyms })
    })
    .then(res => res.json())
    .then(data => {
        btn.innerText = "Configuración Sincronizada ✅";
        setTimeout(() => {
            btn.innerText = "Guardar Entrenamiento";
            btn.disabled = false;
        }, 2000);
    });
}
</script>

<style>
    .sortable-ghost { opacity: 0; }
    .number-badge { font-size: 1.1rem; }
</style>
@endsection