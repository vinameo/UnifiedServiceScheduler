<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * FR-08.5: /health/live answers while PHP-FPM runs; /health/ready only when the database answers.
 */
final class HealthController extends Controller
{
    public function live(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }

    public function ready(): JsonResponse
    {
        try {
            DB::select('select 1');
            $database = 'ok';
        } catch (Throwable $e) {
            report($e);
            $database = 'fail';
        }

        $ok = $database === 'ok';

        return response()->json(
            ['status' => $ok ? 'ok' : 'fail', 'checks' => ['database' => $database]],
            $ok ? 200 : 503,
        );
    }
}
