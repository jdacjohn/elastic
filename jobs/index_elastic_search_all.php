<?php

namespace Concrete\Package\Elastic\Job;

use PortlandLabs\Elastic\Job\IndexElastic;

class IndexElasticSearchAll extends IndexElastic
{

    public function getJobName()
    {
        return t("Index ElasticSearch - All");
    }

    public function getJobDescription()
    {
        return t("Empties the page search index and reindexes all pages.");
    }

}
