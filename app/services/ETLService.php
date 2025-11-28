<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class ETLService
{
    private $kafkaRestUrl;

    public function __construct()
    {
        $this->kafkaRestUrl = env('KAFKA_REST_PROXY_URL', 'http://localhost:8082');
    }

    /**
     * Extraire les données de la base principale
     */
    public function extract()
    {
        try {
            // Récupérer les clients non synchronisés
            $clients = Client::on('mysql')
                ->whereNull('synced_at')
                ->orWhereColumn('synced_at', '<', 'updated_at')
                ->get();

            Log::info("ETL Extract: " . $clients->count() . " clients à synchroniser");

            return $clients;
        } catch (\Exception $e) {
            Log::error("ETL Extract Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Transformer les données
     */
    public function transform($clients)
    {
        try {
            $transformedData = [];

            foreach ($clients as $client) {
                $transformedData[] = [
                    'id' => $client->id,
                    'nom' => $client->nom,
                    'prenom' => $client->prenom,
                    'email' => strtolower($client->email),
                    'telephone' => $this->formatTelephone($client->telephone),
                    'adresse' => $client->adresse,
                    'ville' => $client->ville,
                    'code_postal' => $client->code_postal,
                    'pays' => $client->pays ?? 'Burkina Faso',
                    'statut' => $client->statut,
                    'synced_at' => now()->toIso8601String(),
                    'created_at' => $client->created_at->toIso8601String(),
                    'updated_at' => $client->updated_at->toIso8601String(),
                ];
            }

            Log::info("ETL Transform: " . count($transformedData) . " enregistrements transformés");

            return $transformedData;
        } catch (\Exception $e) {
            Log::error("ETL Transform Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Charger les données dans la base secondaire
     */
    public function load($transformedData)
    {
        try {
            $loadedCount = 0;

            foreach ($transformedData as $data) {
                // Publier dans Kafka via REST Proxy
                $this->publishToKafka('client-sync', $data);

                // Insérer/Mettre à jour dans la base secondaire
                DB::connection('mysql_second')->table('clients')->updateOrInsert(
                    ['email' => $data['email']],
                    [
                        'nom' => $data['nom'],
                        'prenom' => $data['prenom'],
                        'email' => $data['email'],
                        'telephone' => $data['telephone'],
                        'adresse' => $data['adresse'],
                        'ville' => $data['ville'],
                        'code_postal' => $data['code_postal'],
                        'pays' => $data['pays'],
                        'statut' => $data['statut'],
                        'synced_at' => now(),
                        'created_at' => $data['created_at'],
                        'updated_at' => now(),
                    ]
                );

                // Marquer comme synchronisé dans la base principale
                $client = Client::on('mysql')->find($data['id']);
                if ($client) {
                    $client->synced_at = now();
                    $client->save();
                }

                $loadedCount++;
            }

            Log::info("ETL Load: " . $loadedCount . " enregistrements chargés");

            return $loadedCount;
        } catch (\Exception $e) {
            Log::error("ETL Load Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Processus ETL complet
     */
    public function runETL()
    {
        try {
            Log::info("ETL Process Started");

            // Extract
            $clients = $this->extract();

            if ($clients->isEmpty()) {
                Log::info("ETL Process: Aucun client à synchroniser");
                return [
                    'success' => true,
                    'message' => 'Aucun client à synchroniser',
                    'count' => 0
                ];
            }

            // Transform
            $transformedData = $this->transform($clients);

            // Load
            $loadedCount = $this->load($transformedData);

            Log::info("ETL Process Completed: " . $loadedCount . " clients synchronisés");

            return [
                'success' => true,
                'message' => 'Synchronisation réussie',
                'count' => $loadedCount
            ];
        } catch (\Exception $e) {
            Log::error("ETL Process Error: " . $e->getMessage());

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'count' => 0
            ];
        }
    }

    /**
     * Publier un message dans Kafka via REST Proxy
     */
    private function publishToKafka($topic, $data)
    {
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/vnd.kafka.json.v2+json',
            ])->post("{$this->kafkaRestUrl}/topics/{$topic}", [
                'records' => [
                    [
                        'value' => $data
                    ]
                ]
            ]);

            if ($response->successful()) {
                Log::info("Kafka Message Published", [
                    'topic' => $topic,
                    'data' => $data
                ]);
            } else {
                Log::warning("Kafka Publish Failed", [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
            }

            // Écrire aussi dans un fichier de backup
            $logFile = storage_path("logs/kafka_{$topic}.log");
            file_put_contents(
                $logFile,
                json_encode($data) . PHP_EOL,
                FILE_APPEND
            );

        } catch (\Exception $e) {
            Log::error("Kafka Publish Error: " . $e->getMessage());

            // Fallback: écrire dans un fichier
            $logFile = storage_path("logs/kafka_{$topic}.log");
            file_put_contents(
                $logFile,
                json_encode($data) . PHP_EOL,
                FILE_APPEND
            );
        }
    }

    /**
     * Consommer les messages Kafka (pour le consumer)
     */
    public function consumeKafkaMessages($topic = 'client-sync', $limit = 10)
    {
        try {
            // Créer un consumer group
            $consumerGroup = 'laravel-etl-consumer';
            $consumerInstance = 'laravel-instance-' . time();

            // Créer une instance de consumer
            $response = Http::post("{$this->kafkaRestUrl}/consumers/{$consumerGroup}", [
                'name' => $consumerInstance,
                'format' => 'json',
                'auto.offset.reset' => 'earliest'
            ]);

            if (!$response->successful()) {
                Log::error("Failed to create Kafka consumer", [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return [];
            }

            $consumerData = $response->json();
            $baseUri = $consumerData['base_uri'];

            // S'abonner au topic
            Http::post("{$baseUri}/subscription", [
                'topics' => [$topic]
            ]);

            // Consommer les messages
            $messagesResponse = Http::get("{$baseUri}/records", [
                'timeout' => 3000,
                'max_bytes' => 300000
            ]);

            $messages = $messagesResponse->json();

            // Fermer le consumer
            Http::delete($baseUri);

            return $messages ?? [];

        } catch (\Exception $e) {
            Log::error("Kafka Consume Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Formater le numéro de téléphone
     */
    private function formatTelephone($telephone)
    {
        if (!$telephone) return null;
        return preg_replace('/[^0-9+]/', '', $telephone);
    }

    /**
     * Formater l'adresse complète
     */
    private function formatAdresse($client)
    {
        $parts = array_filter([
            $client->adresse,
            $client->code_postal,
            $client->ville,
            $client->pays
        ]);

        return implode(', ', $parts) ?: null;
    }
}
