<?php

namespace Drupal\ea_events;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Routing\LinkGeneratorTrait;
use Drupal\Core\Url;
use Drupal\ea_groupings\Entity\Grouping;

/**
 * Defines a class to build a listing of Event entities.
 *
 * @ingroup ea_events
 */
class EventListBuilder extends EntityListBuilder {

  use LinkGeneratorTrait;

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['grouping'] = $this->t('Group');
    $header['date'] = $this->t('Date');
    $header['start_time'] = $this->t('Start time');
    $header['end_time'] = $this->t('End time');
    $header['status'] = $this->t('Status');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['grouping'] = $this->l(
      $entity->get('grouping')->entity->getTitle(),
      new Url('entity.grouping.canonical', [
        'grouping' => $entity->get('grouping')->entity->id(),
      ])
    );
    $row['date'] = \DateTime::createFromFormat('Y-m-d\TH:i:s', $entity->get('start_date')->value)->format('d/m Y');
    $row['start_time'] = \DateTime::createFromFormat('Y-m-d\TH:i:s', $entity->get('start_date')->value)->format('H:i');
    $row['end_time'] = \DateTime::createFromFormat('Y-m-d\TH:i:s', $entity->get('end_date')->value)->format('H:i');
    $row['status'] = $entity->isPublished() ? $this->t('Published') : $this->t('Unpublished');
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityIds() {
    $query = $this->getStorage()->getQuery()
      ->sort($this->entityType->getKey('id'));
    // Filter entities for non-admin users.
    if (\Drupal::currentUser()->id() !== '1') {
      $grouping_ids = Grouping::getAllGroupingsByUser(\Drupal::currentUser(), FALSE);
      $query->condition('grouping', $grouping_ids, 'IN');
    }
    // Only add the pager if a limit is specified.
    if ($this->limit) {
      $query->pager($this->limit);
    }
    $result = $query->execute();
    return $result;
  }

}
