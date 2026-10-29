<?php

namespace Tests\Feature;

use App\Http\Controllers\CheckoutController;
use App\Models\Order;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PDOException;
use ReflectionMethod;
use Tests\TestCase;

class CheckoutOrderNumberRetryTest extends TestCase
{
    use RefreshDatabase;

    private function invokeRetry(callable $callback, int $maxAttempts = 3): mixed
    {
        $controller = $this->app->make(CheckoutController::class);
        $method = new ReflectionMethod($controller, 'createOrderWithRetries');
        $method->setAccessible(true);

        return $method->invoke($controller, $callback, $maxAttempts);
    }

    private function duplicateOrderNumberException(): QueryException
    {
        return new QueryException(
            'sqlite',
            'insert into "orders" ("order_number", "email", "shipping_name") values (?, ?, ?)',
            ['SHB-10001', 'customer@example.com', 'Jane Doe'],
            new PDOException('UNIQUE constraint failed: orders.order_number')
        );
    }

    public function test_it_retries_once_on_a_duplicate_order_number_then_succeeds(): void
    {
        $attempts = 0;
        $placedOrder = new Order;

        $result = $this->invokeRetry(function () use (&$attempts, $placedOrder) {
            $attempts++;

            if ($attempts === 1) {
                throw $this->duplicateOrderNumberException();
            }

            return $placedOrder;
        });

        $this->assertSame($placedOrder, $result);
        $this->assertSame(2, $attempts);
    }

    public function test_it_gives_up_after_the_max_attempts(): void
    {
        $attempts = 0;

        $this->expectException(QueryException::class);

        try {
            $this->invokeRetry(function () use (&$attempts) {
                $attempts++;

                throw $this->duplicateOrderNumberException();
            }, 2);
        } finally {
            $this->assertSame(2, $attempts);
        }
    }

    public function test_it_does_not_retry_an_unrelated_database_error(): void
    {
        $attempts = 0;

        $this->expectException(QueryException::class);

        try {
            $this->invokeRetry(function () use (&$attempts) {
                $attempts++;

                throw new QueryException(
                    'sqlite',
                    'insert into "orders" ("order_number", "email", "shipping_name") values (?, ?, ?)',
                    ['SHB-10001', 'customer@example.com', null],
                    new PDOException('NOT NULL constraint failed: orders.shipping_name')
                );
            });
        } finally {
            $this->assertSame(1, $attempts);
        }
    }
}
