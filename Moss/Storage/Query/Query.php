<?php

/*
 * This file is part of the Storage package
 *
 * (c) Michal Wachowski <wachowski.michal@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Moss\Storage\Query;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Moss\Storage\Model\Definition\FieldInterface;
use Moss\Storage\Model\Definition\RelationInterface;
use Moss\Storage\Model\ModelBag;
use Moss\Storage\Model\ModelInterface;
use Moss\Storage\Mutator\MutatorInterface;
use Moss\Storage\Query\Relation\RelationFactory;

/**
 * Query used to create and execute CRUD operations on entities
 *
 * @author  Michal Wachowski <wachowski.michal@gmail.com>
 * @package Moss\Storage
 */
class Query implements QueryInterface
{
    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var MutatorInterface
     */
    protected $mutator;

    /**
     * @var ModelBag
     */
    protected $models;

    /**
     * @var QueryBuilder
     */
    protected $query;

    /**
     * @var ModelInterface
     */
    protected $model;

    protected $instance;

    protected $operation;

    private $fields = [];
    private $aggregates = [];
    private $group = [];

    private $values = [];

    private $where = [];
    private $having = [];

    private $order = [];

    private $limit = null;
    private $offset = null;

    private $binds = [];
    private $casts = [];

    /**
     * @var RelationInterface[]
     */
    protected $relations = [];

    /**
     * @var RelationFactory
     */
    protected $relationFactory;

    /**
     * Constructor
     *
     * @param Connection $connection
     * @param ModelBag $models
     * @param MutatorInterface $mutator
     */
    public function __construct(Connection $connection, ModelBag $models, MutatorInterface $mutator)
    {
        $this->connection = $connection;
        $this->models = $models;
        $this->mutator = $mutator;

        $this->relationFactory = new RelationFactory($this, $this->models);
    }

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
     * Sets counting operation
     *
     * @param string $entity
     *
     * @return $this
     */
    public function num($entity)
    {
        $this->assertEntityString($entity);
        $this->assignModel($entity);

        $this->operation = 'num';
        $this->query = $this->connection->createQueryBuilder();

        $this->query->select();
        $this->query->from($this->connection->quoteIdentifier($this->model->table()));

        foreach ($this->model->primaryFields() as $field) {
            $this->assignField($field);
        }

        return $this;
    }

    /**
     * Sets read operation
     *
     * @param string $entity
     *
     * @return $this
     */
    public function read($entity)
    {
        $this->assertEntityString($entity);
        $this->assignModel($entity);

        $this->operation = 'read';
        $this->query = $this->connection->createQueryBuilder();

        $this->query->select();
        $this->query->from($this->connection->quoteIdentifier($this->model->table()));
        $this->fields();

        return $this;
    }

    /**
     * Sets read one operation
     *
     * @param string $entity
     *
     * @return $this
     */
    public function readOne($entity)
    {
        $this->assertEntityString($entity);
        $this->assignModel($entity);

        $this->operation = 'read';
        $this->query = $this->connection->createQueryBuilder();

        $this->query->select();
        $this->query->from($this->connection->quoteIdentifier($this->model->table()));
        $this->fields();
        $this->limit(1);

        return $this;
    }

    /**
     * Sets write operation
     *
     * @param string $entity
     * @param array|object $instance
     *
     * @return $this
     */
    public function write($entity, $instance)
    {
        return $this->operation('write', $entity, $instance);
    }

    /**
     * Sets insert operation
     *
     * @param string $entity
     * @param array|object $instance
     *
     * @return $this
     */
    public function insert($entity, $instance)
    {
        return $this->operation('insert', $entity, $instance);
    }

    /**
     * Sets update operation
     *
     * @param string $entity
     * @param array|object $instance
     *
     * @return $this
     */
    public function update($entity, $instance)
    {
        return $this->operation('update', $entity, $instance);
    }

    /**
     * Sets delete operation
     *
     * @param string $entity
     * @param array|object $instance
     *
     * @return $this
     */
    public function delete($entity, $instance)
    {
        return $this->operation('delete', $entity, $instance);
    }

