<?php

/*
* This file is part of the moss-storage package
*
* (c) Michal Wachowski <wachowski.michal@gmail.com>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Moss\Storage\Query;


use Doctrine\DBAL\Connection;
use Moss\Storage\Model\ModelInterface;
use Moss\Storage\Query\Relation\RelationFactoryInterface;

/**
 * Query used to read one entity from table
 *
 * @author  Michal Wachowski <wachowski.michal@gmail.com>
 * @package Moss\Storage
 */
class ReadOneQuery extends ReadQuery
{
    /**
     * Constructor
     *
     * @param Connection               $connection
     * @param ModelInterface           $model
     * @param RelationFactoryInterface $factory
     */
    public function __construct(Connection $connection, ModelInterface $model, RelationFactoryInterface $factory)
    {
        parent::__construct($connection, $model, $factory);
        $this->limit(1);
    }

    /**
     * Executes query
     * After execution query is reset
     *
     * @return mixed
     * @throws QueryException
     */
    public function execute()
    {
        $stmt = $this->bindAndExecuteQuery();
        $result = $stmt->fetchAll(\PDO::FETCH_CLASS, $this->model->entity());

        if (!count($result)) {
            throw new QueryException(sprintf('Result out of range or does not exists for "%s"', $this->model->entity()));
        }

        $result = array_slice($result, 0, 1, false);

        foreach ($this->relations as $relation) {
            $result = $relation->read($result);
        }

        return $result[0];
    }

    /**
     * Resets adapter
     *
     * @return $this
     */
    public function reset()
    {
        parent::reset();
        $this->limit(1);

        return $this;
    }
}
