<?php

namespace PortlandLabs\Elastic\Search;

use PortlandLabs\Elastic\Search\Driver\IndexingDriverInterface;

/**
 * Interface IndexInterface
 * @package PortlandLabs\Elastic\Search\Index
 */
interface IndexInterface extends IndexingDriverInterface
{

    /**
     * Clear out all indexed items
     * @return void
     */
    public function clear();

}