    /**
     * Sets clear operation
     *
     * @param string $entity
     *
     * @return $this
     */
    public function clear($entity)
    {
        $this->assertEntityString($entity);
        $this->assignModel($entity);

        $this->operation = 'clear';
        $this->query = $this->connection->createQueryBuilder();

        $this->query->delete($this->connection->quoteIdentifier($this->model->table()));

        return $this;
    }

    /**
     * Sets and prepares query operation
     *
     * @param string $operation
     * @param string $entity
     * @param null|array|object $instance
     *
     * @return $this
     * @throws QueryException
     */
    public function operation($operation, $entity, $instance = null)
    {
        $this->query = $this->connection->createQueryBuilder();

        $this->assertEntityString($entity);
        $this->assignModel($entity);

        if (in_array($operation, ['num', 'read', 'readOne', 'clear'])) {
            return $this->entityOperation($operation, $entity);
        }

        if (in_array($operation, ['write', 'insert', 'update', 'delete'])) {
            $this->assertEntityInstance($entity, $instance, $operation);

            return $this->instanceOperation($operation, $entity, $instance);
        }

        throw new QueryException(sprintf('Unknown operation "%s" in query "%s"', $operation, $entity));
    }

    /**
     * Prepares operations with entity name
     *
     * @param string $operation
     *
     * @return $this
     */
    protected function entityOperation($operation)
    {
        $this->operation = $operation;

        switch ($operation) {
            case 'num':
                $this->query->select();
                $this->query->from($this->connection->quoteIdentifier($this->model->table()));

                foreach ($this->model->primaryFields() as $field) {
                    $this->field($field);
                }
                break;
            case 'read':
                $this->query->select();
                $this->query->from($this->connection->quoteIdentifier($this->model->table()));
                $this->fields();
                break;
            case 'readOne':
                $this->query->select();
                $this->query->from($this->connection->quoteIdentifier($this->model->table()));
                $this->fields();
                $this->limit(1);
                break;
            case 'clear':
                $this->query->delete($this->model->table());
                break;
        }

        return $this;
    }

    /**
     * Prepares query for operations using entity instance
     *
     * @param string $operation
     * @param string $entity
     * @param array|object $instance
     *
     * @return $this
     */
    protected function instanceOperation($operation, $entity, $instance)
    {
        if ($operation == 'write') {
            $operation = $this->checkIfEntityExists($entity, $instance) ? 'update' : 'insert';
        }

        $this->operation = (string)$operation;
        $this->instance = $instance;

        switch ($operation) {
            case 'insert':
                $this->query->insert($this->connection->quoteIdentifier($this->model->table()));
                $this->values();
                break;
            case 'update':
                $this->query->update($this->connection->quoteIdentifier($this->model->table()));
                $this->values();
                $this->assignPrimaryConditions();
                break;
            case 'delete':
                $this->query->delete($this->connection->quoteIdentifier($this->model->table()));
                $this->assignPrimaryConditions();
                break;
        }

        return $this;
    }

    /**
     * Returns var type
     *
     * @param mixed $var
     *
     * @return string
     */
    protected function getType($var)
    {
        return is_object($var) ? get_class($var) : gettype($var);
    }

    /**
     * Asserts entity name
     *
     * @param string $entity
     *
     * @throws QueryException
     */
    protected function assertEntityString($entity)
    {
        if (!is_string($entity)) {
            throw new QueryException(sprintf('Entity must be a namespaced class name, its alias or object, got "%s"', $this->getType($entity)));
        }

        if (!$this->models->has($entity)) {
            throw new QueryException(sprintf('Missing entity model for "%s"', $entity));
        }
    }

    /**
     * Asserts entity instance
     *
     * @param string $entity
     * @param array|object $instance
     * @param string $operation
     *
     * @throws QueryException
     */
    protected function assertEntityInstance($entity, $instance, $operation)
    {
        $entityClass = $this->models->get($entity)
            ->entity();

        if ($instance === null) {
            throw new QueryException(sprintf('Missing required entity for operation "%s" in query "%s"', $operation, $entityClass));
        }

        if (!is_array($instance) && !$instance instanceof $entityClass) {
            throw new QueryException(sprintf('Entity for operation "%s" must be an instance of "%s" or array got "%s"', $operation, $entityClass, $this->getType($instance)));
        }
    }

