<?php

namespace App\Jobs;

use App\Models\Incident;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DetectSimilarIncidentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $incident;

    /**
     * Create a new job instance.
     */
    public function __construct(Incident $incident)
    {
        $this->incident = $incident;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $aiServiceUrl = config('services.ai_similarity.url', 'http://ai-similarity-service:8000');
        $textToAnalyze = $this->incident->title . " " . $this->incident->description;
        
        Log::info("=== START AI Processing for Incident #{$this->incident->id} ===");

        // 1. Analyse LLM (Priorité, Extraction)
        try {
            Log::debug("Sending to AI Analyze: {$aiServiceUrl}/analyze");
            $analyzeResponse = Http::timeout(120)->post("{$aiServiceUrl}/analyze", [
                'id' => $this->incident->id,
                'text' => $textToAnalyze
            ]);

            if ($analyzeResponse->successful()) {
                $analysis = $analyzeResponse->json()['analysis'];
                $this->incident->update([
                    'ai_site' => $analysis['site'] ?? null,
                    'ai_equipment' => $analysis['equipement'] ?? null,
                    'ai_category' => $analysis['categorie'] ?? null,
                    'ai_impacted_service' => $analysis['serviceImpacte'] ?? null,
                    'ai_priority' => $analysis['priorite'] ?? null,
                    'ai_priority_score' => $analysis['scorePriorite'] ?? null,
                    'ai_summary' => $analysis['resume'] ?? null,
                ]);
                Log::info("AI Analysis successful and saved.");
            } else {
                Log::error("AI Analysis Error: " . $analyzeResponse->body());
            }
        } catch (\Exception $e) {
            Log::error("AI Service Connection Failed (Analyze): " . $e->getMessage());
        }

        // 2. Indexer le nouvel incident dans Qdrant
        try {
            Log::debug("Sending to AI Index: {$aiServiceUrl}/index");
            $indexResponse = Http::timeout(30)->post("{$aiServiceUrl}/index", [
                'id' => $this->incident->id,
                'text' => $textToAnalyze,
                'metadata' => [
                    'city' => $this->incident->city,
                    'client_name' => $this->incident->client_name,
                ]
            ]);
            
            if (!$indexResponse->successful()) {
                Log::error("AI Index Error: " . $indexResponse->body());
            } else {
                Log::info("AI Indexing Successful.");
            }
        } catch (\Exception $e) {
            Log::error("AI Service Connection Failed (Index): " . $e->getMessage());
        }

        // 3. Chercher des incidents similaires
        try {
            Log::debug("Sending to AI Search: {$aiServiceUrl}/search");
            $response = Http::timeout(60)->post("{$aiServiceUrl}/search", [
                'text' => $textToAnalyze,
                'threshold' => 0.35,
                'limit' => 5
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $matches = $data['matches'] ?? [];
                Log::info("AI Search results: " . count($matches) . " items found.");
                
                foreach ($matches as $match) {
                    $similarId = $match['id'];
                    
                    // Sécurité : Vérifier si l'incident existe encore en base de données
                    if ($similarId == $this->incident->id) continue;
                    
                    if (!\App\Models\Incident::where('id', $similarId)->exists()) {
                        Log::warning("AI found similar incident #{$similarId} but it does not exist in DB. Skipping.");
                        continue;
                    }

                    Log::info("MATCH FOUND: #{$this->incident->id} <-> #{$similarId} (Score: {$match['score']})");

                    \DB::table('incident_similarities')->updateOrInsert(
                        ['incident_id' => $this->incident->id, 'similar_incident_id' => $similarId],
                        ['score' => $match['score'], 'created_at' => now(), 'updated_at' => now()]
                    );
                }
            } else {
                Log::error("AI Search Error: " . $response->body());
            }
        } catch (\Exception $e) {
            Log::error("AI Service Connection Failed (Search): " . $e->getMessage());
        }
        
        Log::info("=== END AI Processing for Incident #{$this->incident->id} ===");
    }
}
