<?php

declare(strict_types=1);

namespace OCA\Budget\BackgroundJob;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IClient;
use OCP\BackgroundJob\TimedJob;
use OCP\IDBConnection;
use OCP\Server;
use Psr\Log\LoggerInterface;

class Trading212AccountJob extends TimedJob
{
    private IClient $client;
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

        $this->db  = Server::get(IDBConnection::class);
        $this->logger = Server::get(LoggerInterface::class);

        $clientService = Server::get(IClientService::class);
        $this->client = $clientService->newClient();

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

                $qb->executeStatement();
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

        $rows = [];
        while ($row = $result->fetch()) {
            $rows[] = $row;
        }

        $result->closeCursor();

        return $rows;
    }

    private function doLogic(string $apiKey, $secretKey): string
    {

        try {
            $response = $this->client->get(
                'https://live.trading212.com/api/v0/equity/account/summary',
                [
                    'auth' => [$apiKey, $secretKey],
                    'timeout' => 15,
                    'headers' => [
                        'Accept' => 'application/json',
                    ],
                ]
            );

            $statusCode = $response->getStatusCode();
            $body = $response->getBody();

            if ($statusCode < 200 || $statusCode >= 300) {
                throw new \RuntimeException("HTTP $statusCode: $body");
            }

            $this->logger->debug('Trading212 response received', [
                'app' => 'budget',
                'status' => $statusCode,
            ]);

            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

            $this->logger->debug('Account summary', [
                'app' => 'budget',
                'data' => $data,
            ]);

            try {

                return $data['totalValue'];
            } catch (\Throwable $e) {
                $this->logger->error(
                    'Trading212 API data parse failed: ' . $e->getMessage(),
                    [
                        'app' => 'budget',
                        'exception' => $e,
                    ]
                );
            }

            throw $e;
        } catch (\Throwable $e) {
            $this->logger->error(
                'Trading212 API call failed: ' . $e->getMessage(),
                [
                    'app' => 'budget',
                    'exception' => $e,
                ]
            );

            throw $e;
        }
    }
}
