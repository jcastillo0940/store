<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Services\MagentoService;

class CreateMagentoOrderHybrid extends Command
{
    protected $signature = 'magento:create-order-hybrid 
                            {--customer= : Nombre del cliente}
                            {--phone= : Teléfono WhatsApp}
                            {--email= : Email del cliente}
                            {--branch= : Sucursal}
                            {--delivery= : Método de entrega (pickup/delivery)}
                            {--payment= : Método de pago}
                            {--address= : Dirección de entrega}
                            {--sku=* : SKUs de productos}
                            {--qty=* : Cantidades}
                            {--debug : Mostrar debug}';

    protected $description = 'Crear orden en Magento (GraphQL + REST para pago)';
    protected MagentoService $magentoService;
    protected bool $debug = false;

    public function __construct(MagentoService $magentoService)
    {
        parent::__construct();
        $this->magentoService = $magentoService;
    }

    public function handle()
    {
        $this->debug = $this->option('debug');
        $this->info("🚀 Creando Orden en Magento (Híbrido)\n");

        $orderData = $this->collectDataFromOptions();
        if (!$orderData) return 1;

        $this->showSummary($orderData);
        if (!$this->confirm('¿Crear esta orden?', true)) return 0;

        $this->info("\n📡 Creando orden (GraphQL + REST)...\n");
        $orderNumber = $this->createOrderHybrid($orderData);

        if ($orderNumber) {
            $this->info("\n✅ ¡Orden creada exitosamente!");
            $this->info("   Order Number: {$orderNumber}");
            return 0;
        }

        $this->error("\n❌ Error al crear orden");
        return 1;
    }

