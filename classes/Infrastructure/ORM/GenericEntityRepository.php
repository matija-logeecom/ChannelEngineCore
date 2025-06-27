<?php

namespace ChannelEngineCore\Infrastructure\ORM;

use ChannelEngine\Infrastructure\Logger\Logger;
use ChannelEngine\Infrastructure\ORM\Entity;
use ChannelEngine\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException;
use ChannelEngine\Infrastructure\ORM\Interfaces\RepositoryInterface;
use ChannelEngine\Infrastructure\ORM\QueryFilter\Operators;
use ChannelEngine\Infrastructure\ORM\QueryFilter\QueryCondition;
use ChannelEngine\Infrastructure\ORM\QueryFilter\QueryFilter;
use ChannelEngine\Infrastructure\ORM\Utility\IndexHelper;
use Db;
use DbQuery;
use PrestaShopDatabaseException;
use PrestaShopException;
use RuntimeException;

class GenericEntityRepository implements RepositoryInterface
{
    const THIS_CLASS_NAME = __CLASS__;
    const TABLE_NAME = 'channelengine_entity';

    protected string $entityClass;
    private ?array $indexMapping = null;

    /**
     * Returns full class name.
     *
     * @return string
     */
    public static function getClassName(): string
    {
        return static::THIS_CLASS_NAME;
    }

    /**
     * Sets repository entity.
     *
     * @param string $entityClass
     */
    public function setEntityClass($entityClass): void
    {
        $this->entityClass = $entityClass;
    }

    /**
     * Executes select query.
     *
     * @param QueryFilter|null $filter
     *
     * @return Entity[]
     *
     * @throws QueryFilterInvalidParamException
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function select(QueryFilter $filter = null): array
    {
        $entity = new $this->entityClass;

        $fieldIndexMap = IndexHelper::mapFieldsToIndexes($entity);
        $groups = $filter ? $this->buildConditionGroups($filter, $fieldIndexMap) : array();
        $type = $entity->getConfig()->getType();

        $typeCondition = "type='" . pSQL($type) . "'";
        $whereCondition = $this->buildWhereCondition($groups, $fieldIndexMap);
        $result = $this->getRecordsByCondition(
            $typeCondition . (!empty($whereCondition) ? ' AND ' . $whereCondition : ''),
            $filter
        );

        return $this->unserializeEntities($result);
    }

    /**
     * Executes select query and returns first result.
     *
     * @param QueryFilter|null $filter
     *
     * @return Entity|null
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws QueryFilterInvalidParamException
     */
    public function selectOne(QueryFilter $filter = null): ?Entity
    {
        if ($filter === null) {
            $filter = new QueryFilter();
        }

        $filter->setLimit(1);
        $results = $this->select($filter);

        return empty($results) ? null : $results[0];
    }

    /**
     * Executes insert query and returns ID of created entity. Entity will be updated with new ID.
     *
     * @param Entity $entity
     *
     * @return int
     *
     * @throws PrestaShopDatabaseException
     */
    public function save(Entity $entity): int
    {
        $indexes = IndexHelper::transformFieldsToIndexes($entity);
        $record = $this->prepareDataForInsertOrUpdate($entity, $indexes);
        $record['type'] = pSQL($entity->getConfig()->getType());

        $result = Db::getInstance()->insert(static::TABLE_NAME, $record);

        if (!$result) {
            $message = sprintf(
                'Entity %s cannot be inserted. Error: %s',
                $entity->getConfig()->getType(),
                Db::getInstance()->getMsgError()
            );
            Logger::logError($message);

            throw new RuntimeException($message);
        }

        $entity->setId((int)Db::getInstance()->Insert_ID());

        return $entity->getId();
    }

    /**
     * Counts records that match filter criteria.
     *
     * @param QueryFilter|null $filter
     *
     * @return int
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws QueryFilterInvalidParamException
     */
    public function count(QueryFilter $filter = null): int
    {
        return count($this->select($filter));
    }

    /**
     * Executes update query and returns success flag.
     *
     * @param Entity $entity
     *
     * @return bool
     */
    public function update(Entity $entity): bool
    {
        $indexes = IndexHelper::transformFieldsToIndexes($entity);
        $record = $this->prepareDataForInsertOrUpdate($entity, $indexes);

        $id = (int)$entity->getId();
        $result = Db::getInstance()->update(static::TABLE_NAME, $record, "id = $id");
        if (!$result) {
            $message = sprintf(
                'Entity %s with ID %d cannot be updated.',
                $entity->getConfig()->getType(),
                $id
            );
            Logger::logError($message);
        }

        return $result;
    }

