<?php

namespace Drupal\quanthub_core\Plugin\views\field;

use Drupal\quanthub_core\QuanthubWorkflowInterface;
use Drupal\views_bulk_operations\Plugin\views\field\ViewsBulkOperationsBulkForm;

/**
 * Defines the Quanthub Views Bulk Operations field plugin.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("quanthub_views_bulk_operations_bulk_form")
 */
class QuanthubViewsBulkOperationsBulkForm extends ViewsBulkOperationsBulkForm {

  /**
   * Returns the available operations for this form.
   *
   * @return array
   *   An associative array of operations, suitable for a select element.
   */
  protected function getBulkOptions(): array {
    parent::getBulkOptions();

    if (!$this->currentUser->hasPermission(
      QuanthubWorkflowInterface::PUBLISH_PERMISSION
    )) {
      unset($this->bulkOptions[6]);
    }
    if (!$this->currentUser->hasPermission(
      QuanthubWorkflowInterface::UNPUBLISH_PERMISSION
    )) {
      unset($this->bulkOptions[7]);
    }
    if (!$this->currentUser->hasPermission(
      QuanthubWorkflowInterface::DRAFT_PERMISSION
    )) {
      unset($this->bulkOptions[8]);
    }

    if (
      !$this->currentUser->hasPermission('delete content translations') &&
      !$this->currentUser->hasPermission('delete any dataset content')
    ) {
      unset($this->bulkOptions[9]);
    }
    return $this->bulkOptions;
  }

}
