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
     * @var RelationFactoryInterface
     */
    protected $factory;

    /**
     * @var QueryBuilder
     */
    protected $query;

    /**
     * @var object
     */
    protected $instance;

    /**
     * @var RelationInterface[]
     */
    protected $relations = [];

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
     * Returns query builder instance
     *
     * @return QueryBuilder
     */
    public function queryBuilder()
    {
        return $this->query;
    }

    /**
     * Adds relation to query
     *
     * @param string|array $relation
     *
     * @return $this
     * @throws QueryException
     */
    public function with($relation)
    {
        foreach((array) $relation as $node) {
            $this->factory->reset();
            $instance = $this->factory->relation($this->model, $node)->build();
            $this->setRelation($instance);
        }

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
     * @param object $entity
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
     * Binds values and executes passed query string, returns statement
     *
     * @return \Doctrine\DBAL\Driver\Statement
     */
    protected function bindAndExecuteQuery()
    {
        return $this->query->execute();
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
        $key = ':' . implode('_', [$operation, count($this->query->getParameters()), $field]);
        return $this->query->createNamedParameter($value, $type, $key);
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
            $this->query->setParameters([]);
            return;
        }

        $params = (array) $this->query->getParameters();
        $types = (array) $this->query->getParameterTypes();

        foreach (array_keys($params) as $key) {
            if (strpos($key, $prefix) === 1) {
                unset($params[$key], $types[$key]);
            }
        }

        $this->query->setParameters($params, $types);
    }

    /**
     * Returns array with bound values and their placeholders as keys
     *
     * @return array
     */
    public function binds()
    {
        return $this->query->getParameters();
    }

    /**
     * Asserts entity instance
     *
     * @param array|object $entity
     *
     * @throws QueryException
     */
    protected function assertEntityInstance($entity)
    {
        $entityClass = $this->model->entity();

        if ($entity === null) {
            throw new QueryException(sprintf('Missing required entity of class "%s"', $entityClass));
        }

        if (!is_array($entity) && !$entity instanceof $entityClass) {
            throw new QueryException(sprintf('Entity must be an instance of "%s" or array got "%s"', $entityClass, $this->getType($entity)));
        }
    }
}
