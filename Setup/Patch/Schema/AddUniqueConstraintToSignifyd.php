<?php

declare(strict_types=1);

namespace Signifyd\Connect\Setup\Patch\Schema;

use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\Patch\SchemaPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use RuntimeException;

class AddUniqueConstraintToSignifyd implements SchemaPatchInterface, PatchRevertableInterface
{
    /** @var SchemaSetupInterface */
    protected SchemaSetupInterface $schemaSetup;

    /**
     * AddUniqueConstraintToSignifyd constructor
     *
     * @param SchemaSetupInterface $schemaSetup
     */
    public function __construct(
        SchemaSetupInterface $schemaSetup
    ) {
        $this->schemaSetup = $schemaSetup;
    }

    /**
     * Apply schema changes
     *
     * @return void
     */
    public function apply(): void
    {
        $setup = $this->schemaSetup;
        $setup->startSetup();

        $connection = $setup->getConnection();
        $table = $setup->getTable('signifyd_connect_case');

        $select = $connection->select()
            ->from($table, ['order_id', 'cnt' => new \Zend_Db_Expr('COUNT(*)')])
            ->where('order_id IS NOT NULL')
            ->group('order_id')
            ->having('cnt > ?', 1);

        $duplicates = $connection->fetchAll($select);
        if (!empty($duplicates)) {
            throw new RuntimeException(
                'Unable to create unique index: duplicate order_ids exist. ' .
                'Resolve duplicates before applying the patch: '
                . json_encode(array_slice($duplicates, 0, 5))
            );
        }

        $indexToDrop = $setup->getIdxName($table, ['order_id']);
        $uniqueIndexName = $setup->getIdxName($table, ['order_id'], AdapterInterface::INDEX_TYPE_UNIQUE);
        $indexes = $connection->getIndexList($table);

        $lowerToOriginal = [];
        foreach ($indexes as $name => $info) {
            $lowerToOriginal[strtolower($name)] = $name;
        }

        if (isset($lowerToOriginal[strtolower($indexToDrop)])) {
            $connection->dropIndex($table, $lowerToOriginal[strtolower($indexToDrop)]);
        } else {
            foreach ($indexes as $name => $info) {
                $cols = array_map('strtolower', $info['COLUMNS'] ?? []);
                if (in_array('order_id', $cols, true) && strtolower($name) !== strtolower($uniqueIndexName)) {
                    $connection->dropIndex($table, $name);
                    break;
                }
            }
        }

        $indexes = $connection->getIndexList($table);
        $existingLowerKeys = array_map('strtolower', array_keys($indexes));
        if (!in_array(strtolower($uniqueIndexName), $existingLowerKeys, true)) {
            $connection->addIndex(
                $table,
                $uniqueIndexName,
                ['order_id'],
                AdapterInterface::INDEX_TYPE_UNIQUE
            );
        }

        $setup->endSetup();
    }

    /**
     * Rollback all changes, done by this patch.
     *
     * @return void
     */
    public function revert(): void
    {
        $setup = $this->schemaSetup;
        $setup->startSetup();

        $connection = $setup->getConnection();
        $table = $setup->getTable('signifyd_connect_case');

        $uniqueIndexName = $setup->getIdxName($table, ['order_id'], AdapterInterface::INDEX_TYPE_UNIQUE);
        $normalIndexName = $setup->getIdxName($table, ['order_id']);

        $indexes = $connection->getIndexList($table);
        $lowerToOriginal = [];
        foreach ($indexes as $name => $info) {
            $lowerToOriginal[strtolower($name)] = $name;
        }

        if (isset($lowerToOriginal[strtolower($uniqueIndexName)])) {
            $connection->dropIndex($table, $lowerToOriginal[strtolower($uniqueIndexName)]);
        }

        $indexes = $connection->getIndexList($table);
        $existingLowerKeys = array_map('strtolower', array_keys($indexes));
        if (!in_array(strtolower($normalIndexName), $existingLowerKeys, true)) {
            $connection->addIndex(
                $table,
                $normalIndexName,
                ['order_id'],
                AdapterInterface::INDEX_TYPE_INDEX
            );
        }

        $setup->endSetup();
    }

    /**
     * Get array of patches that have to be executed prior to this.
     *
     * @return string[]
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * Get aliases (previous names) for the patch.
     *
     * @return string[]
     */
    public function getAliases(): array
    {
        return [];
    }
}