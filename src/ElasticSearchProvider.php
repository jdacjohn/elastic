<?php

namespace PortlandLabs\Elastic;

use Concrete\Core\Foundation\Service\Provider;
use Concrete\Core\Page\Page;
use Elastica\Client as Elastica;
use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use PortlandLabs\Elastic\Log\Logger;
use PortlandLabs\Elastic\Query\Factory;
use PortlandLabs\Elastic\Search\DefaultManager;
use PortlandLabs\Elastic\Search\ElasticIndex;
use PortlandLabs\Elastic\Search\IndexManagerInterface;

class ElasticSearchProvider extends Provider
{
    /**
     * Registers the services provided by this provider.
     */
    public function register()
    {

        $this->app->bindIf(IndexManagerInterface::class, DefaultManager::class);
        $this->app->resolving(DefaultManager::class, function (DefaultManager $manager) {
            $manager->addIndex(Page::class, ElasticIndex::class);
        });

        $this->app->bind(Client::class, function ($app) {
            $config = ['hosts' => $app['config']['elastic::elastic.hosts']];
            return ClientBuilder::fromConfig($config);
        });

        $this->app->bind(Elastica::class, function ($app) {
            $repo = $app['config'];
            $config = [];
            $config['host'] = $repo['elastic::elastic']['hosts'][0];

            return new Elastica($config, null, new Logger('elastica', Logger::ERROR));
        });

        $this->app->bind(Factory::class, function ($app) {
            $config = $app->make('config');
            return $app->build(Factory::class, [
                $config['elastic::elastic.index'],
                $config['elastic::elastic.type']
            ]);
        });
    }

}
