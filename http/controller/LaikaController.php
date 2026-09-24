<?php declare(strict_types=1);

namespace RatMD\Laika\Http\Controller;

use Flash;
use Markdown;
use Cms\Classes\PageManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use October\Rain\Support\Facades\Site;

class LaikaController
{
    /**
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function resolveLink(Request $request): RedirectResponse
    {
        $path = $request->query('path');
        if (!is_string($path) || trim($path) === '') {
            Flash::add('error', 'The provided link is missing or has malformed parameters.');
            return redirect('/');
        }

        $address = base64_decode($path, true);
        if ($address === false || trim($address) === '') {
            Flash::add('error', 'The provided link is not properly encoded.');
            return redirect('/');
        }

        $context = $request->query('context');
        if (!is_string($context) || $context === '' || strlen($context) > 4096) {
            Flash::add('error', 'The provided site context is missing or malformed.');
            return redirect('/');
        }

        try {
            [$siteId, $host] = array_pad(explode('|', Crypt::decryptString($context), 2), 2, null);
        } catch (\Throwable) {
            Flash::add('error', 'The provided site context is invalid.');
            return redirect('/');
        }

        if (
            !is_string($siteId) || $siteId === '' ||
            !is_string($host) || !hash_equals($host, strtolower($request->getHost()))
        ) {
            Flash::add('error', 'The provided site context is invalid.');
            return redirect('/');
        }

        $site = Site::getSiteFromId($siteId);
        if (!$site || !$site->is_enabled) {
            Flash::add('error', 'The provided site context is unavailable.');
            return redirect('/');
        }

        Site::applyActiveSite($site);

        $result = PageManager::url($address);
        if (empty($result)) {
            Flash::add('error', 'The requested destination does not exist.');
            return redirect('/');
        } else {
            return redirect($result);
        }
    }

    /**
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function filterContent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'filter'            => ['required', 'in:md,md_safe,md_clean,md_indent'],
            'payload'           => ['required', 'array'],
            'payload.content'   => ['required', 'string'],
        ]);

        // Parse Content
        $result = null;
        try {
            if ($data['filter'] === 'md') {
                $result = Markdown::parse($data['payload']['content'] ?? '');
            } else if ($data['filter'] === 'md_safe') {
                $result = Markdown::parseSafe($data['payload']['content'] ?? '');
            } else if ($data['filter'] === 'md_clean') {
                $result = Markdown::parseClean($data['payload']['content'] ?? '');
            } else if ($data['filter'] === 'md_indent') {
                $result = Markdown::parseIndent($data['payload']['content'] ?? '');
            }
        } catch (\Throwable $exc) {
            return response()->json([
                'status'    => 'error',
                'message'   => $exc->getMessage(),
                'details'   => app()->hasDebugModeEnabled() && !app()->isProduction() ? $exc->getTrace() : null
            ], 422);
        }

        // Respond
        return response()->json([
            'status'    => 'success',
            'result'    => [
                'filter'    => $data['filter'],
                'content'   => $result
            ]
        ], 200);
    }
}
