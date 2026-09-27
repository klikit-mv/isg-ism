<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * A business rule was broken. Rendered as a flash error, or 422 JSON.
 */
class ScoutException extends RuntimeException
{
    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage()], 422);
        }

        return back()->withInput($request->except(['pin', 'pin_confirmation', 'current_pin', 'proof']))->with('error', $this->getMessage());
    }
}
