<?php

namespace Drupal\Tests\static_metadata_records\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests whether both the module hooks correctly add nodes to the queue.
 *
 * @group static_metadata_records
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
class HooksTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'field',
    'text',
    'filter',
    'user',
    'static_metadata_records',
  ];

  /**
   * A user with permission to administer site configuration.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * Fixture authenticated user with no permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $authUser;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'olivero';

  /**
   * {@inheritdoc}
   */
  // phpcs:ignore -- Do not disable strict config schema checking in tests.
  protected $strictConfigSchema = FALSE;

  /**
   * A user with permission to administer site configuration.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $client;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Sample content type.
    $this->drupalCreateContentType([
      'type' => 'islandora_object',
      'name' => 'Islandora Object',
    ]);

    // Enable the hooks in the module config to simulate the tests.
    $this->config('static_metadata_records.settings')
      ->set('enable_hooks', TRUE)
    // To make sure nothing is exluded.
      ->set('excluded_content_types', [])
      ->save();
  }

  /**
   * Tests hook_node_insert.
   */
  public function testNodeInsertHook() {
    // Create node and queue.
    $node = $this->drupalCreateNode([
      'type' => 'islandora_object',
      'title' => 'Hook Test Node',
    ]);

    $queue = \Drupal::queue('static_metadata_records_queue');

    // Check whether the hook successfully added the item to our queue.
    $this->assertEquals(1, $queue->numberOfItems(), 'The node was automatically added to the queue via hook_node_insert.');

    // Checking the data.
    $item = $queue->claimItem();

    // Note for debugging: in queue items are stored as 'stdClass'.
    $queued_nid = NULL;
    if (isset($item->data->nid)) {
      $queued_nid = $item->data->nid;
    }
    $this->assertEquals($node->id(), $queued_nid, 'The queued item has the correct Node ID.');
  }

  /**
   * Tests hook_node_update.
   */
  public function testNodeUpdateHook() {
    // Create node.
    $node = $this->drupalCreateNode([
      'type' => 'islandora_object',
      'title' => 'Original Title',
    ]);

    // Clear the queue as insert hook might have added it.
    $queue = \Drupal::queue('static_metadata_records_queue');
    $queue->deleteQueue();

    // Update the node.
    $node->setTitle('Testing Updated Title');
    $node->save();

    $this->assertEquals(1, $queue->numberOfItems(), 'The node was added to the queue via hook_node_update.');
  }

}
