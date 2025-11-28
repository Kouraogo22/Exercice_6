<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class KafkaMonitorCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kafka:monitor';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitorer l\'état de Kafka et afficher les statistiques';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $kafkaRestUrl = env('KAFKA_REST_PROXY_URL', 'http://localhost:8082');

        $this->info("📊 Monitoring Kafka");
        $this->newLine();

        try {
            // Vérifier la connexion
            $this->info("🔍 Vérification de la connexion...");
            $response = Http::get("{$kafkaRestUrl}/topics");

            if ($response->successful()) {
                $this->info("✅ Connexion Kafka OK");
                $this->newLine();

                $topics = $response->json();

                $this->info("📋 Topics disponibles:");
                $this->table(['Topic'], array_map(fn($topic) => [$topic], $topics));

                // Vérifier le topic client-sync
                if (in_array('client-sync', $topics)) {
                    $this->info("✅ Topic 'client-sync' trouvé");

                    // Obtenir les détails du topic
                    $topicDetails = Http::get("{$kafkaRestUrl}/topics/client-sync");

                    if ($topicDetails->successful()) {
                        $details = $topicDetails->json();
                        $this->newLine();
                        $this->info("📝 Détails du topic 'client-sync':");
                        $this->line("  • Nom: " . ($details['name'] ?? 'N/A'));
                        $this->line("  • Partitions: " . count($details['partitions'] ?? []));
                    }
                } else {
                    $this->warn("⚠️  Topic 'client-sync' non trouvé");
                    $this->info("💡 Il sera créé automatiquement lors de la première publication");
                }

            } else {
                $this->error("❌ Impossible de se connecter à Kafka");
                $this->error("Status: " . $response->status());
            }

        } catch (\Exception $e) {
            $this->error("❌ Erreur: " . $e->getMessage());
            $this->newLine();
            $this->warn("💡 Assurez-vous que Docker est démarré:");
            $this->line("   docker-compose up -d");
        }

        $this->newLine();
        return Command::SUCCESS;
    }
}
