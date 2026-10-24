<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
 * Who the app believes about X-Forwarded-*: only the senders named in
 * TRUSTED_PROXIES (config/trustedproxy.php), and nobody by default. A test
 * request arrives from 127.0.0.1 unless REMOTE_ADDR says otherwise.
 */

beforeEach(function (): void {
    Route::get('/_proxy-probe', fn (Request $request): array => [
        'ip' => $request->ip(),
        'secure' => $request->isSecure(),
    ]);
});

it('ignores a forged X-Forwarded-For from a sender it does not trust', function (): void {
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
        ->withHeaders([
            'X-Forwarded-For' => '203.0.113.195',
            'X-Forwarded-Proto' => 'https',
        ])
        ->get('/_proxy-probe')
        ->assertOk()
        ->assertExactJson(['ip' => '198.51.100.20', 'secure' => false]);
});

it('trusts nobody when no proxy is configured, not even localhost', function (): void {
    config(['trustedproxy.proxies' => null]);

    $this->withHeaders(['X-Forwarded-For' => '203.0.113.195'])
        ->get('/_proxy-probe')
        ->assertExactJson(['ip' => '127.0.0.1', 'secure' => false]);
});

it('believes a configured proxy, detecting HTTPS and generating secure URLs', function (): void {
    config(['trustedproxy.proxies' => '10.0.0.0/8, 127.0.0.1']);

    $this->withHeaders([
        'X-Forwarded-Proto' => 'https',
        'X-Forwarded-For' => '203.0.113.195',
    ])
        ->get('/_proxy-probe')
        ->assertExactJson(['ip' => '203.0.113.195', 'secure' => true]);

    expect(url('/'))->toStartWith('https://');
});

it('still ignores a forged hop in front of a trusted proxy', function (): void {
    config(['trustedproxy.proxies' => '10.0.0.5']);

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
        ->withHeaders(['X-Forwarded-For' => '6.6.6.6, 198.51.100.20'])
        ->get('/_proxy-probe')
        ->assertJsonPath('ip', '198.51.100.20');
});