    /**
     * Assigns entity model
     *
     * @param string $entity
     */
    protected function assignModel($entity)
    {
        $this->model = $this->models->get($entity);
    }

    /**
     * Assigns primary key values as conditions
     */
    protected function assignPrimaryConditions()
    {
        foreach ($this->model->primaryFields() as $field) {
            $value = $this->accessProperty($this->instance, $field->name());
            $value = $this->bind('condition', $field->name(), $field->type(), $value);

            $this->where($field->mapping(), $value, '=', 'and');
        }
    }

    /**
     * Returns true if entity exists database
     *
     * @param string $entity
     * @param array|object $instance
     *
     * @return bool
     */
    protected function checkIfEntityExists($entity, $instance)
    {
        $query = clone $this;
        $query->num($entity);

        $model = $this->models->get($entity);
        foreach ($model->primaryFields() as $field) {
            $value = $this->accessProperty($instance, $field->name());

            if ($value === null) {
                return false;
            }

            $query->where($field->name(), $value);
        }

        return $query->execute() > 0;
    }

    /**
     * Binds value to unique key and returns it
     *
     * @param string $operation
     * @param string $field
     * @param string $type
     * @param mixed $value
     *
     * @return string
     */
    protected function bind($operation, $field, $type, $value)
    {
        $key = ':' . implode('_', [$operation, count($this->binds), $field]);
        $this->binds[$key] = $this->mutator->store($value, $type);

        return $key;
    }

    /**
     * Sets field names which will be read
     *
     * @param array $fields
     *
     * @return $this
     */
    public function fields($fields = [])
    {
        $this->query->select([]);
        $this->casts = [];

        if (empty($fields)) {
            foreach ($this->model->fields() as $field) {
                $this->assignField($field);
            }

            return $this;
        }

        foreach ($fields as $field) {
            $this->assignField($this->model->field($field));
        }

        return $this;
    }

    /**
     * Adds field to query
     *
     * @param string $field
     *
     * @return $this
     */
    public function field($field)
    {
        $this->assignField($this->model->field($field));

        return $this;
    }

    /**
     * Assigns field to query
     *
     * @param FieldInterface $field
     */
    protected function assignField(FieldInterface $field)
    {
        if ($field->mapping()) {
            $this->query->addSelect(sprintf(
                '%s AS %s',
                $this->connection->quoteIdentifier($field->mapping()),
                $this->connection->quoteIdentifier($field->name())
            ));
        } else {
            $this->query->addSelect($this->connection->quoteIdentifier($field->name()));
        }

        $this->casts[$field->name()] = $field->type();
    }

    /**
     * Adds distinct method to query
     *
     * @param string $field
     * @param string $alias
     *
     * @return $this
     */
    public function distinct($field, $alias = null)
    {
        $this->aggregate('distinct', $field, $alias);

        return $this;
    }

    /**
     * Adds count method to query
     *
     * @param string $field
     * @param string $alias
     *
     * @return $this
     */
    public function count($field, $alias = null)
    {
        $this->aggregate('count', $field, $alias);

        return $this;
    }

    /**
     * Adds average method to query
     *
     * @param string $field
     * @param string $alias
     *
     * @return $this
     */
    public function average($field, $alias = null)
    {
        $this->aggregate('average', $field, $alias);

        return $this;
    }

    /**
     * Adds max method to query
     *
     * @param string $field
     * @param string $alias
     *
     * @return $this
     */
    public function max($field, $alias = null)
    {
        $this->aggregate('max', $field, $alias);

        return $this;
    }

    /**
     * Adds min method to query
     *
     * @param string $field
     * @param string $alias
     *
     * @return $this
     */
    public function min($field, $alias = null)
    {
        $this->aggregate('min', $field, $alias);

        return $this;
    }

