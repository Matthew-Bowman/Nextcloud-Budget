<?php

declare(strict_types=1);

namespace OCA\budget\BackgroundJob;

use OCP\BackgroundJob\TimedJob;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Http\Client\IClientService;
use OCP\ILogger;

class Trading212AccountJob extends TimedJob
{
    private IDBConnection $db;
    private IClientService $clientService;
    private ILogger $logger;

    public function __construct()
    {
        parent::__construct();

        $this->setInterval(10);
    }

    protected function run($argument): void
    {

        $server = \OC::$server;

        $db = $server->get(IDBConnection::class);
        $clientService = $server->get(IClientService::class);
        $logger = $server->get(ILogger::class);

        $table = 'budget_accounts';

        // Limit each run so you don’t overload remote services
        $batchSize = 50;

        $rows = $this->fetchCandidateRows($table, $batchSize);

        if (count($rows) === 0) {
            return;
        }

        $client = $this->clientService->newClient();

        foreach ($rows as $row) {
            $id = (int)$row['id'];

            try {

                // --- Your logic using a column ---
                $trading212ApiKeyId = (string)$row['trading212_api_key_id'];
                $trading212ApiSecretKey = (string)$row['trading212_api_secret_key'];
                $computed = $this->doLogic($trading212ApiKeyId, $trading212ApiSecretKey);

                $qb = $this->db->getQueryBuilder();

                $qb->update($table)
                    ->set(
                        'balance',
                        $qb->createNamedParameter($computed, IQueryBuilder::PARAM_STR)
                    )
                    ->where(
                        $qb->expr()->eq(
                            'id',
                            $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)
                        )
                    );
            } catch (\Throwable $e) {
                $this->logger->error('Background job failed for row ' . $id . ': ' . $e->getMessage(), [
                    'app' => 'budget',
                    'exception' => $e,
                ]);
            }
        }
    }

    /**
     * Fetch rows where your condition is met (example: status = 'pending')
     */
    private function fetchCandidateRows(string $table, int $limit): array
    {
        $qb = $this->db->getQueryBuilder();

        $qb->select('id', 'balance', 'type', 'trading212_api_key_id', 'trading212_api_secret_key')
            ->from($table)
            ->where($qb->expr()->eq('type', $qb->createNamedParameter('investment-tracked_212')))
            ->setMaxResults($limit);

        $result = $qb->executeQuery();
        $rows = $result->fetchAllAssociative();
        $result->closeCursor();

        return $rows;
    }

    private function doLogic(string $apiKey, $secretKey): string
    {
        // Put your transformation here
        return '420.69';
    }
}
