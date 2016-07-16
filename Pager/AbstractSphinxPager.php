<?php

namespace Highco\SphinxBundle\Pager;

use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * AbstractSphinxPager
 *
 * @package HighcoSphinBundle
 * @version 0.2
 * @author Stephane PY <py.stephane1@gmail.com>
 * @author Nikola Petkanski <nikola@petkanski.com>
 */
abstract class AbstractSphinxPager implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    /**
     * _extractPksFromResults
     *
     * @return array
     */
    protected function _extractPksFromResults()
    {
        $matches = isset($this->results['matches']) ? $this->results['matches'] : array();

        $pks = array();
        
        foreach ($matches as $match) {
            $pks[] = $match['id'];
        }

        return $pks;
    }
}