    /**
     * Adds sum method to query
     *
     * @param string $field
     * @param string $alias
     *
     * @return $this
     */
    public function sum($field, $alias = null)
    {
        $this->aggregate('sum', $field, $alias);

        return $this;
    }

    /**
     * Adds aggregate method to query
     *
     * @param string $method
     * @param string $field
     * @param string $alias
     *
     * @return $this
     * @throws QueryException
     */
    public function aggregate($method, $field, $alias = null)
    {
        // TODO - add aggregates as CONST
        $this->assertAggregate($method);

        $field = $this->model->field($field);
        $alias = $alias ?: strtolower($method);

        $this->query->addSelect(
            sprintf(
                '%s(%s) AS %s',
                strtoupper($method),
                $this->connection->quoteIdentifier($field->mapping() ? $field->mapping() : $field->name()),
                $this->connection->quoteIdentifier($alias)
            )
        );

        return $this;
    }

    /**
     * Asserts if aggregate method is supported
     *
     * @param string $method
     *
     * @throws QueryException
     */
    protected function assertAggregate($method)
    {
        $aggregateMethods = ['distinct', 'count', 'average', 'max', 'min', 'sum'];

        if (!in_array($method, $aggregateMethods)) {
            throw new QueryException(sprintf('Invalid aggregation method "%s" in query', $method, $this->model->entity()));
        }
    }

    /**
     * Adds grouping to query
     *
     * @param string $field
     *
     * @return $this
     */
    public function group($field)
    {
        $field = $this->model->field($field);

        $this->query->addGroupBy($this->connection->quoteIdentifier($field->mapping() ? $field->mapping() : $field->name()));

        return $this;
    }

    /**
     * Sets field names which values will be written
     *
     * @param array $fields
     *
     * @return $this
     */
    public function values($fields = [])
    {
        $this->values = [];
        $this->binds = [];

        if (empty($fields)) {
            foreach ($this->model->fields() as $field) {
                $this->assignValue($this->model->field($field));
            }

            return $this;
        }

        foreach ($fields as $field) {
            $this->assignValue($this->model->field($field));
        }

        return $this;
    }

    /**
     * Adds field which value will be written
     *
     * @param string $field
     *
     * @return $this
     */
    public function value($field)
    {
        $this->assignValue($this->model->field($field));

        return $this;
    }

    /**
     * Assigns value to query
     *
     * @param FieldInterface $field
     */
    protected function assignValue(FieldInterface $field)
    {
        $value = $this->accessProperty($this->instance, $field->name());

        if ($this->operation === 'insert' && $value === null && $field->attribute('autoincrement')) {
            return;
        }

        $this->query->setValue(
            $field->mapping(),
            $this->bind('value', $field->name(), $field->type(), $value)
        );
    }

    /**
     * Adds where condition to builder
     *
     * @param mixed $field
     * @param mixed $value
     * @param string $comparison
     * @param string $logical
     *
     * @return $this
     * @throws QueryException
     */
    public function where($field, $value, $comparison = '=', $logical = 'and')
    {
        $condition = $this->condition($field, $value, $comparison, $logical);

        if($logical === 'or') {
            $this->query->orWhere($condition);

            return $this;
        }

        $this->query->andWhere($condition);

        return $this;
    }

    /**
     * Adds having condition to builder
     *
     * @param mixed $field
     * @param mixed $value
     * @param string $comparison
     * @param string $logical
     *
     * @return $this
     * @throws QueryException
     */
    public function having($field, $value, $comparison = '=', $logical = 'and')
    {
        $condition = $this->condition($field, $value, $comparison, $logical);

        switch ($logical) {
            case 'and':
                $this->query->andHaving($condition);
                break;
            case 'or':
                $this->query->orHaving($condition);
                break;
        }

        return $this;
    }

    /**
     * Adds where condition to builder
     *
     * @param mixed $field
     * @param mixed $value
     * @param string $comparison
     * @param string $logical
     *
     * @return $this
     * @throws QueryException
     */
    public function condition($field, $value, $comparison, $logical)
    {
        $comparison = strtolower($comparison);
        $logical = strtolower($logical);

        $this->assertComparison($comparison);
        $this->assertLogical($logical);

        if (!is_array($field)) {
            return $this->buildSingularFieldCondition($field, $value, $comparison);
        }

        return $this->buildMultipleFieldsCondition($field, $value, $comparison, $logical);
    }

