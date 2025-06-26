<?php

namespace ChannelEngineCore\Infrastructure\ORM;

use ChannelEngine\Infrastructure\ORM\Entity;
use ChannelEngine\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException;
use ChannelEngine\Infrastructure\ORM\Interfaces\QueueItemRepository as QueueItemRepositoryInterface;
use ChannelEngine\Infrastructure\ORM\QueryFilter\Operators;
use ChannelEngine\Infrastructure\ORM\QueryFilter\QueryFilter;
use ChannelEngine\Infrastructure\TaskExecution\Exceptions\QueueItemSaveException;
use ChannelEngine\Infrastructure\TaskExecution\Interfaces\Priority;
use ChannelEngine\Infrastructure\TaskExecution\QueueItem;
use Db;
use DbQuery;
use PrestaShopDatabaseException;
use PrestaShopException;

class QueueItemRepository extends GenericEntityRepository implements QueueItemRepositoryInterface
{
    const THIS_CLASS_NAME = __CLASS__;

    /**
     * Finds list of earliest queued queue items per queue.
     *
     * @param int $priority
     * @param int $limit
     *
     * @return QueueItem[]
     *
     * @throws QueryFilterInvalidParamException
     * @throws PrestaShopException
     */
    public function findOldestQueuedItems($priority, $limit = 10): array
    {
        if ($priority !== Priority::NORMAL) {
            return array();
        }

        $runningQueueNames = $this->getRunningQueueNames();

        return $this->getQueuedItems($runningQueueNames, $limit);
    }

    /**
     * Creates or updates given queue item
     *
     * @param QueueItem $queueItem
     * @param array $additionalWhere
     *
     * @return int
     *
     * @throws QueueItemSaveException
     * @throws QueryFilterInvalidParamException
     * @throws PrestaShopException
     */
    public function saveWithCondition(QueueItem $queueItem, array $additionalWhere = array()): int
    {
        $savedItemId = null;
        try {
            $itemId = $queueItem->getId();
            if ($itemId === null || $itemId <= 0) {
                $savedItemId = $this->save($queueItem);
            } else {
                $this->updateQueueItem($queueItem, $additionalWhere);
            }
        } catch (PrestaShopDatabaseException $exception) {
            throw new QueueItemSaveException(
                'Failed to save queue item. SQL error: ' . Db::getInstance()->getMsgError(),
                0,
                $exception
            );
        }

        return $savedItemId ?: $itemId;
    }

    /**
     * Updates status of queue items with provided ids
     *
     * @param array $ids
     * @param string $status
     */
    public function batchStatusUpdate(array $ids, $status): void
    {
        if (empty($ids)) {
            return;
        }

        $idsString = implode(',', array_map('intval', $ids));
        $statusIndex = $this->getIndexMapping('status');

        $query = "UPDATE " . _DB_PREFIX_ . static::TABLE_NAME . " 
                  SET data = JSON_SET(data, '$.status', '" . pSQL($status) . "'),
                      " . $statusIndex . " = '" . pSQL($status) . "'
                  WHERE id IN ({$idsString}) 
                  AND type = 'QueueItem'";

        Db::getInstance()->execute($query);
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
            if ($index > 7) {
                break;
            }

            $record['index_' . $index] = $value !== null ? pSQL($value, true) : null;
        }

        return $record;
    }

    /**
     * Updates queue item.
     *
     * @param QueueItem $queueItem
     * @param array $additionalWhere
     *
     * @throws QueryFilterInvalidParamException
     * @throws QueueItemSaveException
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function updateQueueItem(QueueItem $queueItem, array $additionalWhere): void
    {
        $filter = new QueryFilter();
        $filter->where('id', Operators::EQUALS, $queueItem->getId());

        foreach ($additionalWhere as $name => $value) {
            $filter->where($name, Operators::EQUALS, $value === null ? '' : $value);
        }

        $item = $this->selectOne($filter);
        if ($item === null) {
            throw new QueueItemSaveException("Cannot update queue item with id {$queueItem->getId()}.");
        }

        $this->update($queueItem);
    }

    /**
     * Returns names of queues containing items that are currently in progress.
     *
     * @return array
     *
     * @throws QueryFilterInvalidParamException
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function getRunningQueueNames(): array
    {
        $filter = new QueryFilter();
        $filter->where('status', Operators::EQUALS, QueueItem::IN_PROGRESS);
        $filter->setLimit(10000);

        /** @var QueueItem[] $runningQueueItems */
        $runningQueueItems = $this->select($filter);

        return array_map(
            function (QueueItem $runningQueueItem) {
                return $runningQueueItem->getQueueName();
            },
            $runningQueueItems
        );
    }

    /**
     * Returns all queued items.
     *
     * @param array $runningQueueNames
     * @param int $limit
     *
     * @return QueueItem[]
     *
     * @throws PrestaShopException
     */
    private function getQueuedItems(array $runningQueueNames, int $limit): array
    {
        $queuedItems = array();
        $queueNameIndex = $this->getIndexMapping('queueName');

        try {
            $condition = sprintf(
                ' %s',
                $this->buildWhereString(array(
                    'type' => 'QueueItem',
                    $this->getIndexMapping('status') => QueueItem::QUEUED,
                ))
            );

            if (!empty($runningQueueNames)) {
                $condition .= sprintf(
                    ' AND ' . $queueNameIndex . " NOT IN ('%s')",
                    implode("', '", array_map('pSQL', $runningQueueNames))
                );
            }

            $queueNamesQuery = new DbQuery();
            $queueNamesQuery->select($queueNameIndex . ', MIN(id) AS id')
                ->from(static::TABLE_NAME)
                ->where($condition)
                ->groupBy($queueNameIndex)
                ->limit($limit);

            $query = 'SELECT queueTable.id,queueTable.data'
                . ' FROM (' . $queueNamesQuery->build() . ') AS queueView'
                . ' INNER JOIN ' . _DB_PREFIX_ . static::TABLE_NAME . ' AS queueTable'
                . ' ON queueView.id = queueTable.id';

            $records = Db::getInstance()->executeS($query);
            $queuedItems = $this->unserializeEntities($records);
        } catch (PrestaShopDatabaseException $exception) {
        }

        return $queuedItems;
    }

    /**
     * Build properly escaped where condition string based on given key/value parameters.
     *
     * @param array $whereFields
     *
     * @return string
     */
    private function buildWhereString(array $whereFields = array()): string
    {
        $where = array();
        foreach ($whereFields as $field => $value) {
            $where[] = $field . Operators::EQUALS . "'" . pSQL($value) . "'";
        }

        return implode(' AND ', $where);
    }
}