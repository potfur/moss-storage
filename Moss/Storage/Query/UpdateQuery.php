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
use Moss\Storage\GetTypeTrait;
use Moss\Storage\Model\Definition\FieldInterface;
use Moss\Storage\Model\ModelInterface;
use Moss\Storage\Query\Accessor\Accessor;
use Moss\Storage\Query\Relation\RelationFactoryInterface;

/**
 * Query used to read data from table
 *
 * @author  Michal Wachowski <wachowski.michal@gmail.com>
 * @package Moss\Storage
 */
class UpdateQuery extends AbstractEntityValueQuery implements UpdateQueryInterface
{
    use GetTypeTrait;

    protected $instance;

    /**
     * Constructor
     *
     * @param Connection               $connection
     * @param mixed                    $entity
     * @param ModelInterface           $model
     * @param RelationFactoryInterface $factory
     */
    public function __construct(Connection $connection, $entity, ModelInterface $model, RelationFactoryInterface $factory)
    {
        parent::__construct($connection, $entity, $model, $factory);

        $this->setQuery();
        $this->values();
        $this->setPrimaryKeyConditions();
    }

    /**
     * Sets query instance with delete operation and table
     */
    protected function setQuery()
    {
        $this->builder = $this->connection->createQueryBuilder();
        $this->builder->update($this->connection->quoteIdentifier($this->model->table()));
    }

    /**
     * Assigns value to query
     *
     * @param FieldInterface $field
     */
    protected function assignValue(FieldInterface $field)
    {
        $value = $this->accessor->getPropertyValue($this->instance, $field->name());

        if ($value === null) {
            $this->getValueFromReferencedEntity($field);
        }

        $this->builder->set(
            $this->connection->quoteIdentifier($field->mappedName()),
            $this->bind('value', $field->name(), $field->type(), $value)
        );
    }

    /**
     * Executes query
     * After execution query is reset
     *
     * @return mixed
     */
    public function execute()
    {
        $this->builder->execute();

        foreach ($this->relations as $relation) {
            $relation->write($this->instance);
        }

        return $this->instance;
    }

    /**
     * Resets adapter
     *
     * @return $this
     */
    public function reset()
    {
        $this->builder->resetQueryParts();
        $this->relations = [];
        $this->resetBinds();

        $this->setQuery();
        $this->values();
        $this->setPrimaryKeyConditions();

        return $this;
    }
}
