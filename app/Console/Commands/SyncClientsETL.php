<?php

namespace App\Console\Commands;

use App\Services\ETLService;
use Illuminate\Console\Command;

class SyncClientsETL extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clients:sync-etl {--force : Force la synchronisation même sans nouveaux clients}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronise les clients entre les deux bases de données via ETL Kafka';

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
        $this->info('Démarrage du processus ETL...');
        $this->newLine();

        $startTime = microtime(true);

        try {
            // Lancer le processus ETL
            $result = $this->etlService->runETL();

            $endTime = microtime(true);
            $duration = round($endTime - $startTime, 2);

            if ($result['success']) {
                $this->info("{$result['message']}");
                $this->info("Nombre de clients synchronisés: {$result['count']}");
                $this->info("Temps d'exécution: {$duration} secondes");

                return Command::SUCCESS;
            } else {
                $this->error("Erreur: {$result['message']}");
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error("Erreur critique: " . $e->getMessage());
            $this->error($e->getTraceAsString());

            return Command::FAILURE;
        }
    }
}
