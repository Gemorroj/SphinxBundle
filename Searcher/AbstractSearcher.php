<?php

namespace Highco\SphinxBundle\Searcher;

abstract class AbstractSearcher implements InterfaceSearcher
{
    protected $offset = 0;
    protected $limit  = 10;
    protected $query;

    /**
     * configureFilters
     * @param  array  $filters  [description]
     * @param  array  $excludes [description]
     * @return void
     */
    public function configureFilters(array $filters = array(), array $excludes = array())
    {
        $this->client->ResetFilters();
        foreach ($filters as $filter => $value)
        {
            if ($value)
            {
                $this->client->SetFilter($filter, (array) $value, in_array($filter, $excludes));
            }
        }
    }

    /**
     * getResults
     *
     * @return void
     */
    public function getResults()
    {
        $this->client->SetLimits($this->offset, $this->limit);
        $results = $this->client->Query($this->query, $this->getIndex());
        $this->bridge->setSphinxResults($results);

        return $this->bridge->getPager();
    }

    /**
     * getAllFields
     *
     * @return void
     */
    public function getAllFields()
    {
        return array_keys($this->getFieldsWeight());
    }

    /**
     * setOffset
     *
     * @param mixed $offset
     * @return void
     */
    public function setOffset($offset)
    {
        $this->offset = (int) $offset;
    }

    /**
     * setLimit
     *
     * @param mixed $limit
     * @return void
     */
    public function setLimit($limit)
    {
        $this->limit = (int) $limit;
    }

    /**
     * setPage
     *
     * @param mixed $page
     * @return void
     */
    public function setPage($page)
    {
        $this->offset = ($page * $this->limit) - $this->limit;
    }

    /**
     * setQuery
     *
     * @param mixed $query
     * @return void
     */
    public function setQuery($query)
    {
        $this->query = $query;
    }
}
