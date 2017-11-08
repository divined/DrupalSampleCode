<?php

namespace Drupal\art_invoice\Import;

use Drupal\Core\Site\Settings;

/**
 * Provides a factory for creating invoice file archive storage objects.
 */
class InvoiceFileArchiveStorageFactory {

  /**
   * Returns a FileStorage object working with the archive directory.
   *
   * @return \Drupal\Core\Config\FileStorage
   *   File storage.
   */
  public static function getSync() {
    return new InvoiceFileStorage(Settings::get('file_archive_path'));
  }

}
