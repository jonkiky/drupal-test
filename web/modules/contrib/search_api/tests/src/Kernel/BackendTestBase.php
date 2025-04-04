<?php

namespace Drupal\Tests\search_api\Kernel;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Query\ResultSetInterface;
use Drupal\search_api\Utility\Utility;
use Drupal\Tests\search_api\Functional\ExampleContentTrait;

/**
 * Provides a base class for backend tests.
 *
 * Implementing classes are encouraged to override the following methods:
 * - checkServerBackend()
 * - updateIndex()
 * - checkSecondServer()
 * - checkModuleUninstall()
 * - checkBackendSpecificFeatures()
 * - backendSpecificRegressionTests()
 */
abstract class BackendTestBase extends KernelTestBase {

  use ExampleContentTrait;
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'search_api',
    'user',
    'system',
    'entity_test',
    'filter',
    'text',
    'search_api_test_example_content',
  ];

  /**
   * A search server ID.
   *
   * @var string
   */
  protected $serverId = 'search_server';

  /**
   * A search index ID.
   *
   * @var string
   */
  protected $indexId = 'search_index';

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installSchema('search_api', ['search_api_item']);
    $this->installSchema('user', ['users_data']);
    $this->installEntitySchema('entity_test_mulrev_changed');
    $this->installEntitySchema('search_api_task');
    $this->installConfig('search_api_test_example_content');
    $this->installConfig('search_api');

    // Do not use a batch for tracking the initial items after creating an
    // index when running the tests via the GUI. Otherwise, it seems Drupal's
    // Batch API gets confused and the test fails.
    if (!Utility::isRunningInCli()) {
      \Drupal::state()->set('search_api_use_tracking_batch', FALSE);
    }

    $this->setUpExampleStructure();
  }

  /**
   * Tests various indexing scenarios for the search backend.
   *
   * Uses a single method to save time.
   */
  public function testBackend() {
    $this->insertExampleContent();
    $this->checkDefaultServer();
    $this->checkServerBackend();
    $this->checkDefaultIndex();
    $this->updateIndex();
    $this->searchNoResults();
    $this->indexItems($this->indexId);
    $this->searchSuccess();
    if ($this->getServer()->supportsFeature('search_api_facets')) {
      $this->checkFacets();
    }
    $this->checkSecondServer();
    $this->regressionTests();
    $this->clearIndex();

    $this->indexItems($this->indexId);
    $this->backendSpecificRegressionTests();
    $this->checkBackendSpecificFeatures();
    $this->clearIndex();

    $this->enableHtmlFilter();
    $this->indexItems($this->indexId);
    $this->disableHtmlFilter();
    $this->clearIndex();

    $this->searchNoResults();
    $this->regressionTests2();

    $this->checkIndexWithoutFields();

    $this->checkModuleUninstall();
  }

  /**
   * Tests the correct setup of the server backend.
   */
  protected function checkServerBackend() {}

  /**
   * Checks whether changes to the index's fields are picked up by the server.
   */
  protected function updateIndex() {}

  /**
   * Tests that a second server doesn't interfere with the first.
   */
  protected function checkSecondServer() {}

  /**
   * Tests whether removing the configuration again works as it should.
   */
  protected function checkModuleUninstall() {}

  /**
   * Checks backend specific features.
   */
  protected function checkBackendSpecificFeatures() {}

  /**
   * Runs backend specific regression tests.
   */
  protected function backendSpecificRegressionTests() {}

  /**
   * Tests the server that was installed through default configuration files.
   */
  protected function checkDefaultServer() {
    $server = $this->getServer();
    $this->assertInstanceOf(Server::class, $server, 'The server was successfully created.');
  }

  /**
   * Tests the index that was installed through default configuration files.
   */
  protected function checkDefaultIndex() {
    $index = $this->getIndex();
    $this->assertInstanceOf(Index::class, $index, 'The index was successfully created.');

    $this->assertEquals(["entity:entity_test_mulrev_changed"], $index->getDatasourceIds(), 'Datasources are set correctly.');
    $this->assertEquals('default', $index->getTrackerId(), 'Tracker is set correctly.');

    $this->assertEquals(5, $index->getTrackerInstance()->getTotalItemsCount(), 'Correct item count.');
    $this->assertEquals(0, $index->getTrackerInstance()->getIndexedItemsCount(), 'All items still need to be indexed.');
  }

  /**
   * Enables the "HTML Filter" processor for the index.
   */
  protected function enableHtmlFilter() {
    $index = $this->getIndex();

    /** @var \Drupal\search_api\Processor\ProcessorInterface $processor */
    $processor = \Drupal::getContainer()
      ->get('search_api.plugin_helper')
      ->createProcessorPlugin($index, 'html_filter');
    $index->addProcessor($processor)->save();

    $this->assertArrayHasKey('html_filter', $index->getProcessors(), 'HTML filter processor is added.');
  }

  /**
   * Disables the "HTML Filter" processor for the index.
   */
  protected function disableHtmlFilter() {
    $index = $this->getIndex();
    $index->removeProcessor('html_filter');
    $index->save();

    $this->assertArrayNotHasKey('html_filter', $index->getProcessors(), 'HTML filter processor is removed.');
  }

  /**
   * Builds a search query for testing purposes.
   *
   * Used as a helper method during testing.
   *
   * @param string|array|null $keys
   *   (optional) The search keys to set, if any.
   * @param string[] $conditions
   *   (optional) Conditions to set on the query, in the format "field,value".
   * @param string[]|null $fields
   *   (optional) Fulltext fields to search for the keys.
   * @param bool $place_id_sort
   *   (optional) Whether to place a default sort on the item ID.
   *
   * @return \Drupal\search_api\Query\QueryInterface
   *   A search query on the test index.
   */
  protected function buildSearch($keys = NULL, array $conditions = [], ?array $fields = NULL, $place_id_sort = TRUE) {
    static $i = 0;

    $query = $this->getIndex()->query();
    if ($keys) {
      $query->keys($keys);
      if ($fields) {
        $query->setFulltextFields($fields);
      }
    }
    foreach ($conditions as $condition) {
      [$field, $value] = explode(',', $condition, 2);
      $query->addCondition($field, $value);
    }
    $query->range(0, 10);
    if ($place_id_sort) {
      // Use the normal "id" and the magic "search_api_id" field alternately, to
      // make sure both work as expected.
      $query->sort((++$i % 2) ? 'id' : 'search_api_id');
    }

    return $query;
  }

  /**
   * Tests that a search on the index doesn't have any results.
   */
  protected function searchNoResults() {
    $results = $this->buildSearch('test')->execute();
    $this->assertResults([], $results, 'Search before indexing');
  }

  /**
   * Tests whether some test searches have the correct results.
   */
  protected function searchSuccess() {
    $results = $this->buildSearch('test')->range(1, 2)->execute();
    $this->assertEquals(4, $results->getResultCount(), 'Search for »test« returned correct number of results.');
    $this->assertEquals($this->getItemIds([2, 3]), array_keys($results->getResultItems()), 'Search for »test« returned correct result.');
    $this->assertEmpty($results->getIgnoredSearchKeys());
    $this->assertEmpty($results->getWarnings());

    $id = $this->getItemIds([2])[0];
    $this->assertEquals($id, key($results->getResultItems()));
    $this->assertEquals($id, $results->getResultItems()[$id]->getId());
    $this->assertEquals('entity:entity_test_mulrev_changed', $results->getResultItems()[$id]->getDatasourceId());

    $results = $this->buildSearch('test foo')->execute();
    $this->assertResults([1, 2, 4], $results, 'Search for »test foo«');

    $results = $this->buildSearch('foo', ['type,item'])->execute();
    $this->assertResults([1, 2], $results, 'Search for »foo«');

    $keys = [
      '#conjunction' => 'AND',
      'test',
      [
        '#conjunction' => 'OR',
        'baz',
        'foobar',
      ],
      [
        '#conjunction' => 'OR',
        '#negation' => TRUE,
        'bar',
        // cspell:disable-next-line
        'fooblob',
      ],
    ];
    $results = $this->buildSearch($keys)->execute();
    $this->assertResults([4], $results, 'Complex search 1');

    $query = $this->buildSearch();
    $conditions = $query->createAndAddConditionGroup('OR');
    $conditions->addCondition('name', 'bar');
    $conditions->addCondition('body', 'bar');
    $results = $query->execute();
    $this->assertResults([1, 2, 3, 5], $results, 'Search with multi-field fulltext filter');

    $results = $this->buildSearch()
      ->addCondition('keywords', ['grape', 'apple'], 'IN')
      ->execute();
    $this->assertResults([2, 4, 5], $results, 'Query with IN filter');

    $results = $this->buildSearch()->addCondition('keywords', ['grape', 'apple'], 'NOT IN')->execute();
    $this->assertResults([1, 3], $results, 'Query with NOT IN filter');

    $results = $this->buildSearch()->addCondition('width', ['0.9', '1.5'], 'BETWEEN')->execute();
    $this->assertResults([4], $results, 'Query with BETWEEN filter');

    $results = $this->buildSearch()
      ->addCondition('width', ['0.9', '1.5'], 'NOT BETWEEN')
      ->execute();
    $this->assertResults([1, 2, 3, 5], $results, 'Query with NOT BETWEEN filter');

    $results = $this->buildSearch()
      ->setLanguages(['und', 'en'])
      ->addCondition('keywords', ['grape', 'apple'], 'IN')
      ->execute();
    $this->assertResults([2, 4, 5], $results, 'Query with IN filter');

    $results = $this->buildSearch()
      ->setLanguages(['und'])
      ->execute();
    $this->assertResults([], $results, 'Query with languages');

    $query = $this->buildSearch();
    $query->createAndAddConditionGroup('OR')
      ->addCondition('search_api_language', 'und')
      ->addCondition('width', ['0.9', '1.5'], 'BETWEEN');
    $results = $query->execute();
    $this->assertResults([4], $results, 'Query with search_api_language filter');

    $results = $this->buildSearch()
      ->addCondition('search_api_language', 'und')
      ->addCondition('width', ['0.9', '1.5'], 'BETWEEN')
      ->execute();
    $this->assertResults([], $results, 'Query with search_api_language filter');

    $results = $this->buildSearch()
      ->addCondition('search_api_language', ['und', 'en'], 'IN')
      ->addCondition('width', ['0.9', '1.5'], 'BETWEEN')
      ->execute();
    $this->assertResults([4], $results, 'Query with search_api_language filter');

    $results = $this->buildSearch()
      ->addCondition('search_api_language', ['und', 'de'], 'NOT IN')
      ->addCondition('width', ['0.9', '1.5'], 'BETWEEN')
      ->execute();
    $this->assertResults([4], $results, 'Query with search_api_language "NOT IN" filter');

    $results = $this->buildSearch()
      ->addCondition('search_api_id', $this->getItemIds([1])[0])
      ->execute();
    $this->assertResults([1], $results, 'Query with search_api_id filter');

    $results = $this->buildSearch()
      ->addCondition('search_api_id', $this->getItemIds([2, 4]), 'NOT IN')
      ->execute();
    $this->assertResults([1, 3, 5], $results, 'Query with search_api_id "NOT IN" filter');

    $results = $this->buildSearch()
      ->addCondition('search_api_id', $this->getItemIds([3])[0], '>')
      ->execute();
    $this->assertResults([4, 5], $results, 'Query with search_api_id "greater than" filter');

    $results = $this->buildSearch()
      ->addCondition('search_api_datasource', 'foobar')
      ->execute();
    $this->assertResults([], $results, 'Query for a non-existing datasource');

    $results = $this->buildSearch()
      ->addCondition('search_api_datasource', ['foobar', 'entity:entity_test_mulrev_changed'], 'IN')
      ->execute();
    $this->assertResults([1, 2, 3, 4, 5], $results, 'Query with search_api_id "IN" filter');

    $results = $this->buildSearch()
      ->addCondition('search_api_datasource', ['foobar', 'entity:entity_test_mulrev_changed'], 'NOT IN')
      ->execute();
    $this->assertResults([], $results, 'Query with search_api_id "NOT IN" filter');

    // For a query without keys, all of these except for the last one should
    // have no effect. Therefore, we expect results with IDs in descending
    // order.
    $results = $this->buildSearch(NULL, [], [], FALSE)
      ->sort('search_api_relevance')
      ->sort('search_api_datasource', QueryInterface::SORT_DESC)
      ->sort('search_api_language')
      ->sort('search_api_id', QueryInterface::SORT_DESC)
      ->execute();
    $this->assertResults([5, 4, 3, 2, 1], $results, 'Query with magic sorts');
  }

  /**
   * Tests whether facets work correctly.
   */
  protected function checkFacets() {
    // OR facets should ignore condition groups with the corresponding tag.
    $query = $this->buildSearch();
    $conditions = $query->createAndAddConditionGroup('OR', ['facet:category']);
    $conditions->addCondition('category', 'article_category');
    $facets['category'] = [
      'field' => 'category',
      'limit' => 0,
      'min_count' => 1,
      'missing' => TRUE,
      'operator' => 'or',
    ];
    $query->setOption('search_api_facets', $facets);
    $results = $query->execute();
    $this->assertResults([4, 5], $results, 'OR facets query');
    $expected = [
      ['count' => 2, 'filter' => '"article_category"'],
      ['count' => 2, 'filter' => '"item_category"'],
      ['count' => 1, 'filter' => '!'],
    ];
    $category_facets = $results->getExtraData('search_api_facets')['category'];
    usort($category_facets, [$this, 'facetCompare']);
    $this->assertEquals($expected, $category_facets, 'Incorrect OR facets were returned');

    // This should also work with a nested condition group.
    $query = $this->buildSearch();
    $conditions = $query->createConditionGroup('OR', ['facet:category']);
    $conditions->addCondition('category', 'article_category');
    $query->createAndAddConditionGroup()->addConditionGroup($conditions);
    $query->setOption('search_api_facets', $facets);
    $results = $query->execute();
    $this->assertResults([4, 5], $results, 'OR facets query');
    $category_facets = $results->getExtraData('search_api_facets')['category'];
    usort($category_facets, [$this, 'facetCompare']);
    $this->assertEquals($expected, $category_facets, 'Incorrect OR facets were returned');

    // Other condition groups should not be affected.
    $query = $this->buildSearch();
    $conditions = $query->createAndAddConditionGroup('OR', ['facet:category']);
    $conditions->addCondition('category', 'article_category');
    $conditions = $query->createAndAddConditionGroup();
    $conditions->addCondition('category', NULL, '<>');
    $facets['category'] = [
      'field' => 'category',
      'limit' => 0,
      'min_count' => 1,
      'missing' => TRUE,
      'operator' => 'or',
    ];
    $query->setOption('search_api_facets', $facets);
    $results = $query->execute();
    $this->assertResults([4, 5], $results, 'OR facets query');
    $expected = [
      ['count' => 2, 'filter' => '"article_category"'],
      ['count' => 2, 'filter' => '"item_category"'],
    ];
    $category_facets = $results->getExtraData('search_api_facets')['category'];
    usort($category_facets, [$this, 'facetCompare']);
    $this->assertEquals($expected, $category_facets, 'Incorrect OR facets were returned');

    // AND facets won't ignore existing conditions.
    $query = $this->buildSearch();
    $query->createAndAddConditionGroup('OR', ['facet:category'])
      ->addCondition('category', 'article_category');
    $facets['category'] = [
      'field' => 'category',
      'limit' => 0,
      'min_count' => 1,
      'missing' => TRUE,
      'operator' => 'and',
    ];
    $query->setOption('search_api_facets', $facets);
    $results = $query->execute();
    $this->assertResults([4, 5], $results, 'AND facets query');
    $expected = [
      ['count' => 2, 'filter' => '"article_category"'],
    ];
    $category_facets = $results->getExtraData('search_api_facets')['category'];
    usort($category_facets, [$this, 'facetCompare']);
    $this->assertEquals($expected, $category_facets, 'Incorrect AND facets were returned');
  }

  /**
   * Executes regression tests for issues that were already fixed.
   */
  protected function regressionTests() {
    $this->regressionTest2007872();
    $this->regressionTest1863672();
    $this->regressionTest2040543();
    $this->regressionTest2111753();
    $this->regressionTest2127001();
    $this->regressionTest2136409();
    $this->regressionTest1658964();
    $this->regressionTest2469547();
    $this->regressionTest1403916();
    $this->regressionTest2783987();
    $this->regressionTest2809753();
    $this->regressionTest2767609();
    $this->regressionTest2745655();
  }

  /**
   * Regression tests for missing results when using OR filters.
   *
   * @see https://www.drupal.org/node/2007872
   */
  protected function regressionTest2007872() {
    $results = $this->buildSearch('test', [], [], FALSE)
      ->sort('id')
      ->sort('type')
      ->execute();
    $this->assertResults([1, 2, 3, 4], $results, 'Sorting on field with NULLs');

    $query = $this->buildSearch(NULL, [], [], FALSE);
    $conditions = $query->createAndAddConditionGroup('OR');
    $conditions->addCondition('id', 3);
    $conditions->addCondition('type', 'article');
    $query->sort('search_api_id', QueryInterface::SORT_DESC);
    $results = $query->execute();
    $this->assertResults([5, 4, 3], $results, 'OR filter on field with NULLs');
  }

  /**
   * Regression tests for same content multiple times in the search result.
   *
   * Error was caused by multiple terms for filter.
   *
   * @see https://www.drupal.org/node/1863672
   */
  protected function regressionTest1863672() {
    $query = $this->buildSearch();
    $conditions = $query->createAndAddConditionGroup('OR');
    $conditions->addCondition('keywords', 'orange');
    $conditions->addCondition('keywords', 'apple');
    $results = $query->execute();
    $this->assertResults([1, 2, 4, 5], $results, 'OR filter on multi-valued field');

    $query = $this->buildSearch();
    $conditions = $query->createAndAddConditionGroup('OR');
    $conditions->addCondition('keywords', 'orange');
    $conditions->addCondition('keywords', 'strawberry');
    $conditions = $query->createAndAddConditionGroup('OR');
    $conditions->addCondition('keywords', 'apple');
    $conditions->addCondition('keywords', 'grape');
    $results = $query->execute();
    $this->assertResults([2, 4, 5], $results, 'Multiple OR filters on multi-valued field');

    $query = $this->buildSearch();
    $conditions1 = $query->createAndAddConditionGroup('OR');
    $conditions = $query->createConditionGroup();
    $conditions->addCondition('keywords', 'orange');
    $conditions->addCondition('keywords', 'apple');
    $conditions1->addConditionGroup($conditions);
    $conditions = $query->createConditionGroup();
    $conditions->addCondition('keywords', 'strawberry');
    $conditions->addCondition('keywords', 'grape');
    $conditions1->addConditionGroup($conditions);
    $results = $query->execute();
    $this->assertResults([2, 4, 5], $results, 'Complex nested filters on multi-valued field');
  }

  /**
   * Regression tests for (none) facet shown when feature is set to "no".
   *
   * @see https://www.drupal.org/node/2040543
   */
  protected function regressionTest2040543() {
    $query = $this->buildSearch();
    $facets['category'] = [
      'field' => 'category',
      'limit' => 0,
      'min_count' => 1,
      'missing' => TRUE,
    ];
    $query->setOption('search_api_facets', $facets);
    $query->range(0, 0);
    $results = $query->execute();
    $expected = [
      ['count' => 2, 'filter' => '"article_category"'],
      ['count' => 2, 'filter' => '"item_category"'],
      ['count' => 1, 'filter' => '!'],
    ];
    $type_facets = $results->getExtraData('search_api_facets')['category'];
    usort($type_facets, [$this, 'facetCompare']);
    $this->assertEquals($expected, $type_facets, 'Correct facets were returned');

    $query = $this->buildSearch();
    $facets['category']['missing'] = FALSE;
    $query->setOption('search_api_facets', $facets);
    $query->range(0, 0);
    $results = $query->execute();
    $expected = [
      ['count' => 2, 'filter' => '"article_category"'],
      ['count' => 2, 'filter' => '"item_category"'],
    ];
    $type_facets = $results->getExtraData('search_api_facets')['category'];
    usort($type_facets, [$this, 'facetCompare']);
    $this->assertEquals($expected, $type_facets, 'Correct facets were returned');
  }

  /**
   * Regression tests for searching for multiple words using "OR" condition.
   *
   * @see https://www.drupal.org/node/2111753
   */
  protected function regressionTest2111753() {
    $keys = [
      '#conjunction' => 'OR',
      'foo',
      'test',
    ];
    $query = $this->buildSearch($keys, [], ['name']);
    $results = $query->execute();
    $this->assertResults([1, 2, 4], $results, 'OR keywords');

    $query = $this->buildSearch($keys, [], ['name', 'body']);
    $query->range(0, 0);
    $results = $query->execute();
    $this->assertEquals(5, $results->getResultCount(), 'Multi-field OR keywords returned correct number of results.');
    $this->assertEmpty($results->getResultItems(), 'Multi-field OR keywords returned correct result.');
    $this->assertEmpty($results->getIgnoredSearchKeys());
    $this->assertEmpty($results->getWarnings());

    $keys = [
      '#conjunction' => 'OR',
      'foo',
      'test',
      [
        '#conjunction' => 'AND',
        'bar',
        'baz',
      ],
    ];
    $query = $this->buildSearch($keys, [], ['name']);
    $results = $query->execute();
    $this->assertResults([1, 2, 4, 5], $results, 'Nested OR keywords');

    $keys = [
      '#conjunction' => 'OR',
      [
        '#conjunction' => 'AND',
        'foo',
        'test',
      ],
      [
        '#conjunction' => 'AND',
        'bar',
        'baz',
      ],
    ];
    $query = $this->buildSearch($keys, [], ['name', 'body']);
    $results = $query->execute();
    $this->assertResults([1, 2, 4, 5], $results, 'Nested multi-field OR keywords');
  }

  /**
   * Regression tests for non-working operator "contains none of these words".
   *
   * @see https://www.drupal.org/node/2127001
   */
  protected function regressionTest2127001() {
    $keys = [
      '#conjunction' => 'AND',
      '#negation' => TRUE,
      'foo',
      'bar',
    ];
    $results = $this->buildSearch($keys)->execute();
    $this->assertResults([3, 4], $results, 'Negated AND fulltext search');

    $keys = [
      '#conjunction' => 'OR',
      '#negation' => TRUE,
      'foo',
      'baz',
    ];
    $results = $this->buildSearch($keys)->execute();
    $this->assertResults([3], $results, 'Negated OR fulltext search');

    $keys = [
      '#conjunction' => 'AND',
      'test',
      [
        '#conjunction' => 'AND',
        '#negation' => TRUE,
        'foo',
        'bar',
      ],
    ];
    $results = $this->buildSearch($keys)->execute();
    $this->assertResults([3, 4], $results, 'Nested NOT AND fulltext search');
  }

  /**
   * Regression tests for handling of NULL filters.
   *
   * @see https://www.drupal.org/node/2136409
   */
  protected function regressionTest2136409() {
    $query = $this->buildSearch();
    $query->addCondition('category', NULL);
    $results = $query->execute();
    $this->assertResults([3], $results, 'NULL filter');

    $query = $this->buildSearch();
    $query->addCondition('category', NULL, '<>');
    $results = $query->execute();
    $this->assertResults([1, 2, 4, 5], $results, 'NOT NULL filter');
  }

  /**
   * Regression tests for facets with counts of 0.
   *
   * @see https://www.drupal.org/node/1658964
   */
  protected function regressionTest1658964() {
    $query = $this->buildSearch();
    $facets['type'] = [
      'field' => 'type',
      'limit' => 0,
      'min_count' => 0,
      'missing' => TRUE,
    ];
    $query->setOption('search_api_facets', $facets);
    $query->addCondition('type', 'article');
    $query->range(0, 0);
    $results = $query->execute();
    $expected = [
      ['count' => 2, 'filter' => '"article"'],
      ['count' => 0, 'filter' => '!'],
      ['count' => 0, 'filter' => '"item"'],
    ];
    $facets = $results->getExtraData('search_api_facets', [])['type'];
    usort($facets, [$this, 'facetCompare']);
    $this->assertEquals($expected, $facets, 'Correct facets were returned');
  }

  /**
   * Regression tests for facets on fulltext fields.
   *
   * @see https://www.drupal.org/node/2469547
   */
  protected function regressionTest2469547() {
    $query = $this->buildSearch();
    $facets = [];
    $facets['body'] = [
      'field' => 'body',
      'limit' => 0,
      'min_count' => 1,
      'missing' => FALSE,
    ];
    $query->setOption('search_api_facets', $facets);
    $query->addCondition('id', 5, '<>');
    $query->range(0, 0);
    $results = $query->execute();
    $expected = [
      ['count' => 4, 'filter' => '"test"'],
      ['count' => 2, 'filter' => '"Case"'],
      ['count' => 2, 'filter' => '"casE"'],
      ['count' => 1, 'filter' => '"bar"'],
      ['count' => 1, 'filter' => '"case"'],
      ['count' => 1, 'filter' => '"foobar"'],
    ];
    // We can't guarantee the order of returned facets, since "bar" and "foobar"
    // both occur once, so we have to manually sort the returned facets first.
    $facets = $results->getExtraData('search_api_facets', [])['body'];
    usort($facets, [$this, 'facetCompare']);
    $this->assertEquals($expected, $facets, 'Correct facets were returned for a fulltext field.');
  }

  /**
   * Regression tests for multi word search results sets and wrong facet counts.
   *
   * @see https://www.drupal.org/node/1403916
   */
  protected function regressionTest1403916() {
    $query = $this->buildSearch('test foo');
    $facets = [];
    $facets['type'] = [
      'field' => 'type',
      'limit' => 0,
      'min_count' => 1,
      'missing' => TRUE,
    ];
    $query->setOption('search_api_facets', $facets);
    $query->range(0, 0);
    $results = $query->execute();
    $expected = [
      ['count' => 2, 'filter' => '"item"'],
      ['count' => 1, 'filter' => '"article"'],
    ];
    $facets = $results->getExtraData('search_api_facets', [])['type'];
    usort($facets, [$this, 'facetCompare']);
    $this->assertEquals($expected, $facets, 'Correct facets were returned');
  }

  /**
   * Regression test for facet with "min_count" greater than 1.
   *
   * @see https://www.drupal.org/node/2783987
   */
  protected function regressionTest2783987() {
    $query = $this->buildSearch('test foo');
    $facets = [];
    $facets['type'] = [
      'field' => 'type',
      'limit' => 0,
      'min_count' => 2,
      'missing' => TRUE,
    ];
    $query->setOption('search_api_facets', $facets);
    $query->range(0, 0);
    $results = $query->execute();
    $expected = [
      ['count' => 2, 'filter' => '"item"'],
    ];
    $facets = $results->getExtraData('search_api_facets', [])['type'];
    $this->assertEquals($expected, $facets, 'Correct facets were returned');
  }

  /**
   * Regression test for multiple facets.
   *
   * @see https://www.drupal.org/node/2809753
   */
  protected function regressionTest2809753() {
    $query = $this->buildSearch();
    $condition_group = $query->createAndAddConditionGroup('OR', ['facet:type']);
    $condition_group->addCondition('type', 'article');
    $facets['type'] = [
      'field' => 'type',
      'limit' => 0,
      'min_count' => 1,
      'missing' => FALSE,
      'operator' => 'or',
    ];
    $facets['category'] = [
      'field' => 'category',
      'limit' => 0,
      'min_count' => 1,
      'missing' => FALSE,
      'operator' => 'or',
    ];
    $query->setOption('search_api_facets', $facets);
    $results = $query->execute();

    $this->assertResults([4, 5], $results, 'Multi-facets query');
    $expected = [
      ['count' => 3, 'filter' => '"item"'],
      ['count' => 2, 'filter' => '"article"'],
    ];
    $type_facets = $results->getExtraData('search_api_facets')['type'];
    usort($type_facets, [$this, 'facetCompare']);
    $this->assertEquals($expected, $type_facets, 'Correct facets were returned for first facet');
    $expected = [
      ['count' => 2, 'filter' => '"article_category"'],
    ];
    $category_facets = $results->getExtraData('search_api_facets')['category'];
    $this->assertEquals($expected, $category_facets, 'Correct facets were returned for second facet');
  }

  /**
   * Regression test for conditions with empty strings as values.
   *
   * @see https://www.drupal.org/node/2767609
   */
  protected function regressionTest2767609() {
    $results = $this->buildSearch(NULL, ['type,'])->execute();
    $this->assertResults([], $results, 'Search for empty-string type');

    $results = $this->buildSearch(NULL, ['category,'])->execute();
    $this->assertResults([], $results, 'Search for empty-string category');

    $results = $this->buildSearch()
      ->addCondition('category', '', '<>')
      ->execute();
    $this->assertResults([1, 2, 3, 4, 5], $results, 'Search for items with category not an empty string');

    // It's not clear what the results for "category < ''" should be, but in
    // combination with the BETWEEN this should never return results.
    $results = $this->buildSearch()
      ->addCondition('category', '', '<')
      ->addCondition('category', ['', 'foo'], 'BETWEEN')
      ->addCondition('category', ['', 'a', 'b'], 'NOT IN')
      ->execute();
    $this->assertResults([], $results, 'Search with various empty-string filters');
  }

  /**
   * Tests (NOT) NULL conditions on fulltext fields.
   *
   * @see https://www.drupal.org/node/2745655
   */
  protected function regressionTest2745655() {
    $name = $this->entities[3]->name[0]->value;
    $this->entities[3]->name[0]->value = NULL;
    $this->entities[3]->save();
    $this->indexItems($this->indexId);

    $results = $this->buildSearch()
      ->addCondition('name', NULL)
      ->execute();
    $this->assertResults([3], $results, 'Search for items without name');

    $results = $this->buildSearch()
      ->addCondition('name', NULL, '<>')
      ->execute();
    $this->assertResults([1, 2, 4, 5], $results, 'Search for items with name');

    $this->entities[3]->set('name', [$name]);
    $this->entities[3]->save();
    $this->indexItems($this->indexId);
  }

  /**
   * Compares two facet filters to determine their order.
   *
   * Used as a callback for usort() in regressionTests().
   *
   * Will first compare the counts, ranking facets with higher count first, and
   * then by filter value.
   *
   * @param array $a
   *   The first facet filter.
   * @param array $b
   *   The second facet filter.
   *
   * @return int
   *   -1 or 1 if the first filter should, respectively, come before or after
   *   the second; 0 if both facet filters are equal.
   */
  protected function facetCompare(array $a, array $b) {
    if ($a['count'] != $b['count']) {
      return $b['count'] - $a['count'];
    }
    return strcmp($a['filter'], $b['filter']);
  }

  /**
   * Clears the test index.
   */
  protected function clearIndex() {
    $this->getIndex()->clear();
  }

  /**
   * Executes regression tests which are unpractical to run in between.
   */
  protected function regressionTests2() {
    // Create a "prices" field on the test entity type.
    FieldStorageConfig::create([
      'field_name' => 'prices',
      'entity_type' => 'entity_test_mulrev_changed',
      'type' => 'decimal',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ])->save();
    FieldConfig::create([
      'field_name' => 'prices',
      'entity_type' => 'entity_test_mulrev_changed',
      'bundle' => 'item',
      'label' => 'Prices',
    ])->save();

    $this->regressionTest1916474();
    $this->regressionTest2284199();
    $this->regressionTest2471509();
    $this->regressionTest2616804();
  }

  /**
   * Regression tests for correctly indexing  multiple float/decimal fields.
   *
   * @see https://www.drupal.org/node/1916474
   */
  protected function regressionTest1916474() {
    $index = $this->getIndex();
    $this->addField($index, 'prices', 'decimal');
    $success = $index->save();
    $this->assertNotEmpty($success, 'The index field settings were successfully changed.');

    // Reset the static cache so the new values will be available.
    $this->resetEntityCache('server');
    $this->resetEntityCache();

    $this->addTestEntity(6, [
      'prices' => ['3.5', '3.25', '3.75', '3.5'],
      'type' => 'item',
    ]);

    $this->indexItems($this->indexId);

    $query = $this->buildSearch(NULL, ['prices,3.25']);
    $results = $query->execute();
    $this->assertResults([6], $results, 'Filter on decimal field');

    $query = $this->buildSearch(NULL, ['prices,3.5']);
    $results = $query->execute();
    $this->assertResults([6], $results, 'Filter on decimal field');

    // Use the "prices" field, since we've added it now, to also check for
    // proper handling of (NOT) BETWEEN for multi-valued fields.
    $query = $this->buildSearch()
      ->addCondition('prices', [3.6, 3.8], 'BETWEEN');
    $results = $query->execute();
    $this->assertResults([6], $results, 'BETWEEN filter on multi-valued field');

    $query = $this->buildSearch()
      ->addCondition('prices', [3.6, 3.8], 'NOT BETWEEN');
    $results = $query->execute();
    $this->assertResults([1, 2, 3, 4, 5], $results, 'NOT BETWEEN filter on multi-valued field');
  }

  /**
   * Regression tests for problems with taxonomy term parent.
   *
   * @see https://www.drupal.org/node/2284199
   */
  protected function regressionTest2284199() {
    $this->addTestEntity(7, ['type' => 'item']);

    $count = $this->indexItems($this->indexId);
    $this->assertEquals(1, $count, 'Indexing an item with an empty value for a non string field worked.');
  }

  /**
   * Regression tests for strings longer than 50 chars.
   *
   * @see https://www.drupal.org/node/2471509
   * @see https://www.drupal.org/node/2616268
   */
  protected function regressionTest2471509() {
    $this->addTestEntity(8, [
      'name' => 'Article with long body',
      'type' => 'article',
      // cspell:disable-next-line
      'body' => 'astringlongerthanfiftycharactersthatcantbestoredbythedbbackend',
    ]);
    $count = $this->indexItems($this->indexId);
    $this->assertEquals(1, $count, 'Indexing an item with a word longer than 50 characters worked.');

    $index = $this->getIndex();
    $index->getField('body')->setType('string');
    $index->save();
    $count = $this->indexItems($this->indexId);
    $this->assertEquals(count($this->entities), $count, 'Switching type from text to string worked.');

    // For a string field, 50 characters shouldn't be a problem.
    // cspell:disable-next-line
    $query = $this->buildSearch(NULL, ['body,astringlongerthanfiftycharactersthatcantbestoredbythedbbackend']);
    $results = $query->execute();
    $this->assertResults([8], $results, 'Filter on new string field');

    $index->getField('body')->setType('text');
    $index->save();
    $count = $this->indexItems($this->indexId);
    $this->assertEquals(count($this->entities), $count, 'All items needed to be re-indexed after switching type from string to text.');
  }

  /**
   * Regression tests for multibyte characters exceeding 50 byte.
   *
   * @see https://www.drupal.org/node/2616804
   */
  protected function regressionTest2616804() {
    // The word has 28 Unicode characters but 56 bytes. Verify that it is still
    // indexed correctly.
    // cspell:disable-next-line
    $mb_word = 'äöüßáŧæøðđŋħĸµäöüßáŧæøðđŋħĸµ';
    // We put the word 8 times into the body so we can also verify that the 255
    // character limit for strings counts characters, not bytes.
    $mb_body = implode(' ', array_fill(0, 8, $mb_word));
    $this->addTestEntity(9, [
      'name' => 'Test item 9',
      'type' => 'item',
      'body' => $mb_body,
    ]);
    $count = $this->indexItems($this->indexId);
    $this->assertEquals(1, $count, 'Indexing an item with a word with 28 multi-byte characters worked.');

    $query = $this->buildSearch($mb_word);
    $results = $query->execute();
    $this->assertResults([9], $results, 'Search for word with 28 multi-byte characters');

    $query = $this->buildSearch($mb_word . 'ä');
    $results = $query->execute();
    $this->assertResults([], $results, 'Search for unknown word with 29 multi-byte characters');

    // Test the same body when indexed as a string (255 characters limit should
    // not be reached).
    $index = $this->getIndex();
    $index->getField('body')->setType('string');
    $index->save();
    $entity_count = count($this->entities);
    $count = $this->indexItems($this->indexId);
    $this->assertEquals($entity_count, $count, 'Switching type from text to string worked.');

    $query = $this->buildSearch(NULL, ["body,$mb_body"]);
    $results = $query->execute();
    $this->assertResults([9], $results, 'Search for body with 231 multi-byte characters');

    $query = $this->buildSearch(NULL, ["body,{$mb_body}ä"]);
    $results = $query->execute();
    $this->assertResults([], $results, 'Search for unknown body with 232 multi-byte characters');

    $index->getField('body')->setType('text');
    $index->save();
  }

  /**
   * Checks the correct handling of an index without fields.
   *
   * @return \Drupal\search_api\IndexInterface
   *   The created test index.
   */
  protected function checkIndexWithoutFields() {
    $index = Index::create([
      'id' => 'test_index_2',
      'name' => 'Test index 2',
      'status' => TRUE,
      'server' => $this->serverId,
      'datasource_settings' => [
        'entity:entity_test_mulrev_changed' => [],
      ],
      'tracker_settings' => [
        'default' => [],
      ],
    ]);
    $index->save();

    $indexed_count = $this->indexItems($index->id());
    $this->assertEquals(count($this->entities), $indexed_count);

    $search_count = $index->query()->execute()->getResultCount();
    $this->assertEquals(count($this->entities), $search_count);

    return $index;
  }

  /**
   * Asserts that the given result set complies with expectations.
   *
   * @param int[] $result_ids
   *   The expected result item IDs, as raw entity IDs.
   * @param \Drupal\search_api\Query\ResultSetInterface $results
   *   The returned result set.
   * @param string $search_label
   *   (optional) A label for the search to include in assertion messages.
   * @param string[] $ignored
   *   (optional) The ignored keywords that should be present, if any.
   * @param string[] $warnings
   *   (optional) The ignored warnings that should be present, if any.
   */
  protected function assertResults(array $result_ids, ResultSetInterface $results, $search_label = 'Search', array $ignored = [], array $warnings = []) {
    $this->assertEquals(count($result_ids), $results->getResultCount(), "$search_label returned correct number of results.");
    if ($result_ids) {
      $this->assertEquals($this->getItemIds($result_ids), array_keys($results->getResultItems()), "$search_label returned correct results.");
    }
    $this->assertEquals($ignored, $results->getIgnoredSearchKeys());
    $this->assertEquals($warnings, $results->getWarnings());
  }

  /**
   * Retrieves the search server used by this test.
   *
   * @return \Drupal\search_api\ServerInterface
   *   The search server.
   */
  protected function getServer() {
    return Server::load($this->serverId);
  }

  /**
   * Retrieves the search index used by this test.
   *
   * @return \Drupal\search_api\IndexInterface
   *   The search index.
   */
  protected function getIndex() {
    return Index::load($this->indexId);
  }

  /**
   * Adds a field to a search index.
   *
   * The index will not be saved automatically.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The search index.
   * @param string $property_name
   *   The property's name.
   * @param string $type
   *   (optional) The field type.
   */
  protected function addField(IndexInterface $index, $property_name, $type = 'text') {
    $field_info = [
      'label' => $property_name,
      'type' => $type,
      'datasource_id' => 'entity:entity_test_mulrev_changed',
      'property_path' => $property_name,
    ];
    $field = \Drupal::getContainer()
      ->get('search_api.fields_helper')
      ->createField($index, $property_name, $field_info);
    $index->addField($field);
    $index->save();
  }

  /**
   * Resets the entity cache for the specified entity.
   *
   * @param string $type
   *   (optional) The type of entity whose cache should be reset. Either "index"
   *   or "server".
   */
  protected function resetEntityCache($type = 'index') {
    $entity_type_id = 'search_api_' . $type;
    \Drupal::entityTypeManager()
      ->getStorage($entity_type_id)
      ->resetCache([$this->{$type . 'Id'}]);
  }

}
