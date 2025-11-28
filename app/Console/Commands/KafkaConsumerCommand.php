<?php

namespace App\Console\Commands;

use App\Services\ETLService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class KafkaConsumerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kafka:consume
                            {--topic=client-sync : Le topic Kafka à consommer}
                            {--timeout=30 : Timeout en secondes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Consumer Kafka qui écoute les messages et traite la synchronisation';

    protected $etlService;

    /**
     * Create a new command instance.
     */
    public function __construct(ETLService $etlService)
    {
        parent::__construct();
        $this->etlService = $etlService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $topic = $this->option('topic');
        $timeout = (int) $this->option('timeout');
        $kafkaRestUrl = env('KAFKA_REST_PROXY_URL', 'http://localhost:8082');

        $this->info("🎧 Démarrage du consumer Kafka...");
        $this->info("📡 Topic: {$topic}");
        $this->info("⏱️  Timeout: {$timeout}s");
        $this->newLine();

        // Lire depuis les logs fichiers (plus simple et plus fiable)
        $logFile = storage_path("logs/kafka_{$topic}.log");

        if (!file_exists($logFile)) {
            $this->warn("⚠️  Aucun message dans le log Kafka pour le topic '{$topic}'");
            $this->info("💡 Lancez d'abord: php artisan clients:sync-etl");
            return Command::SUCCESS;
        }

        $this->info("📖 Lecture des messages depuis le log Kafka...");
        $this->newLine();

        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $messageCount = count($lines);

        if ($messageCount === 0) {
            $this->warn("⚠️  Aucun message trouvé");
            return Command::SUCCESS;
        }

        $this->info("📨 {$messageCount} messages trouvés");
        $this->newLine();

        $displayCount = min(10, $messageCount); // Afficher max 10 derniers messages
        $lastMessages = array_slice($lines, -$displayCount);

        foreach ($lastMessages as $index => $line) {
            try {
                $message = json_decode($line, true);
                if ($message) {
                    $this->processMessage($message);
                }
            } catch (\Exception $e) {
                $this->warn("⚠️  Message mal formé ignoré");
            }
        }

        if ($messageCount > $displayCount) {
            $this->newLine();
            $this->comment("💡 {$messageCount} messages au total (affichage des {$displayCount} derniers)");
        }

        $this->newLine();
        $this->info("✅ Consumer terminé");
        return Command::SUCCESS;
    }

    /**
     * Traiter un message Kafka
     */
    private function processMessage($message)
    {
        try {
            $email = $message['email'] ?? 'N/A';
            $nom = $message['nom'] ?? '';
            $prenom = $message['prenom'] ?? '';

            $this->line("┌─────────────────────────────────────");
            $this->info("│  Client: {$nom} {$prenom}");
            $this->line("│  Email: {$email}");

            if (isset($message['telephone'])) {
                $this->line("│ Tél: {$message['telephone']}");
            }

            if (isset($message['statut'])) {
                $this->line("│  Statut: {$message['statut']}");
            }

            if (isset($message['synced_at'])) {
                $this->line("│ Sync: {$message['synced_at']}");
            }

            $this->line("└─────────────────────────────────────");
            $this->info(" Message traité avec succès");
            $this->newLine();

        } catch (\Exception $e) {
            $this->error(" Erreur de traitement: " . $e->getMessage());
            Log::error("Message Processing Error", [
                'message' => $message,
                'error' => $e->getMessage()
            ]);
        }
    }
}
