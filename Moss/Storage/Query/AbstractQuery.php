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
use Doctrine\DBAL\Query\QueryBuilder;
use Moss\Storage\Converter\ConverterInterface;
use Moss\Storage\GetTypeTrait;
use Moss\Storage\Model\ModelInterface;
use Moss\Storage\Query\Relation\RelationFactoryInterface;
use Moss\Storage\Query\Relation\RelationInterface;

/**
 * Abstract base class with common methods
 *
 * @author  Michal Wachowski <wachowski.michal@gmail.com>
 * @package Moss\Storage
 */
abstract class AbstractQuery
{
    use PropertyAccessorTrait;
    use GetTypeTrait;

    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var ModelInterface
     */
    protected $model;

    /**
     * @var ConverterInterface
     */
    protected $converter;

    /**
     * @var RelationFactoryInterface
     */
    protected $factory;

    /**
     * @var QueryBuilder
     */
    protected $query;

    /**
     * @var array|object
     */
    protected $instance;

    /**
     * @var array|RelationInterface[]
     */
    protected $relations = [];

    /**
     * @var array
     */
    protected $binds = [];

    /**
     * Returns connection
     *
     * @return Connection
     */
    public function connection()
    {
        return $this->connection;
    }

    /**
     * Adds relation to query
     *
     * @param string $relation
     *
     * @return $this
     * @throws QueryException
     */
    public function with($relation)
    {
        $this->factory->reset();
        $instance = $this->factory->relation($this->model, $relation)->build();
        $this->setRelation($instance);

        return $this;
    }

    /**
     * Returns relation instance
     *
     * @param string $relation
     *
     * @return RelationInterface
     * @throws QueryException
     */
    public function relation($relation)
    {
        list($relation, $furtherRelations) = $this->factory->splitRelationName($relation);

        $instance = $this->getRelation($relation);

        if ($furtherRelations) {
            return $instance->relation($furtherRelations);
        }

        return $instance;
    }

    /**
     * Adds relation to query or if relation with same name exists - replaces it with new one
     *
     * @param RelationInterface $relation
     *
     * @return $this
     */
    public function setRelation(RelationInterface $relation)
    {
        $this->relations[$relation->name()] = $relation;

        return $this;
    }

    /**
     * Returns relation with set name
     *
     * @param string $name
     *
     * @return RelationInterface
     * @throws QueryException
     */
    public function getRelation($name)
    {
        if (!isset($this->relations[$name])) {
            throw new QueryException(sprintf('Unable to retrieve relation "%s" query, relation does not exists in query "%s"', $name, $this->model->entity()));
        }

        return $this->relations[$name];
    }

    /**
     * Assigns passed identifier to primary key
     * Possible only when entity has one primary key
     *
     * @param array|object $entity
     * @param int|string   $identifier
     *
     * @return void
     */
    protected function identifyEntity($entity, $identifier)
    {
        $primaryKeys = $this->model->primaryFields();
        if (count($primaryKeys) !== 1) {
            return;
        }

        $field = reset($primaryKeys)->name();

        $this->setPropertyValue($entity, $field, $identifier);
    }

    /**
     * Returns current query string
     *
     * @return string
     */
    public function queryString()
    {
        return (string) $this->query->getSQL();
    }

    /**
     * Binds value to unique key and returns it
     *
     * @param string $operation
     * @param string $field
     * @param string $type
     * @param mixed  $value
     *
     * @return string
     */
    protected function bind($operation, $field, $type, $value)
    {
        $key = ':' . implode('_', [$operation, count($this->binds), $field]);
        $this->binds[$key] = $this->converter->store($value, $type);

        return $key;
    }

    /**
     * Removes bound values by their prefix
     * If prefix is null - clears all bound values
     *
     * @param null|string $prefix
     */
    protected function resetBinds($prefix = null)
    {
        if ($prefix === null) {
            $this->binds = [];
            return;
        }

        foreach ($this->binds as $key => $value) {
            if (strpos($key, $prefix) === 1) {
                unset($this->binds[$key]);
            }
        }
    }

    /**
     * Returns array with bound values and their placeholders as keys
     *
     * @return array
     */
    public function binds()
    {
        return $this->binds;
    }
}
