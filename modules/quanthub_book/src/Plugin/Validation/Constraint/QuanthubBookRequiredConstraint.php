<?php

namespace Drupal\quanthub_book\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Validation constraint for changing the book outline in pending revisions.
 *
 * @Constraint(
 *   id = "QuanthubBookRequired",
 *   label = @Translation("Quanthub Book required.", context = "Validation"),
 * )
 */
class QuanthubBookRequiredConstraint extends Constraint {

  /**
   * Message for book not exist.
   *
   * @var string
   */
  public $messageBookNotExist = 'Book page require a book entity. Please create new book or select existed.';

  /**
   * Message for book invalid.
   *
   * @var string
   */
  public $messageBookInvalid = 'Book page require a book entity. Selected Book is invalid.';

}
