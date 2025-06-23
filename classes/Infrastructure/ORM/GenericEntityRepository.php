<?php

namespace ChannelEngineCore\Infrastructure\ORM;

use ChannelEngine\Infrastructure\ORM\Entity;
use ChannelEngine\Infrastructure\ORM\IntermediateObject;
use ChannelEngine\Infrastructure\ORM\Interfaces\RepositoryInterface;
use ChannelEngine\Infrastructure\ORM\QueryFilter\QueryFilter;
use ChannelEngine\Infrastructure\Serializer\Serializer;
use Db;
use DbQuery;
use Exception;
use PrestaShopException;

/**
 * Generic repository that handles all ChannelEngine entities using the generic table
 */
class GenericEntityRepository implements RepositoryInterface
{
    const TABLE_NAME = 'channelengine_entity';

    private string $entityClass = '';

    /**
     * @return string
     */
    public static function getClassName(): string
    {
        return static::class;
    }

    /**
     * Sets repository entity class.
     *
     * @param $entityClass
     *
     * @return void
     */
    public function setEntityClass($entityClass): void
    {
        $this->entityClass = $entityClass;
    }


    /**
     * Executes select query and returns first result.
     *
     * @param QueryFilter|null $filter
     *
     * @return Entity|null
     */
    public function selectOne(?QueryFilter $filter = null): ?Entity
    {
        $results = $this->select($filter);
        return !empty($results) ? $results[0] : null;
    }

    /**
     * Executes select query.
     *
     * @return Entity[]
     */
    public function select(?QueryFilter $filter = null): array
    {
        $query = new DbQuery();
        $query->select('*')
            ->from(self::TABLE_NAME)
            ->where('type = "' . pSQL($this->getEntityType()) . '"');

        if ($filter) {
            $this->applyFilter($query, $filter);
        }

        try {
            $results = Db::getInstance()->executeS($query);
            if (!$results) {
                return [];
            }

            $entities = [];
            foreach ($results as $row) {
                $entity = $this->hydrateEntity($row);
                if ($entity) {
                    $entities[] = $entity;
                }
            }

            return $entities;
        } catch (PrestaShopException $e) {
            return [];
        }
    }


    /**
     * Executes insert query and returns ID of created entity.
     *
     * @param Entity $entity
     *
     * @return int
     */
    public function save(Entity $entity): int
    {
        $intermediateObject = $this->entityToIntermediate($entity);

        $data = [
            'type' => pSQL($this->getEntityType()),
            'data' => pSQL($intermediateObject->getData()),
        ];

        for ($i = 1; $i <= 6; $i++) {
            $indexValue = $intermediateObject->getIndexValue($i);
            if ($indexValue !== null) {
                $data['index_' . $i] = pSQL($indexValue);
            }
        }

        try {
            if (Db::getInstance()->insert(self::TABLE_NAME, $data)) {
                $id = (int)Db::getInstance()->Insert_ID();
                $entity->setId($id);
                return $id;
            }
        } catch (PrestaShopException $e) {
        }

        return 0;
    }

    /**
     * Executes update query.
     *
     * @param Entity $entity
     *
     * @return bool
     */
    public function update(Entity $entity): bool
    {
        if (!$entity->getId()) {
            return false;
        }

        $intermediateObject = $this->entityToIntermediate($entity);

        $data = [
            'type' => pSQL($this->getEntityType()),
            'data' => pSQL($intermediateObject->getData()),
        ];

        for ($i = 1; $i <= 6; $i++) {
            $indexValue = $intermediateObject->getIndexValue($i);
            $data['index_' . $i] = $indexValue !== null ? pSQL($indexValue) : null;
        }

        try {
            return (bool)Db::getInstance()->update(
                self::TABLE_NAME,
                $data,
                'id = ' . (int)$entity->getId()
            );
        } catch (PrestaShopException $e) {
            return false;
        }
    }

    /**
     * Executes delete query.
     *
     * @param Entity $entity
     *
     * @return bool
     */
    public function delete(Entity $entity): bool
    {
        if (!$entity->getId()) {
            return false;
        }

        try {
            return (bool)Db::getInstance()->delete(
                self::TABLE_NAME,
                'id = ' . (int)$entity->getId()
            );
        } catch (PrestaShopException $e) {
            return false;
        }
    }

