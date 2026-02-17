<?php declare(strict_types=1);

namespace RatMD\Laika\Http;

use Cms\Classes\Controller;
use Cms\Classes\Page;
use Illuminate\Http\Request;
use RatMD\Laika\Services\Context;
use RatMD\Laika\Services\ContextResolver;
use RatMD\Laika\Services\Payload;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class Responder
{
    /**
     *
     * @param Request $request
     * @param Payload $payload
     * @return void
     */
    public function __construct(
        protected Request $request,
        protected Payload $payload
    ) { }

    /**
     *
     * @return string
     */
    static public function createToken()
    {
        $expires = time() + 15 * 60;
        $nonce = bin2hex(random_bytes(16));
        $secret = config('app.key');

        return csrf_token() . '|' . base64_encode(json_encode([
            'exp'   => $expires,
            'nonce' => $nonce,
            'sig'   => hash_hmac('sha256', $expires . ':' . $nonce, $secret),
        ]));
    }

    /**
     *
     * @param Controller $controller
     * @param Page $page
     * @param string $url
     * @param mixed $result
     * @return null
     */
    public function respond(Controller $controller, Page $page, string $url, mixed $result)
    {
        if (!$this->request->header('X-Laika')) {
            return null;
        }

        if ($result instanceof SymfonyResponse) {
            return $this->transformSymfonyResponse($result);
        }

        // Set Context
        $resolver = app(ContextResolver::class);
        if (!$resolver->has()) {
            $resolver->set(Context::createFromController($controller, $page));
        }

        // Handle specific AJAX requests
        $request = $controller->getAjaxRequest();
        if ($request->hasAjaxHandler()) {
            return null; // @todo WiP
        }

        // Laika Response
        $payload = $this->payload->toArray();
        $only = $this->flattenLeafKeys($payload);

        return response()->json($payload, 200, [
            'Vary'          => 'X-Laika',
            'X-Laika'       => '1',
            'X-Laika-Only'  => implode(',', $only),
        ]);
    }

    /**
     * Flatten to leaf dot-keys only (no intermediate nodes).
     * @param array $data
     * @param string $prefix
     * @return array
     */
    protected function flattenLeafKeys(array $data, string $prefix = ''): array
    {
        $keys = [];

        foreach ($data as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;

            if (is_array($value)) {
                if ($value === []) {
                    $keys[] = $path;
                    continue;
                }

                $childKeys = $this->flattenLeafKeys($value, $path);
                if (empty($childKeys)) {
                    $keys[] = $path;
                } else {
                    $keys = array_merge($keys, $childKeys);
                }

                continue;
            }

            $keys[] = $path;
        }

        return $keys;
    }

    /**
     *
     * @param array $data
     * @param string $prefix
     * @return array
     */
    protected function flattenDotKeys(array $data, string $prefix = ''): array
    {
        $keys = [];

        foreach ($data as $key => $value) {
            $path = $prefix === '' ? (string)$key : $prefix . '.' . $key;
            $keys[] = $path;

            if (is_array($value)) {
                $keys = array_merge($keys, $this->flattenDotKeys($value, $path));
            }
        }

        return $keys;
    }

    /**
     *
     * @param SymfonyResponse $response
     * @return SymfonyResponse
     */
    protected function transformSymfonyResponse(SymfonyResponse $response): SymfonyResponse
    {
        if ($response->isRedirection()) {
            $location = $response->headers->get('Location');

            return response('', 409, [
                'X-Laika' => '1',
                'X-Laika-Location' => $location,
            ]);
        } else {
            return $response;
        }
    }
}