    /**
     * Executes delete query and returns success flag.
     *
     * @param Entity $entity
     *
     * @return bool
     */
    public function delete(Entity $entity): bool
    {
        $id = (int)$entity->getId();
        $result = Db::getInstance()->delete(static::TABLE_NAME, "id = $id");

        if (!$result) {
            Logger::logError(
                sprintf(
                    'Could not delete entity %s with ID %d.',
                    $entity->getConfig()->getType(),
                    $entity->getId()
                )
            );
        }

        return $result;
    }

    /**
     * Deletes entities identified by filter.
     *
     * @param QueryFilter $filter
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws QueryFilterInvalidParamException
     */
    public function deleteWhere(QueryFilter $filter): void
    {
        $items = $this->select($filter);
        foreach ($items as $item) {
            $this->delete($item);
        }
    }

    /**
     * Batch delete entities
     *
     * @param Entity[] $entities
     *
     * @return bool
     */
    public function batchDelete(array $entities): bool
    {
        if (empty($entities)) {
            return true;
        }

        $ids = [];
        foreach ($entities as $entity) {
            if ($entity->getId()) {
                $ids[] = (int)$entity->getId();
            }
        }

        if (empty($ids)) {
            return true;
        }

        $idsString = implode(',', $ids);
        $result = Db::getInstance()->delete(static::TABLE_NAME, "id IN ({$idsString})");

        if (!$result) {
            Logger::logError(
                'Could not batch delete entities.',
                'Core',
                ['entity_count' => count($entities), 'ids' => $idsString]
            );
        }

        return $result;
    }

    /**
     * Translates database records to ChannelEngine entities.
     *
     * @param array $records Array of database records.
     *
     * @return Entity[]
     */
    protected function unserializeEntities($records): array
    {
        $entities = array();
        foreach ($records as $record) {
            $entity = $this->unserializeEntity($record['data']);
            $entity->setId((int)$record['id']);
            $entities[] = $entity;
        }

        return $entities;
    }

    /**
     * Returns index mapped to given property.
     *
     * @param string $property Property name.
     *
     * @return string|null
     */
    protected function getIndexMapping(string $property): ?string
    {
        if ($this->indexMapping === null) {
            $this->indexMapping = IndexHelper::mapFieldsToIndexes(new $this->entityClass);
        }

        if (array_key_exists($property, $this->indexMapping)) {
            return 'index_' . $this->indexMapping[$property];
        }

        return null;
    }

    /**
     * Returns columns that should be in the result of a select query on ChannelEngine entity table.
     *
     * @return array
     */
    protected function getSelectColumns(): array
    {
        return array('id', 'data');
    }

    /**
     * Builds condition groups (each group is chained with OR internally, and with AND externally) based on query
     * filter.
     *
     * @param QueryFilter $filter
     * @param array $fieldIndexMap
     *
     * @return array
     *
     * @throws QueryFilterInvalidParamException
     */
    private function buildConditionGroups(QueryFilter $filter, array $fieldIndexMap): array
    {
        $groups = array();
        $counter = 0;
        $fieldIndexMap['id'] = 0;
        foreach ($filter->getConditions() as $condition) {
            if (!empty($groups[$counter]) && $condition->getChainOperator() === 'OR') {
                $counter++;
            }

            if (!array_key_exists($condition->getColumn(), $fieldIndexMap)) {
                throw new QueryFilterInvalidParamException(
                    sprintf('Field %s is not indexed!', $condition->getColumn())
                );
            }

            $groups[$counter][] = $condition;
        }

        return $groups;
    }

    /**
     * Builds WHERE statement of SELECT query by separating AND and OR conditions.
     *
     * @param array $groups
     * @param array $fieldIndexMap
     *
     * @return string
     */
    private function buildWhereCondition(array $groups, array $fieldIndexMap): string
    {
        $whereStatement = '';
        foreach ($groups as $groupIndex => $group) {
            $conditions = array();
            foreach ($group as $condition) {
                $conditions[] = $this->addCondition($condition, $fieldIndexMap);
            }

            $whereStatement .= '(' . implode(' AND ', $conditions) . ')';

            if (\count($groups) !== 1 && $groupIndex < count($groups) - 1) {
                $whereStatement .= ' OR ';
            }
        }

        return $whereStatement;
    }

