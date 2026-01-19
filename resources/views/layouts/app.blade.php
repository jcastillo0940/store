<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>VirziApp - Smart Checkout</title>

    <script src="https://cdn.tailwindcss.com"></script>
    
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        [x-cloak] { display: none !important; }
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-100 antialiased">

    <main>
        @yield('content')
    </main>

    @if(session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
             class="fixed bottom-20 left-4 right-4 bg-green-600 text-white px-6 py-3 rounded-2xl shadow-2xl z-50 flex justify-between items-center">
            <span>{{ session('success') }}</span>
            <button @click="show = false">✕</button>
        </div>
    @endif

    @if(session('error'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
             class="fixed bottom-20 left-4 right-4 bg-red-600 text-white px-6 py-3 rounded-2xl shadow-2xl z-50 flex justify-between items-center">
            <span>{{ session('error') }}</span>
            <button @click="show = false">✕</button>
        </div>
    @endif

</body>
</html>