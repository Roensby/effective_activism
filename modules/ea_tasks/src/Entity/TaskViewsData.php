<?php

/**
 * @file
 * Contains \Drupal\ea_tasks\Entity\Task.
 */

namespace Drupal\ea_tasks\Entity;

use Drupal\views\EntityViewsData;
use Drupal\views\EntityViewsDataInterface;

/**
 * Provides Views data for Task entities.
 */
class TaskViewsData extends EntityViewsData implements EntityViewsDataInterface {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();
    $data['task']['table']['base'] = array(
      'field' => 'id',
      'title' => $this->t('Task'),
      'help' => $this->t('The Task ID.'),
    );
    return $data;
  }

}