    /**
     * Filters records by given condition.
     *
     * @param QueryCondition $condition
     * @param array $indexMap
     *
     * @return string
     */
    private function addCondition(QueryCondition $condition, array $indexMap): string
    {
        $column = $condition->getColumn();
        $columnName = $column === 'id' ? 'id' : 'index_' . $indexMap[$column];
        if ($column === 'id') {
            $conditionValue = (int)$condition->getValue();
        } else {
            $conditionValue = IndexHelper::castFieldValue($condition->getValue(), $condition->getValueType());
        }

        if (in_array($condition->getOperator(), array(Operators::NOT_IN, Operators::IN), true)) {
            $values = array_map(function ($item) {
                if (is_string($item)) {
                    return "'" . pSQL($item) . "'";
                }

                if (is_int($item)) {
                    $val = IndexHelper::castFieldValue($item, 'integer');
                    return "'" . pSQL($val) . "'";
                }

                $val = IndexHelper::castFieldValue($item, 'double');

                return "'" . pSQL($val) . "'";
            }, $condition->getValue());
            $conditionValue = '(' . implode(',', $values) . ')';
        } else {
            $conditionValue = "'" . pSQL($conditionValue, true) . "'";
        }

        return $columnName . ' ' . $condition->getOperator()
            . (!in_array($condition->getOperator(), array(Operators::NULL, Operators::NOT_NULL), true)
                ? $conditionValue : ''
            );
    }

    /**
     * Returns ChannelEngine entity records that satisfy provided condition.
     *
     * @param string $condition
     * @param QueryFilter|null $filter
     *
     * @return array
     *
     * @throws PrestaShopDatabaseException
     * @throws QueryFilterInvalidParamException
     */
    private function getRecordsByCondition(string $condition, QueryFilter $filter = null): array
    {
        $query = new DbQuery();
        $query->select(implode(',', $this->getSelectColumns()))
            ->from(static::TABLE_NAME)
            ->where($condition);
        $this->applyLimitAndOrderBy($query, $filter);

        $result = Db::getInstance()->executeS($query);

        return !empty($result) ? $result : array();
    }

    /**
     * Applies limit and order by statements to provided SELECT query.
     *
     * @param DbQuery $query
     * @param QueryFilter|null $filter
     *
     * @throws QueryFilterInvalidParamException
     */
    private function applyLimitAndOrderBy(DbQuery $query, QueryFilter $filter = null): void
    {
        if ($filter) {
            $limit = (int)$filter->getLimit();

            if ($limit) {
                $query->limit($limit, $filter->getOffset());
            }

            $orderByColumn = $filter->getOrderByColumn();
            if ($orderByColumn) {
                $indexedColumn = $orderByColumn === 'id' ? 'id' : $this->getIndexMapping($orderByColumn);
                if (empty($indexedColumn)) {
                    throw new QueryFilterInvalidParamException(
                        sprintf(
                            'Unknown or not indexed OrderBy column %s',
                            $filter->getOrderByColumn()
                        )
                    );
                }

                $query->orderBy($indexedColumn . ' ' . $filter->getOrderDirection());
            }
        }
    }

    /**
     * Prepares data for inserting a new record or updating an existing one.
     *
     * @param Entity $entity
     * @param array $indexes
     *
     * @return array
     */
    protected function prepareDataForInsertOrUpdate(Entity $entity, array $indexes): array
    {
        $record = array('data' => pSQL($this->serializeEntity($entity), true));

        foreach ($indexes as $index => $value) {
            $record['index_' . $index] = $value !== null ? pSQL($value, true) : null;
        }

        return $record;
    }

    /**
     * Serializes Entity to string.
     *
     * @param Entity $entity
     *
     * @return string
     */
    protected function serializeEntity(Entity $entity): string
    {
        return json_encode($entity->toArray());
    }

    /**
     * Unserializes entity form given string.
     *
     * @param string $data
     *
     * @return Entity
     */
    private function unserializeEntity($data): Entity
    {
        $jsonEntity = json_decode($data, true);
        if (array_key_exists('class_name', $jsonEntity)) {
            $entity = new $jsonEntity['class_name'];
        } else {
            $entity = new $this->entityClass;
        }

        $entity->inflate($jsonEntity);

        return $entity;
    }
}