    private function createOrderHybrid(array $data): ?string
    {
        $graphqlUrl = rtrim(config('services.magento.base_url'), '/') . '/graphql';
        $baseUrl = rtrim(config('services.magento.base_url'), '/');
        $token = config('services.magento.token');

        try {
            // 1. Crear carrito (GraphQL)
            $this->line("1️⃣  Creando carrito (GraphQL)...");
            $result = $this->gql($graphqlUrl, 'mutation { createEmptyCart }');
            $cartId = $result['data']['createEmptyCart'] ?? null;
            if (!$cartId) return null;
            $this->info("   ✅ {$cartId}");

            // 2. Agregar productos (GraphQL)
            $this->line("\n2️⃣  Agregando productos (GraphQL)...");
            $itemsAddedSuccessfully = 0;
            $totalItems = count($data['products']);

            foreach ($data['products'] as $product) {
                $sku = $product['sku'];
                $qty = (float)$product['quantity'];
                $mutation = "mutation { addProductsToCart(cartId: \"{$cartId}\", cartItems: [{sku: \"{$sku}\", quantity: {$qty}}]) { cart { items { id } } } }";
                $result = $this->gql($graphqlUrl, $mutation);

                if (isset($result['errors'])) {
                    $this->warn("   ⚠️  {$sku} - " . $result['errors'][0]['message']);
                } else {
                    $itemsAddedSuccessfully++;
                    $this->info("   ✅ {$sku} (agregado {$itemsAddedSuccessfully}/{$totalItems})");
                }
            }

            // Verificar que al menos un producto se haya agregado
            if ($itemsAddedSuccessfully === 0) {
                $this->error("\n❌ No se pudo agregar ningún producto al carrito.");
                $this->error("   Todos los productos tienen problemas de stock o no existen.");
                $this->error("   Abortando creación de orden.");
                return null;
            }

            $this->line("\n   📊 Resumen: {$itemsAddedSuccessfully}/{$totalItems} productos agregados exitosamente");

            // 3. Email (GraphQL)
            $this->line("\n3️⃣  Configurando email (GraphQL)...");
            $this->gql($graphqlUrl, "mutation { setGuestEmailOnCart(input: {cart_id: \"{$cartId}\", email: \"{$data['email']}\"}) { cart { email } } }");
            $this->info("   ✅ OK");

            // Preparar datos de dirección
            $firstname = addslashes($data['customer_name']);
            $street = addslashes($data['delivery_address']);
            $city = addslashes($data['branch']);
            $phone = addslashes($data['whatsapp']);

            // 4. Billing Address (GraphQL)
            $this->line("\n4️⃣  Dirección de facturación (GraphQL)...");
            $billingMutation = <<<GQL
mutation {
  setBillingAddressOnCart(
    input: {
      cart_id: "{$cartId}"
      billing_address: {
        address: {
          firstname: "{$firstname}"
          lastname: "Cliente"
          street: ["{$street}"]
          city: "{$city}"
          region_id: 1166
          province: "PANAMÁ"
          postcode: "00000"
          country_code: "PA"
          telephone: "{$phone}"
        }
      }
    }
  ) {
    cart { billing_address { firstname } }
  }
}
GQL;
            $this->gql($graphqlUrl, $billingMutation);
            $this->info("   ✅ OK");

            // 5. Shipping Address (GraphQL)
            $this->line("\n5️⃣  Dirección de envío (GraphQL)...");
            $shippingMutation = <<<GQL
mutation {
  setShippingAddressesOnCart(
    input: {
      cart_id: "{$cartId}"
      shipping_addresses: [{
        address: {
          firstname: "{$firstname}"
          lastname: "Cliente"
          street: ["{$street}"]
          city: "{$city}"
          region_id: 1166
          province: "PANAMÁ"
          postcode: "00000"
          country_code: "PA"
          telephone: "{$phone}"
        }
      }]
    }
  ) {
    cart { shipping_addresses { available_shipping_methods { carrier_code } } }
  }
}
GQL;
            $this->gql($graphqlUrl, $shippingMutation);
            $this->info("   ✅ OK");

            // 6. Shipping Method (GraphQL)
            $this->line("\n6️⃣  Método de envío (GraphQL)...");
            $carrier = $data['delivery_method'] === 'pickup' ? 'instore' : 'tablerate';
            $method = $data['delivery_method'] === 'pickup' ? 'pickup' : 'bestway';
            $this->gql($graphqlUrl, "mutation { setShippingMethodsOnCart(input: {cart_id: \"{$cartId}\", shipping_methods: [{carrier_code: \"{$carrier}\", method_code: \"{$method}\"}]}) { cart { id } } }");
            $this->info("   ✅ OK");

            // 7. Obtener métodos de pago disponibles (REST)
            $this->line("\n7️⃣  Obteniendo métodos de pago disponibles (REST)...");

            $paymentsResponse = Http::get("{$baseUrl}/rest/V1/guest-carts/{$cartId}/payment-methods");

            if ($this->debug) {
                $this->line("━━━ REQUEST ━━━");
                $this->line("GET {$baseUrl}/rest/V1/guest-carts/{$cartId}/payment-methods");
                $this->line("━━━ RESPONSE ━━━");
                $this->line($paymentsResponse->body());
                $this->newLine();
            }

            if ($paymentsResponse->failed()) {
                $this->error("   ❌ No se pudieron obtener métodos de pago");
                return null;
            }

            $availableMethods = $paymentsResponse->json();
            $methodCodes = array_column($availableMethods, 'code');

            if (empty($methodCodes)) {
                $this->error("   ❌ No hay métodos de pago disponibles");
                return null;
            }

            // Mostrar métodos disponibles
            $this->info("   Métodos disponibles:");
            foreach ($availableMethods as $method) {
                $this->line("   - {$method['code']}: {$method['title']}");
            }

            // Verificar si el método solicitado está disponible
            if (!in_array($data['payment_method'], $methodCodes)) {
                $this->warn("   ⚠️  Método solicitado '{$data['payment_method']}' NO disponible");

                // Preguntar al usuario qué método usar
                $selectedMethod = $this->choice(
                    '¿Qué método de pago deseas usar?',
                    $methodCodes,
                    0
                );

                $data['payment_method'] = $selectedMethod;
                $this->info("   ✅ Usando: {$selectedMethod}");
            } else {
                $this->info("   ✅ Método válido: {$data['payment_method']}");
            }

            // 8. Crear orden con REST (payment-information configura pago y crea orden en un solo paso)
            $this->line("\n8️⃣  Creando orden con REST (configurando pago y finalizando)...");

            $paymentInfo = [
                'email' => $data['email'],
                'paymentMethod' => [
                    'method' => $data['payment_method']
                ]
            ];

            if ($this->debug) {
                $this->line("   Payload:");
                $this->line(json_encode($paymentInfo, JSON_PRETTY_PRINT));
            }

            $orderResponse = Http::withToken($token)
                ->timeout(60)
                ->post("{$baseUrl}/rest/V1/guest-carts/{$cartId}/payment-information", $paymentInfo);

            if ($orderResponse->successful()) {
                $magentoOrderId = $orderResponse->json();

                if (is_numeric($magentoOrderId) && $magentoOrderId > 0) {
                    $this->info("   ✅ Orden creada exitosamente");
                    return (string)$magentoOrderId;
                }

                $this->error("   ❌ Respuesta inesperada: " . $orderResponse->body());
                return null;
            }

            // Si falla, mostrar error detallado
            $this->error("   ❌ Error al crear orden (HTTP {$orderResponse->status()})");
            $errorBody = $orderResponse->json();

            if (isset($errorBody['message'])) {
                $this->error("   Mensaje: {$errorBody['message']}");
            }

            if ($this->debug) {
                $this->line("\n   Respuesta completa:");
                $this->line($orderResponse->body());
                $this->debugMagentoOrder($cartId, $baseUrl, $graphqlUrl, $token, $data);
            }

            return null;

        } catch (\Exception $e) {
            $this->error("Excepción: " . $e->getMessage());
            if ($this->debug) {
                $this->error("Trace: " . $e->getTraceAsString());
            }
            return null;
        }
    }

