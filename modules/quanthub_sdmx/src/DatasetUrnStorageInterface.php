<?php

namespace Drupal\quanthub_sdmx;

/**
 * Interface for quanthub.dataset_urn service.
 */
interface DatasetUrnStorageInterface {

  /**
   * Dataset URN field name.
   */
  const URN_FIELD = 'field_quanthub_urn';

  /**
   * Provides datasets list existing in portal.
   *
   * The list is requested from DB.
   *
   * @return string[]
   *   Array of URNs.
   */
  public function getLocalDatasets(): array;

  /**
   * Provides user-allowed dataset URNs.
   *
   * The list is requested from SDMX API (and cached).
   *
   * @return null|string[]
   *   Array of user-allowed URNs or empty array if not allowed.
   *   NULL if access shouldn't be restricted (user has bypass permissions,
   *   connection not configured, etc.)
   */
  public function getAllowedDatasets(): ?array;

  /**
   * Provides user-prohibited dataset URNs.
   *
   * The list is difference between local datasets and allowed list.
   *
   * @return null|string[]
   *   Array of user-prohibited URNs or empty array if allowed all.
   *   NULL if access shouldn't be restricted (user has bypass permissions,
   *   connection not configured, etc.)
   */
  public function getProhibitedDatasets(): ?array;

}
