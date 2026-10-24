<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted proxies
    |--------------------------------------------------------------------------
    |
    | The addresses whose X-Forwarded-* headers are believed, as a
    | comma-separated list of IPs or CIDRs. Laravel's TrustProxies middleware
    | reads this on every request.
    |
    | Empty by default, which trusts nobody: in production Caddy is the public
    | edge and talks FastCGI to PHP-FPM, so REMOTE_ADDR is already the client's
    | own address and HTTPS arrives as Caddy's HTTPS=on parameter. Trusting a
    | sender here lets it choose the IP that rate limits and the login throttle
    | count against, so only name a proxy that really sits in front of the app,
    | such as a load balancer or CDN added later. Never `*`.
    |
    */

    'proxies' => env('TRUSTED_PROXIES') ?: null,

];
