<?php

namespace Drupal\quanthub_core;

/**
 * Interface for workflow logic and permissions.
 */
interface QuanthubWorkflowInterface {
  const PUBLISHED_STATE = 'published';
  const ARCHIVED_STATE = 'archived';
  const DRAFT_STATE = 'draft';

  const PUBLISH_PERMISSION = 'use quanthub_workflow transition publish';
  const DRAFT_PERMISSION = 'use quanthub_workflow transition to_draft';
  const UNPUBLISH_PERMISSION = 'use quanthub_workflow transition unpublish';

}
