<?php

namespace Drupal\static_metadata_records\Plugin\MetadataExtractor;

use GuzzleHttp\Client;

/**
 * Extracts MODS metadata via OAI-PMH.
 */
class MODSExtractor implements MetadataExtractorInterface {

  /**
   * {@inheritdoc}
   */
  public function getData($nid, array $headers) {
    $client = new Client();
    // $url = "https://islandora.dev/oai/request?identifier=oai%3Aislandora.dev%3Anode-$nid&metadataPrefix=mods&verb=GetRecord";
    // $xmlSchema = "https://www.loc.gov/standards/mods/v3/mods-3-8.xsd";
    // Dynamically construct OAI-PMH URL and identifier based on current site.
    $host = \Drupal::request()->getHttpHost();
    // Remove port from hostname if present.
    if (strpos($host, ':') !== FALSE) {
      $host_parts = explode(':', $host);
      $host = $host_parts[0];
    }
    // $scheme = \Drupal::request()->getScheme();
    $scheme = "https";
    $url = $scheme . '://' . $host . '/oai/request?identifier=oai%3A' . urlencode($host) . '%3Anode-' . $nid . '&metadataPrefix=mods&verb=GetRecord';

    try {
      // Send request.
      $response = $client->request(
            'GET',
            $url,
            $headers,
        );

      // Validate response object.
      if (!$response) {
        \Drupal::logger('static_metadata_records')->error("No response received for node $nid.");
        return NULL;
      }
      if ($response->getStatusCode() !== 200) {
        \Drupal::logger('static_metadata_records')->error("HTTP request failed for node $nid. \nStatus: $response->getStatusCode() - $response->getReasonPhrase().");
        return NULL;
      }

      $body = (string) $response->getBody();

      return $this->parseBody($body);
    }
    catch (Exception $e) {
      \Drupal::logger('static_metadata_records')->error("Exception in MODS Records for node $nid. \nError: $e->getMessage().");
      return NULL;
    }
  }

  /**
   * Parses the body by taking steps such as simple and schema validation.
   */
  public function parseBody($body) {
    // Validate the body and xml.
    if (empty($body)) {
      // phpcs:ignore -- Line exceeds 80 characters.
      // \Drupal::logger('static_metadata_records')->error("Empty response body for Node ID $nid.");
      return NULL;
    }

    if (strpos($body, '<?xml') === FALSE || strpos($body, '<OAI-PMH') === FALSE) {
      // phpcs:ignore -- Line exceeds 80 characters.
      // \Drupal::logger('static_metadata_records')->error("Response body does not appear to be XML for Node ID $nid.");
      return NULL;
    }

    // Disable libxml errors and allow user to get error information as needed.
    libxml_use_internal_errors(TRUE);
    if (simplexml_load_string($body) === FALSE) {
      // phpcs:ignore -- Line exceeds 80 characters.
      // \Drupal::logger('static_metadata_records')->error("Invalid XML for node $nid.");
      return NULL;
    }
    if (!empty(libxml_get_errors())) {
      libxml_clear_errors();
      return NULL;
    }

    // Get the content between the metadata tags.
    $start_tag = "<metadata>";
    $end_tag = "</metadata>";
    // 10 is the length of <metadata>, and we dont want to display it
    $tag_length = 10;
    $start_index = strpos($body, $start_tag);
    $end_index = strpos($body, $end_tag);

    if (!$start_index || !$end_index) {
      // phpcs:ignore -- Line exceeds 80 characters.
      // \Drupal::logger('static_metadata_records')->error("Start or ending index not found XML for node $nid.");
      return NULL;
    }

    $length = $end_index - $start_index;
    $refined = trim(substr($body, $start_index + $tag_length, $length - $tag_length));

    if (empty($refined)) {
      return '';
    }

    // phpcs:disable -- Drupal.Files.LineLength.TooLong
    // Schema validation.
    // $xmlSchema = "https://www.loc.gov/standards/mods/v3/mods-3-8.xsd";
    // $dom = new \DOMDocument();
    // if ($dom->loadXML($refined)) {
    // libxml_use_internal_errors(TRUE);
    // if ($dom->schemaValidate($xmlSchema)) {
    // \Drupal::logger('static_metadata_records')->info("MODS Schema validated.");
    // }
    // else {
    // $errors = libxml_get_errors();
    // if (!empty($errors)) {
    // foreach ($errors as $error) {
    // \Drupal::logger('static_metadata_records')->error("MODS Schema validation error: " . $error->message);
    // }
    // libxml_clear_errors();
    // }
    // else {
    // \Drupal::logger('static_metadata_records')->error("MODS Schema validation failed.");
    // }
    // }
    // }
    // else {
    // \Drupal::logger('static_metadata_records')->error("Failed to load MODS XML for validation.");
    // }.
    // Remove extra white spaces between tags.
    $dom = new \DOMDocument();
    // Ignore extra spaces.
    $dom->preserveWhiteSpace = FALSE;
    $dom->loadXML($refined);
    // Set to true because we want pretty printing for our display.
    $dom->formatOutput = TRUE;
    $refined = $dom->saveXML($dom->documentElement);

    return $refined;
  }

}
