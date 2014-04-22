<?php

namespace Vdw\SphinxSearchBundle\Entity;

class SearchResult
{
    private $id;
    private $object;
    private $weight;
    private $fieldMatches = null;

    /**
     * @param int $id
     * @param object $object
     * @param int $weight
     * @param array|null $fieldMatches
     */
    public function __construct($id, $object, $weight, array $fieldMatches = null)
    {
        $this->id = $id;
        $this->object = $object;
        $this->weight = $weight;
        $this->fieldMatches = $fieldMatches;
    }

    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get object
     *
     * @return object
     */
    public function getObject()
    {
        return $this->object;
    }

    /**
     * Get object type (class name)
     *
     * @return string
     */
    public function getObjectType()
    {
        return @end(explode('\\', get_class($this->object)));
    }

    /**
     * Get search-sorting weight
     *
     * @return int
     */
    public function getWeight()
    {
        return $this->weight;
    }

    /**
     * Get field matches (which fields from the index had search-keyword hits)
     *
     * @return array|null
     */
    public function getFieldMatches()
    {
        if (empty($this->fieldMatches)) {
            return null;
        }

        return $this->fieldMatches;
    }

}
