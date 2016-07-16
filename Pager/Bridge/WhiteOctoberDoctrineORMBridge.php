<?php

namespace Highco\SphinxBundle\Pager\Bridge;

use Pagerfanta\Pagerfanta;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\QueryBuilder;

use Highco\SphinxBundle\Pager\InterfaceSphinxPager;
use Highco\SphinxBundle\Pager\AbstractSphinxPager;

/**
 * WhiteOctoberDoctrineORMBridge
 *
 * @uses AbstractSphinxPager
 * @uses InterfaceSphinxPager
 * @author Stephane PY <py.stephane1@gmail.com>
 * @author Nikola Petkanski <nikola@petkanski.com>
 */
class WhiteOctoberDoctrineORMBridge extends AbstractSphinxPager implements InterfaceSphinxPager
{
    /**
     * @var EntityManager|null
     */
    protected $em;

    /**
     * @var string
     */
    protected $repositoryClass;

    /**
     * @var string
     */
    protected $pkColumn = "id";

    /**
     * @var QueryBuilder
     */
    protected $query = null;

    /**
     * The results obtained from sphinx
     *
     * @var array
     */
    protected $results;

    /**
     * The discriminator column name
     *
     * @var string
     */
    protected $discriminatorColumn = null;

    /**
     *
     * @var array Discriminator dependant repositories
     */
    protected $discriminatorRepositories = array();

    /**
     * @return EntityManager
     */
    protected function getEntityManager()
    {
        if ($this->em === null) {
            $this->em = $this->container->get('doctrine')->getManager();
        }

        return $this->em;
    }

    /**
     * Sets the name of the entity manager which should be used to transform Sphinx results to entities.
     *
     * @param string $name
     *
     * @return WhiteOctoberDoctrineORMBridge
     *
     * @throws \LogicException
     */
    public function setEntityManagerByName($name)
    {
        return $this->setEntityManager($this->container->get('doctrine')->getManager($name));
    }

    /**
     * Sets the exact instance of entity manager which should be used to transform Sphinx results to entities.
     *
     * @param EntityManager $em
     *
     * @return WhiteOctoberDoctrineORMBridge
     */
    public function setEntityManager(EntityManager $em)
    {
        if ($this->em !== null) {
            throw new \LogicException('Entity manager can only be set before any results are fetched');
        }

        $this->em = $em;

        return $this;
    }

    /**
     * setRepositoryClass
     *
     * @param mixed $repositoryClass
     *
     * @return WhiteOctoberDoctrineORMBridge
     */
    public function setRepositoryClass($repositoryClass)
    {
        $this->repositoryClass = $repositoryClass;

        return $this;
    }

    /**
     * setPkColumn
     *
     * @param mixed $pkColumn
     *
     * @return WhiteOctoberDoctrineORMBridge
     */
    public function setPkColumn($pkColumn)
    {
        $this->pkColumn = $pkColumn;

        return $this;
    }

    public function setDiscriminatorColumn($column)
    {
        $this->discriminatorColumn = $column;

        return $this;
    }

    public function setDiscriminatorRepository($discriminatorValue, $repositoryClass, $entityManager = 'default')
    {
        $this->discriminatorRepositories[$discriminatorValue] = array(
            'class' => $repositoryClass,
            'em' => $entityManager
        );

        return $this;
    }

    /**
     *
     * @param array $repositories
     *
     * @return WhiteOctoberDoctrineORMBridge
     */
    public function setDiscriminatorRepositories(array $repositories)
    {
        foreach ($repositories as $discriminatorColumn => $data) {
            if (is_array($data)) {
                $params = array(
                    'discriminatorValue'    => $discriminatorColumn,
                    'class'                 => $data['class'],
                );

                if (key_exists('em', $data)) {
                    $params['em'] = $data['em'];
                }

                call_user_func_array(array($this, 'setDiscriminatorRepository'), $params);
            } else {
                $this->setDiscriminatorRepository($discriminatorColumn, $data);
            }
        }

        return $this;
    }

    /**
     * setSphinxResults
     *
     * @param mixed   $results
     * @param boolean $consolidate
     *
     * @return WhiteOctoberDoctrineORMBridge
     */
    public function setSphinxResults($results, $consolidate = false)
    {
        if (!$consolidate) {
            $this->results = $results;
        } else {
            $this->results = array(
                'matches'       => array(),
                'total'         => 0,
                'total_found'   => 0,
            );
            
            foreach($results as $result) {
                $this->results['total'] += $result['total'];
                $this->results['total_found'] += $result['total_found'];
                
                if ($result['total_found'] > 0) {
                    $this->results['matches'] = array_merge($this->results['matches'], $result['matches']);
                }
            }
        }

        return $this;
    }

