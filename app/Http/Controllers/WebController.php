<?php

namespace App\Http\Controllers;

use App\Jobs\BatchAnalysisJob;
use App\Models\AnalysisJob;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

/**
 * Web sąsajos kontroleris.
 * 
 * Valdomas minimali vartotojo sąsają failo įkėlimui ir progreso stebėjimui.
 */
class WebController extends Controller
{
    /**
     * Pagrindinis puslapis.
     */
    public function index()
    {
        return view('index');
    }

    /**
     * Failo įkėlimas ir analizės paleidimas.
     */
    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'json_file' => 'required|file|mimes:json|max:10240', // 10MB
            'models' => 'required|array|min:1',
            'models.*' => 'required|string|in:claude-4,gemini-2.5-pro,gpt-4.1'
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            // Perskaityti JSON failą
            $fileContent = file_get_contents($request->file('json_file')->getRealPath());
            $jsonData = json_decode($fileContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return back()->withErrors(['json_file' => 'Neteisingas JSON formato failas'])
                           ->withInput();
            }

            // Validuoti JSON struktūrą
            if (!$this->validateJsonStructure($jsonData)) {
                return back()->withErrors(['json_file' => 'JSON failas neatitinka reikalavimų struktūros'])
                           ->withInput();
            }

            $jobId = Str::uuid();
            $models = $request->input('models');
            $totalTexts = count($jsonData);

            // Sukurti analizės darbą
            AnalysisJob::create([
                'job_id' => $jobId,
                'status' => AnalysisJob::STATUS_PENDING,
                'total_texts' => $totalTexts,
                'processed_texts' => 0,
            ]);

            // Paleisti batch analizės darbą
            BatchAnalysisJob::dispatch($jobId, $jsonData, $models);

            Log::info('Analizė paleista per web sąsają', [
                'job_id' => $jobId,
                'total_texts' => $totalTexts,
                'models' => $models
            ]);

            return redirect()->route('progress', ['jobId' => $jobId])
                           ->with('success', 'Analizė sėkmingai paleista!');

        } catch (\Exception $e) {
            Log::error('Web failo įkėlimo klaida', [
                'error' => $e->getMessage()
            ]);

            return back()->withErrors(['upload' => 'Klaida paleidžiant analizę: ' . $e->getMessage()])
                       ->withInput();
        }
    }

    /**
     * Progreso stebėjimo puslapis.
     */
    public function progress(string $jobId)
    {
        $job = AnalysisJob::where('job_id', $jobId)->first();

        if (!$job) {
            return redirect()->route('home')->withErrors(['Analizės darbas nerastas']);
        }

        return view('progress', compact('job'));
    }

    /**
     * Validuoti JSON failo struktūrą.
     */
    private function validateJsonStructure(array $data): bool
    {
        if (!is_array($data)) {
            return false;
        }

        foreach ($data as $item) {
            if (!isset($item['id'], $item['data']['content'])) {
                return false;
            }

            // Patikrinti ar yra anotacijos ekspertų duomenims
            if (!isset($item['annotations']) || !is_array($item['annotations'])) {
                return false;
            }
        }

        return true;
    }
}