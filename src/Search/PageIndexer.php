<?php

namespace PortlandLabs\Elastic\Search;

use Concrete\Core\Application\ApplicationAwareInterface;
use Concrete\Core\Database\Connection\Connection;
use Concrete\Core\Page\Collection\Collection;
use Concrete\Core\Page\Page;
use Concrete\Core\Page\Search\IndexedSearch;
use PortlandLabs\Elastic\Application\ApplicationAwareTrait;
use PortlandLabs\Elastic\Search\Driver\IndexingDriverInterface;

class PageIndexer implements IndexingDriverInterface, ApplicationAwareInterface
{

    use ApplicationAwareTrait;

    /**
     * @var \Concrete\Core\Page\Search\IndexedSearch
     */
    private $search;

    /**
     * DefaultPageDriver constructor.
     * @param \Concrete\Core\Page\Search\IndexedSearch $search
     */
    public function __construct(IndexedSearch $search)
    {
        $this->search = $search;
    }

    /**
     * Add a page to the index
     * @param string|int|Page $page Page to index. String is path, int is cID
     * @return bool Success or fail
     */
    public function index($page)
    {
        if ($pages = $this->getPages($page)) {
            foreach ($pages as $page) {
                if ($page->getVersionObject()) {
                    return $page->reindex($this->search, true);
                }
            }
        }

        return false;
    }

    /**
     * Remove a page from the index
     * @param string|int|Page $page. String is path, int is cID
     * @return bool Success or fail
     */
    public function forget($page)
    {
        if ($pages = $this->getPages($page)) {
            foreach ($pages as $page) {
                /** @var Connection $database */
                $database = $this->app['database']->connection();
                $database->executeQuery('DELETE FROM PageSearchIndex WHERE cID=?', $page->getCollectionID());
            }
        }

        return false;
    }

    /**
     * Get a page based on criteria
     * @param string|int|Page|Collection $page
     * @return \Concrete\Core\Page\Page
     */
    private function resolvePage($page)
    {
        // Handle passed cID
        if (is_numeric($page)) {
            return Page::getByID($page);
        }

        // Handle passed /path/to/collection
        if (is_string($page)) {
            return Page::getByPath($page);
        }

        // If it's a page, just return the page
        if ($page instanceof Page) {
            return $page;
        }

        // If it's not a page but it's a collection, lets try getting a page by id
        if ($page instanceof Collection) {
            return $this->resolvePage($page->getCollectionID());
        }
    }

    protected function getPages($pages)
    {
        $pages = (array) $pages;

        foreach ($pages as $page) {
            if ($pageObject = $this->resolvePage($page)) {
                if ($pageObject->getPageTypeHandle() != "core_stack" &&
                    substr($pageObject->getCollectionPath(), 0, 2) !== '/!' &&
                    strtolower(substr($pageObject->getCollectionPath(), 0, 11)) !== '/dashboard/'
                ) {
                    yield $pageObject->getCollectionID() => $pageObject;
                }
            }
        }
    }

}