    /**
     * Builds condition for singular field
     *
     * @param string $field
     * @param mixed $value
     * @param string $comparison
     *
     * @return array
     */
    protected function buildSingularFieldCondition($field, $value, $comparison)
    {
        $f = $this->model->field($field);

        $fieldName = $f->mapping() ? $f->mapping() : $f->name();
        return $this->buildConditionString(
            $this->connection->quoteIdentifier($fieldName),
            $value === null ? null : $this->bindValues($fieldName, $f->type(), $value),
            $comparison
        );
    }

    /**
     * Builds conditions for multiple fields
     *
     * @param array $field
     * @param mixed $value
     * @param string $comparison
     * @param string $logical
     *
     * @return array
     */
    protected function buildMultipleFieldsCondition($field, $value, $comparison, $logical)
    {
        $conditions = [];
        foreach ((array)$field as $i => $f) {
            $f = $this->model->field($f);

            $fieldName = $f->mapping() ? $f->mapping() : $f->name();
            $conditions[] = $this->buildConditionString(
                $this->connection->quoteIdentifier($fieldName),
                $value === null ? null : $this->bindValues($fieldName, $f->type(), $value),
                $comparison
            );

            $conditions[] = $logical;
        }

        array_pop($conditions);

        return '(' . implode(' ', $conditions) . ')';
    }

    /**
     * Builds condition string
     *
     * @param string $field
     * @param string|array $bind
     * @param string $operator
     *
     * @return string
     */
    private function buildConditionString($field, $bind, $operator)
    {
        if (is_array($bind)) {
            foreach ($bind as &$val) {
                $val = $this->buildConditionString($field, $val, $operator);
                unset($val);
            }

            $operator = $operator === '!=' ? 'and' : 'or';

            return '(' . implode(sprintf(' %s ', $operator), $bind) . ')';
        }

        if ($bind === null) {
            return $field . ' ' . ($operator == '!=' ? 'IS NOT NULL' : 'IS NULL');
        }

        if ($operator === 'regexp') {
            return sprintf('%s regexp %s', $field, $bind);
        }

        return $field . ' ' . $operator . ' ' . $bind;
    }

    /**
     * Asserts correct comparison operator
     *
     * @param string $operator
     *
     * @throws QueryException
     */
    protected function assertComparison($operator)
    {
        // TODO - add comparison operators as CONST
        $comparisonOperators = ['=', '!=', '<', '<=', '>', '>=', 'like', 'regexp'];

        if (!in_array($operator, $comparisonOperators)) {
            throw new QueryException(sprintf('Query does not supports comparison operator "%s" in query "%s"', $operator, $this->model->entity()));
        }
    }

    /**
     * Asserts correct logical operation
     *
     * @param string $operator
     *
     * @throws QueryException
     */
    protected function assertLogical($operator)
    {
        // TODO - add logical operators as CONST
        $comparisonOperators = ['or', 'and'];

        if (!in_array($operator, $comparisonOperators)) {
            throw new QueryException(sprintf('Query does not supports logical operator "%s" in query "%s"', $operator, $this->model->entity()));
        }
    }

    /**
     * Binds condition value to key
     *
     * @param $name
     * @param $type
     * @param $values
     *
     * @return array|string
     */
    protected function bindValues($name, $type, $values)
    {
        if (!is_array($values)) {
            return $this->bind('condition', $name, $type, $values);
        }

        foreach ($values as $key => $value) {
            $values[$key] = $this->bindValues($name, $type, $value);
        }

        return $values;
    }

    /**
     * Adds sorting to query
     *
     * @param string $field
     * @param string|array $order
     *
     * @return $this
     * @throws QueryException
     */
    public function order($field, $order = 'desc')
    {
        $field = $this->model->field($field);

        $this->assertOrder($order);

        $field = $field->mapping() ? $field->mapping() : $field->name();
        $this->query->addOrderBy($this->connection->quoteIdentifier($field), $order);

        return $this;
    }