    private function debugMagentoOrder(string $cartId, string $baseUrl, string $graphqlUrl, string $token, array $data): void
    {
        $this->line("\n🔍 Debug detallado de Magento:\n");
        
        // 1. Diagnóstico GraphQL del carrito
        $this->line("1. Estado del carrito (GraphQL):");
        $diagQuery = "query { cart(cart_id: \"{$cartId}\") { id email items { id quantity product { sku name } } billing_address { firstname lastname street city telephone } shipping_addresses { firstname lastname street city telephone selected_shipping_method { carrier_code method_code carrier_title method_title } } selected_payment_method { code title } prices { grand_total { value currency } } } }";
        $diagResult = $this->gql($graphqlUrl, $diagQuery);
        
        if (isset($diagResult['data']['cart'])) {
            $this->line(json_encode($diagResult['data']['cart'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
        
        // 2. Totals via REST
        $this->line("\n2. Verificando totals (REST):");
        $totalsResponse = Http::get("{$baseUrl}/rest/V1/guest-carts/{$cartId}/totals");
        
        if ($totalsResponse->successful()) {
            $this->line(json_encode($totalsResponse->json(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->error("   Status: " . $totalsResponse->status());
            $this->line($totalsResponse->body());
        }
        
        // 3. Intentar crear orden via REST POST
        $this->line("\n3. Intentando crear orden via REST POST /order:");
        $orderPayload = [
            'paymentMethod' => [
                'method' => $data['payment_method']
            ]
        ];
        
        if ($this->debug) {
            $this->line("━━━ REQUEST ━━━");
            $this->line("POST {$baseUrl}/rest/V1/guest-carts/{$cartId}/order");
            $this->line(json_encode($orderPayload, JSON_PRETTY_PRINT));
        }
        
        $orderResponse = Http::post("{$baseUrl}/rest/V1/guest-carts/{$cartId}/order", $orderPayload);
        
        $this->line("Status: " . $orderResponse->status());
        $this->line("Response: " . $orderResponse->body());
        
        if ($orderResponse->successful()) {
            $orderId = $orderResponse->json();
            $this->info("✅ ¡Orden creada via REST!: {$orderId}");
            
            // Obtener order info
            $orderInfo = Http::withToken($token)->get("{$baseUrl}/rest/V1/orders/{$orderId}");
            if ($orderInfo->successful()) {
                $incrementId = $orderInfo->json()['increment_id'] ?? $orderId;
                $this->info("Order Number: {$incrementId}");
            }
        }
        
        // 4. Intentar via REST payment-information
        $this->line("\n4. Intentando crear orden via REST payment-information:");
        $paymentInfoPayload = [
            'email' => $data['email'],
            'paymentMethod' => [
                'method' => $data['payment_method']
            ]
        ];
        
        if ($this->debug) {
            $this->line("━━━ REQUEST ━━━");
            $this->line("POST {$baseUrl}/rest/V1/guest-carts/{$cartId}/payment-information");
            $this->line(json_encode($paymentInfoPayload, JSON_PRETTY_PRINT));
        }
        
        $paymentInfoResponse = Http::post("{$baseUrl}/rest/V1/guest-carts/{$cartId}/payment-information", $paymentInfoPayload);
        
        $this->line("Status: " . $paymentInfoResponse->status());
        $this->line("Response: " . $paymentInfoResponse->body());
        
        if ($paymentInfoResponse->successful()) {
            $orderId = $paymentInfoResponse->json();
            $this->info("✅ ¡Orden creada via payment-information!: {$orderId}");
            
            // Obtener order info
            $orderInfo = Http::withToken($token)->get("{$baseUrl}/rest/V1/orders/{$orderId}");
            if ($orderInfo->successful()) {
                $incrementId = $orderInfo->json()['increment_id'] ?? $orderId;
                $this->info("Order Number: {$incrementId}");
            }
        }
    }

    private function gql(string $url, string $query): ?array
    {
        if ($this->debug) {
            $this->line("━━━ QUERY ━━━");
            $this->line($query);
        }

        $response = Http::post($url, ['query' => $query]);

        if ($this->debug) {
            $this->line("━━━ RESPONSE ━━━");
            $this->line($response->body());
            $this->newLine();
        }

        return $response->successful() ? $response->json() : null;
    }

    private function collectDataFromOptions(): ?array
    {
        $skus = $this->option('sku');
        if (empty($skus)) {
            $this->error("Especifica al menos un SKU");
            return null;
        }

        $products = [];
        foreach ($skus as $index => $sku) {
            $qtyArray = $this->option('qty') ?? [];
            $qty = $qtyArray[$index] ?? 1;
            $info = $this->magentoService->getProductBySku($sku);
            $products[] = [
                'sku' => $sku,
                'name' => $info['name'] ?? $sku,
                'price' => $info['price'] ?? 0,
                'quantity' => (float)$qty
            ];
        }

        return [
            'customer_name' => $this->option('customer') ?? 'Cliente Prueba',
            'whatsapp' => $this->option('phone') ?? '+507 6000-0000',
            'email' => $this->option('email') ?? 'test@example.com',    
            'branch' => $this->option('branch') ?? 'Aguadulce',
            'delivery_method' => $this->option('delivery') ?? 'pickup',
            'payment_method' => $this->option('payment') ?? 'cashondelivery',
            'delivery_address' => $this->option('address') ?? 'Orden desde Artisan',
            'products' => $products
        ];
    }

    private function showSummary(array $data): void
    {
        $this->table(['Campo', 'Valor'], [
            ['Cliente', $data['customer_name']],
            ['Email', $data['email']],
            ['Método Pago', $data['payment_method']],
            ['Productos', count($data['products'])]
        ]);
    }
}