<?php

namespace Highco\SphinxBundle\Searcher;

interface InterfaceSearcher
{
    public function configureFilters(array $filters = array());
    public function getResults();
    public function getAllFields();
    public function setOffset($offset);
    public function setLimit($limit);
    public function setPage($page);
    public function setQuery($query);
}
