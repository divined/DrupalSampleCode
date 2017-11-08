<?php

namespace Drupal\art_invoice\Import;

use Drupal\art_invoice\Entity\Invoice;
use Drupal\Core\Config\ConfigImporterException;
use Drupal\Core\Config\StorageComparerInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Defines a invoice importer.
 */
class InvoiceImporter {
  use StringTranslationTrait;
  use DependencySerializationTrait;

  /**
   * The name used to identify the lock.
   */
  const LOCK_NAME = 'invoice_importer';

  /**
   * The storage comparer used to discover configuration changes.
   *
   * @var \Drupal\Core\Config\StorageComparerInterface
   */
  protected $storageDiff;

  /**
   * Count of invoice changes processed by the import().
   *
   * @var array
   */
  protected $processedInvoice = 0;

  /**
   * The entity storage.
   *
   * @var EntityStorageInterface
   */
  protected $entityStorage;

  /**
   * The database lock object.
   *
   * @var LockBackendInterface
   */
  protected $lock;

  /**
   * Invoice data array.
   *
   * @var array
   */
  protected $invoiceData = [];

  /**
   * Total invoice to import.
   *
   * @var int
   */
  protected $totalInvoiceToProcess = 0;

  /**
   * A log of any errors encountered.
   *
   * If errors are logged during the validation event the invoice
   * import will not occur. If errors occur during an import then best
   * efforts are made to complete the import.
   *
   * @var array
   */
  protected $errors = [];

  /**
   * The source storage.
   *
   * @var StorageInterface
   */
  protected $source;

  /**
   * InvoiceImporter constructor.
   *
   * @param LockBackendInterface $lock
   * @param StorageComparerInterface $storage_diff
   * @param EntityStorageInterface $entityStorage
   * @param StorageInterface $source
   */
  public function __construct(LockBackendInterface $lock, StorageComparerInterface $storage_diff, EntityStorageInterface $entityStorage, StorageInterface $source) {
    $this->lock = $lock;
    $this->storageDiff = $storage_diff;
    $this->entityStorage = $entityStorage;
    $this->source = $source;
  }

  /**
   * Logs an error message.
   *
   * @param string $message
   *   The message to log.
   */
  public function logError($message) {
    $this->errors[] = $message;
  }

  /**
   * Returns error messages created while running the import.
   *
   * @return array
   *   List of messages.
   */
  public function getErrors() {
    return $this->errors;
  }

  /**
   * Calls a invoice import step.
   *
   * @param string|callable $import_step
   *   The step to do. Either a method on the ConfigImporter class or a
   *   callable.
   * @param array $context
   *   A batch context array. If the config importer is not running in a batch
   *   the only array key that is used is $context['finished']. A process needs
   *   to set $context['finished'] = 1 when it is done.
   */
  public function doImportStep($import_step, &$context) {
    $this->$import_step($context);
  }

  /**
   * Initializes the config importer in preparation for processing a batch.
   *
   * @return array
   *   An array of \Drupal\Core\Config\ConfigImporter method names and callables
   *   that are invoked to complete the import. If there are modules or themes
   *   to process then an extra step is added.
   *
   * @throws \Drupal\Core\Config\ConfigImporterException
   *   If the configuration is already importing.
   */
  public function initialize() {
    if (!$this->lock->acquire(static::LOCK_NAME)) {
      throw new ConfigImporterException(sprintf('%s is already importing', static::LOCK_NAME));
    }

    $import_steps = [];
    $import_steps[] = 'processInvoices';
    $import_steps[] = 'finish';

    return $import_steps;
  }

