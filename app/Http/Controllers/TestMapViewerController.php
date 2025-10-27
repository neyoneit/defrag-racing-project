<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class TestMapViewerController extends Controller
{
    public function getMapData(Request $request)
    {
        // Get map name from query parameter, default to pado
        $mapName = $request->query('map', 'pado');

        $pk3Path = storage_path("app/{$mapName}.pk3");

        if (!file_exists($pk3Path)) {
            return response()->json(['error' => 'Map file not found: ' . $mapName], 404);
        }

        // Run Python script to parse BSP and return JSON
        $pythonScript = base_path('scripts/parse_bsp_to_json.py');

        if (!file_exists($pythonScript)) {
            return response()->json(['error' => 'Parser script not found'], 500);
        }

        $result = Process::run("python3 {$pythonScript} {$pk3Path}");

        if ($result->failed()) {
            return response()->json(['error' => 'Failed to parse BSP', 'output' => $result->errorOutput()], 500);
        }

        $mapData = json_decode($result->output(), true);

        if (!$mapData) {
            return response()->json(['error' => 'Invalid JSON output'], 500);
        }

        return response()->json($mapData);
    }
}
