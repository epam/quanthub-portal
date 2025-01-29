<?php

namespace Drupal\quanthub_core\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\media_directories_ui\Form\AddMediaFormBase;

/**
 * A form to add remote Power BI resources.
 */
class PowerBiMediaDirectoriesForm extends AddMediaFormBase {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $media_type = $form_state->get('media_type');
    $media_type_id = $media_type ? $media_type->id() : $form_state->get('selected_type');
    $media = $this->entityTypeManager->getStorage('media')->create([
      'bundle' => $media_type_id,
      'directory' => $this->getDirectory($form_state),
    ]);
    $form_state->set('media', [$media]);

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function buildInputElement(array $form, FormStateInterface $form_state) {
    // No need for a file upload element in the form.
  }

}
