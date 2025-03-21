<?php

namespace Drupal\scheduler_rules_integration\Plugin\Condition;

use Drupal\Core\Entity\EntityInterface;
use Drupal\rules\Core\RulesConditionBase;

/**
 * Provides 'Publishing is enabled for the type of this entity' condition.
 *
 * @Condition(
 *   id = "scheduler_publishing_is_enabled",
 *   deriver = "Drupal\scheduler_rules_integration\Plugin\Condition\ConditionDeriver"
 * )
 */
class PublishingIsEnabled extends RulesConditionBase {

  /**
   * Determines whether scheduled publishing is enabled for this entity type.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to be checked.
   *
   * @return bool
   *   TRUE if scheduled publishing is enabled for the bundle of this entity
   *   type.
   */
  public function doEvaluate(EntityInterface $entity) {
    $config = \Drupal::config('scheduler.settings');
    $bundle_field = $entity->getEntityType()->get('entity_keys')['bundle'];
    return ($entity->$bundle_field->entity->getThirdPartySetting('scheduler', 'publish_enable', $config->get('default_publish_enable')));
  }

}