    /**
     * Deletes entities identified by filter.
     *
     * @param QueryFilter $filter
     *
     * @return void
     */
    public function deleteWhere(QueryFilter $filter): void
    {
        $query = new DbQuery();
        $query->select('id')
            ->from(self::TABLE_NAME)
            ->where('type = "' . pSQL($this->getEntityType()) . '"');

        $this->applyFilter($query, $filter);

        try {
            $results = Db::getInstance()->executeS($query);
            if ($results) {
                $ids = array_map(function($row) {
                    return (int)$row['id'];
                }, $results);

                if (!empty($ids)) {
                    Db::getInstance()->delete(
                        self::TABLE_NAME,
                        'id IN (' . implode(',', $ids) . ')'
                    );
                }
            }
        } catch (PrestaShopException $e) {
        }
    }

    /**
     * Counts records that match filter criteria.
     *
     * @param QueryFilter|null $filter
     *
     * @return int
     */
    public function count(?QueryFilter $filter = null): int
    {
        $query = new DbQuery();
        $query->select('COUNT(*)')
            ->from(self::TABLE_NAME)
            ->where('type = "' . pSQL($this->getEntityType()) . '"');

        if ($filter) {
            $this->applyFilter($query, $filter);
        }

        try {
            $result = Db::getInstance()->getValue($query);
            return (int)$result;
        } catch (PrestaShopException $e) {
            return 0;
        }
    }

    /**
     * Convert database row to entity.
     *
     * @param array $row
     *
     * @return Entity|null
     */
    private function hydrateEntity(array $row): ?Entity
    {
        try {
            $intermediateObject = new IntermediateObject();
            $intermediateObject->setData($row['data']);

            for ($i = 1; $i <= 6; $i++) {
                if (isset($row['index_' . $i])) {
                    $intermediateObject->setIndexValue($i, $row['index_' . $i]);
                }
            }

            $entityData = json_decode($intermediateObject->getData(), true);
            if (!$entityData || !isset($entityData['class_name'])) {
                return null;
            }

            $entityClass = $entityData['class_name'];
            $entity = $entityClass::fromArray($entityData);
            $entity->setId((int)$row['id']);

            return $entity;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Convert entity to intermediate object for storage.
     *
     * @param Entity $entity
     *
     * @return IntermediateObject
     */
    private function entityToIntermediate(Entity $entity): IntermediateObject
    {
        $intermediateObject = new IntermediateObject();

        $entityArray = $entity->toArray();
        $intermediateObject->setData(json_encode($entityArray));

        $entityConfig = $entity->getConfig();
        $indexMap = $entityConfig->getIndexMap();
        $indexes = $indexMap->getIndexes();

        $indexNumber = 1;
        foreach ($indexes as $index) {
            if ($indexNumber > 6) {
                break;
            }

            try {
                $value = $entity->getIndexValue($index->getProperty());
                $intermediateObject->setIndexValue($indexNumber, (string)$value);
                $indexNumber++;
            } catch (Exception $e) {
            }
        }

        return $intermediateObject;
    }

    /**
     *  Get entity type from class name.
     *
     * @return string
     */
    private function getEntityType(): string
    {
        if (!$this->entityClass) {
            return '';
        }

        $tempEntity = new $this->entityClass();
        $config = $tempEntity->getConfig();
        return $config->getType();
    }

    /**
     * Apply query filter to DB query.
     *
     * @param DbQuery $query
     * @param QueryFilter $filter
     *
     * @return void
     */
    private function applyFilter(DbQuery $query, QueryFilter $filter): void
    {
        $conditions = $filter->getConditions();

        foreach ($conditions as $condition) {
            $column = $condition->getColumn();
            $operator = $condition->getOperator();
            $value = $condition->getValue();

            $indexColumn = $this->mapPropertyToIndex($column);

            if ($indexColumn && $operator === '=') {
                $query->where($indexColumn . " = '" . pSQL($value) . "'");
            }
        }
    }

    /**
     * Map entity property to database index column.
     *
     * @param string $property
     *
     * @return string|null
     */
    private function mapPropertyToIndex(string $property): ?string
    {
        if (!$this->entityClass) {
            return null;
        }

        try {
            $tempEntity = new $this->entityClass();
            $config = $tempEntity->getConfig();
            $indexMap = $config->getIndexMap();
            $indexes = $indexMap->getIndexes();

            $indexNumber = 1;
            foreach ($indexes as $index) {
                if ($index->getProperty() === $property) {
                    return 'index_' . $indexNumber;
                }
                $indexNumber++;
                if ($indexNumber > 6) {
                    break;
                }
            }
        } catch (Exception $e) {
        }

        return null;
    }
}