    /**
     * Returns an instance of the pager
     *
     * @return Pagerfanta
     */
    public function getPager()
    {
        $hasDiscriminator = $this->discriminatorColumn !== null;
        $hasRepositoryClass = $this->repositoryClass !== null;

        if (!$hasRepositoryClass && !$hasDiscriminator) {
            throw new \RuntimeException('You should define either a repository class, either discriminator');
        }

        if (is_null($this->results)) {
            throw new \RuntimeException('You should define sphinx results on '.__CLASS__);
        }

        $adapter = $this->container->get('highco.sphinx.pager.white_october.doctrine_orm.adapter');

        $results = $hasDiscriminator ? $this->getDiscriminatorResults() : $this->getResults();
        
        $adapter->setArray($results);

        $adapter->setNbResults(isset($this->results['total_found']) ? $this->results['total_found'] : 0);

        return new Pagerfanta($adapter);
    }

    /**
     *
     * @return array
     * @throws \UnexpectedValueException
     */
    protected function getDiscriminatorResults()
    {
        $rawResults = $this->results;

        if (empty($rawResults) || empty($rawResults['matches'])) {
            return array();
        }

        $results = array();
        $usedDiscriminators = array();

        /**
         * Collect discriminators and their records
         */
        foreach ($rawResults['matches'] as $row) {
            $id = $row['id'];
            
            if (!key_exists($this->discriminatorColumn, $row['attrs'])) {
                throw new \UnexpectedValueException('Missing discriminator column in sphinx result entry');
            }

            $rowDiscriminator = $row['attrs'][$this->discriminatorColumn];

            if (!key_exists($rowDiscriminator, $usedDiscriminators)) {
                $usedDiscriminators[$rowDiscriminator] = array();
            }

            $results[$id] = null;
            $usedDiscriminators[$rowDiscriminator][$id] = $row;
        }

        /**
         * Fetchs the results for each discriminator used and populate the $results array,
         * which contains the proper order of items as returned by Sphinx
         */
        foreach ($usedDiscriminators as $discriminatorValue => $discriminatorResults) {
            $qb = $this->getDiscriminatorQuery($discriminatorValue);

            $primaryKeys = array_keys($discriminatorResults);

            $query = $qb->where($qb->expr()->in('r.'.$this->pkColumn, $primaryKeys))->getQuery();

            foreach ($query->execute() as $id => $entity) {
                $results[$id] = $entity;
            }
        }

        return $results;
    }

    /**
     * @return array
     */
    protected function getResults()
    {
        $pks = $this->_extractPksFromResults();

        $results = array();

        if (false === empty($pks)) {
            $qb = $this->getQuery();

            $qb = $qb->where($qb->expr()->in('r.'.$this->pkColumn, $pks))
                //->addOrderBy('FIELD(r.id,...)', 'ASC')
                ->getQuery();
            //@todo watching on doctrine FIELD extension ... we cannot use it natively . . . .

            $unordoredResults = $qb->getResult();

            foreach ($pks as $pk) {
                if (isset($unordoredResults[$pk])) {
                    $results[$pk] = $unordoredResults[$pk];
                }
            }
        }

        return $results;
    }

    public function getDiscriminatorQuery($discriminatorValue)
    {
        $discriminatorData = $this->discriminatorRepositories[$discriminatorValue];

        $em = $this->container->get('doctrine')->getManager($discriminatorData['em']);
        /* @var $em EntityManager */

        $qb = $em->createQueryBuilder();

        $repositoryClass = $discriminatorData['class'];

        return $qb->select('r') ->from($repositoryClass, sprintf('r INDEX BY r.%s', $this->pkColumn));
    }

    /**
     * @return QueryBuilder query
     */
    public function getDefaultQuery()
    {
        $qb = $this->getEntityManager()->createQueryBuilder();

        return $qb->select('r') ->from($this->repositoryClass, sprintf('r INDEX BY r.%s', $this->pkColumn));

    }

    /**
     * @param QueryBuilder|null $query
     * @return WhiteOctoberDoctrineORMBridge
     */
    public function setQuery(QueryBuilder $query = null)
    {
        $this->query = $query;

        return $this;
    }

    /**
     * setDefaultQuery
     *
     * @return QueryBuilder
     */
    public function getQuery()
    {
        if ($this->query == null) {
            return $this->getDefaultQuery();
        }

        return $this->query;
    }
}
