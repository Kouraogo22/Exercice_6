<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class ETLService
{
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
                    'synced_at' => now(),
                    'created_at' => $client->created_at,
                    'updated_at' => $client->updated_at,
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
                // Publier dans un système de log simulant Kafka
                $this->publishToKafkaSimulation('client-sync', $data);

                // Insérer/Mettre à jour dans la base secondaire
                // Utiliser l'email comme clé unique car c'est unique dans votre schéma
                DB::connection('mysql_second')->table('clients')->updateOrInsert(
                    ['email' => $data['email']], // Condition de recherche
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
                        'synced_at' => $data['synced_at'],
                        'created_at' => $data['created_at'],
                        'updated_at' => now(), // Toujours mettre à jour la date de modification
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
     * Simuler la publication dans Kafka (pour Windows sans extension rdkafka)
     * En production, ceci serait remplacé par une vraie connexion Kafka
     */
    private function publishToKafkaSimulation($topic, $data)
    {
        try {
            // Option 1: Logger les messages (simulation)
            Log::channel('kafka')->info("Kafka Topic: {$topic}", $data);

            // Option 2: Si Kafka REST Proxy est disponible
            // Http::post('http://localhost:8082/topics/' . $topic, [
            //     'records' => [
            //         ['value' => $data]
            //     ]
            // ]);

            // Option 3: Écrire dans un fichier pour traitement ultérieur
            $logFile = storage_path("logs/kafka_{$topic}.log");
            file_put_contents(
                $logFile,
                json_encode($data) . PHP_EOL,
                FILE_APPEND
            );

        } catch (\Exception $e) {
            Log::error("Kafka Publish Error: " . $e->getMessage());
        }
    }

    /**
     * Formater le numéro de téléphone
     */
    private function formatTelephone($telephone)
    {
        if (!$telephone) return null;

        // Nettoyer et formater le téléphone
        $telephone = preg_replace('/[^0-9+]/', '', $telephone);

        return $telephone;
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
