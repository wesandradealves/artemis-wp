<?php

/**
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Models\Storages;

use DUP_PRO_FTP_Chunker;
use DUP_PRO_FTPcURL;
use DUP_PRO_Global_Entity;
use DUP_PRO_Log;
use DUP_PRO_Package;
use DUP_PRO_Package_File_Type;
use DUP_PRO_Package_Upload_Info;
use DUP_PRO_STR;
use DUP_PRO_U;
use Duplicator\Core\Views\TplMng;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Utils\Exceptions\ChunkingTimeoutException;
use Duplicator\Utils\SFTPAdapter;
use Exception;

class SFTPStorage extends AbstractStorageEntity
{
    /** @var null|DUP_PRO_FTPcURL|DUP_PRO_FTP_Chunker */
    protected $client = null;

    /**
     * Get default config
     *
     * @return array<string,scalar>
     */
    protected static function getDefaultCoinfig()
    {
        $config = parent::getDefaultCoinfig();
        $config = array_merge(
            $config,
            [
                'server'               => '',
                'port'                 => 21,
                'username'             => '',
                'password'             => '',
                'private_key'          => '',
                'private_key_password' => '',
                'timeout_in_secs'      => 15,
                'chunking'             => true,
            ]
        );
        return $config;
    }

    /**
     * Serialize
     *
     * Wakeup method.
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
                'server'               => $this->sftp_server,
                'port'                 => $this->sftp_port,
                'username'             => $this->sftp_username,
                'password'             => $this->sftp_password,
                'private_key'          => $this->sftp_private_key,
                'private_key_password' => $this->sftp_private_key_password,
                'storage_folder'       => '/' . ltrim($this->sftp_storage_folder, '/\\'),
                'max_packages'         => $this->sftp_max_files,
                'timeout_in_secs'      => $this->sftp_timeout_in_secs,
                'chunking'             => !$this->sftp_disable_chunking_mode,
            ];
            // reset old values
            $this->sftp_server                = '';
            $this->sftp_port                  = 21;
            $this->sftp_username              = '';
            $this->sftp_password              = '';
            $this->sftp_private_key           = '';
            $this->sftp_private_key_password  = '';
            $this->sftp_storage_folder        = '';
            $this->sftp_max_files             = 10;
            $this->sftp_timeout_in_secs       = 15;
            $this->sftp_disable_chunking_mode = false;
        }
    }

    /**
     * Return the storage type
     *
     * @return int
     */
    public static function getSType()
    {
        return 5;
    }

    /**
     * Returns the FontAwesome storage type icon.
     *
     * @return string Returns the font-awesome icon
     */
    public static function getStypeIcon()
    {
        return '<i class="fas fa-network-wired fa-fw"></i>';
    }

    /**
     * Returns the storage type name.
     *
     * @return string
     */
    public static function getStypeName()
    {
        return __('SFTP', 'duplicator-pro');
    }

    /**
     * Get storage location string
     *
     * @return string
     */
    public function getLocationString()
    {
        return $this->config['server'] . ":" . $this->config['port'];
    }

    /**
     * Get priority, used to sort storages.
     * 100 is neutral value, 0 is the highest priority
     *
     * @return int
     */
    public static function getPriority()
    {
        return 90;
    }

    /**
     * Check if storage is supported
     *
     * @return bool
     */
    public static function isSupported()
    {
        return extension_loaded('gmp');
    }

    /**
     * Get supported notice, displayed if storage isn't supported
     *
     * @return string html string or empty if storage is supported
     */
    public static function getNotSupportedNotice()
    {
        if (static::isSupported()) {
            return '';
        }

        return sprintf(
            _x(
                'SFTP requires the %1$sgmp extension%2$s. Please contact your host to install.',
                '1: <a> tag, 2: </a> tag',
                'duplicator-pro'
            ),
            '<a href="http://php.net/manual/en/book.gmp.php" target="_blank">',
            '</a>'
        );
    }

    /**
     * Check if storage is valid
     *
     * @return bool Return true if storage is valid and ready to use, false otherwise
     */
    public function isValid()
    {
        if (strlen($this->config['server']) < 1) {
            return false;
        }
        if (strlen($this->config['username']) < 1) {
            return false;
        }
        if (strlen($this->config['storage_folder']) < 1) {
            return false;
        }
        if ($this->config['port'] < 0) {
            return false;
        }
        return true;
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
                return sprintf(
                    __('Transferring to SFTP server %1$s in folder:<br/> <i>%2$s</i>', "duplicator-pro"),
                    $this->config['server'],
                    $this->getStorageFolder()
                );
            case 'pending':
                return sprintf(
                    __('Transfer to SFTP server %1$s in folder %2$s is pending', "duplicator-pro"),
                    $this->config['server'],
                    $this->getStorageFolder()
                );
            case 'failed':
                return sprintf(
                    __('Failed to transfer to SFTP server %1$s in folder %2$s', "duplicator-pro"),
                    $this->config['server'],
                    $this->getStorageFolder()
                );
            case 'cancelled':
                return sprintf(
                    __('Cancelled before could transfer to SFTP server:<br/>%1$s in folder %2$s', "duplicator-pro"),
                    $this->config['server'],
                    $this->getStorageFolder()
                );
            case 'success':
                return sprintf(
                    __('Transferred package to SFTP server:<br/>%1$s in folder %2$s', "duplicator-pro"),
                    $this->config['server'],
                    $this->getStorageFolder()
                );
            default:
                throw new Exception('Invalid key');
        }
    }

    /**
     * List quick view
     *
     * @param bool $echo Echo or return
     *
     * @return string
     */
    public function getListQuickView($echo = true)
    {
        ob_start();
        ?>
        <div>
            <label><?php esc_html_e('Server', 'duplicator-pro'); ?>:</label>
            <?php echo esc_html($this->config['server']); ?>: <?php echo intval($this->config['port']);  ?>  <br/>
            <label><?php esc_html_e('Location', 'duplicator-pro') ?>:</label>
            <?php echo $this->getHtmlLocationLink(); ?>
        </div>
        <?php
        if ($echo) {
            ob_end_flush();
            return '';
        } else {
            return ob_get_clean();
        }
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
            'admin_pages/storages/configs/sftp',
            [
                'storage'       => $this,
                'server'        => $this->config['server'],
                'port'          => $this->config['port'],
                'username'      => $this->config['username'],
                'password'      => $this->config['password'],
                'privateKey'    => $this->config['private_key'],
                'privateKeyPwd' => $this->config['private_key_password'],
                'storageFolder' => $this->config['storage_folder'],
                'maxPackages'   => $this->config['max_packages'],
                'timeout'       => $this->config['timeout_in_secs'],
                'chunking'      => $this->config['chunking'],
            ],
            $echo
        );
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

        $this->config['max_packages'] = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'sftp_max_files', 10);
        $this->config['server']       = SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'sftp_server', '');
        $this->config['port']         = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'sftp_port', 10);
        $this->config['username']     = SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'sftp_username', '');
        $password                     = SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'sftp_password', '');
        $password2                    = SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'sftp_password2', '');
        $this->config['private_key']  = SnapUtil::sanitizeDefaultInput(SnapUtil::INPUT_REQUEST, 'sftp_private_key', '');
        $keyPassword                  = SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'sftp_private_key_password', '');
        $keyPassword2                 = SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'sftp_private_key_password2', '');
        if (strlen($password) > 0) {
            if ($password !== $password2) {
                $message = __('Passwords do not match', 'duplicator-pro');
                return false;
            }
            $this->config['password'] = $password;
        } elseif (strlen($keyPassword) > 0) {
            if ($keyPassword !== $keyPassword2) {
                $message = __('Priva key Passwords do not match', 'duplicator-pro');
                return false;
            }
            $this->config['private_key_password'] = $keyPassword;
        }
        $this->config['storage_folder']  = self::getSanitizedInputFolder('_sftp_storage_folder', 'add');
        $this->config['timeout_in_secs'] = max(10, SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'sftp_timeout_in_secs', 15));
        $this->config['chunking']        = SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, 'sftp_chunking_mode', false);

        $message = sprintf(
            __('SFTP Storage Updated - Server %1$s, Folder %2$s was created.', 'duplicator-pro'),
            $this->config['server'],
            $this->getStorageFolder()
        );
        return true;
    }

    /**
     * Close connection
     *
     * @return void
     */
    protected function closeConnection()
    {
        if ($this->client) {
            if (!$this->config['use_curl']) {
                $this->client->close();
            }
            $this->client = null;
        }
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

        $storage_folder       = $this->config['storage_folder'];
        $server               = $this->config['server'];
        $port                 = $this->config['port'];
        $username             = $this->config['username'];
        $password             = $this->config['password'];
        $private_key          = $this->config['private_key'];
        $private_key_password = $this->config['private_key_password'];

        $result = true;

        try {
            $source_filepath = false;
            // -- Store the temp file --
            $this->testLog->addMessage(__('Attempting to create a temp file', 'duplicator-pro'));
            DUP_PRO_Log::trace("Attempting to create a temp file");
            $source_filepath = tempnam(sys_get_temp_dir(), 'DUP');

            if ($source_filepath === false) {
                DUP_PRO_Log::trace("Couldn't create the temp file for the SFTP send test");
                throw new Exception(__("Couldn't create the temp file for the SFTP send test", 'duplicator-pro'));
            }

            $basename = basename($source_filepath);
            $this->testLog->addMessage(sprintf(__('Created a temp file "%1$s"', 'duplicator-pro'), $source_filepath));
            DUP_PRO_Log::trace("Created a temp file $source_filepath");

            if (DUP_PRO_STR::startsWith($storage_folder, '/') == false) {
                $storage_folder = '/' . $storage_folder;
            }

            if (DUP_PRO_STR::endsWith($storage_folder, '/') == false) {
                $storage_folder = $storage_folder . '/';
            }

            $sFtpAdapter = new SFTPAdapter($server, $port, $username, $password, $private_key, $private_key_password);
            $sFtpAdapter->setMessages($this->testLog);

            if (!$sFtpAdapter->connect()) {
                DUP_PRO_Log::trace("Couldn't connect to sftp server while doing the SFTP send test");
                throw new Exception(__("Couldn't connect to sftp server while doing the SFTP send test", 'duplicator-pro'));
            }

            $this->testLog->addMessage(sprintf(__('Checking if remote storage folder "%1$s" already exists', 'duplicator-pro'), $storage_folder));
            DUP_PRO_Log::trace("Checking if remote storage folder '$storage_folder' already exists");
            if (!$sFtpAdapter->fileExists($storage_folder)) {
                DUP_PRO_Log::trace("The remote storage folder '$storage_folder' does not exist, attempting to create it");
                $this->testLog->addMessage(
                    sprintf(
                        __('The remote storage folder "%1$s" does not exist, attempting to create it', 'duplicator-pro'),
                        $storage_folder
                    )
                );
                $storage_folder = $sFtpAdapter->mkDirRecursive($storage_folder);
                if (!$sFtpAdapter->fileExists($storage_folder)) {
                    DUP_PRO_Log::trace("The SFTP connection is working fine, but the directory can't be created.");
                    throw new Exception(__("The SFTP connection is working fine, but the directory can't be created.", 'duplicator-pro'));
                } else {
                    DUP_PRO_Log::trace("The remote storage folder is created successfully");
                    $this->testLog->addMessage(__('The remote storage folder is created successfully', 'duplicator-pro'));
                }
            } else {
                DUP_PRO_Log::trace("The remote storage folder already exists");
                $this->testLog->addMessage(__('The remote storage folder already exists', 'duplicator-pro'));
            }

            // Try to upload a test file
            $this->testLog->addMessage(__('Attempting to upload the test file', 'duplicator-pro'));
            DUP_PRO_Log::trace("Attempting to upload the test file");
            $continueUpload = true;
            try {
                if (!$sFtpAdapter->put($storage_folder . $basename, $source_filepath)) {
                    $continueUpload = false;
                    $this->testLog->addMessage(
                        __(
                            'Error uploading test file, maybe the directory does not exist or you have no write permissions',
                            'duplicator-pro'
                        )
                    );
                    DUP_PRO_Log::trace("Error uploading test file, maybe the directory does not exist or you have no write permissions.");
                    $message = __('Error uploading test file.', 'duplicator-pro');
                }
            } catch (ChunkingTimeoutException $e) {
                $continueUpload = true;
            }
            if ($continueUpload) {
                $result = true;
                $this->testLog->addMessage(__('Test file uploaded successfully', 'duplicator-pro'));
                DUP_PRO_Log::trace("Test file uploaded successfully.");
                $message = __('The connection was successful.', 'duplicator-pro');
                $this->testLog->addMessage(__('Attempting to delete the remote test file', 'duplicator-pro'));
                DUP_PRO_Log::trace("Attempting to delete the remote test file");
                if ($sFtpAdapter->delete($storage_folder . $basename)) {
                    $this->testLog->addMessage(__('Remote test file deleted successfully', 'duplicator-pro'));
                    DUP_PRO_Log::trace("Remote test file deleted successfully.");
                } else {
                    $this->testLog->addMessage(__('Couldn\'t delete the remote test file', 'duplicator-pro'));
                    DUP_PRO_Log::trace("Couldn't delete the remote test file.");
                }
            }
        } catch (Exception $e) {
            $this->testLog->addMessage($e->getMessage());
            DUP_PRO_Log::trace($e->getMessage());
            $message = $e->getMessage();
            $result  = false;
        }

        if (file_exists($source_filepath)) {
            $this->testLog->addMessage(sprintf(__('Attempting to delete local temp file "%1$s"', 'duplicator-pro'), $source_filepath));
            if (unlink($source_filepath) == false) {
                $this->testLog->addMessage(sprintf(__('Could not delete the temp file "%1$s"', 'duplicator-pro'), $source_filepath));
                DUP_PRO_Log::trace("Could not delete the temp file $source_filepath");
            } else {
                $this->testLog->addMessage(sprintf(__('Deleted temp file "%1$s"', 'duplicator-pro'), $source_filepath));
                DUP_PRO_Log::trace("Deleted temp file $source_filepath");
            }
        }

        if ($result) {
            $this->testLog->addMessage(__('Successfully stored and deleted file', 'duplicator-pro'));
            $message = __('Successfully stored and deleted file', 'duplicator-pro');
            return true;
        } else {
            return false;
        }
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

        $source_archive_filepath   = $package->getLocalPackageFilePath(DUP_PRO_Package_File_Type::Archive);
        $source_installer_filepath = $package->getLocalPackageFilePath(DUP_PRO_Package_File_Type::Installer);

        if ($source_archive_filepath === false) {
            DUP_PRO_Log::traceError("Archive doesn't exist for $package->Name!? - $source_archive_filepath");
            $upload_info->failed = true;
        }

        if ($source_installer_filepath === false) {
            DUP_PRO_Log::traceError("Installer doesn't exist for $package->Name!? - $source_installer_filepath");
            $upload_info->failed = true;
        }

        if ($upload_info->failed == true) {
            DUP_PRO_Log::infoTrace('SFTP storage failed flag ($upload_info->failed) has been already set.');
            $package->update();
            return;
        }

        $sFtpAdapter = null;

        try {
            $storage_folder       = $this->config['storage_folder'];
            $server               = $this->config['server'];
            $port                 = $this->config['port'];
            $username             = $this->config['username'];
            $password             = $this->config['password'];
            $private_key          = $this->config['private_key'];
            $private_key_password = $this->config['private_key_password'];
            $chunking_mode        = $this->config['chunking'];

            if (DUP_PRO_STR::startsWith($storage_folder, '/') == false) {
                $storage_folder = '/' . $storage_folder;
            }

            if (DUP_PRO_STR::endsWith($storage_folder, '/') == false) {
                $storage_folder = $storage_folder . '/';
            }

            $sFtpAdapter = new SFTPAdapter($server, $port, $username, $password, $private_key, $private_key_password);

            if ($sFtpAdapter->connect() === false) {
                DUP_PRO_Log::trace("SFTP connection fail");
                throw new Exception('SFTP connection fail');
            }
            if (!$sFtpAdapter->fileExists($storage_folder)) {
                DUP_PRO_Log::trace("Attempting to create $storage_folder via SFTP");
                $sFtpAdapter->mkDirRecursive($storage_folder);
            }

            if ($upload_info->copied_installer == false) {
                $source_filepath    = $source_installer_filepath;
                $basename           = $package->Installer->getInstallerName();
                $continueWithUpload = true;
                try {
                    $sFtpAdapter->startChunkingTimer();
                    if (!$sFtpAdapter->put($storage_folder . $basename, $source_filepath)) {
                        $upload_info->failed = true;
                        $continueWithUpload  = false;
                        DUP_PRO_Log::infoTrace("FAIL: installer $source_installer_filepath upload to SFTP $storage_folder.");
                    }
                } catch (ChunkingTimeoutException $e) {
                    $continueWithUpload = true;
                }

                if ($continueWithUpload) {
                    DUP_PRO_Log::infoTrace("SUCCESS: installer upload to SFTP $storage_folder.");
                    $upload_info->progress         = 5;
                    $upload_info->copied_installer = true;
                }
                // The package update will automatically capture the upload_info since its part of the package
                $package->update();
            } else {
                DUP_PRO_Log::trace("Already copied installer on previous execution of SFTP $this->name so skipping");
            }

            if ($upload_info->copied_archive == false) {
                if ($chunking_mode) {
                    //Make sure time threshold not exceed the server maximum execution time
                    $time_threshold = $this->config['timeout_in_secs'];
                    DUP_PRO_Log::trace('SFTP chunking mode is enabled, so setting the time_threshold=' . $time_threshold);
                } else {
                    DUP_PRO_Log::trace('SFTP chunking mode is disabled.');
                    $time_threshold = -1;
                }

                $source_filepath = $source_archive_filepath;
                $basename        = basename($source_filepath);

                $continueWithUpload = false;
                try {
                    $continueWithUpload = true;
                    $sFtpAdapter->startChunkingTimer($time_threshold);
                    $upload_info->archive_offset = $sFtpAdapter->filesize($storage_folder . $basename);
                    if (!$upload_info->archive_offset) {
                        $upload_info->archive_offset = 0;
                    }
                    DUP_PRO_Log::trace("At start of iteration archive offset is: " . $upload_info->archive_offset);
                    if (!$sFtpAdapter->put($storage_folder . $basename, $source_filepath, $upload_info->archive_offset)) {
                        $upload_info->failed = true;
                        $continueWithUpload  = false;
                        DUP_PRO_Log::infoTrace("FAIL: archive upload to SFTP.");
                    }
                } catch (ChunkingTimeoutException $e) {
                    $continueWithUpload = true;
                }

                // For some reason $sFtpAdapter->filesize($storage_folder . $basename) does not work here,
                // so there is no way to know new archive offset after call to put command.

                if ($continueWithUpload) {
                    $file_size             = filesize($source_filepath);
                    $upload_info->progress = max(
                        5,
                        DUP_PRO_U::percentage($upload_info->archive_offset, $file_size, 0)
                    );

                    DUP_PRO_Log::infoTrace(
                        "At start of iteration, archive upload offset: " . $upload_info->archive_offset .
                        " [File size: $file_size] [Upload progress: $upload_info->progress%]"
                    );

                    if ($upload_info->progress >= 100) {
                        $upload_info->copied_archive = true;
                        DUP_PRO_Log::infoTrace("SUCCESS: archive upload to SFTP.");
                        $this->purgeOldPackages();
                    }
                }

                // The package update will automatically capture the upload_info since its part of the package
                $package->update();
            } else {
                DUP_PRO_Log::trace("Already copied archive on previous execution of SFTP $this->name so skipping");
            }

            if ($upload_info->failed) {
                $source_filepath = $source_archive_filepath;
                $basename        = basename($source_filepath);
                $sFtpAdapter->delete($storage_folder . $basename);

                $source_filepath = $source_installer_filepath;
                $basename        = basename($source_filepath);
                $sFtpAdapter->delete($storage_folder . $basename);
            } else {
                $upload_info->failure_count = 0;
            }
        } catch (Exception $e) {
            $upload_info->increase_failure_count();
            DUP_PRO_Log::trace("Exception caught copying package $package->Name to $storage_folder. " . $e->getMessage());
        }

        if ($upload_info->failed) {
            DUP_PRO_Log::infoTrace('SFTP storage failed flag ($upload_info->failed) has been already set.');
        }

        // The package update will automatically capture the upload_info since its part of the package
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

        try {
            $storage_folder       = $this->config['storage_folder'];
            $server               = $this->config['server'];
            $port                 = $this->config['port'];
            $username             = $this->config['username'];
            $password             = $this->config['password'];
            $private_key          = $this->config['private_key'];
            $private_key_password = $this->config['private_key_password'];

            if (DUP_PRO_STR::startsWith($storage_folder, '/') == false) {
                $storage_folder = '/' . $storage_folder;
            }

            if (DUP_PRO_STR::endsWith($storage_folder, '/') == false) {
                $storage_folder = $storage_folder . '/';
            }

            $sFtpAdapter = new SFTPAdapter($server, $port, $username, $password, $private_key, $private_key_password);
            if (!$sFtpAdapter->connect()) {
                throw new Exception('Connction fail');
            }

            $storage_folder = $storage_folder;
            if (DUP_PRO_STR::startsWith($storage_folder, '/') == false) {
                $storage_folder = '/' . $storage_folder;
            }
            if (DUP_PRO_STR::endsWith($storage_folder, '/') == false) {
                $storage_folder = $storage_folder . '/';
            }
            $global    = DUP_PRO_Global_Entity::getInstance();
            $file_list = $sFtpAdapter->filesList($storage_folder);
            $file_list = array_diff($file_list, array(".", ".."));
            if (empty($file_list)) {
                DUP_PRO_Log::traceError(
                    "FAIL: purging SFTP packages. Problems making SFTP connection, Purging old packages not possible. Error retrieving file list for " .
                    $server . ":" . $port . " Storage Dir: " . $storage_folder
                );
            } else {
                $valid_file_list = array();
                foreach ($file_list as $file_name) {
                    DUP_PRO_Log::trace("considering filename {$file_name}");
                    if (DUP_PRO_Package::get_timestamp_from_filename($file_name) !== false) {
                        $valid_file_list[] = $file_name;
                    }
                }

                DUP_PRO_Log::traceObject('valid file list', $valid_file_list);
                try {
                    // Sort list by the timestamp associated with it
                    usort($valid_file_list, array(DUP_PRO_Package::class, 'compare_package_filenames_by_date'));
                } catch (Exception $e) {
                    DUP_PRO_Log::trace("Sort error when attempting to purge old FTP files");
                    return false;
                }

                $php_files         = array();
                $archive_filepaths = array();
                foreach ($valid_file_list as $file_name) {
                    $file_path = "$storage_folder/$file_name";
                    // just look for the archives and delete only if has matching _installer
                    if (DUP_PRO_STR::endsWith($file_path, "_{$global->installer_base_name}")) {
                        array_push($php_files, $file_path);
                    } elseif (DUP_PRO_STR::endsWith($file_path, '_archive.zip') || DUP_PRO_STR::endsWith($file_path, '_archive.daf')) {
                        array_push($archive_filepaths, $file_path);
                    }
                }

                $index                  = 0;
                $num_archives           = count($archive_filepaths);
                $num_archives_to_delete = $num_archives - $this->config['max_packages'];
                DUP_PRO_Log::trace("Num archives to delete=$num_archives_to_delete");
                while ($index < $num_archives_to_delete) {
                    $archive_filepath = $archive_filepaths[$index];
                    // Matching installer has to be present for us to delete
                    if (DUP_PRO_STR::endsWith($archive_filepath, '_archive.zip')) {
                        $installer_filepath = str_replace('_archive.zip', "_{$global->installer_base_name}", $archive_filepath);
                    } else {
                        $installer_filepath = str_replace('_archive.daf', "_{$global->installer_base_name}", $archive_filepath);
                    }

                    if (in_array($installer_filepath, $php_files)) {
                        DUP_PRO_Log::trace("$installer_filepath in array so deleting installer and archive");
                        $sFtpAdapter->delete($installer_filepath);
                        $sFtpAdapter->delete($archive_filepath);
                    } else {
                        DUP_PRO_Log::trace("$installer_filepath not in array so NOT deleting");
                    }

                    $index++;
                }
            }
        } catch (Exception $e) {
            DUP_PRO_Log::infoTraceException($e, "FAIL: purge package for storage " . $this->name . '[ID: ' . $this->id . '] type:' . $this->getStypeName());
            return false;
        }

        DUP_PRO_Log::infoTrace("Purge of old packages at " . $this->name . '[ID: ' . $this->id . "] storage completed.");

        return true;
    }
}
