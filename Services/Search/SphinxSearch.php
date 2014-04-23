<?php

namespace Vdw\SphinxSearchBundle\Services\Search;

use Symfony\Component\OptionsResolver\OptionsResolver;
use Vdw\SphinxSearchBundle\Entity\SearchResult;

class SphinxSearch
{
    /**
     * @var string $host
     */
    private $host;

    /**
     * @var string $port
     */
    private $port;

    /**
     * @var string $socket
     */
    private $socket;

    /**
     * @var array $indexes
     *
     * $this->indexes should look like:
     *
     * $this->indexes = array(
     *   'IndexLabel' => 'Index name as defined in sphinx/etc/sphinx.conf',
     *   ...,
     * );
     */
    private $indexes;

    /**
     * @var \SphinxClient $sphinx
     */
    protected $sphinx;

    /**
     * Constructor.
     *
     * @param string $host The server's host name/IP.
     * @param string $port The port that the server is listening on.
     * @param string $socket The UNIX socket that the server is listening on.
     * @param array $indexes The list of indexes that can be used.
     */
    public function __construct($host = 'localhost', $port = '9312', $socket = null, array $indexes = array())
    {
        $this->host = $host;
        $this->port = $port;
        $this->socket = $socket;
        $this->indexes = $indexes;

        $this->sphinx = new \SphinxClient();
        if( $this->socket !== null )
            $this->sphinx->SetServer($this->socket);
        else
            $this->sphinx->SetServer($this->host, $this->port);
    }

    /**
     * Escape the supplied string.
     *
     * @param string $string The string to be escaped.
     *
     * @return string The escaped string.
     */
    public function escapeString($string)
    {
        return $this->sphinx->EscapeString($string);
    }

    /**
     * Set the desired match mode.
     *
     * @param int $mode The matching mode to be used.
     */
    public function setMatchMode($mode)
    {
        $this->sphinx->SetMatchMode($mode);
    }

    public function setRankingMode($mode)
    {
        // undocumented dependency: fieldmask ranker depends on extended2 match mode
        if ($mode === SPH_RANK_FIELDMASK) {
            $this->sphinx->SetMatchMode(SPH_MATCH_EXTENDED2);
        }
        $this->sphinx->SetRankingMode($mode);
    }

    /**
     * Set limits on the range and number of results returned.
     *
     * @param int $offset The number of results to seek past.
     * @param int $limit The number of results to return.
     * @param int $max The maximum number of matches to retrieve.
     * @param int $cutoff The cutoff to stop searching at.
     */
    public function setLimits($offset, $limit, $max = 0, $cutoff = 0)
    {
        $this->sphinx->SetLimits($offset, $limit, $max, $cutoff);
    }

    /**
     * Set weights for individual fields.  $weights should look like:
     *
     * $weights = array(
     *   'Normal field name' => 1,
     *   'Important field name' => 10,
     * );
     *
     * @param array $weights Array of field weights.
     */
    public function setFieldWeights(array $weights)
    {
        $this->sphinx->SetFieldWeights($weights);
    }

    /**
     * Set the desired search filter.
     *
     * @param string $attribute The attribute to filter.
     * @param array $values The values to filter.
     * @param bool $exclude Is this an exclusion filter?
     */
    public function setFilter($attribute, $values, $exclude = false)
    {
        $this->sphinx->SetFilter($attribute, $values, $exclude);
    }

    /**
     * Reset all previously set filters.
     */
    public function resetFilters()
    {
        $this->sphinx->ResetFilters();
    }

    /**
     * Reset SphinxClient to its defaults
     */
    public function reset()
    {
        $this->sphinx->ResetFilters();
        $this->sphinx->ResetGroupBy();
        $this->sphinx->ResetOverrides();
        $this->setMatchMode(SPH_MATCH_ALL);
        $this->setRankingMode(SPH_RANK_PROXIMITY_BM25);
    }

    /**
     * @param string $query
     * @param array $options
     * @return array|int
     * @throws \InvalidArgumentException
     */
    public function search($query, array $options = array())
    {
        $options = $this->resolveSearchOptions($options);

        $searchResults = array(); // return value, an array of SearchResult objects
        $fieldMatches = array();  // id => array(matchedFields)

        // perform the necessary searches
        if ($options['include_field_matches'] && !$options['total_only']) {
            $fieldMatches = $this->doSearchFieldMatches($query, $options['indexes'], $options, $options['escape_query']);
        }
        $results = $this->doSearch($query, $options['indexes'], $options, $options['escape_query']);

        // return early if we can
        if ($options['total_only']) return $results['total_found'];
        if ($options['raw_results']) return $results;

        // convert results to an array of SearchResult objects
        if (isset($results['matches'])) {

            $resultWeights = array(); // id => weight
            foreach ($results['matches'] as $id => $data) $resultWeights[(int)$id] = (int)$data['weight'];

            // convert the ids to entities if a hydrator closure was passed in
            if (isset($options['entity_hydrator'])) {

                $hydrator = $options['entity_hydrator'];

                $hydratedResults = $hydrator(array_keys($resultWeights));

                // sort the converted entities by their result weights
                usort($hydratedResults, function($a, $b) use ($resultWeights) {
                    return $resultWeights[$a->getId()] > $resultWeights[$b->getId()] ? -1 : 1;
                });

                // populate the return value with new SearchResult objects
                foreach ($hydratedResults as $entity) {
                    $searchResults[] = new SearchResult($entity->getId(), $entity, $resultWeights[$entity->getId()],
                        $options['include_field_matches'] ? $fieldMatches[$entity->getId()] : null
                    );
                }

            } else {

                foreach ($results['matches'] as $id => $data) {
                    $data['id'] = $id;
                    $searchResults[] = new SearchResult($id, (object)$data, (int)$data['weight'],
                        $options['include_field_matches'] ? $fieldMatches[$id] : null
                    );
                }
            }
        }

        return $searchResults;
    }

