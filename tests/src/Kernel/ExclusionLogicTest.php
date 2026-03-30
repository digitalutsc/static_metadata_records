<?php

namespace Drupal\Tests\static_metadata_records\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\node\Entity\Node;

/**
 * Test for checking the node exclusion logic based on the config form filters.
 *
 * @group static_metadata_records
 */
class ExclusionLogicTest extends KernelTestBase {
  /**
   * The list of modules to include.
   *
   * @var string[]
   */
  protected static $modules = ['static_metadata_records', 'node', 'user', 'system'];

  /**
   * The inital setUp.
   */
  protected function setUp(): void {
    // Initial setup with installations.
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installConfig(['static_metadata_records']);

    // Create a dummy content type.
    NodeType::create(['type' => 'islandora_object', 'name' => 'Islandora Object'])->save();
  }

  /**
   * Tests the exclusion logic by setting dummy values in the config form.
   */
  public function testExclusionByContentType() {
    // Simulate to exclude 'islandora_object' content type.
    $this->config('static_metadata_records.settings')
      ->set('excluded_content_types', ['islandora_object' => 'islandora_object'])
      ->save();

    // Create sample node.
    $node = Node::create([
      'type' => 'islandora_object',
      'title' => 'Test Node',
    ]);
    $node->save();

    // Include the file manually to call the global helper function.
    include_once __DIR__ . '/../../../static_metadata_records.module';

    $is_excluded = static_metadata_records_exclude_node($node->id());
    $this->assertTrue($is_excluded);
  }

}
