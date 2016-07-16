<?php

namespace Highco\SphinxBundle\Client;

/**
 * DefaultClient
 *
 * @package HighcoSphinBundle
 * @version 0.2
 * @author Stephane PY <py.stephane1(at)gmail.com>
 * @author Nikola Petkanski <nikola@petkanski.com>
 */
class DefaultClient extends \SphinxClient
{
    public function __construct()
    {
        parent::__construct();

        $this->SetArrayResult(true);
    }
}
