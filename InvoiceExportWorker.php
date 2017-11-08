<?php

namespace Drupal\art_invoice\Plugin\QueueWorker;

use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class InvoiceExportWorker.
 *
 * @QueueWorker(
 *   id = "invoice_export",
 *   title = @Translation("Invoice export"),
 *   cron = {"time" = 30}
 * )
 */
class InvoiceExportWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Temp database storage for export.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $forExport;

  /**
   * Export target file storage.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $export;

  /**
   * The config manager.
   *
   * @var ConfigManagerInterface
   */
  protected $configManager;

  /**
   * The queue factory.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

  /**
   * InvoiceExportWorker constructor.
   *
   * @param array $configuration
   *   The configuration array.
   * @param string $plugin_id
   *   The plugin name.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Config\StorageInterface $for_export
   *   Temp database storage for export.
   * @param \Drupal\Core\Config\StorageInterface $export
   *   Export target file storage.
   * @param \Drupal\Core\Config\ConfigManagerInterface $configManager
   *   The config manager.
   * @param \Drupal\Core\Queue\QueueFactory $queueFactory
   *   The queue factory.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, StorageInterface $for_export, StorageInterface $export, ConfigManagerInterface $configManager, QueueFactory $queueFactory) {
    $this->forExport     = $for_export;
    $this->export        = $export;
    $this->configManager = $configManager;
    $this->queue         = $queueFactory->get('invoice_export');

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
      $container->get('art_invoice.storage.for_export'),
      $container->get('art_invoice.storage.export'),
      $container->get('config.manager'),
      $container->get('queue')
    );
  }

  /**
   * Import initialize.
   */
  public function initialize() {
    if (REQUEST_TIME - \Drupal::state()->get('invoice_export.last_run', 0) <= 24 * 60 * 60) {
      return;
    }

    \Drupal::state()->set('invoice_export.last_run', REQUEST_TIME);

    $export_data = [];

    foreach ($this->forExport->listAll() as $item) {
      if ($data = $this->forExport->read($item)) {
        $this->forExport->delete($item);
        $export_data[] = $data;
      }
    }

    $this->queue->createItem($export_data);
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $invoice_pack = date('Y-m-d-H-i-s') . '_paid_invoices';
    $this->export->write($invoice_pack, $data);
  }

}
