<?php declare(strict_types=1);

namespace RatMD\Laika\Http\Middleware;

use Illuminate\Http\Request;

class LaikaTokenMiddleware
{
    /**
     * Handle an incoming request.
     * @param Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle(Request $request, \Closure $next)
    {
        $laikaRequest = $request->header('X-Laika', '0');
        $debug = app()->hasDebugModeEnabled();

        if ($laikaRequest === '1') {
            $token = (string) $request->header('X-Laika-Token', '');
            abort_if(empty($token), 401, $debug ? 'X-Laika-Token is missing' : '');

            $encoded = base64_decode($token);
            abort_if($encoded === false, 401, $debug ? 'X-Laika-Token corrupt' : '');

            $decoded = json_decode($encoded, true);
            abort_if(!is_array($decoded), 401, $debug ? 'X-Laika-Token invalid' : '');
            abort_if(empty($decoded['exp']), 401, $debug ? 'X-Laika-Token expiration missing' : '');
            abort_if(empty($decoded['nonce']), 401, $debug ? 'X-Laika-Token nonce missing' : '');
            abort_if(empty($decoded['sig']), 401, $debug ? 'X-Laika-Token signature missing' : '');
            abort_if((int) $decoded['exp'] < time(), 401, $debug ? 'X-Laika-Token expired' : '');

            $secret = config('app.key');
            $expected = hash_hmac('sha256', $decoded['exp'] . ':' . $decoded['nonce'], $secret);
            abort_if(
                !hash_equals($expected, (string) $decoded['sig']),
                401,
                $debug ? 'X-Laika-Token invalid signature' : ''
            );
        }

        return $next($request);
    }
}
