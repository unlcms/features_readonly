<?php

namespace Drupal\features_readonly\Config;

use Drupal\features_readonly\Exception\FeaturesReadonlyStorageException;
use Drupal\Core\Config\CachedStorage;
use Drupal\Core\Config\ConfigImporter;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Site\Settings;

use Drupal\Core\Routing\RouteObjectInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Defines a config read-only storage controller.
 *
 * This fails on write operations.
 */
class FeaturesReadonlyStorage extends CachedStorage {

  /**
   * The used lock backend instance.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected $lock;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a new ConfigReadonlyStorage.
   *
   * @param \Drupal\Core\Config\StorageInterface $storage
   *   A configuration storage to be cached.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   A cache backend used to store configuration.
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   The lock backend to check if config imports are in progress.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(StorageInterface $storage, CacheBackendInterface $cache, LockBackendInterface $lock, RequestStack $request_stack) {
    parent::__construct($storage, $cache);
    $this->lock = $lock;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public function createCollection($collection) {
    return new static(
      $this->storage->createCollection($collection),
      $this->cache,
      $this->lock,
      $this->requestStack
    );
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\features_readonly\Exception\FeaturesReadonlyStorageException
   */
  public function write($name, array $data) {
    $this->checkLock($name);
    return parent::write($name, $data);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\features_readonly\Exception\FeaturesReadonlyStorageException
   */
  public function delete($name) {
    $this->checkLock($name);
    return parent::delete($name);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\features_readonly\Exception\FeaturesReadonlyStorageException
   */
  public function rename($name, $new_name) {
    $this->checkLock($name);
    $this->checkLock($new_name);
    return parent::rename($name, $new_name);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\features_readonly\Exception\FeaturesReadonlyStorageException
   */
  public function deleteAll($prefix = '') {
    $this->checkLock();
    return parent::deleteAll($prefix);
  }

  /**
   * Check whether config is currently locked.
   *
   * @param string $name
   *   Check for a specific lock config.
   *
   * @throws \Drupal\features_readonly\Exception\FeaturesReadonlyStorageException
   */
  protected function checkLock($name = '') {
    // If settings.php says to lock config changes and if the config importer
    // isn't running (we do not want to lock config imports), then throw an
    // exception.
    // @see \Drupal\Core\Config\ConfigImporter::alreadyImporting()
    if (!Settings::get('features_readonly_disable') && $this->lock->lockMayBeAvailable(ConfigImporter::LOCK_NAME)) {
      $request = $this->requestStack->getCurrentRequest();
      if ($request && $request->attributes->get(RouteObjectInterface::ROUTE_NAME) === 'system.db_update') {
        // We seem to be in the middle of running update.php.
        // @see \Drupal\Core\Update\UpdateKernel::setupRequestMatch()
        // @todo - always allow or support a flag for blocking it?
        return;
      }

      throw new FeaturesReadonlyStorageException('Your site configuration active store is currently locked.');
    }
  }

}
