<?php

namespace Drupal\art_invoice\Export;

use Drupal\art_invoice\Entity\Invoice;
use Drupal\art_invoice\InvoiceStatus;
use Drupal\Core\Config\ConfigImporterException;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\StorageComparer;
use Drupal\Core\Config\StorageComparerInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Defines a invoice exporter.
 */
class InvoiceExporter {
  use DependencySerializationTrait;

  /**
   * Database storage.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $storage;

  /**
   * InvoiceExporter constructor.
   *
   * @param \Drupal\Core\Config\StorageInterface $storage
   *   Database storage.
   */
  public function __construct(StorageInterface $storage) {
    $this->storage = $storage;
  }

  /**
   * Store invoice in database for export.
   *
   * @param \Drupal\art_invoice\Entity\Invoice $invoice
   *   The invoice entity.
   */
  public function addInvoice(Invoice $invoice) {
    $this->storage->write($invoice->label(), $this->mapInvoice($invoice));
  }

  /**
   * Map fields.
   *
   * @param Invoice $invoice
   *   Invoice entity.
   *
   * @return array
   *   Mapped fields.
   */
  private function mapInvoice(Invoice $invoice) {
    $entity_data = [];

    if (empty($invoice->label())) {
      return [];
    }

    $mapping = [
      'Sale ID'             => 'field_invoice_sale_number',
      'Invoice Reference'   => 'name',
      'Invoice number'      => 'field_invoice_invoice_number',
      'Amount'              => 'amount',
      'Bank transaction ID' => 'field_invoice_transaction_id',
    ];

    foreach ($mapping as $name => $field_name) {
      $entity_data[$name] = $invoice->get($field_name)->value ?: NULL;
    }

    return $entity_data;
  }

}
