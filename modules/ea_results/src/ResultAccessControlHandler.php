<?php

namespace Drupal\ea_results;

use Drupal\ea_results\Entity\ResultType;
use Drupal\ea_permissions\Permission;
use Drupal\ea_groupings\Entity\Grouping;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultAllowed;

/**
 * Access controller for the Result entity.
 *
 * @see \Drupal\ea_results\Entity\Result.
 */
class ResultAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /* @var \Drupal\ea_results\ResultInterface $entity */
    switch ($operation) {
      case 'view':
        if (!$entity->isPublished()) {
          return Permission::allowedIfIsManager($account, Grouping::load($entity->type->entity->get('organization')));
        }
        else {
          $groupings = $entity->type->entity->get('groupings');
          if (!empty($groupings)) {
            foreach ($groupings as $grouping) {
              if (Permission::allowedIfIsOrganizer($account, Grouping::load($grouping))->isAllowed()) {
                return new AccessResultAllowed();
              }
            }
          }
          return Permission::allowedIfIsManager($account, Grouping::load($entity->type->entity->get('organization')));
        }
      case 'update':
        return Permission::allowedIfIsManager($account, Grouping::load($entity->type->entity->get('organization')));

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete result entities');

    }
    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    if (!empty($entity_bundle)) {
      $result_type = ResultType::load($entity_bundle);
      $groupings = $result_type->get('groupings');
      if (!empty($groupings)) {
        foreach ($groupings as $grouping) {
          if (Permission::allowedIfIsOrganizer($account, Grouping::load($grouping))->isAllowed()) {
            return new AccessResultAllowed();
          }
        }
      }
      return Permission::allowedIfIsManager($account, Grouping::load($result_type->get('organization')));
    }
    return AccessResult::allowedIfHasPermission($account, 'add result entities');
  }

}