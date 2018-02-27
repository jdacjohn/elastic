<?php
namespace PortlandLabs\Elastic\ItemList;

use Concrete\Core\Attribute\Key\CollectionKey;
use Concrete\Core\Logging\Logger;
use Concrete\Core\Search\ItemList\Database\AttributedItemList;
use Concrete\Core\Search\Pagination\Pagination;
use Elastica\Client;
use Elastica\QueryBuilder;
use Illuminate\Config\Repository;
use PortlandLabs\Elastic\Pagerfanta\ElasticaAdapter;
use PortlandLabs\Elastic\Query\Factory;

class PageList extends AttributedItemList
{

    protected $proxyPageList;

    protected $elastica;

    protected $query;

    protected $factory;

    protected $type;

    protected $index;

    public function __construct(Client $client, Factory $queryFactory, Repository $config)
    {
        $this->elastica = $client;
        $this->index = $client->getIndex($config->get('elastic::elastic.index'));
        $this->type = $this->index->getType($config->get('elastic::elastic.type'));
        $this->proxyPageList = new \Concrete\Core\Page\PageList();
        $this->factory = $queryFactory;
    }

    public function executeGetResults()
    {
        $query = $this->query;
        $result = $this->type->search($query);

        return $result->getResults();
    }

    protected function getAttributeKeyClassName()
    {
        return CollectionKey::class;
    }

    public function createQuery()
    {
        // We don't need to do this here.
    }

    /**
     * @param \Elastica\Result $mixed
     * @return \Concrete\Core\Page\Page
     */
    public function getResult($mixed)
    {
        $result = [
            'cID' => $mixed->getId(),
            'cIndexScore' => $mixed->getScore()
        ];

        $page = $this->proxyPageList->getResult($result);
        if ($page && $page->isActive()) {
            return $page;
        }

        $this->elastica->deleteIds([$mixed->getId()], $this->getIndex(), $this->getType());
    }

    /**
     * @return \Concrete\Core\Search\Pagination\Pagination
     */
    protected function createPaginationObject()
    {
        return new Pagination($this, new ElasticaAdapter($this->type, $this->query));
    }

    /**
     * Returns the total results in this item list.
     *
     * @return int
     */
    public function getTotalResults()
    {
        $results = $this->type->search($this->query);
        return $results->count();
    }

    public function filterByAttribute($handle, $value, $comparison = '=')
    {
        return;
    }

    public function setQuery($query)
    {
        $this->query = $query;
    }

    /**
     * @return \Elastica\Type
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return \Elastica\Index
     */
    public function getIndex()
    {
        return $this->index;
    }

}
