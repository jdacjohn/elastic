<?php
namespace PortlandLabs\Elastic\Job;

use CollectionAttributeKey;
use Concrete\Core\Database\Connection\Connection;
use Concrete\Core\File\File;
use Concrete\Core\Page\Page;
use Concrete\Core\User\User;
use FileAttributeKey;
use Loader;
use PortlandLabs\Elastic\Search\IndexManagerInterface;
use UserAttributeKey;
use ZendQueue\Message as ZendQueueMessage;
use ZendQueue\Queue as ZendQueue;

abstract class IndexElastic extends \Concrete\Core\Job\QueueableJob
{

    protected $jQueueBatchSize = 1000;

    // A flag for clearing the index
    const CLEAR = "-1";

    public $jNotUninstallable = 1;
    public $jSupportsQueue = true;

    protected $usersIndexed = 0;
    protected $pagesIndexed = 0;
    protected $filesIndexed = 0;

    /*
     * @var \Concrete\Core\Search\Index\IndexManagerInterface
     */
    protected $indexManager;

    /**
     * @var \Concrete\Core\Database\Connection\Connection
     */
    protected $connection;

    public function __construct(IndexManagerInterface $indexManager, Connection $connection)
    {
        $this->indexManager = $indexManager;
        $this->connection = $connection;
    }

    public function start(ZendQueue $queue)
    {
        if (!$queue->count()) {
            $this->clearIndex($this->indexManager);

            // Queue everything
            foreach ($this->pagesToQueue() as $message) {
                $queue->send($message);
            }
        }
    }

    /**
     * Messages to add to the queue
     * @return \Iterator
     */
    protected function queueMessages()
    {
        foreach ($this->pagesToQueue() as $id) {
            yield "P{$id}";
        }
        foreach ($this->usersToQueue() as $id) {
            yield "U{$id}";
        }
        foreach ($this->filesToQueue() as $id) {
            yield "F{$id}";
        }
    }

    public function processQueueItem(ZendQueueMessage $msg)
    {
        $index = $this->indexManager;

        // Handle a "clear" message
        if ($msg->body == self::CLEAR) {
            $this->clearIndex($index);
        } else {
            return $index->index(Page::class, $msg->body);
        }
    }

    public function finish(ZendQueue $q)
    {
        return t(
            'Indexed %s Pages, %s Users, %s Files.',
            $this->pagesIndexed,
            $this->usersIndexed,
            $this->filesIndexed
        );
    }

    /**
     * Clear out all indexes
     * @param $index
     */
    protected function clearIndex($index)
    {
        $index->clear(Page::class);
        $index->clear(User::class);
        $index->clear(File::class);
    }

    /**
     * Get Pages to add to the queue
     * @return \Iterator
     */
    protected function pagesToQueue()
    {
        $qb = $this->connection->createQueryBuilder();

        // Find all pages that need indexing
        $query = $qb
            ->select('p.cID')
            ->from('Pages', 'p')
            ->leftJoin('p', 'CollectionSearchIndexAttributes', 'a', 'p.cID = a.cID')
            ->where('cIsActive = 1')
            ->andWhere($qb->expr()->orX(
                'a.ak_exclude_search_index is null',
                'a.ak_exclude_search_index = 0'
            ))->execute();

        while ($id = $query->fetchColumn()) {
            yield $id;
        }
    }

    /**
     * Get Users to add to the queue
     * @return \Iterator
     */
    protected function usersToQueue()
    {
        /** @var Connection $db */
        $db = $this->connection;

        $query = $db->executeQuery('SELECT uID FROM Users WHERE uIsActive = 1');
        while ($id = $query->fetchColumn()) {
            yield $id;
        }
    }

    /**
     * Get Files to add to the queue
     * @return \Iterator
     */
    protected function filesToQueue()
    {
        /** @var Connection $db */
        $db = $this->connection;

        $query = $db->executeQuery('SELECT fID FROM Files');
        while ($id = $query->fetchColumn()) {
            yield $id;
        }
    }

    private function block($count, $list)
    {
        $block = [];
        $items = 0;

        foreach ($list as $key => $item) {
            $block[$key] = $item;
            $items++;

            if ($items == $count) {
                yield $block;
                $block = [];
                $items = 0;
            }
        }

        if ($items) {
            yield $block;
        }
    }

}
