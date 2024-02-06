<?php

namespace Drupal\quanthub_book\Plugin\Validation\Constraint;

use Drupal\book\BookManagerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Constraint validator for checking that book is selected or created.
 */
class QuanthubBookRequiredConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * The book manager.
   *
   * @var \Drupal\book\BookManagerInterface
   */
  protected $bookManager;

  /**
   * Creates a new BookOutlineConstraintValidator instance.
   *
   * @param \Drupal\book\BookManagerInterface $book_manager
   *   The book manager.
   */
  public function __construct(BookManagerInterface $book_manager) {
    $this->bookManager = $book_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('book.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate($entity, Constraint $constraint) {
    if ($entity->bundle() == 'book') {
      if ($entity->book['bid'] == 'new') {
        // Nothing to do here. This page will be title of new book.
      }
      else {
        if ($entity->book['bid'] == 0) {
          // Book entity is not set.
          $this->context->buildViolation($constraint->messageBookNotExist)
            ->atPath('book.pid')
            ->setInvalidValue($entity)
            ->addViolation();
        }
        elseif (!in_array($entity->book['bid'], array_keys($this->bookManager->getAllBooks()))) {
          // Book entity is set. But not exist in list of all books entities.
          $this->context->buildViolation($constraint->messageBookInvalid)
            ->atPath('book.pid')
            ->setInvalidValue($entity)
            ->addViolation();
        }
      }
    }
  }

}
