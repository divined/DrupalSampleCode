<?php

namespace Drupal\art_invoice\Export;

use Drupal\art_invoice\Import\InvoiceFileStorage;
use Drupal\Core\Site\Settings;

/**
 * Provides a factory for creating invoice file export storage objects.
 */
class InvoiceFileExportStorageFactory {

  /**
   * Returns a FileStorage object working with the export directory.
   *
   * @return \Drupal\Core\Config\FileStorage
   *   File storage.
   */
  public static function getSync() {
    return new InvoiceFileStorage(Settings::get('file_export_path'));
  }

}
