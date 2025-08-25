<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyShopifyEmbed
{
    public function handle(Request $request, Closure $next)
    {
        // Must be an embedded request
        if ($request->get('embedded') !== '1') {
            abort(403, 'Access denied: Not an embedded Shopify request.');
        }

        // Basic param check
        if (!$request->has(['shop', 'hmac'])) {
            abort(403, 'Missing Shopify parameters.');
        }

        // Validate HMAC
        $params = $request->except('hmac');
        ksort($params);

        $queryString = urldecode(http_build_query($params));
        $calculatedHmac = hash_hmac('sha256', $queryString, env('SHOPIFY_API_SECRET'), false);

        if (!hash_equals($request->get('hmac'), $calculatedHmac)) {
            abort(403, 'Invalid Shopify HMAC.');
        }

        return $next($request);
    }
}
