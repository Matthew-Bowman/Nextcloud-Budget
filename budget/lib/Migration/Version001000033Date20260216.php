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
class Version001000033Date20260216 extends SimpleMigrationStep
{

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
	{
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('budget_accounts')) {
			$table = $schema->getTable('budget_accounts');

			// Add first column
			if (!$table->hasColumn('trading212APIKeyID')) {
				$table->addColumn('trading212APIKeyID', 'string', [
					'length' => 255,
					'notnull' => false,
					'default' => null,
				]);
			}

			// Add second column
			if (!$table->hasColumn('trading212SecretKey')) {
				$table->addColumn('trading212SecretKey', 'string', [
					'length' => 255,
					'notnull' => false,
					'default' => null,
				]);
			}
		}

		return $schema;
	}
}
