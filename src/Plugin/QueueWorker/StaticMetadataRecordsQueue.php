<?php

namespace Drupal\static_metadata_records\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use Drupal\static_metadata_records\Plugin\MetadataExtractor\MODSExtractor;
use Drupal\static_metadata_records\Plugin\MetadataExtractor\DCExtractor;

/**
 * Queue worker for the static metadata records queue.
 *
 * @QueueWorker(
 *   id = "static_metadata_records_queue",
 *   title = @Translation("Static Metadata Records Queue Worker"),
 *   cron = {"time" = 60}
 * )
 */
class StaticMetadataRecordsQueue extends QueueWorkerBase {

  /**
   * Processes a single queue item.
   *
   * @param object $data
   *   The data passed to the queue item, contains the 'nid' and 'uid'.
   */
  public function processItem($data) {
    $nid = $data->nid;
    $uid = $data->uid;

    // Node validation.
    $node = Node::load($nid);
    if (!$node) {
      // Add debug msg here.
      return;
    }

    // User validation and then change user accounts using the account switcher.
    $user = User::load($uid);
    if (!$user) {
      \Drupal::logger('static_metadata_records')->error("User not found with uid $uid.");
      return;
    }
    // Validate that the user is not blocked.
    if ($user->isBlocked()) {
      \Drupal::logger('static_metadata_records')->error("User is blocked with uid $uid.");
      return;
    }

    // Switch accounts.
    $account_switcher = \Drupal::service("account_switcher");
    if (!$account_switcher) {
      \Drupal::logger('static_metadata_records')->error("Account Switcher does not exist.");
      return;
    }
    $account_switcher->switchTo($user);

    // Core logic.
    try {
      // Jwt authentication
      // generate JWT token and construct headers to be sent with the request.
      $jwt_service = \Drupal::service("jwt.authentication.jwt");
      if (!$jwt_service) {
        \Drupal::logger('static_metadata_records')->error("Cannot initialize JWT authentication.");
        return;
      }

      // phpcs:ignore -- Line exceeds 80 characters.
      // note: default expiry of a JWT token is 1 hour (see: https://plugins.miniorange.com/drupal-rest-api-authentication#features)
      $token = $jwt_service->generateToken();
      if (empty($token)) {
        \Drupal::logger('static_metadata_records')->error("Failed to generate JWT token.");
        return;
      }
      $headers = [
        "headers" => [
          "Authorization" => "Bearer " . $token,
        ],
      ];

      // Validate config exists, and extract the required field names.
      $config = \Drupal::config('static_metadata_records.settings');
      if (!$config) {
        \Drupal::logger('static_metadata_records')->error("Configuration 'static_metadata_records.settings' not found.");
        return;
      }
      $dc_field_name = $config->get("dc_field_selection");
      $mods_field_name = $config->get("mods_field_selection");

      // Validate at least one field is configured.
      if (empty($dc_field_name) && empty($mods_field_name)) {
        \Drupal::logger('static_metadata_records')->error("No DC or MODS fields configured. Please configure atleast one field in the admin settings.");
        return;
      }

      $dc_extractor_object = new DCExtractor();
      $mods_extractor_object = new MODSExtractor();

      // Send a request (internally inside the objects) and extract data.
      $raw_dc_data = NULL;
      $raw_mods_data = NULL;

      if (!empty($dc_field_name)) {
        $raw_dc_data = $dc_extractor_object->getData($nid, $headers);
        // Echo "raw dc:" . $raw_dc_data;.
      }

      if (!empty($mods_field_name)) {
        $raw_mods_data = $mods_extractor_object->getData($nid, $headers);
        // Echo "raw mods:" . $raw_mods_data;.
      }

      // Null check - only fail if the configured field's data is null.
      if ((!empty($dc_field_name) && is_null($raw_dc_data)) ||
            (!empty($mods_field_name) && is_null($raw_mods_data))) {
        \Drupal::logger('static_metadata_records')->error("Extraction failed for node $nid.");
        return;
      }

      $need_to_save = FALSE;
      // Dc.
      if (!empty($dc_field_name) && !is_null($raw_dc_data)) {
        if ($node->hasField($dc_field_name)) {
          $node->set($dc_field_name, $raw_dc_data);
          $need_to_save = TRUE;
        }
        else {
          \Drupal::logger('static_metadata_records')->error("'$dc_field_name' Does Not Exist");
        }
      }

      // Mods.
      if (!empty($mods_field_name) && !is_null($raw_mods_data)) {
        if ($node->hasField($mods_field_name)) {
          $node->set($mods_field_name, $raw_mods_data);
          $need_to_save = TRUE;
        }
        else {
          \Drupal::logger('static_metadata_records')->error("'$mods_field_name' Does Not Exist");
        }
      }

      // Save only if you need to, otherwise we dont need to
      // because it is an expensive operation.
      if ($need_to_save) {
        // save_flag is used in the .module file to prevent infinite loops.
        $node->save_flag = TRUE;
        $node->save();
      }
    }
    catch (Exception $e) {
      \Drupal::logger('static_metadata_records')->error("Queue processing failed for node $nid: " . $e->getMessage());
    }
    finally {
      \Drupal::logger('static_metadata_records')->notice("Finished processing node $nid.");
      $account_switcher->switchBack();
    }
  }

}
