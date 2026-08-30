<?php

namespace Tests\Feature;

use App\Models\Transaction;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    public function test_login_is_rate_limited_against_bruteforce(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/admin/login', [
                'username' => 'gema',
                'password' => 'salah',
            ])->assertRedirect();
        }

        // Percobaan ke-6 harus ditolak rate limiter (429).
        $this->post('/admin/login', [
            'username' => 'gema',
            'password' => 'salah',
        ])->assertStatus(429);
    }

    public function test_sqli_payload_cannot_bypass_login(): void
    {
        Http::fake();

        $this->post('/admin/login', [
            'username' => "' OR 1=1 --",
            'password' => "x' OR '1'='1",
        ])->assertRedirect();

        $this->assertFalse(session()->has('admin_authenticated'));
    }

    public function test_blade_escapes_user_content_against_xss(): void
    {
        $trx = new Transaction([
            'transaction_date' => '2026-08-01',
            'name' => '<img src=x onerror=alert(1)>',
            'payment_method' => '<script>alert(2)</script>',
            'description' => '<script>alert("xss")</script>',
            'type' => 'income',
            'amount' => 1000,
            'category' => 'kas',
            'months' => null,
        ]);

        $paginator = new LengthAwarePaginator(
            collect([$trx]),
            1,
            10,
            1,
            ['path' => '/transactions/table']
        );

        $html = view('public._transactions', [
            'transactions' => $paginator,
            'isAdmin' => false,
        ])->render();

        $this->assertStringNotContainsString('<script>alert(2)</script>', $html);
        $this->assertStringNotContainsString('<script>alert("xss")</script>', $html);
        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_valid_admin_login_succeeds_and_regenerates_session(): void
    {
        Http::fake();

        $username = (string) config('app.admin_username');
        $password = (string) config('app.admin_password');

        $this->assertNotEmpty($username, 'ADMIN_USERNAME belum dikonfigurasi.');
        $this->assertNotEmpty($password, 'ADMIN_PASSWORD belum dikonfigurasi.');

        $this->post('/admin/login', [
            'username' => $username,
            'password' => $password,
        ])->assertRedirect('/');

        $this->assertTrue(session()->has('admin_authenticated'));
    }
}
