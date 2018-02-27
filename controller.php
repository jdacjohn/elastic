<?php

namespace Concrete\Package\Elastic;

use Concrete\Core\Foundation\Service\ProviderList;
use Concrete\Core\Job\Job;
use Concrete\Core\Package\Package;
use Concrete\Core\Support\Facade\Application;
use PortlandLabs\Elastic\ElasticSearchProvider;
use PortlandLabs\Elastic\Search\ElasticIndex;

class Controller extends Package
{

    public function getPackageHandle()
    {
        return "elastic";
    }

    public function getPackageVersion()
    {
        return '0.0.9';
    }

    public function getPackageAutoloaderRegistries()
    {
        return [
            'src' => "\\PortlandLabs\\Elastic"
        ];
    }

    public function getApplicationVersionRequired()
    {
        return '5.7.0';
    }

    public function getPackageName()
    {
        return t('Elastic Search');
    }

    public function getPackageDescription()
    {
        return t('Replaces default search index with elasticsearch.');
    }

    public function on_start()
    {
        // Include dependencies
        require_once __DIR__ . "/vendor/autoload.php";

        if (!$this->app) {
            $this->app = Application::getFacadeApplication();
        }

        $list = $this->app->make(ProviderList::class);
        $list->registerProvider(ElasticSearchProvider::class);
    }

    public function install()
    {
        $pkg = parent::install();
        $pkg->on_start();

        $job = Job::installByPackage('index_elastic_search', $pkg);
        $job = Job::installByPackage('index_elastic_search_all', $pkg);
    }

}
