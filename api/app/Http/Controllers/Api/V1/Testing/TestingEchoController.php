<?php

namespace App\Http\Controllers\Api\V1\Testing;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TestingEchoController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        if ($request->query('fail') === '500') {
            abort(500, 'Simulated 500 error');
        }

        DB::statement('CREATE TABLE IF NOT EXISTS _testing_echo_counter (id INT PRIMARY KEY, count INT NOT NULL)');
        DB::statement('INSERT INTO _testing_echo_counter (id, count) VALUES (1, 1) ON CONFLICT (id) DO UPDATE SET count = _testing_echo_counter.count + 1');

        $count = (int) DB::table('_testing_echo_counter')->where('id', 1)->value('count');

        return response()->json([
            'count' => $count,
            'body' => $request->all(),
        ]);
    }
}