    protected function resolveSearchOptions(array $options)
    {
        $resolver = new OptionsResolver();

        $resolver->setDefaults(array(
            'result_offset' => 0,
            'result_limit' => 100000,
            'escape_query' => true,
            'include_field_matches' => false,
            'total_only' => false,
            'raw_results' => false,
        ));

        $resolver->setOptional(array(
            'field_weights',
            'entity_hydrator',
        ));

        $resolver->setRequired(array(
            'indexes',
        ));

        $resolver->setAllowedTypes(array(
            'result_offset' => 'int',
            'result_limit' => 'int',
            'escape_query' => 'bool',
            'indexes' => array('string', 'array'),
            'include_field_matches' => 'bool',
            'total_only' => 'bool',
            'entity_hydrator' => 'callable',
            'field_weights' => 'array',
            'raw_results' => 'bool',
        ));

        $options = $resolver->resolve($options);

        if ($options['raw_results'] && isset($options['entity_hydrator'])) {
            throw new \InvalidArgumentException('Can only use one of `raw_results` or `entity_hydrator` at a time.');
        }

        // coerce indexes to an array if it isn't already
        if (!is_array($options['indexes'])) {
            $options['indexes'] = array($options['indexes']);
        }

        return $options;
    }

    /**
     * Search for the specified query string.
     *
     * @param string $query The query string that we are searching for.
     * @param array $indexes The indexes to perform the search on.
     * @param array $options The options for the query.
     * @param bool $escapeQuery Should the query string be escaped?
     *
     * @return array The results of the search.
     * @throws \RuntimeException
     */
    protected function doSearch($query, array $indexes, array $options = array(), $escapeQuery = true)
    {
        if( $escapeQuery )
            $query = $this->sphinx->EscapeString($query);

        /**
         * Build the list of indexes to be queried.
         */
        $indexNames = '';
        foreach( $indexes as &$label ) {
            if( isset($this->indexes[$label]) )
                $indexNames .= $this->indexes[$label] . ' ';
        }

        /**
         * If no valid indexes were specified, return an empty result set.
         *
         * FIXME: This should probably throw an exception.
         */
        if( empty($indexNames) )
            return array();

        /**
         * Set the offset and limit for the returned results.
         */
        if( isset($options['result_offset']) && isset($options['result_limit']) )
            $this->sphinx->SetLimits($options['result_offset'], $options['result_limit']);

        /**
         * Weight the individual fields.
         */
        if( isset($options['field_weights']) )
            $this->sphinx->SetFieldWeights($options['field_weights']);

        /**
         * Perform the query.
         */
        $results = $this->sphinx->Query($query, $indexNames);

        if( $results['status'] !== SEARCHD_OK ) {
            throw new \RuntimeException(sprintf('Searching index%s "%s" for "%s" failed with error "%s".',
                count($indexes) > 1 ? 'es' : '', implode(', ', $indexes), $query, $this->sphinx->getLastError()
            ));
        }

        return $results;
    }

    /**
     * Search for the specified query string in the specified indexes, returning which fields matched for each result
     *
     * @param $query
     * @param array $indexes
     * @param array $options
     * @param bool $escapeQuery
     * @return array
     * @throws \RuntimeException
     */
    protected function doSearchFieldMatches($query, array $indexes, array $options = array(), $escapeQuery = true)
    {
        $this->setRankingMode(SPH_RANK_FIELDMASK);
        $searchResults = $this->doSearch($query, $indexes, $options, $escapeQuery);
        $this->reset();

        $fieldMasks = array();
        foreach ($searchResults['matches'] as $id => $data) $fieldMasks[$id] = (int)$data['weight'];

        $fieldMatchResults = array();
        foreach ($fieldMasks as $id => $weight) {

            foreach ($searchResults['fields'] as $bitPosition => $field) {
                // bitwise AND will equal 2^$bitPosition if the field matched (true), zero otherwise (false)
                $fieldMatchResults[$id][$field] = (bool)(pow(2, $bitPosition) & $weight);
            }
        }

        return $fieldMatchResults;
    }

    /**
     * Adds a query to a multi-query batch using current settings.
     *
     * @param string $query The query string that we are searching for.
     * @param array $indexes The indexes to perform the search on.
     */
    public function addQuery($query, array $indexes)
    {
        $indexNames = '';
        foreach( $indexes as &$label ) {
            if( isset($this->indexes[$label]) )
                $indexNames .= $this->indexes[$label] . ' ';
        }

        if( !empty($indexNames) )
            $this->sphinx->AddQuery($query, $indexNames);
    }

    /**
     * Runs the currently batched queries, and returns the results.
     *
     * @return array The results of the queries.
     */
    public function runQueries()
    {
        return $this->sphinx->RunQueries();
    }
}
