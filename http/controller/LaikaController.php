<?php declare(strict_types=1);

namespace RatMD\Laika\Http\Controller;

use Markdown;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LaikaController
{
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