    /**
     * Asserts correct order
     *
     * @param string|array $order
     *
     * @throws QueryException
     */
    protected function assertOrder($order)
    {
        if (!in_array($order, ['asc', 'desc'])) {
            throw new QueryException(sprintf('Unsupported sorting method "%s" in query "%s"', is_scalar($order) ? $order : gettype($order), $this->model->entity()));
        }
    }

    /**
     * Sets limits to query
     *
     * @param int $limit
     * @param null|int $offset
     *
     * @return $this
     */
    public function limit($limit, $offset = null)
    {
        if ($offset) {
            $this->query->setFirstResult((int)$offset);
        }

        $this->query->setMaxResults((int)$limit);

        return $this;
    }

    /**
     * Adds relation to query with optional conditions and sorting (as key value pairs)
     *
     * @param string|array $relation
     * @param array $conditions
     * @param array $order
     *
     * @return $this
     * @throws QueryException
     */
    public function with($relation, array $conditions = [], array $order = [])
    {
        if (!$this->model) {
            throw new QueryException('Unable to create relation, missing entity model');
        }

        foreach ($this->relationFactory->create($this->model, $relation, $conditions, $order) as $rel) {
            $this->relations[] = $rel;
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
        list($relation, $furtherRelations) = $this->relationFactory->splitRelationName($relation);

        if (!isset($this->relations[$relation])) {
            throw new QueryException(sprintf('Unable to retrieve relation "%s" query, relation does not exists in query "%s"', $relation, $this->model->entity()));
        }

        $instance = $this->relations[$relation];

        if ($furtherRelations) {
            return $instance->relation($furtherRelations);
        }

        return $instance;
    }

    /**
     * Executes query
     * After execution query is reset
     *
     * @return mixed|null|void
     */
    public function execute()
    {
        switch ($this->operation) {
            case 'num':
                $result = $this->executeNumber();
                break;
            case 'readOne':
                $result = $this->executeReadOne();
                break;
            case 'read':
                $result = $this->executeRead();
                break;
            case 'insert':
                $result = $this->executeInsert();
                break;
            case 'update':
                $result = $this->executeUpdate();
                break;
            case 'delete':
                $result = $this->executeDelete();
                break;
            case 'clear':
                $result = $this->executeClear();
                break;
            default:
                $result = false;
        }

        $this->reset();

        return $result;
    }

    /**
     * Executes counting operation
     *
     * @return int
     */
    protected function executeNumber()
    {
        $stmt = $this->connection->prepare($this->queryString());
        $stmt->execute($this->binds);

        return $stmt->rowCount();
    }

    /**
     * Executes reading one entity operation
     *
     * @return array|object
     * @throws QueryException
     */
    protected function executeReadOne()
    {
        $result = $this->executeRead();

        if (!count($result)) {
            throw new QueryException(sprintf('Result out of range or does not exists for "%s"', $this->model->entity()));
        }

        return array_shift($result);
    }

    /**
     * Executes read operation
     *
     * @return array
     */
    protected function executeRead()
    {
        $stmt = $this->connection->prepare($this->queryString());
        $stmt->execute($this->binds);

        $result = $stmt->fetchAll(\PDO::FETCH_CLASS, $this->model->entity());

        $ref = new \ReflectionClass($this->model->entity());
        foreach ($result as $entity) {
            $this->restoreObject($entity, $this->casts, $ref);
        }

        $this->executeReadRelations($result);

        return $result;
    }

    protected function restoreObject($entity, array $restore, \ReflectionClass $ref)
    {
        foreach ($restore as $field => $type) {
            if (!$ref->hasProperty($field)) {
                $entity->$field = $this->mutator->restore($entity->$field, $type);
                continue;
            }

            $prop = $ref->getProperty($field);
            $prop->setAccessible(true);

            $value = $prop->getValue($entity);
            $value = $this->mutator->restore($value, $type);
            $prop->setValue($entity, $value);
        }

        return $entity;
    }

    /**
     * Executes insert operation
     *
     * @return array|object
     */
    protected function executeInsert()
    {
        $result = $this->connection
            ->prepare($this->buildInsert())
            ->execute($this->binds)
            ->lastInsertId();

        $this->identifyEntity($this->instance, $result);

        $this->executeWriteRelations();

        return $this->instance;
    }

    /**
     * Executes update operation
     *
     * @return array|object
     */
    protected function executeUpdate()
    {
        $this->connection
            ->prepare($this->buildUpdate())
            ->execute($this->binds);

        $this->executeWriteRelations();

        return $this->instance;
    }

    /**
     * Executes deleting operation
     *
     * @return array|object
     */
    protected function executeDelete()
    {
        $this->executeDeleteRelations();

        $this->connection
            ->prepare($this->buildDelete())
            ->execute($this->binds);

        $this->identifyEntity($this->instance, null);

        return $this->instance;
    }

    /**
     * Executes clearing operation
     *
     * @return bool
     */
    protected function executeClear()
    {
        $this->executeClearRelations();

        $this->connection
            ->prepare($this->buildClear())
            ->execute();

        return true;
    }

    /**
     * Executes reading relations
     *
     * @param $result
     */
    protected function executeReadRelations(&$result)
    {
        foreach ($this->relations as $relation) {
            $result = $relation->read($result);
        }
    }

    /**
     * Executes writing (insert/update) relations
     */
    protected function executeWriteRelations()
    {
        foreach ($this->relations as $relation) {
            $relation->write($this->instance);
        }
    }

    /**
     * Executes deleting relations
     */
    protected function executeDeleteRelations()
    {
        foreach ($this->relations as $relation) {
            $relation->delete($this->instance);
        }
    }

    /**
     * Executes clearing relations
     */
    protected function executeClearRelations()
    {
        foreach ($this->relations as $relation) {
            $relation->clear();
        }
    }

    /**
     * Assigns passed identifier to primary key
     * Possible only when entity has one primary key
     *
     * @param array|object $entity
     * @param int|string $identifier
     *
     * @return void
     */
    protected function identifyEntity($entity, $identifier)
    {
        $primaryKeys = $this->model->primaryFields();
        if (count($primaryKeys) !== 1) {
            return;
        }

        $field = reset($primaryKeys);
        $field = $field->name();

        if (is_array($entity) || $entity instanceof \ArrayAccess) {
            $entity[$field] = $identifier;

            return;
        }

        $ref = new \ReflectionObject($entity);
        if (!$ref->hasProperty($field)) {
            $entity->$field = $identifier;

            return;
        }

        $prop = $ref->getProperty($field);
        $prop->setAccessible(true);
        $prop->setValue($entity, $identifier);
    }

    /**
     * Returns property value
     *
     * @param null|array|object $entity
     * @param string $field
     *
     * @return mixed
     * @throws QueryException
     */
    protected function accessProperty($entity, $field)
    {
        if (!$entity) {
            throw new QueryException('Unable to access entity properties, missing instance');
        }

        if (is_array($entity) || $entity instanceof \ArrayAccess) {
            return isset($entity[$field]) ? $entity[$field] : null;
        }

        $ref = new \ReflectionObject($entity);
        if (!$ref->hasProperty($field)) {
            return null;
        }

        $prop = $ref->getProperty($field);
        $prop->setAccessible(true);

        return $prop->getValue($entity);
    }

    /**
     * Returns current query string
     *
     * @return string
     */
    public function queryString()
    {
        return (string)$this->query->getSQL();
    }

    public function binds()
    {
        return $this->binds;
    }

    /**
     * Resets adapter
     *
     * @return $this
     */
    public function reset()
    {
        $this->model = null;

        $this->instance = null;

        $this->operation = null;

        $this->joins = [];

        $this->fields = [];
        $this->aggregates = [];
        $this->group = [];

        $this->values = [];

        $this->where = [];
        $this->having = [];

        $this->order = [];

        $this->limit = null;
        $this->offset = null;

        $this->binds = [];
        $this->casts = [];

        $this->connection;

        $this->relations = [];

        return $this;
    }
}