  /**
   * Gets the next invoice operation to perform.
   *
   * @return \Drupal\Core\Entity\EntityInterface|bool
   *   An array containing the next operation and configuration name to perform
   *   it on. If there is nothing left to do returns FALSE;
   */
  public function getNextInvoiceImportOperation() {
    $this->storageDiff->reset();
    $invoice_packs = $this->storageDiff->getChangelist('create');

    foreach ($invoice_packs as $invoice_pack) {
      $import_data = array_shift($this->invoiceData[$invoice_pack]);
      $invoice_data = $this->mapInvoice($import_data);

      if (!empty($invoice_data)) {
        return $this->entityStorage->create($invoice_data);
      }

      $data = $this->storageDiff->getSourceStorage()->read($invoice_pack);

      if ($data) {
        $this->storageDiff->getTargetStorage()->write($invoice_pack, $data);
        $this->source->delete($invoice_pack);
      }
    }

    return FALSE;
  }

  /**
   * Invoice import function.
   *
   * @param \Drupal\art_invoice\Entity\Invoice $invoice
   *   Invoice entity.
   */
  public function importInvoice(Invoice $invoice) {
    if ($invoice->validate()->count() == 0) {
      $this->entityStorage->save($invoice);
    }

    $this->processedInvoice++;
  }

  /**
   * Get total invoice to process.
   *
   * @return int
   *   Invoice count.
   */
  public function getTotalInvoiceToProcess() {
    $count = 1;
    $invoice_packs = $this->storageDiff->getChangelist('create');

    foreach ($invoice_packs as $invoice_pack) {
      $this->invoiceData[$invoice_pack] = $this->storageDiff->getSourceStorage()->read($invoice_pack);
      $count += count($this->invoiceData[$invoice_pack]);
    }

    return $count;
  }

  /**
   * Processes configuration as a batch operation.
   *
   * @param array|\ArrayAccess $context
   *   The batch context.
   */
  protected function processInvoices(&$context) {
    if ($this->totalInvoiceToProcess == 0) {
      $this->storageDiff->reset();
      $this->totalInvoiceToProcess = $this->getTotalInvoiceToProcess();
    }

    /** @var Invoice $invoice */
    $invoice = $this->getNextInvoiceImportOperation();

    if (empty($invoice)) {
      $context['finished'] = TRUE;
      return;
    }

    $this->importInvoice($invoice);

    $context['finished'] = $this->processedInvoice / $this->totalInvoiceToProcess;
    $context['message'] = t('Import invoice: @name / @int of @count', [
      '@name' => $invoice->label(),
      '@int' => $this->processedInvoice,
      '@count' => $this->totalInvoiceToProcess,
    ]);
  }

  /**
   * Finishes the batch.
   *
   * @param array|\ArrayAccess $context
   *   The batch context.
   */
  protected function finish(&$context) {
    $this->lock->release(static::LOCK_NAME);

    $context['message'] = t('Finalizing invoice import.');
    $context['finished'] = TRUE;
  }

  /**
   * Determines if a import is already running.
   *
   * @return bool
   *   TRUE if an import is already running, FALSE if not.
   */
  public function alreadyImporting() {
    return !$this->lock->lockMayBeAvailable(static::LOCK_NAME);
  }

  /**
   * Map fields.
   *
   * @param array $invoice_data
   *   CSV array data.
   *
   * @return array
   *   Mapped fields.
   */
  public function mapInvoice($invoice_data = []) {
    $entity_data = [];

    if (empty($invoice_data['INVREF'])) {
      return [];
    }

    $mapping = [
      'INVREF' => 'name',
      'INVNO' => 'field_invoice_invoice_number',
      'CUSTNO' => 'field_invoice_customer_number',
      'SALENO' => 'field_invoice_sale_number',
      'SALENAME' => 'field_invoice_sale_name',
      'BIDDER' => 'field_invoice_bidder',
      'SALEDATE' => 'field_invoice_sale_date',
      'FIRSTNAME' => 'field_invoice_first_name',
      'LASTNAME' => 'field_invoice_last_name',
      'EMAIL' => 'field_invoice_email',
      'DEBIT' => 'field_invoice_debit',
      'CREDIT' => 'field_invoice_credit',
      'LANGUAGE' => 'field_invoice_language',
    ];

    foreach ($mapping as $key => $field_name) {
      $entity_data[$field_name] = isset($invoice_data[$key]) ? $invoice_data[$key] : NULL;
    }

    return $entity_data;
  }

}
