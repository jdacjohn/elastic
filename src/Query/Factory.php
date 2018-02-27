<?php

namespace PortlandLabs\Elastic\Query;


use PortlandLabs\Elastic\Query;

class Factory
{

    /**
     * @var string
     */
    private $index;

    /**
     * @var string
     */
    private $type;

    public function __construct($index, $type)
    {
        $this->index = $index;
        $this->type = $type;
    }

    /**
     * Create an elastic query
     * @param bool $type
     * @param null $id
     * @return \PortlandLabs\Elastic\Query\Query
     */
    public function query($type = false, $id = null)
    {
        $query = new Query();
        $query->index($this->index);

        if ($type) {
            $query->type($this->type);
        }

        if ($id) {
            $query->id($id);
        }

        return $query;
    }

    /**
     * Alias to $this->query(true);
     */
    public function typeQuery($id = null)
    {
        return $this->query(true, $id);
    }

}
