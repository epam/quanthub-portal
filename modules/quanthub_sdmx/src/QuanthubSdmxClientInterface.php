<?php

namespace Drupal\quanthub_sdmx;

/**
 * Interface for quanthub.sdmx_client service.
 */
interface QuanthubSdmxClientInterface {

  /**
   * Checks if the client is configured.
   */
  public function isConfigured(): bool;

  /**
   * The get dataflow list request to SDMX api.
   *
   * @param array $headers
   *   Extra headers for the SDMX request.
   *
   * @return string[]|null
   *   The dataset urn's list, or NULL on error.
   */
  public function getDatasetList($headers = []): ?array;

  /**
   * The get dataflow request to SDMX api.
   *
   * @param string $urn
   *   The dataset urn.
   * @param bool $full
   *   The option of getting full detail.
   * @param bool $references
   *   The option of getting references.
   * @param array $headers
   *   Extra headers for the SDMX request.
   *
   * @return array|null
   *   The response body array decoded json, or NULL on error.
   */
  public function getDatasetStructure(string $urn, $full = FALSE, $references = FALSE, $headers = []): ?array;

}
