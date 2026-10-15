<?php

test('request behind trusted proxy detects HTTPS and generates secure URLs', function (): void {
    $response = $this->withHeaders([
        'X-Forwarded-Proto' => 'https',
        'X-Forwarded-For' => '203.0.113.195',
    ])->get('/up');

    $response->assertOk();

    // The request() helper in tests may not reflect the proxied request.
    // Use the response's request or assert on generated URLs instead.
    $this->assertStringStartsWith('https://', url('/'));
});
