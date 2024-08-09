<?php

/**
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Models\Storages;

use DUP_PRO_Global_Entity;
use DUP_PRO_Installer;
use DUP_PRO_Log;
use DUP_PRO_Package;
use DUP_PRO_Package_Upload_Info;
use Duplicator\Core\Views\TplMng;
use Duplicator\Libs\Snap\SnapIO;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Package\Storage\StorageUploadChunkFiles;
use Duplicator\Utils\PathUtil;
use Exception;
use wpdb;

class LocalStorage extends AbstractStorageEntity
{
    const LOCAL_STORAGE_CHUNK_SIZE_IN_MB = 16;

    /**
     * Get default config
     *
     * @return array<string,scalar>
     */
    protected static function getDefaultCoinfig()
    {
        $config                      = parent::getDefaultCoinfig();
        $config['storage_folder']    = '';
        $config['purge_packages']    = true;
        $config['filter_protection'] = true;
        return $config;
    }

    /**
     * Wakeup method
     *
     * @return void
     */
    public function __wakeup()
    {
        parent::__wakeup();

        if ($this->legacyEntity) {
            // Old storage entity
            $this->legacyEntity = false;
            // Make sure the storage type is right from the old entity
            $this->storage_type = $this->getSType();
            $this->config       = [
                'storage_folder'    => '/' . ltrim($this->local_storage_folder, '/\\'),
                'max_packages'      => $this->local_max_files,
                'filter_protection' => $this->local_filter_protection,
            ];

            // reset old values
            $this->local_storage_folder    = '';
            $this->local_max_files         = 0;
            $this->local_filter_protection = true;
        }
    }

    /**
     * Return the storage type
     *
     * @return int
     */
    public static function getSType()
    {
        return 0;
    }

    /**
     * Returns the FontAwesome storage type icon.
     *
     * @return string Returns the font-awesome icon
     */
    public static function getStypeIcon()
    {
        return '<i class="fas fa-hdd fa-fw"></i>';
    }

    /**
     * Returns the storage type name.
     *
     * @return string
     */
    public static function getStypeName()
    {
        return __('Local', 'duplicator-pro');
    }

    /**
     * Get priority, used to sort storages.
     * 100 is neutral value, 0 is the highest priority
     *
     * @return int
     */
    public static function getPriority()
    {
        return 50;
    }

    /**
     * Returns true if storage type is local
     *
     * @return bool
     */
    public static function isLocal()
    {
        return true;
    }

    /**
     * Get storage location string
     *
     * @return string
     */
    public function getLocationString()
    {
        return $this->getStorageFolder();
    }

    /**
     * Returns an html anchor tag of location
     *
     * @return string Returns an html anchor tag with the storage location as a hyperlink.
     *
     * @example
     * OneDrive Example return
     * <a target="_blank" href="https://1drv.ms/f/sAFrQtasdrewasyghg">https://1drv.ms/f/sAFrQtasdrewasyghg</a>
     */
    public function getHtmlLocationLink()
    {
        return '<span>' . $this->getStorageFolder() . '</span>';
    }

    /**
     * Check if storage is valid
     *
     * @return bool Return true if storage is valid and ready to use, false otherwise
     */
    public function isValid()
    {
        return (is_dir($this->config['storage_folder']) && is_writable($this->config['storage_folder']));
    }

    /**
     * Delete view
     *
     * @param bool $echo Echo or return
     *
     * @return string HTML string
     */
    public function getDeleteView($echo = true)
    {
        ob_start();
        ?>
        <div class="item">
            <span class="lbl">Name:</span><?php echo esc_html($this->getName()); ?><br>
            <span class="lbl">Type:</span><?php echo $this->getStypeIcon(); ?>&nbsp;<?php echo esc_html($this->getStypeName()); ?><br>
            <span class="lbl">Folder:</span><?php echo esc_html($this->getLocationString()); ?><br>
            <span class="lbl">Note:</span><span class="maroon">
                <i class="fas fa-exclamation-triangle"></i>
                &nbsp;<?php _e('By removing this storage all its packages inside it will be deleted.', 'duplicator-pro') ?>
            </span><br>

        </div>
        <?php
        if ($echo) {
            ob_end_flush();
            return '';
        } else {
            return (string) ob_get_clean();
        }
    }

    /**
     * Is filter protection enabled
     *
     * @return bool
     */
    public function isFilterProtection()
    {
        return $this->config['filter_protection'];
    }

    /**
     * Get action key text
     *
     * @param string $key Key name (action, pending, failed, cancelled, success)
     *
     * @return string
     */
    protected function getActionKeyText($key)
    {
        switch ($key) {
            case 'action':
                return __('Copying to directory:', 'duplicator-pro') . '<br>' . $this->getStorageFolder();
            case 'pending':
                return sprintf(__('Copy to directory %1$s is pending', "duplicator-pro"), $this->getStorageFolder());
            case 'failed':
                return sprintf(__('Failed to copy to directory %1$s', "duplicator-pro"), $this->getStorageFolder());
            case 'cancelled':
                return sprintf(__('Cancelled before could copy to directory %1$s', "duplicator-pro"), $this->getStorageFolder());
            case 'success':
                return sprintf(__('Copied package to directory %1$s', "duplicator-pro"), $this->getStorageFolder());
            default:
                throw new Exception('Invalid key');
        }
    }

    /**
     * Create file
     *
     * @param string $fileName File name
     * @param string $content  File content
     *
     * @return bool true if success, false otherwise
     */
    protected function createFile($fileName, $content = '')
    {
        if (!$this->isValid()) {
            return false;
        }

        return (file_put_contents($this->getStorageFolder() . '/' . $fileName, $content) !== false);
    }

    /**
     * Delete file
     *
     * @param string $fileName File name
     *
     * @return bool true if success, false otherwise
     */
    protected function deleteFile($fileName)
    {
        return SnapIO::unlink($this->getStorageFolder() . '/' . $fileName);
    }

    /**
     * Get file content
     *
     * @param string $fileName File name
     *
     * @return false|string false if file not found, file content otherwise
     */
    protected function getFileContent($fileName)
    {
        if (!file_exists($this->getStorageFolder() . '/' . $fileName)) {
            return false;
        }

        return file_get_contents($this->getStorageFolder() . '/' . $fileName);
    }

    /**
     * File exists
     *
     * @param string $fileName File name
     *
     * @return bool
     */
    protected function fileExists($fileName)
    {
        return file_exists($this->getStorageFolder() . '/' . $fileName);
    }

    /**
     * Render form config fields
     *
     * @param bool $echo Echo or return
     *
     * @return string
     */
    public function renderConfigFields($echo = true)
    {
        return TplMng::getInstance()->render(
            'admin_pages/storages/configs/local',
            [
                'storage'            => $this,
                'maxPackages'        => $this->config['max_packages'],
                'isFilderProtection' => $this->config['filter_protection'],
                'storageFolder'      => $this->config['storage_folder'],
            ],
            $echo
        );
    }

    /**
     * Storages test
     *
     * @param string $message Test message
     *
     * @return bool return true if success, false otherwise
     */
    public function test(&$message = '')
    {
        if (parent::test($message) == false) {
            return false;
        }

        $folder       = $this->getStorageFolder();
        $testFileName = 'dup_test_' . md5(uniqid((string) rand(), true)) . '.txt';

        $this->testLog->addMessage(sprintf(__('Checking if directory exists "%1$s"', 'duplicator-pro'), $folder));
        if (!is_dir($folder)) {
            $this->testLog->addMessage(sprintf(__(
                'The storage path does not exists "%1$s"',
                'duplicator-pro'
            ), $folder));
            $message = __('The storage path does not exists', 'duplicator-pro');
            return false;
        }

        $this->testLog->addMessage(sprintf(__('Checking if the directory is writable "%1$s"', 'duplicator-pro'), $folder));
        if (!is_writable($folder)) {
            $this->testLog->addMessage(sprintf(__(
                'The storage path is not writable "%1$s"',
                'duplicator-pro'
            ), $folder));
            $message = __('The storage path is not writable', 'duplicator-pro');
            return false;
        }

        $this->testLog->addMessage(sprintf(__('Checking if the temporary file exists "%1$s"...', 'duplicator-pro'), $testFileName));
        if ($this->fileExists($testFileName)) {
            $this->testLog->addMessage(sprintf(__(
                'File with the temporary file name already exists, please try again "%1$s"',
                'duplicator-pro'
            ), $testFileName));
            $message = __('File with the temporary file name already exists, please try again', 'duplicator-pro');
            return false;
        }

        $this->testLog->addMessage(sprintf(__('Creating temporary file "%1$s"...', 'duplicator-pro'), $testFileName));
        if (!$this->createFile($testFileName)) {
            $this->testLog->addMessage(
                __(
                    'There was a problem when storing the temporary file',
                    'duplicator-pro'
                )
            );
            $message = __('There was a problem storing the temporary file', 'duplicator-pro');
            return false;
        }

        $this->testLog->addMessage(sprintf(__('Checking if the temporary file exists "%1$s"...', 'duplicator-pro'), $testFileName));
        /** @var bool */
        $check = $this->fileExists($testFileName);
        if (!$check) {
            $this->testLog->addMessage(sprintf(__(
                'The temporary file was not found "%1$s"',
                'duplicator-pro'
            ), $testFileName));
            $message = __('The temporary file was not found', 'duplicator-pro');
            return false;
        }

        $this->testLog->addMessage(sprintf(__('Deleting temporary file "%1$s"...', 'duplicator-pro'), $testFileName));
        if (!$this->deleteFile($testFileName)) {
            $this->testLog->addMessage(sprintf(__(
                'There was a problem when deleting the temporary file "%1$s"',
                'duplicator-pro'
            ), $testFileName));
            $message = __('There was a problem deleting the temporary file', 'duplicator-pro');
            return false;
        }

        $this->testLog->addMessage(__('Successfully stored and deleted file', 'duplicator-pro'));
        $message = __('Successfully stored and deleted file', 'duplicator-pro');
        return true;
    }

    /**
     * Update data from http request, this method don't save data, just update object properties
     *
     * @param string $message Message
     *
     * @return bool True if success and all data is valid, false otherwise
     */
    public function updateFromHttpRequest(&$message = '')
    {
        if ((parent::updateFromHttpRequest($message) === false)) {
            return false;
        }
        $this->config['filter_protection'] = SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, '_local_filter_protection');
        $this->config['max_packages']      = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'local_max_files', 10);


        $newFolder = self::getSanitizedInputFolder('_local_storage_folder', 'add');
        if ($this->updateFolderCheck($newFolder, $message) === false) {
            return false;
        }

        if ($this->initStorageDirectory() == false) {
            $message = sprintf(
                __('Storage Provider Updated - Unable to init folder %1$s.', 'duplicator-pro'),
                $this->config['storage_folder']
            );
            return false;
        }

        $message = sprintf(
            __('Storage Provider Updated - Folder %1$s was created.', 'duplicator-pro'),
            $this->config['storage_folder']
        );
        return true;
    }

    /**
     * Update folder
     *
     * @param string $newFolder New folder
     * @param string $message   Error message
     *
     * @return bool
     */
    protected function updateFolderCheck($newFolder, &$message = '')
    {
        if ($this->config['storage_folder'] === $newFolder) {
            return true;
        }
        $this->config['storage_folder'] = $newFolder;
        if (strlen($this->config['storage_folder']) == 0) {
            $message = __('Local storage path can\'t be empty.', 'duplicator-pro');
            return false;
        }
        if (PathUtil::isPathInCoreDirs($this->config['storage_folder'])) {
            $message = __(
                'This storage path can\'t be used because it is a core WordPress directory or a sub-path of a core directory.',
                'duplicator-pro'
            );
            return false;
        }
        if ($this->isPathRepeated()) {
            $message = __(
                'A local storage already exists or current folder is a child of another existing storage folder.',
                'duplicator-pro'
            );
            return false;
        }
        if (!self::isFolderEmpty($this->config['storage_folder'])) {
            $message = __('Selected storage path already exists and isn\'t empty select another path.', 'duplicator-pro') . ' ' .
                __('Select another folder or remove all files that are not backup archives.', 'duplicator-pro');
            return false;
        }

        return true;
    }

    /**
     * Creates the snapshot directory if it doesn't already exists
     *
     * @param bool $skipIfExists If true it will skip creating the directory if it already exists
     *
     * @return bool True if success, false otherwise
     */
    public function initStorageDirectory($skipIfExists = false)
    {
        $path = $this->getStorageFolder();
        if (file_exists($path) && $skipIfExists) {
            if (is_dir($path)) {
                return true;
            } else {
                DUP_PRO_Log::infoTrace('Storage path exists but is not a directory: ' . $path);
                return false;
            }
        }

        if ((wp_mkdir_p($path) == false)) {
            return false;
        }
        SnapIO::chmod($path, 'u+rwx');

        self::setupStorageHtaccess($path);
        self::setupStorageIndex($path);
        self::setupStorageDirRobotsFile($path);
        self::performHardenProcesses($path);

        return true;
    }

    /**
     * Copies the package files from the default local storage to another local storage location
     *
     * @param DUP_PRO_Package             $package     the package
     * @param DUP_PRO_Package_Upload_Info $upload_info the upload info
     *
     * @return void
     */
    public function copyFromDefault(DUP_PRO_Package $package, DUP_PRO_Package_Upload_Info $upload_info)
    {
        DUP_PRO_Log::infoTrace("Copyng to Storage " . $this->name . '[ID: ' . $this->id . '] type:' . $this->getStypeName());

        $sFolder = SnapIO::trailingslashit($this->config['storage_folder']);

        //must be $to => $from because array key has to be unique
        $replacements = array(
            $package->Installer->getSafeFilePath() => $sFolder . basename($package->Installer->getSafeFilePath()),
            $package->Archive->getSafeFilePath()   => $sFolder . basename($package->Archive->getSafeFilePath()),
        );

        $storageUpload = new StorageUploadChunkFiles(
            array(
                'replacements' => $replacements,
                'chunkSize'    => DUP_PRO_Global_Entity::getInstance()->local_upload_chunksize_in_MB * MB_IN_BYTES,
                'upload_info'  => $upload_info,
                'package'      => $package,
                'storage'      => $this,
            ),
            0,
            1000
        );

        switch ($storageUpload->start()) {
            case StorageUploadChunkFiles::CHUNK_COMPLETE:
                DUP_PRO_Log::trace('LOCAL UPLOAD IN CHUNKS COMPLETED');
                $upload_info->copied_installer = true;
                $upload_info->copied_archive   = true;

                if ($this->config['max_packages'] > 0) {
                    DUP_PRO_Log::trace('Purge old local packages');
                    $this->purgeOldPackages();
                }
                break;
            case StorageUploadChunkFiles::CHUNK_STOP:
                DUP_PRO_Log::trace('LOCAL UPLOAD IN CHUNKS NOT COMPLETED >> CONTINUE NEXT CHUNK');
                //do nothing for now
                break;
            case StorageUploadChunkFiles::CHUNK_ERROR:
            default:
                DUP_PRO_Log::infoTrace('Local upload in chunks, upload error: ' . $storageUpload->getLastErrorMessage());
                $upload_info->failed = true;
        }
        $package->update();
    }

    /**
     * Purge old packages
     *
     * @return bool true if success, false otherwise
     */
    public function purgeOldPackages()
    {
        if ($this->config['max_packages'] <= 0) {
            return true;
        }

        DUP_PRO_Log::infoTrace("Attempting to purge old packages at " . $this->name . '[ID: ' . $this->id . '] type:' . $this->getSTypeName());

        /** @var wpdb $wpdb*/
        global $wpdb;

        try {
            $fileList = SnapIO::regexGlob($this->config['storage_folder'], array(
                'regexFile'   => array(DUPLICATOR_PRO_ARCHIVE_REGEX_PATTERN),
                'regexFolder' => false,
            ));
            //Sort by creation time
            usort($fileList, function ($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            $nameHashesToPurge = array();
            for ($i = 0; $i < count($fileList) - $this->config['max_packages']; $i++) {
                $nameHash = substr(basename($fileList[$i]), 0, -12);
                /** @var string */
                $prepare             = $wpdb->prepare("%s", $nameHash);
                $nameHashesToPurge[] = $prepare;
                DUP_PRO_Package::deletePackageFilesInDir($nameHash, $this->config['storage_folder'], true);
            }

            // Purge package record logic
            if ($this->config['purge_packages'] && count($nameHashesToPurge) > 0) {
                $table       = $wpdb->base_prefix . "duplicator_pro_packages";
                $max_created = $wpdb->get_var(
                    "SELECT max(created) FROM " . $table . " WHERE concat_ws('_', name, hash) IN (" . implode(', ', $nameHashesToPurge) . ")"
                );
                $sql         = $wpdb->prepare("DELETE FROM " . $table . " WHERE created <= %s AND status = %d", $max_created, 100);
                $wpdb->query($sql);
            }
        } catch (Exception $e) {
            DUP_PRO_Log::infoTraceException($e, "FAIL: purge package for storage " . $this->name . '[ID: ' . $this->id . '] type:' . $this->getStypeName());
            return false;
        }

        DUP_PRO_Log::infoTrace("Purge of old packages at " . $this->name . '[ID: ' . $this->id . "] storage completed.");
        return true;
    }

    /**
     * Delete this storage
     *
     * @return bool True on success, or false on error.
     */
    public function delete()
    {
        if (parent::delete() === false) {
            return false;
        }

        if (self::isFolderEmpty($this->config['storage_folder'])) {
            SnapIO::rrmdir($this->config['storage_folder']);
        } else {
            // Don't delete the folder if it's not empty but don't show an error
            DUP_PRO_Log::infoTrace("Storage folder is not empty, can't delete it");
        }

        return true;
    }

    /**
     * Checks if the storage path is already used by another local storage or is a child of another local storage
     *
     * @return bool Whether the storage path is already used by another local storage
     */
    protected function isPathRepeated()
    {
        $storages = self::getAll();
        foreach ($storages as $storage) {
            if (
                !$storage->isLocal() ||
                $storage->id == $this->id
            ) {
                continue;
            }
            if (
                SnapIO::isChildPath(
                    $this->config['storage_folder'],
                    $storage->getStorageFolder(),
                    false,
                    true,
                    true
                )
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Attempts to create a secure .htaccess file in the download directory
     *
     * @param string $path The folder path
     *
     * @return bool True if success, false otherwise
     */
    protected static function setupStorageHtaccess($path)
    {
        try {
            $fileName = SnapIO::safePathTrailingslashit($path) . '.htaccess';

            if (DUP_PRO_Global_Entity::getInstance()->storage_htaccess_off) {
                @unlink($fileName);
            } elseif (!file_exists($fileName)) {
                $fileContent = <<<FILECONTENT
# Duplicator config, In case of file downloading problem, you can disable/enable it in Settings/Sotrag plugin settings

Options -Indexes
<IfModule mod_headers.c>
    <FilesMatch "\.(daf)$">
        ForceType application/octet-stream
        Header set Content-Disposition attachment
    </FilesMatch>
</IfModule>
FILECONTENT;
                if (file_put_contents($fileName, $fileContent) === false) {
                    throw new Exception('Can\'t create ' . $fileName);
                }
            }
        } catch (Exception $ex) {
            DUP_PRO_Log::Trace("Unable create file htaccess {$fileName} msg:" . $ex->getMessage());
            return false;
        }

        return true;
    }

    /**
     * Attempts to create an index.php file in the backups directory
     *
     * @param string $path The folder path
     *
     * @return bool True if success, false otherwise
     */
    protected static function setupStorageIndex($path)
    {
        try {
            $fileName = SnapIO::safePathTrailingslashit($path) . 'index.php';
            if (!file_exists($fileName)) {
                $fileContent = <<<FILECONTENT
<?php
// silence;
FILECONTENT;
                if (file_put_contents($fileName, $fileContent) === false) {
                    throw new Exception('Can\'t create file ' . $fileName);
                }
            }
        } catch (Exception $ex) {
            DUP_PRO_Log::Trace("Unable create index.php {$fileName} msg:" . $ex->getMessage());
            return false;
        }

        return true;
    }

    /**
    * Attempts to create a robots.txt file in the backups directory
    * to prevent search engines
    *
    * @param string $path The folder path
    *
    * @return bool True if success, false otherwise
    */
    protected static function setupStorageDirRobotsFile($path)
    {
        try {
            $fileName = SnapIO::safePathTrailingslashit($path) . 'robots.txt';
            if (!file_exists($fileName)) {
                $fileContent = <<<FILECONTENT
User-agent: *
Disallow: /
FILECONTENT;
                if (file_put_contents($fileName, $fileContent) === false) {
                    throw new Exception('Can\'t create ' . $fileName);
                }
            }
        } catch (Exception $ex) {
            DUP_PRO_Log::Trace("Unable create robots.txt {$fileName} msg:" . $ex->getMessage());
            return false;
        }

        return true;
    }

    /**
    * Run various secure processes to harden the backups dir
    *
    * @param string $path The folder path
    *
    * @return bool True if success, false otherwise
    */
    public static function performHardenProcesses($path)
    {
        try {
            $backupsDir = SnapIO::safePathTrailingslashit($path);

            //Edge Case: Remove any installer dirs
            $dupInstallFolder = $backupsDir . "dup-installer";
            if (file_exists($dupInstallFolder)) {
                SnapIO::rrmdir($dupInstallFolder);
            }

            // Rename installer php files to .bak
            SnapIO::regexGlobCallback(
                $backupsDir,
                function ($path) {
                    $parts   = pathinfo($path);
                    $newPath = $parts['dirname'] . '/' . $parts['filename'] . DUP_PRO_Installer::INSTALLER_SERVER_EXTENSION;
                    SnapIO::rename($path, $newPath);
                },
                array(
                    'regexFile'   => '/^.+_installer.*\.php$/',
                    'regexFolder' => false,
                    'recursive'   => true,
                )
            );
        } catch (Exception $ex) {
            DUP_PRO_Log::Trace("Unable to cleanup the storage folder {$path} msg:" . $ex->getMessage());
            return false;
        }

        return true;
    }

    /**
    * Check if folder is empty or have only package files
    *
    * @param string $path The folder path
    *
    * @return bool True is ok, false otherwise
    */
    protected static function isFolderEmpty($path)
    {
        if (!file_exists($path)) {
            return true;
        } elseif (!is_dir($path)) {
            return false;
        }

        $acceptFiles = [
            'index.php',
            'robots.txt',
            '.htaccess',
            'index.html',
        ];

        try {
            SnapIO::regexGlobCallback(
                $path,
                function ($path) use ($acceptFiles) {
                    if (is_dir($path)) {
                        throw new Exception('Folder have subfolders', 10);
                    }

                    if (!in_array(basename($path), $acceptFiles)) {
                        throw new Exception('Folder isn\'t empty', 10);
                    }

                    return;
                },
                array(
                    'regexFile'   => array(DUPLICATOR_PRO_GEN_FILE_REGEX_PATTERN),
                    'regexFolder' => false,
                    'recursive'   => false,
                    'invert'      => true,
                )
            );
        } catch (Exception $ex) {
            if ($ex->getCode() == 10) {
                return false;
            }
            throw $ex;
        }

        return true;
    }
}
