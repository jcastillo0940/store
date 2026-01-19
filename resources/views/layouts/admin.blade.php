<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Administrativo - VirziApp</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-100 font-sans antialiased" x-data="{ sidebarOpen: false }">

    <header class="lg:hidden bg-white border-b p-4 flex justify-between items-center sticky top-0 z-40">
        <span class="font-black text-green-700 text-xl italic">VirziApp Admin</span>
        <button @click="sidebarOpen = true" class="p-2 text-gray-600 focus:outline-none">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
        </button>
    </header>

    <div class="flex h-screen overflow-hidden">
        
        <div x-show="sidebarOpen" @click="sidebarOpen = false" class="fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden"></div>

        <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'" 
               class="fixed inset-y-0 left-0 w-64 bg-white border-r shadow-sm transform transition-transform duration-300 ease-in-out lg:translate-x-0 lg:static lg:inset-0 z-50 overflow-y-auto">
            
            <div class="p-6 flex items-center gap-2 border-b">
                <div class="w-8 h-8 bg-green-600 rounded-lg flex items-center justify-center text-white font-bold">V</div>
                <span class="font-black text-gray-800 text-xl tracking-tight">VirziApp</span>
            </div>

            <nav class="mt-6 px-4 space-y-2">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2 px-2">Gestión</p>
                
                <a href="{{ route('admin.products.index') }}" class="flex items-center gap-3 p-3 rounded-xl {{ request()->routeIs('admin.products.*') ? 'bg-green-50 text-green-700 font-bold' : 'text-gray-600 hover:bg-gray-50' }}">
                    <span>📦</span> Catálogo
                </a>

                <a href="{{ route('admin.curation.index') }}" class="flex items-center gap-3 p-3 rounded-xl {{ request()->routeIs('admin.curation.*') ? 'bg-green-50 text-green-700 font-bold' : 'text-gray-600 hover:bg-gray-50' }}">
                    <span>🧠</span> Entrenador AI
                </a>

                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mt-8 mb-2 px-2">Configuración</p>

                <a href="#" class="flex items-center gap-3 p-3 rounded-xl text-gray-600 hover:bg-gray-50">
                    <span>👥</span> Usuarios
                </a>

                <div class="pt-10">
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="w-full flex items-center gap-3 p-3 text-red-600 font-bold hover:bg-red-50 rounded-xl">
                            <span>🚪</span> Cerrar Sesión
                        </button>
                    </form>
                </div>
            </nav>
        </aside>

        <main class="flex-1 flex flex-col min-w-0 overflow-hidden bg-gray-50">
            <header class="hidden lg:flex items-center justify-between px-8 py-4 bg-white border-b">
                <h1 class="text-lg font-bold text-gray-800">@yield('title')</h1>
                <div class="flex items-center gap-4">
                    <span class="text-sm text-gray-500 font-medium italic">{{ Auth::user()->name }} ({{ Auth::user()->role }})</span>
                    <div class="w-10 h-10 bg-gray-200 rounded-full border-2 border-green-500"></div>
                </div>
            </header>

            <div class="flex-1 overflow-x-hidden overflow-y-auto p-4 lg:p-8">
                @yield('content')
            </div>
        </main>
    </div>

</body>
</html>