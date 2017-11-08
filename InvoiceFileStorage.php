<?php

namespace Drupal\art_invoice\Import;

use Drupal\Component\FileCache\FileCacheFactory;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\StorageInterface;
use Drupal\csv_serialization\Encoder\CsvEncoder;

/**
 * Defines the file storage.
 */
class InvoiceFileStorage extends FileStorage {

  /**
   * {@inheritdoc}
   */
  public function __construct($directory, $collection = StorageInterface::DEFAULT_COLLECTION) {
    $this->directory  = $directory;
    $this->collection = $collection;
    $this->fileCache  = FileCacheFactory::get('invoice_import', ['cache_backend_class' => NULL]);
  }

  /**
   * {@inheritdoc}
   */
  public static function getFileExtension() {
    return 'csv';
  }

  /**
   * {@inheritdoc}
   */
  public function encode($data) {
    $encoder = new CsvEncoder(';');

    return $encoder->encode($data, '');
  }

  /**
   * {@inheritdoc}
   */
  public function decode($raw) {
    $encoder = new CsvEncoder(';');
    $data    = $encoder->decode($raw, '');

    if (!is_array($data)) {
      return FALSE;
    }

    return $data;
  }

}
