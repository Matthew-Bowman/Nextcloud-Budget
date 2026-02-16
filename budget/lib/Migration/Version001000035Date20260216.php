<?php

declare(strict_types=1);

namespace OCA\Budget\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Widen encrypted banking columns to accommodate encrypted output.
 *
 * These columns store values encrypted via EncryptionService (AES-CBC + HMAC),
 * which produces ~232 chars for a typical IBAN. The previous column lengths
 * (10-100 chars) caused "Data too long" errors on save.
 *
 * Fixes: https://github.com/otherworld-dev/budget/issues/38
 */
class Version001000034Date20260216 extends SimpleMigrationStep
{

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
	{
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('budget_accounts')) {
			$table = $schema->getTable('budget_accounts');

			// Add new column
			if (!$table->hasColumn('trading212_api_key_id')) {
				$table->addColumn('trading212_api_key_id', 'string', [
					'length' => 255,
					'notnull' => false,
					'default' => null,
				]);
			}
			if (!$table->hasColumn('trading212_api_secret_key')) {
				$table->addColumn('trading212_api_secret_key', 'string', [
					'length' => 255,
					'notnull' => false,
					'default' => null,
				]);
			}

			// Remove old column
			if ($table->hasColumn('trading212APIKeyID')) {
				$table->dropColumn('trading212APIKeyID');
			}
			if ($table->hasColumn('trading212APISecretKey')) {
				$table->dropColumn('trading212APISecretKey');
			}
		}

		return $schema;
	}
}
