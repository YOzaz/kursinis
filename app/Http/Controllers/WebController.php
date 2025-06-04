<?php

namespace App\Http\Controllers;

use App\Jobs\BatchAnalysisJob;
use App\Models\AnalysisJob;
use App\Services\PromptService;
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
        $recentJobs = AnalysisJob::orderBy('created_at', 'desc')->limit(5)->get();
        $promptService = app(PromptService::class);
        $standardPrompt = $promptService->getStandardRisenPrompt();
        
        return view('index', compact('recentJobs', 'standardPrompt'));
    }

    /**
     * Failo įkėlimas ir analizės paleidimas.
     */
    public function upload(Request $request)
    {
        $availableModels = collect(config('llm.models', []))->keys()->implode(',');
        
        $validator = Validator::make($request->all(), [
            'json_file' => 'required|file|mimetypes:application/json,text/plain|max:102400', // 100MB
            'models' => 'required|array|min:1',
            'models.*' => "required|string|in:{$availableModels}"
        ], [
            'json_file.required' => 'Prašome pasirinkti JSON failą.',
            'json_file.file' => 'Įkeltas failas nėra tinkamas.',
            'json_file.mimetypes' => 'Failas turi būti JSON formato.',
            'json_file.max' => 'Failo dydis negali viršyti 100MB.',
            'models.min' => 'Pasirinkite bent vieną modelį.',
            'models.required' => 'Pasirinkite bent vieną modelį.',
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

            // Apdoroti custom prompt
            $customPrompt = null;
            if ($request->has('custom_prompt_parts')) {
                $promptService = app(PromptService::class);
                $customParts = json_decode($request->input('custom_prompt_parts'), true);
                $customPrompt = $promptService->generateCustomRisenPrompt($customParts);
            } elseif ($request->has('custom_prompt')) {
                $customPrompt = $request->input('custom_prompt');
            }

            // Sukurti analizės darbą
            AnalysisJob::create([
                'job_id' => $jobId,
                'status' => AnalysisJob::STATUS_PENDING,
                'total_texts' => $totalTexts,
                'processed_texts' => 0,
                'name' => $request->input('name', 'Batch analizė'),
                'description' => $request->input('description'),
                'custom_prompt' => $customPrompt,
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