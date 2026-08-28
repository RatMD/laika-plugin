<?php declare(strict_types=1);

namespace RatMD\Laika\Http\Middleware;

use Illuminate\Http\Request;
use RatMD\Laika\Services\ContextResolver;
use RatMD\Laika\Services\Payload;
use Symfony\Component\HttpFoundation\Response;

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
        $isLaikaRequest = $request->header('X-Laika', '0') === '1';
        $debug = app()->hasDebugModeEnabled();

        if ($isLaikaRequest) {
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

        /** @var Response $response */
        $response = $next($request);

        $isAjaxResponse = $response instanceof Response && $response->headers->has('X-AJAX-RESPONSE');
        if ($isLaikaRequest && $isAjaxResponse && app(ContextResolver::class)->has()) {
            $this->appendLaikaPayload($response);
        }

        return $response;
    }

    /**
     * Append the post-handler LAIKA state to an October AJAX response.
     * @param Response $response
     * @return void
     */
    protected function appendLaikaPayload(Response $response): void
    {
        try {
            $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
            if (!is_array($content)) {
                return;
            }

            $content['__laika'] = app(Payload::class)->toArray();
            $response->setContent(json_encode(
                $content,
                \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE
            ));
            $response->headers->remove('Content-Length');
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
