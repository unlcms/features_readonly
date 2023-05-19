<?php

namespace Drupal\features_readonly\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\features_readonly\ReadOnlyFormEvent;

/**
 * Check if the given form should be read-only.
 */
class ReadOnlyFormSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public function onFormAlter(ReadOnlyFormEvent $event) {
    $mark_form_read_only = FALSE;
    $form_object = $event->getFormState()->getFormObject();

    if ($form_object instanceof ConfigEntityListBuilder) {
      $config_entities = $form_object->load();
      foreach ($config_entities as $config) {
        $config_names[] = $config->getConfigDependencyName();
      }
      foreach ($config_names as $name) {
        if ($this->isControlledByFeatures($name)) {
          $mark_form_read_only = TRUE;
          break;
        }
      }
    }

    if ($form_object instanceof EntityFormInterface) {
      $entity = $form_object->getEntity();
      $name = $entity->getConfigDependencyName();
      if ($this->isControlledByFeatures($name)) {
        $mark_form_read_only = TRUE;
      }
    }

    if ($form_object instanceof ConfigFormBase) {
      $editable_config = $this->getEditableConfigNames($form_object);

      // If at least one config item is controlled by Features, block the form.
      foreach ($editable_config as $name) {
        if ($this->isControlledByFeatures($name)) {
          $mark_form_read_only = TRUE;
          break;
        }
      }
    }

    if ($mark_form_read_only) {
      $event->markFormReadOnly();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = [];
    $events[ReadOnlyFormEvent::NAME][] = ['onFormAlter', 200];
    return $events;
  }

  /**
   * Get the editable configuration names.
   *
   * @param \Drupal\Core\Form\ConfigFormBase $form
   *   The configuration form.
   *
   * @return array
   *   An array of configuration object names that are editable if called in
   *   conjunction with the trait's config() method.
   *
   * @see \Drupal\Core\Form\ConfigFormBaseTrait::getEditableConfigNames()
   */
  protected function getEditableConfigNames(ConfigFormBase $form) {
    // Use reflection to work around getEditableConfigNames() as protected.
    // @todo Review in 9.x for API change.
    // @see https://www.drupal.org/node/2095289
    $reflection = new \ReflectionMethod(get_class($form), 'getEditableConfigNames');
    $reflection->setAccessible(TRUE);
    return $reflection->invoke($form);
  }

  /**
   * Determines if a config object is managed by a Feature.
   *
   * @param string $name
   *   The name of a config object.
   *
   * @return bool
   */
  public function isControlledByFeatures($name) {
    $features_manager = \Drupal::service('features.manager');
    $installed_features = $features_manager->getFeaturesModules(NULL, TRUE);

    foreach ($installed_features as $extension) {
      foreach ($features_manager->listExtensionConfig($extension) as $config) {
        if ($name == $config) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

}
