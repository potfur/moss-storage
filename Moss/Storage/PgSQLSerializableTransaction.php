<?php

/*
 * This file is part of the Storage package
 *
 * (c) Michal Wachowski <wachowski.michal@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Moss\Storage;

use Moss\Storage\Builder\QueryBuilderInterface;
use Moss\Storage\Driver\DriverInterface;

/**
 * Abstract class implementing basic storage functionalityUse this class when your PostgreSql transaction level config
 * is default (READ COMMITTED). This class solve concurrent transactions issue.
 *
 * @author  Grzegorz Imiolek <grzegorz.imiolek@gmail.com>
 * @package Moss\Storage
 */
class PgSQLSerializableTransaction extends StorageQuery
{
    /**
     * Current transaction id
     *
     * @var integer
     */
    private $currentTransactionId;

    /**
     * Constructor
     *
     * @param DriverInterface       $driver
     * @param QueryBuilderInterface $builder
     */
    public function __construct(DriverInterface $driver, QueryBuilderInterface $builder)
    {
        parent::__construct($driver, $builder);
    }

    /**
     * Starts transaction
     *
     * @return $this
     */
    public function transactionStart()
    {
        return $this->driver->execute('BEGIN ISOLATION LEVEL SERIALIZABLE');
    }

    /**
     * Commits transaction
     *
     * @return $this
     */
    public function transactionCommit()
    {
        return $this->driver->execute('COMMIT');
    }

    /**
     * RollBacks transaction
     *
     * @return $this
     */
    public function transactionRollback()
    {
        return $this->driver->execute('ROLLBACK');
    }

    /**
     * Returns true if in transaction
     *
     * @return bool
     */
    public function transactionCheck()
    {
        $this->currentTransactionId = $this->getCurrentTransactionId();

        return is_numeric($this->currentTransactionId) ? true : false;
    }

    /**
     * Get current transaction id
     *
     * @return integer
     */
    private function getCurrentTransactionId()
    {
        if(is_numeric($this->currentTransactionId)) {
            return $this->currentTransactionId;
        }

        $this->driver->execute('SELECT txid_current()');
        $result = $this->driver->fetchAssoc();

        return (int) $result['txid_current'];
    }
}