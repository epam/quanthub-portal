<?php

namespace Drupal\quanthub_core\Plugin\Action;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\Entity\Node;
use Drupal\quanthub_core\QuanthubWorkflowInterface;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;

/**
 * Content moderation publish node.
 *
 * @Action(
 *   id = "quanthub_workflow_publish_action",
 *   label = @Translation("Publish node (quanthub moderation_state)"),
 *   type = "node",
 *   confirm = TRUE
 * )
 */
class QuanthubPublish extends ViewsBulkOperationsActionBase {

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    if ($object instanceof Node) {
      $can_update = $object->access('update', $account, TRUE);
      $can_edit = $object->access('edit', $account, TRUE);

      $access = $can_edit->andIf($can_update);

      return $return_as_object ? $access : $access->isAllowed();
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(ContentEntityInterface $entity = NULL) {
    if (!$state = $entity->get('moderation_state')->getString()) {
      return $this->t(":title  - can't change state",
        [
          ':title' => $entity->getTitle(),
        ]
      );
    }

    // Nothing to do here for published.
    // Moved draft to published and for archive firstly move to draft
    // than to published.
    switch ($state) {
      case QuanthubWorkflowInterface::DRAFT_STATE:
        $entity->set('moderation_state', QuanthubWorkflowInterface::PUBLISHED_STATE);
        $entity->save();
        break;

      case QuanthubWorkflowInterface::ARCHIVED_STATE:
        $entity->set('moderation_state', QuanthubWorkflowInterface::DRAFT_STATE);
        $entity->save();
        $entity->set('moderation_state', QuanthubWorkflowInterface::PUBLISHED_STATE);
        $entity->save();
        break;
    }

    return $this->t(':title state changed to :state',
      [
        ':title' => $entity->getTitle(),
        ':state' => $entity->get('moderation_state')->getString(),
      ]
    );
  }

}
