<?php

namespace Drupal\art_invoice\Import;

use Drupal\Core\Site\Settings;

/**
 * Provides a factory for creating invoice file import storage objects.
 */
class InvoiceFileStorageFactory {

  /**
   * Returns a FileStorage object working with the import directory.
   *
   * @return \Drupal\Core\Config\FileStorage
   *   File storage.
   */
  public static function getSync() {
    return new InvoiceFileStorage(Settings::get('file_import_path'));
  }

}
