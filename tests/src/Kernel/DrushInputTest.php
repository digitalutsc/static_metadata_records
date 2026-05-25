<?php

namespace Drupal\Tests\static_metadata_records\Kernel;

use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\KernelTests\KernelTestBase;
use Drupal\static_metadata_records\Drush\Commands\MetadataRecordsCommands;

/**
 * Tests Drush command input validation.
 *
 * @group static_metadata_records
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
class DrushInputTest extends KernelTestBase {

  /**
   * Add node and field here.
   *
   * @var string[]
   */
  protected static $modules = ['static_metadata_records', 'system', 'user', 'node', 'field', 'text', 'filter'];

  /**
   * The initial set up.
   */
  protected function setUp(): void {
    parent::setUp();

    // Installation.
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');

    NodeType::create([
      'type' => 'islandora_object',
      'name' => 'Repository Item',
    ])->save();

    $this->installConfig(['field', 'node', 'text', 'filter', 'static_metadata_records']);

    // Explicitly create the queue to ensure the table is initialized.
    $this->container->get('queue')->get('static_metadata_records_queue')->createQueue();
  }

  /**
   * Uid and file invalid.
   */
  public function testUidAndFileInvalid() {
    $commands = new MetadataRecordsCommands();
    $options = ['uid' => '', 'file' => 'fake_file.csv'];
    // phpcs:ignore -- Unused variable $result.
    $result = $commands->metadataRecords($options);

    $queue = \Drupal::getContainer()->get('queue')->get('static_metadata_records_queue');
    $this->assertEquals(0, $queue->numberOfItems());
  }

  /**
   * Uid valid, file invalid.
   */
  public function testUidValid() {
    $commands = new MetadataRecordsCommands();
    $options = ['uid' => '1', 'file' => 'fake_path.csv'];
    $commands->metadataRecords($options);

    $queue = \Drupal::getContainer()->get('queue')->get('static_metadata_records_queue');
    $this->assertEquals(0, $queue->numberOfItems());
  }

  /**
   * Uid invalid, file valid.
   */
  public function testFileValid() {
    // Creating a node so drush command can load it.
    $node = Node::create([
      'nid' => 101,
      'type' => 'islandora_object',
      'title' => 'Test Node',
    ]);
    $node->save();

    // Creating a temp file.
    $tempFilePath = tempnam(sys_get_temp_dir(), 'test_csv') . '.csv';
    file_put_contents($tempFilePath, "101");

    // Run the command.
    $commands = new MetadataRecordsCommands();
    $options = ['uid' => '', 'file' => $tempFilePath];
    $commands->metadataRecords($options);

    // Final check.
    $queue = \Drupal::getContainer()->get('queue')->get('static_metadata_records_queue');
    $this->assertEquals(0, $queue->numberOfItems(), 'Node 101 should not be in the queue.');

    // Cleanup.
    unlink($tempFilePath);
  }

  /**
   * Uid valid, file valid.
   */
  public function testUidAndFileValid() {
    // Creating a node so drush command can load it.
    $node = Node::create([
      'nid' => 101,
      'type' => 'islandora_object',
      'title' => 'Test Node',
    ]);
    $node->save();

    // Creating a temp file.
    $tempFilePath = tempnam(sys_get_temp_dir(), 'test_csv') . '.csv';
    file_put_contents($tempFilePath, "101");

    // Run the command.
    $commands = new MetadataRecordsCommands();
    $options = ['uid' => 1, 'file' => $tempFilePath];
    $commands->metadataRecords($options);

    // Final check.
    $queue = \Drupal::getContainer()->get('queue')->get('static_metadata_records_queue');

    $this->assertEquals(1, $queue->numberOfItems(), 'Node 101 should be in the queue.');

    // Cleanup.
    unlink($tempFilePath);
  }

}
