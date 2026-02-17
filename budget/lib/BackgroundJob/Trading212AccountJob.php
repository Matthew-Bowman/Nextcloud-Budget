<?php

declare(strict_types=1);

namespace OCA\Budget\BackgroundJob;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\BackgroundJob\TimedJob;
use OCP\IDBConnection;
use OCP\Server;
use Psr\Log\LoggerInterface;

class Trading212AccountJob extends TimedJob
{
    private IDBConnection $db;
    private LoggerInterface $logger;

    public function __construct(ITimeFactory $time)
    {
        parent::__construct($time);

        // Run once per day
        $this->setInterval(24 * 60 * 60);
        $this->setTimeSensitivity(\OCP\BackgroundJob\IJob::TIME_INSENSITIVE);
    }

    protected function run($argument): void
    {

        $server = \OC::$server;

        $this->db  = Server::get(IDBConnection::class);
        $this->logger = Server::get(LoggerInterface::class);

        $table = 'budget_accounts';

        // Limit each run so you don’t overload remote services
        $batchSize = 50;

        $rows = $this->fetchCandidateRows($table, $batchSize);

        if (count($rows) === 0) {
            return;
        }

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

        $this->logger->debug('Processing result', [
            'app' => 'budget',
            'data' => $result
        ]);

        $rows = [];
        while ($row = $result->fetch()) {
            $rows[] = $row;
        }

        $result->closeCursor();

        return $rows;
    }

    private function doLogic(string $apiKey, $secretKey): string
    {
        // Put your transformation here
        return '420.69';
    }
}
