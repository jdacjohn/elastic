<?php

namespace Concrete\Package\Elastic\Job;

use PortlandLabs\Elastic\Job\IndexElastic;

class IndexElasticSearch extends IndexElastic
{

    public function getJobName()
    {
        return t("Index ElasticSearch - Updates");
    }
    public function getJobDescription()
    {
        return t(
            "Index the site to allow searching to work quickly and accurately"
        );
    }

    protected function clearIndex($index)
    {
        // Don't clear the index.
    }

    /**
     * Get Pages to add to the queue
     * @return \Iterator
     */
    protected function pagesToQueue()
    {
        $qb = $this->connection->createQueryBuilder();
        $timeout = \Config::get('concrete.misc.page_search_index_lifetime');
        //'( or psi.cID is null or psi.cDateLastIndexed is null)'
        $statement = $qb->select('p.cID')
            ->from('Pages', 'p')
            ->leftJoin('p', 'Collections', 'c', 'p.cID = c.cID')
            ->leftJoin('p', 'PageSearchIndex', 's', 'p.cID = s.cID')
            ->where('c.cDateModified > s.cDateLastIndexed')
            ->orWhere('UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(s.cDateLastIndexed) > ' . $timeout)
            ->orWhere('s.cID is null')
            ->orWhere('s.cDateLastIndexed is null')->execute();

        while ($id = $statement->fetchColumn()) {
            yield $id;
        }
    }

}
