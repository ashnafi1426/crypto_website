<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use App\Models\Cryptocurrency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Order::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $side = $this->faker->randomElement(['buy', 'sell']);
        $orderType = $this->faker->randomElement(['market', 'limit']);
        
        return [
            'user_id' => User::factory(),
            'cryptocurrency_symbol' => 'BTC',
            'order_type' => $orderType,
            'side' => $side,
            'quantity' => $this->faker->randomFloat(8, 0.00000001, 10.00000000),
            'price' => $orderType === 'market' ? null : $this->faker->randomFloat(8, 1000.00000000, 100000.00000000),
            'filled_quantity' => '0.00000000',
            'status' => $this->faker->randomElement(['pending', 'partial', 'filled', 'cancelled']),
            'reservation_id' => $this->faker->uuid(),
        ];
    }

    /**
     * Indicate that the order is a buy order.
     */
    public function buy(): static
    {
        return $this->state(fn (array $attributes) => [
            'side' => 'buy',
        ]);
    }

    /**
     * Indicate that the order is a sell order.
     */
    public function sell(): static
    {
        return $this->state(fn (array $attributes) => [
            'side' => 'sell',
        ]);
    }

    /**
     * Indicate that the order is a limit order.
     */
    public function limit(): static
    {
        return $this->state(fn (array $attributes) => [
            'order_type' => 'limit',
            'price' => $this->faker->randomFloat(8, 1000.00000000, 100000.00000000),
        ]);
    }

    /**
     * Indicate that the order is a market order.
     */
    public function market(): static
    {
        return $this->state(fn (array $attributes) => [
            'order_type' => 'market',
            'price' => null,
        ]);
    }

    /**
     * Indicate that the order is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'filled_quantity' => '0.00000000',
        ]);
    }

    /**
     * Indicate that the order is filled.
     */
    public function filled(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'filled',
                'filled_quantity' => $attributes['quantity'],
            ];
        });
    }

    /**
     * Indicate that the order is partially filled.
     */
    public function partial(): static
    {
        return $this->state(function (array $attributes) {
            $quantity = $attributes['quantity'];
            $filledQuantity = bcmul($quantity, '0.5', 8); // 50% filled
            
            return [
                'status' => 'partial',
                'filled_quantity' => $filledQuantity,
            ];
        });
    }

    /**
     * Indicate that the order is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }

    /**
     * Set a specific cryptocurrency symbol.
     */
    public function forCryptocurrency(string $symbol): static
    {
        return $this->state(fn (array $attributes) => [
            'cryptocurrency_symbol' => $symbol,
        ]);
    }

    /**
     * Set a specific price.
     */
    public function withPrice(string $price): static
    {
        return $this->state(fn (array $attributes) => [
            'price' => $price,
        ]);
    }

    /**
     * Set a specific quantity.
     */
    public function withQuantity(string $quantity): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => $quantity,
        ]);
    }
}