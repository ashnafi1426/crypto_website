<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Cryptocurrency;
use App\Models\Wallet;
use App\Models\Order;
use App\Services\Contracts\TradingEngineInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class TradingControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;
    private Cryptocurrency $bitcoin;
    private TradingEngineInterface $tradingEngine;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test user
        $this->user = User::factory()->create();
        
        // Create test cryptocurrency
        $this->bitcoin = Cryptocurrency::create([
            'symbol' => 'BTC',
            'name' => 'Bitcoin',
            'current_price' => '50000.00000000',
            'volatility' => 0.02,
            'is_active' => true
        ]);

        // Create USD cryptocurrency for trading pairs
        Cryptocurrency::create([
            'symbol' => 'USD',
            'name' => 'US Dollar',
            'current_price' => '1.00000000',
            'volatility' => 0.001,
            'is_active' => true
        ]);

        // Create wallets for the user
        Wallet::create([
            'user_id' => $this->user->id,
            'cryptocurrency_symbol' => 'BTC',
            'balance' => '1.00000000',
            'reserved_balance' => '0.00000000'
        ]);

        Wallet::create([
            'user_id' => $this->user->id,
            'cryptocurrency_symbol' => 'USD',
            'balance' => '100000.00000000',
            'reserved_balance' => '0.00000000'
        ]);

        $this->tradingEngine = app(TradingEngineInterface::class);
    }

    /** @test */
    public function it_can_place_a_limit_buy_order()
    {
        $orderData = [
            'cryptocurrency_symbol' => 'BTC',
            'order_type' => 'limit',
            'side' => 'buy',
            'quantity' => '0.10000000',
            'price' => '45000.00000000'
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/orders', $orderData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Order placed successfully'
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'order' => [
                    'id',
                    'user_id',
                    'cryptocurrency_symbol',
                    'order_type',
                    'side',
                    'quantity',
                    'price',
                    'status'
                ]
            ]);

        $this->assertDatabaseHas('orders', [
            'user_id' => $this->user->id,
            'cryptocurrency_symbol' => 'BTC',
            'order_type' => 'limit',
            'side' => 'buy',
            'quantity' => '0.10000000',
            'price' => '45000.00000000',
            'status' => 'pending'
        ]);
    }

    /** @test */
    public function it_can_place_a_limit_sell_order()
    {
        $orderData = [
            'cryptocurrency_symbol' => 'BTC',
            'order_type' => 'limit',
            'side' => 'sell',
            'quantity' => '0.05000000',
            'price' => '55000.00000000'
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/orders', $orderData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Order placed successfully'
            ]);

        $this->assertDatabaseHas('orders', [
            'user_id' => $this->user->id,
            'cryptocurrency_symbol' => 'BTC',
            'order_type' => 'limit',
            'side' => 'sell',
            'quantity' => '0.05000000',
            'price' => '55000.00000000',
            'status' => 'pending'
        ]);
    }

    /** @test */
    public function it_can_place_a_market_buy_order()
    {
        $orderData = [
            'cryptocurrency_symbol' => 'BTC',
            'order_type' => 'market',
            'side' => 'buy',
            'quantity' => '0.10000000'
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/orders', $orderData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Order placed successfully'
            ]);

        $this->assertDatabaseHas('orders', [
            'user_id' => $this->user->id,
            'cryptocurrency_symbol' => 'BTC',
            'order_type' => 'market',
            'side' => 'buy',
            'quantity' => '0.10000000',
            'price' => '50000.00000000', // Should use current market price
            'status' => 'pending'
        ]);
    }

    /** @test */
    public function it_validates_order_placement_data()
    {
        $invalidOrderData = [
            'cryptocurrency_symbol' => 'INVALID',
            'order_type' => 'invalid_type',
            'side' => 'invalid_side',
            'quantity' => -1,
            'price' => -1
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/orders', $invalidOrderData);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Validation failed'
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'errors'
            ]);
    }

    /** @test */
    public function it_requires_price_for_limit_orders()
    {
        $orderData = [
            'cryptocurrency_symbol' => 'BTC',
            'order_type' => 'limit',
            'side' => 'buy',
            'quantity' => '0.10000000'
            // Missing price for limit order
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/orders', $orderData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['price']);
    }

    /** @test */
    public function it_can_retrieve_user_orders()
    {
        // Create some test orders
        Order::factory()->create([
            'user_id' => $this->user->id,
            'cryptocurrency_symbol' => 'BTC',
            'order_type' => 'limit',
            'side' => 'buy',
            'status' => 'pending'
        ]);

        Order::factory()->create([
            'user_id' => $this->user->id,
            'cryptocurrency_symbol' => 'BTC',
            'order_type' => 'limit',
            'side' => 'sell',
            'status' => 'filled'
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/orders');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ])
            ->assertJsonStructure([
                'success',
                'orders' => [
                    '*' => [
                        'id',
                        'cryptocurrency_symbol',
                        'cryptocurrency_name',
                        'order_type',
                        'side',
                        'quantity',
                        'price',
                        'filled_quantity',
                        'remaining_quantity',
                        'total',
                        'status',
                        'created_at',
                        'updated_at'
                    ]
                ],
                'total_count'
            ]);
    }

    /** @test */
    public function it_can_cancel_an_order()
    {
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'cryptocurrency_symbol' => 'BTC',
            'status' => 'pending'
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/orders/{$order->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ]);
    }

    /** @test */
    public function it_prevents_cancelling_other_users_orders()
    {
        $otherUser = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $otherUser->id,
            'cryptocurrency_symbol' => 'BTC',
            'status' => 'pending'
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/orders/{$order->id}");

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Order not found or already processed'
            ]);
    }

    /** @test */
    public function it_validates_order_id_for_cancellation()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson('/api/orders/0');

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid order ID'
            ]);
    }

    /** @test */
    public function it_can_retrieve_order_book()
    {
        // Create some test orders for the order book
        Order::factory()->create([
            'cryptocurrency_symbol' => 'BTC',
            'side' => 'buy',
            'price' => '49000.00000000',
            'quantity' => '0.50000000',
            'status' => 'pending'
        ]);

        Order::factory()->create([
            'cryptocurrency_symbol' => 'BTC',
            'side' => 'sell',
            'price' => '51000.00000000',
            'quantity' => '0.30000000',
            'status' => 'pending'
        ]);

        $response = $this->getJson('/api/orderbook/BTC');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'cryptocurrency' => 'BTC'
            ])
            ->assertJsonStructure([
                'success',
                'cryptocurrency',
                'bids',
                'asks',
                'last_updated'
            ]);
    }

    /** @test */
    public function it_validates_cryptocurrency_symbol_for_order_book()
    {
        $response = $this->getJson('/api/orderbook/INVALID');

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid cryptocurrency symbol'
            ]);
    }

    /** @test */
    public function it_requires_authentication_for_protected_endpoints()
    {
        // Test order placement without authentication
        $response = $this->postJson('/api/orders', [
            'cryptocurrency_symbol' => 'BTC',
            'order_type' => 'limit',
            'side' => 'buy',
            'quantity' => '0.10000000',
            'price' => '45000.00000000'
        ]);

        $response->assertStatus(401);

        // Test order retrieval without authentication
        $response = $this->getJson('/api/orders');
        $response->assertStatus(401);

        // Test order cancellation without authentication
        $response = $this->deleteJson('/api/orders/1');
        $response->assertStatus(401);
    }

    /** @test */
    public function it_handles_insufficient_balance_for_orders()
    {
        // Update wallet to have insufficient balance
        $wallet = Wallet::where('user_id', $this->user->id)
            ->where('cryptocurrency_symbol', 'USD')
            ->first();
        $wallet->update(['balance' => '100.00000000']); // Not enough for large order

        $orderData = [
            'cryptocurrency_symbol' => 'BTC',
            'order_type' => 'limit',
            'side' => 'buy',
            'quantity' => '10.00000000', // Large quantity requiring more USD than available
            'price' => '50000.00000000'
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/orders', $orderData);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Insufficient balance'
            ]);
    }
}