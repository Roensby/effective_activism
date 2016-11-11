<?php

namespace Drupal\ea_imports\Parser;

use Drupal\ea_events\Entity\Event;
use Drupal\ea_people\Entity\Person;
use Drupal\ea_data\Entity\Data;
use Drupal\ea_results\Entity\Result;
use Drupal\ea_results\Entity\ResultType;
use Drupal\ea_groupings\Entity\Grouping;
use Drupal\Core\Entity\EntityInterface;

/**
 * Entity parsing functions.
 */
class EntityParser {

  /**
   * Filters standard entity fields.
   *
   * @param string $type
   *   The entity type.
   * @param string $bundle
   *   The entity bundle.
   *
   * @return array
   *   A filtered array of fields.
   */
  private function getFields($type, $bundle = NULL) {
    if (empty($bundle)) {
      $bundle = $type;
    }
    $fields = array_keys(\Drupal::entityManager()->getFieldDefinitions($type, $bundle));
    // Do not include standard fields.
    unset($fields[array_search('id', $fields)]);
    unset($fields[array_search('uuid', $fields)]);
    unset($fields[array_search('user_id', $fields)]);
    unset($fields[array_search('status', $fields)]);
    unset($fields[array_search('langcode', $fields)]);
    unset($fields[array_search('default_langcode', $fields)]);
    unset($fields[array_search('created', $fields)]);
    unset($fields[array_search('changed', $fields)]);
    // Also exclude revision fields.
    unset($fields[array_search('revision_id', $fields)]);
    unset($fields[array_search('revision_created', $fields)]);
    unset($fields[array_search('revision_user', $fields)]);
    unset($fields[array_search('revision_log_message', $fields)]);
    $fields = array_values($fields);
    return $fields;
  }

  /**
   * Validates an entity.
   *
   * @param EntityInterface $entity
   *   Entity to validate.
   * @param array $fieldsToIgnore
   *   Validation errors to ignore.
   *
   * @return bool
   *   TRUE if entity has no violations, FALSE otherwise.
   */
  private function validateEntity(EntityInterface $entity, $fieldsToIgnore = []) {
    $isValid = TRUE;
    if ($entity) {
      $this->errorMessages = [];
      foreach ($entity->validate() as $violation) {
        if (!in_array($violation->getPropertyPath(), $fieldsToIgnore)) {
          $isValid = FALSE;
        }
      }
    }
    return $isValid;
  }

  /**
   * Validates a participant entity.
   *
   * @param array $values
   *   Data to validate as participant entity.
   *
   * @return bool
   *   TRUE if participant is valid, FALSE otherwise.
   */
  public function validateParticipant($values) {
    $fields = $this->getFields('person');
    $data = array_combine($fields, $values);
    return $this->validateEntity(Person::create($data));
  }

  /**
   * Validates a result.
   *
   * @param array $values
   *   Data to validate as result entity.
   * @param string $importName
   *   The import name.
   * @param Grouping $grouping
   *   The grouping.
   *
   * @return bool
   *   TRUE if result is valid, FALSE otherwise.
   */
  public function validateResult($values, $importName, Grouping $grouping) {
    // Get organization from grouping.
    $organizationId = empty($grouping->get('parent')->entity) ? $grouping->id() : $grouping->get('parent')->entity->id();
    $resultType = ResultType::getResultTypeByImportName($importName, $organizationId);
    // Make sure the result type is valid.
    if (empty($resultType)) {
      $this->errorMessages[] = t('Illegal import name');
      return FALSE;
    }
    $fields = $this->getFields('result', $resultType->id());
    $fieldsToIgnore = [];
    foreach ($fields as $key => $field) {
      // Create any data entities identified by field name 'field_*'.
      if (strpos($field, 'field_') === 0) {
        $dataType = substr($field, strlen('field_'));
        $this->validateData([
          $dataType,
          $values[$key],
        ], $dataType);
        // Do not validate this field for the result entity.
        $values[$key] = NULL;
        $fieldsToIgnore[] = $field;
      }
      // Replace import name with result type id.
      elseif ($field === 'type') {
        $values[$key] = $resultType->id();
      }
    }
    $data = array_combine($fields, $values);
    return $this->validateEntity(Result::create($data), $fieldsToIgnore);
  }

  /**
   * Validates a data entity.
   *
   * @param array $values
   *   Data to validate as data entity.
   *
   * @return bool
   *   TRUE if data is valid, FALSE otherwise.
   */
  public function validateData($values, $bundle) {
    $fields = $this->getFields('data', $bundle);
    $data = array_combine($fields, $values);
    return $this->validateEntity(Data::create($data));
  }

  /**
   * Validates an event entity.
   *
   * @param array $values
   *   Values to validate as an event entity.
   *
   * @return bool
   *   TRUE if event is valid, FALSE otherwise.
   */
  public function validateEvent($values) {
    $fields = $this->getFields('event');
    $data = array_combine($fields, $values);
    return $this->validateEntity(Event::create($data));
  }

  /**
   * Imports a participant entity.
   *
   * @param array $values
   *   Values to import as a participant entity.
   *
   * @return int|bool
   *   The participant id or FALSE if import failed.
   */
  public function importParticipant($values) {
    $fields = $this->getFields('person');
    $data = array_combine($fields, $values);
    $entity = Person::create($data);
    if ($entity->save()) {
      return $entity;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Imports a result entity.
   *
   * @param array $values
   *   Values to import as a result entity.
   * @param string $importName
   *   The import name of the result entity.
   * @param Grouping $grouping
   *   The grouping to import to.
   *
   * @return int|bool
   *   The participants id or FALSE if import failed.
   */
  public function importResult($values, $importName, Grouping $grouping) {
    // Get organization from grouping.
    $organizationId = empty($grouping->get('parent')->entity) ? $grouping->id() : $grouping->get('parent')->entity->id();
    $resultType = ResultType::getResultTypeByImportName($importName, $organizationId);
    $fields = $this->getFields('result', $resultType->id());
    foreach ($fields as $key => $field) {
      // Create any data entities identified by field name 'field_*'.
      if (strpos($field, 'field_') === 0) {
        $dataType = substr($field, strlen('field_'));
        $dataEntity = $this->importData($values[$key], $dataType);
        // Overwrite value with corresponding data entity.
        $values[$key] = $dataEntity->id();
      }
      // Replace import name with result type id.
      elseif ($field === 'type') {
        $values[$key] = $resultType->id();
      }
    }
    $data = array_combine($fields, $values);
    $entity = Result::create($data);
    if ($entity->save()) {
      return $entity;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Imports a data entity.
   *
   * @param array $dataValue
   *   The data value.
   * @param string $bundle
   *   The bundle of the result entity.
   *
   * @return int|bool
   *   The participants id or FALSE if import failed.
   */
  public function importData($dataValue, $bundle) {
    $fields = $this->getFields('data', $bundle);
    $data = array_combine($fields, [
      $bundle,
      $dataValue,
    ]);
    $entity = Data::create($data);
    if ($entity->save()) {
      return $entity;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Imports an event entity.
   *
   * @param array $values
   *   Values to import as an event.
   *
   * @return int|bool
   *   The event id or FALSE if import failed.
   */
  public function importEvent($values) {
    $fields = $this->getFields('event');
    $data = array_combine($fields, $values);
    $entity = Event::create($data);
    if ($entity->save()) {
      return $entity;
    }
    else {
      return FALSE;
    }
  }

}
