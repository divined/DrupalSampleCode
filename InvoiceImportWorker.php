<?php

namespace Drupal\art_invoice\Plugin\QueueWorker;

use Drupal\art_invoice\Import\InvoiceFileStorage;
use Drupal\art_invoice\Import\InvoiceImporter;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\StorageComparer;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class InvoiceImportWorker.
 *
 * @QueueWorker(
 *   id = "invoice_import",
 *   title = @Translation("Invoice Import"),
 *   cron = {"time" = 30}
 * )
 */
class InvoiceImportWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The file storage.
   *
   * @var InvoiceFileStorage
   */
  protected $storage;

  /**
   * The archive file storage.
   *
   * @var InvoiceFileStorage
   */
  protected $archive;

  /**
   * The config manager.
   *
   * @var ConfigManagerInterface
   */
  protected $configManager;

  /**
   * Invoice data array.
   *
   * @var array
   */
  protected $invoiceData = [];

  /**
   * The invoice storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $entityStorage;

  /**
   * The storage comparer used to discover configuration changes.
   *
   * @var \Drupal\Core\Config\StorageComparerInterface
   */
  protected $storageDiff;

  /**
   * The iInvoice importer.
   *
   * @var InvoiceImporter
   */
  protected $invoiceImporter;

  /**
   * The queue factory.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

  /**
   * The database lock object.
   *
   * @var LockBackendInterface
   */
  protected $lock;

  /**
   * InvoiceImportWorker constructor.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, InvoiceFileStorage $storage, InvoiceFileStorage $archive, LockBackendInterface $lock, ConfigManagerInterface $configManager, EntityTypeManagerInterface $manager, QueueFactory $queueFactory) {
    $this->storage         = $storage;
    $this->archive         = $archive;
    $this->configManager   = $configManager;
    $this->lock            = $lock;
    $this->queue           = $queueFactory->get('invoice_import');
    $this->entityStorage   = $manager->getStorage('art_invoice');
    $this->storageDiff     = new StorageComparer($this->storage, $this->archive, $this->configManager);
    $this->invoiceImporter = new InvoiceImporter(
      $lock,
      $this->storageDiff,
      $this->entityStorage,
      $this->storage
    );

    $this->initialize();
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('art_invoice.storage.sync'),
      $container->get('art_invoice.storage.archive'),
      $container->get('lock.persistent'),
      $container->get('config.manager'),
      $container->get('entity_type.manager'),
      $container->get('queue')
    );
  }

  /**
   * Import initialize.
   */
  public function initialize() {
    try {
      $this->invoiceImporter->initialize();

      $source_list = $this->storage->listAll();

      if (empty($source_list) || !$this->storageDiff->createChangelist()->hasChanges()) {
        return;
      }

      $this->invoiceImporter->getTotalInvoiceToProcess();

      while ($item = $this->invoiceImporter->getNextInvoiceImportOperation()) {
        $this->queue->createItem($item);
      }
    }
    catch (\Exception $e) {

    }
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $this->invoiceImporter->importInvoice($data);
  }

}
