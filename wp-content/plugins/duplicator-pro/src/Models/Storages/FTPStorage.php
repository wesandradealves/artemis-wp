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
use DUP_PRO_Package_Runner;
use DUP_PRO_Package_Upload_Info;
use DUP_PRO_STR;
use DUP_PRO_U;
use Duplicator\Core\Views\TplMng;
use Duplicator\Libs\Snap\SnapUtil;
use Exception;

class FTPStorage extends AbstractStorageEntity
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
                'server'          => '',
                'port'            => 21,
                'username'        => '',
                'password'        => '',
                'use_curl'        => false,
                'timeout_in_secs' => 15,
                'ssl'             => false,
                'passive_mode'    => false,
            ]
        );
        return $config;
    }

    /**
     * Class destructor
     */
    public function __destruct()
    {
        $this->closeConnection();
    }

    /**
     * Will be called, automatically, when Serialize
     *
     * @return array<string, mixed>
     */
    public function __serialize() // phpcs:ignore PHPCompatibility.FunctionNameRestrictions.NewMagicMethods.__serializeFound
    {
        $data = parent::__serialize();
        unset($data['client']);
        return $data;
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
                'server'          => $this->ftp_server,
                'port'            => $this->ftp_port,
                'username'        => $this->ftp_username,
                'password'        => $this->ftp_password,
                'use_curl'        => $this->ftp_use_curl,
                'storage_folder'  => '/' . ltrim($this->ftp_storage_folder, '/\\'),
                'max_packages'    => $this->ftp_max_files,
                'timeout_in_secs' => $this->ftp_timeout_in_secs,
                'ssl'             => $this->ftp_ssl,
                'passive_mode'    => $this->ftp_passive_mode,
            ];
            // reset old values
            $this->ftp_server          = '';
            $this->ftp_port            = 21;
            $this->ftp_username        = '';
            $this->ftp_password        = '';
            $this->ftp_use_curl        = false;
            $this->ftp_storage_folder  = '';
            $this->ftp_max_files       = 10;
            $this->ftp_timeout_in_secs = 15;
            $this->ftp_ssl             = false;
            $this->ftp_passive_mode    = false;
        }
    }

    /**
     * Return the storage type
     *
     * @return int
     */
    public static function getSType()
    {
        return 2;
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
        return __('FTP', 'duplicator-pro');
    }

    /**
     * Get priority, used to sort storages.
     * 100 is neutral value, 0 is the highest priority
     *
     * @return int
     */
    public static function getPriority()
    {
        return 80;
    }

    /**
     * Get storage location string
     *
     * @return string
     */
    public function getLocationString()
    {
        return "ftp://" . $this->config['server'] . ":" . $this->config['port'] . $this->getStorageFolder();
    }

    /**
     * Check if storage is supported
     *
     * @return bool
     */
    public static function isSupported()
    {
        return apply_filters('duplicator_pro_ftp_connect_exists', function_exists('ftp_connect'));
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
            esc_html__(
                'FTP Storage requires FTP module enabled. Please install the FTP module as described in the %s.',
                'duplicator-pro'
            ),
            '<a href="https://secure.php.net/manual/en/ftp.installation.php" target="_blank">https://secure.php.net/manual/en/ftp.installation.php</a>'
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
                    __('Transferring to FTP server %1$s in folder:<br/> <i>%2$s</i>', "duplicator-pro"),
                    $this->config['server'],
                    $this->getStorageFolder()
                );
            case 'pending':
                return sprintf(
                    __('Transfer to FTP server %1$s in folder %2$s is pending', "duplicator-pro"),
                    $this->config['server'],
                    $this->getStorageFolder()
                );
            case 'failed':
                return sprintf(
                    __('Failed to transfer to FTP server %1$s in folder %2$s', "duplicator-pro"),
                    $this->config['server'],
                    $this->getStorageFolder()
                );
            case 'cancelled':
                return sprintf(
                    __('Cancelled before could transfer to FTP server:<br/>%1$s in folder %2$s', "duplicator-pro"),
                    $this->config['server'],
                    $this->getStorageFolder()
                );
            case 'success':
                return sprintf(
                    __('Transferred package to FTP server:<br/>%1$s in folder %2$s', "duplicator-pro"),
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
            'admin_pages/storages/configs/ftp',
            [
                'storage'       => $this,
                'server'        => $this->config['server'],
                'port'          => $this->config['port'],
                'username'      => $this->config['username'],
                'password'      => $this->config['password'],
                'storageFolder' => $this->config['storage_folder'],
                'maxPackages'   => $this->config['max_packages'],
                'timeout'       => $this->config['timeout_in_secs'],
                'useCurl'       => $this->config['use_curl'],
                'isPassive'     => $this->config['passive_mode'],
                'useSSL'        => $this->config['ssl'],
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

        $this->config['max_packages'] = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'ftp_max_files', 10);
        $this->config['server']       = SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'ftp_server', '');
        $this->config['port']         = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'ftp_port', 10);
        $this->config['username']     = SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'ftp_username', '');
        $password                     = SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'ftp_password', '');
        $password2                    = SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'ftp_password2', '');
        if (strlen($password) > 0) {
            if ($password !== $password2) {
                $message = __('Passwords do not match', 'duplicator-pro');
                return false;
            }
            $this->config['password'] = $password;
        }
        $this->config['storage_folder']  = self::getSanitizedInputFolder('_ftp_storage_folder', 'add');
        $this->config['timeout_in_secs'] = max(10, SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'ftp_timeout_in_secs', 15));
        $this->config['use_curl']        = SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, '_ftp_use_curl', false);
        $this->config['ssl']             = SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, '_ftp_ssl', false);
        $this->config['passive_mode']    = SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, '_ftp_passive_mode', false);


        $message = sprintf(
            __('FTP Storage Updated - Server %1$s, Folder %2$s was created.', 'duplicator-pro'),
            $this->config['server'],
            $this->getStorageFolder()
        );
        return true;
    }

    /**
     * Get opened connection
     *
     * @return false|DUP_PRO_FTP_Chunker|DUP_PRO_FTPcURL
     */
    protected function getOpenedConnection()
    {
        if ($this->client !== null) {
            return $this->client;
        }

        try {
            if ($this->config['use_curl']) {
                $this->client = new DUP_PRO_FTPcURL(
                    $this->config['server'],
                    $this->config['port'],
                    $this->config['username'],
                    $this->config['password'],
                    $this->getStorageFolder(),
                    $this->config['timeout_in_secs'],
                    $this->config['ssl'],
                    $this->config['passive_mode']
                );
                $this->client->test_conn();
            } else {
                $this->client = new DUP_PRO_FTP_Chunker(
                    $this->config['server'],
                    $this->config['port'],
                    $this->config['username'],
                    $this->config['password'],
                    $this->config['timeout_in_secs'],
                    $this->config['ssl'],
                    $this->config['passive_mode']
                );
                if ($this->client->open() == false) {
                    throw new Exception('FTP Connection Failed');
                }
            }
        } catch (Exception $e) {
            DUP_PRO_Log::infoTrace('FTP Connection Failed: ' . $e->getMessage());
            $this->client = null;
            return false;
        }

        return $this->client;
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

        try {
            $use_curl        = $this->config['use_curl'];
            $storage_folder  = $this->getStorageFolder();
            $dest_handle     = null;
            $source_filepath = false;
            $dest_filepath   = false;

            if ($use_curl) {
                if (!self::isSupported()) {
                    $message = __(
                        'FTP storage with use cURL requires cURL extension to be enabled. That extension is not currently available on your system.',
                        'duplicator-pro'
                    );
                    $this->testLog->addMessage(
                        "CURL support check failed."
                    );
                    return false;
                }
            } else {
                if (!self::isSupported()) {
                    $message  = __(
                        'FTP storage without use cURL requires FTP module to be enabled. That module is not currently available on your system.',
                        'duplicator-pro'
                    );
                    $message .= '<br>' . sprintf(
                        __(
                            'Please install the FTP module as described in the %s. or tick the \'Use cURL\' checkbox.',
                            'duplicator-pro'
                        ),
                        '<a href="https://secure.php.net/manual/en/ftp.installation.php" target="_blank">' .
                        'https://secure.php.net/manual/en/ftp.installation.php</a>'
                    );
                    $this->testLog->addMessage(
                        "FTP support check failed."
                    );
                    return false;
                }
            }

            $this->testLog->addMessage(__('Creating temp file on temp local ...', 'duplicator-pro'));
            $source_filepath = tempnam(sys_get_temp_dir(), 'DUP');
            if ($source_filepath === false) {
                $message = __('Couldn\'t create the temp file for the FTP send test.', 'duplicator-pro');
                $this->testLog->addMessage(__('Create temp file failed.', 'duplicator-pro'));
                return false;
            }
            $rnd = rand();
            if (file_put_contents($source_filepath, $rnd) === false) {
                $message = __('Couldn\'t write to the temp file for the FTP send test.', 'duplicator-pro');
                $this->testLog->addMessage(__('Write temp file failed.', 'duplicator-pro'));
                return false;
            }
            $this->testLog->addMessage(sprintf(__('Created temp file "%1$s"', 'duplicator-pro'), $source_filepath));


            // -- Send the file --
            $basename = basename($source_filepath);

            $this->testLog->addMessage(__('Attempting to open FTP connection', 'duplicator-pro'));
            if ($this->getOpenedConnection() == false) {
                $message = __('FTP Connection Failed', 'duplicator-pro');
                $this->testLog->addMessage(__('FTP Connection Failed', 'duplicator-pro'));
                return false;
            }
            $this->testLog->addMessage(__('FTP connection is successfully established', 'duplicator-pro'));

            $ftp_client = $this->client;

            $this->testLog->addMessage(sprintf(__('Checking if remote storage directory exists: "%1$s"', 'duplicator-pro'), $storage_folder));
            DUP_PRO_Log::trace("Checking if remote storage directory exists: '$storage_folder'");

            if ($ftp_client->directory_exists($storage_folder)) {
                DUP_PRO_Log::trace("The remote storage directory already exists");
                $this->testLog->addMessage(__('The remote storage directory already exists', 'duplicator-pro'));
            } else {
                DUP_PRO_Log::trace("The remote storage directory does not exist yet");
                $this->testLog->addMessage(__('The remote storage directory does not exist yet', 'duplicator-pro'));
                DUP_PRO_Log::trace("Attempting to create the remote storage directory '$storage_folder'");
                $this->testLog->addMessage(sprintf(__('Attempting to create the remote storage directory "%1$s"', 'duplicator-pro'), $storage_folder));
                $ftp_directory_exists = $ftp_client->create_directory($storage_folder);
                if (!$ftp_directory_exists) {
                    if ($use_curl) {
                        DUP_PRO_Log::trace("The FTP connection is working fine but the directory can't be created.");
                        throw new Exception(
                            __(
                                "The FTP connection is working fine but the directory can't be created.",
                                'duplicator-pro'
                            )
                        );
                    } else {
                        DUP_PRO_Log::trace("The FTP connection is working fine but the directory can't be created. Check the \"cURL\" checkbox and retry.");
                        throw new Exception(
                            __(
                                "The FTP connection is working fine but the directory can't be created. Check the \"cURL\" checkbox and retry.",
                                'duplicator-pro'
                            )
                        );
                    }
                } else {
                    DUP_PRO_Log::trace("The remote storage directory is created successfully");
                    $this->testLog->addMessage(__('The remote storage directory is created successfully', 'duplicator-pro'));
                }
            }

            DUP_PRO_Log::trace("Attempting to upload temp file to remote directory");
            $this->testLog->addMessage(__('Attempting to upload temp file to remote directory', 'duplicator-pro'));
            if ($use_curl) {
                $ret_upload = $ftp_client->upload_file($source_filepath, basename($source_filepath));
            } else {
                $ret_upload = $ftp_client->upload_file($source_filepath, $storage_folder);
            }
            if (!$ret_upload) {
                DUP_PRO_Log::trace("Error uploading file.");
                throw new Exception(__('Error uploading file.', 'duplicator-pro'));
            }
            DUP_PRO_Log::trace("The temp file was uploaded successfully");
            $this->testLog->addMessage(__('The temp file was uploaded successfully', 'duplicator-pro'));

            // -- Download the file --
            DUP_PRO_Log::trace("Creating destination temp file for the FTP send test");
            $this->testLog->addMessage(__('Creating destination temp file for the FTP send test', 'duplicator-pro'));
            $dest_filepath = wp_tempnam('DUP', DUPLICATOR_PRO_SSDIR_PATH_TMP);

            DUP_PRO_Log::trace("Created temp file '$dest_filepath'");
            $this->testLog->addMessage(sprintf(__('Created temp file "%1$s"', 'duplicator-pro'), $dest_filepath));

            $remote_source_filepath = $use_curl ? $basename : "$storage_folder/$basename";
            $this->testLog->addMessage(sprintf(__('About to FTP download "%1$s" to "%2$s"', 'duplicator-pro'), $remote_source_filepath, $dest_filepath));
            DUP_PRO_Log::trace("About to FTP download $remote_source_filepath to $dest_filepath");

            if (!$ftp_client->download_file($remote_source_filepath, $dest_filepath, false)) {
                DUP_PRO_Log::trace("Error downloading file.");
                throw new Exception(__('Error downloading file.', 'duplicator-pro'));
            }
            $this->testLog->addMessage(__('The file is successfully downloaded', 'duplicator-pro'));
            DUP_PRO_Log::trace("The file is successfully downloaded");

            $this->testLog->addMessage(__('Attempting to delete the remote file', 'duplicator-pro'));
            DUP_PRO_Log::trace("Attempting to delete the remote file");
            $deleted_temp_file = true;

            if ($ftp_client->delete($remote_source_filepath) == false) {
                $this->testLog->addMessage(__('Couldn\'t delete the remote test file', 'duplicator-pro'));
                DUP_PRO_Log::traceError("Couldn't delete the remote test file.");
                $deleted_temp_file = false;
            } else {
                $this->testLog->addMessage(__('Successfully deleted the remote file', 'duplicator-pro'));
                DUP_PRO_Log::trace("Successfully deleted the remote file");
            }

            $this->testLog->addMessage(sprintf(__('Attempting to read downloaded file "%1$s"', 'duplicator-pro'), $dest_filepath));
            DUP_PRO_Log::trace("Attempting to read downloaded file '$dest_filepath'");
            $dest_handle = fopen($dest_filepath, 'r');
            if (!$dest_handle) {
                DUP_PRO_Log::trace("Could not open file for reading.");
                throw new Exception(__('Could not open file for reading.', 'duplicator-pro'));
            }
            $dest_string = fread($dest_handle, 100);
            fclose($dest_handle);
            $dest_handle = null;

            $this->testLog->addMessage(__('Looking for missmatch in files', 'duplicator-pro'));
            DUP_PRO_Log::trace("Looking for missmatch in files");
            /* The values better match or there was a problem */
            if ($rnd != (int) $dest_string) {
                $this->testLog->addMessage(sprintf(__('Mismatch in files: %1$s != %2$d', 'duplicator-pro'), $rnd, $dest_string));
                DUP_PRO_Log::traceError("Mismatch in files: $rnd != $dest_string");
                throw new Exception(__('There was a problem storing or retrieving the temporary file on this account.', 'duplicator-pro'));
            }

            $this->testLog->addMessage(__('Files match!', 'duplicator-pro'));
            DUP_PRO_Log::trace("Files match!");
            if ($deleted_temp_file) {
                if ($use_curl) {
                    DUP_PRO_Log::trace("Successfully stored and retrieved file.");
                    $json['success'] = true;
                    $json['message'] = __('Successfully stored and retrieved file.', 'duplicator-pro');
                } else {
                    $raw = ftp_raw($ftp_client->ftp_connection_id, 'REST');
                    if (is_array($raw) && !empty($raw) && isset($raw[0])) {
                        $code = intval($raw[0]);
                        if (502 === $code) {
                            DUP_PRO_Log::trace(
                                "FTP server doesn't support REST command. " .
                                "It will cause problem in PHP native function chunk upload. Please proceed with ticking \"Use Curl\" checkbox. Error: " .
                                $raw[0]
                            );
                            $message  = __('FTP server doesn\'t support REST command.', 'duplicator-pro') . ' ';
                            $message .= sprintf(
                                _x(
                                    'It will cause problem in PHP native function chunk upload. Please proceed with ticking "Use Curl" checkbox. Error: %1$s',
                                    '1: error message',
                                    'duplicator-pro'
                                ),
                                $raw[0]
                            );
                            throw new Exception($message);
                        } else {
                            DUP_PRO_Log::trace("Successfully stored and retrieved file.");
                            $json['success'] = true;
                            $json['message'] = __('Successfully stored and retrieved file.', 'duplicator-pro');
                        }
                    } else {
                        DUP_PRO_Log::trace("Successfully stored and retrieved file.");
                        $json['success'] = true;
                        $json['message'] = __('Successfully stored and retrieved file.', 'duplicator-pro');
                    }
                }
            } else {
                DUP_PRO_Log::trace("Successfully stored and retrieved file however couldn't delete the temp file on the server.");
                $json['success'] = true;
                $json['message'] = __("Successfully stored and retrieved file however couldn't delete the temp file on the server.", 'duplicator-pro');
            }
        } catch (Exception $e) {
            if ($dest_handle != null) {
                fclose($dest_handle);
            }

            $errorMessage = $e->getMessage();
            $this->testLog->addMessage($errorMessage);
            DUP_PRO_Log::trace($errorMessage);
            $message  = $errorMessage . ' ';
            $message .= sprintf(
                _x(
                    'For additional help see the online %1$sFTP troubleshooting steps%2$s.',
                    '1: open link, 2: close link',
                    'duplicator-pro'
                ),
                '<a href="' . DUPLICATOR_PRO_DUPLICATOR_DOCS_URL . 'how-do-i-fix-issues-with-sftp-ftp-storage-types" target="_blank">',
                '</a>'
            );
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

        if (file_exists($dest_filepath)) {
            $this->testLog->addMessage(sprintf(__('Attempting to delete local temp file "%1$s"', 'duplicator-pro'), $dest_filepath));
            if (unlink($dest_filepath) == false) {
                $this->testLog->addMessage(sprintf(__('Could not delete the temp file "%1$s"', 'duplicator-pro'), $dest_filepath));
                DUP_PRO_Log::trace("Could not delete the temp file $dest_filepath");
            } else {
                $this->testLog->addMessage(sprintf(__('Deleted temp file "%1$s"', 'duplicator-pro'), $dest_filepath));
                DUP_PRO_Log::trace("Deleted temp file $dest_filepath");
            }
        }

        $this->testLog->addMessage(__('Successfully stored and deleted file', 'duplicator-pro'));
        $message = __('Successfully stored and deleted file', 'duplicator-pro');
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

        $source_archive_filepath = $package->getLocalPackageFilePath(DUP_PRO_Package_File_Type::Archive);
        // $source_archive_filepath = DUP_PRO_U::$PLUGIN_DIRECTORY . '/lib/DropPHP/Poedit-1.6.4.2601-setup.bin';
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
            DUP_PRO_Log::infoTrace('FTP storage failed flag ($upload_info->failed) has been already set.');
            $package->update();
            return;
        }

        $ftp_client = $this->getOpenedConnection();
        $useCurl    = $this->config['use_curl'];

        $global            = DUP_PRO_Global_Entity::getInstance();
        $throttleDelayInUs = $global->getMicrosecLoadReduction();
        $folder            = $this->config['storage_folder'];
        $server            =   $this->config['server'];

        if ($useCurl || $ftp_client->open()) {
            if (
                $upload_info->archive_offset <= 0 && // For archive_offset > 0 it's obvious that ftp_storage_folder exists
                !$ftp_client->directory_exists($folder) &&
                !$ftp_client->create_directory($folder)
            ) {
                DUP_PRO_Log::infoTrace("FAIL: Could not create FTP dir $folder");
                DUP_PRO_Log::trace("Couldn't create $folder on $server");
            }

            try {
                if ($upload_info->copied_installer == false) {
                    DUP_PRO_Log::trace("ATTEMPT: FTP upload installer file $source_installer_filepath to $folder");
                    $dest_installer_filename = $package->Installer->getInstallerName();
                    $ret_upload_file         = $ftp_client->upload_file($source_installer_filepath, $folder, $dest_installer_filename);

                    if ($ret_upload_file == false) {
                        // This will just increase the failure count and reattempt with the next worker
                        throw new Exception("FAIL: installer upload to FTP. Error uploading $source_installer_filepath to $folder");
                    } else {
                        DUP_PRO_Log::infoTrace("SUCCESS: installer uploaded to FTP.");
                        $upload_info->copied_installer = true;
                        $upload_info->progress         = 5;
                    }

                    // The package update will automatically capture the upload_info since its part of the package
                    $package->update();
                } else {
                    DUP_PRO_Log::trace("Already copied installer on previous execution of FTP $this->name, so skipping");
                }

                if ($upload_info->copied_archive == false) {
                    $global = DUP_PRO_Global_Entity::getInstance();
                    DUP_PRO_Log::trace("Calling upload_chunk with timeout for archive.");
                    $ftp_upload_info = $ftp_client->upload_chunk(
                        $source_archive_filepath,
                        $useCurl ? '' : $folder,
                        $global->php_max_worker_time_in_sec,
                        $upload_info->archive_offset,
                        $throttleDelayInUs
                    );
                    DUP_PRO_Log::trace("Call to upload_chunk for archive is completed.");
                    if ($ftp_upload_info->error_details == null) {
                        // Since there was a successful chunk reset the failure count
                        $upload_info->failure_count  = 0;
                        $upload_info->archive_offset = $ftp_upload_info->next_offset;
                        $file_size                   = filesize($source_archive_filepath);
                        //  $upload_info->progress = max(5, 100 * (bcdiv($upload_info->archive_offset, $file_size, 2)));
                        $upload_info->progress = max(5, DUP_PRO_U::percentage($upload_info->archive_offset, $file_size, 0));
                        DUP_PRO_Log::infoTrace(
                            "Archive upload offset: $upload_info->archive_offset [File size: $file_size] [Upload progress: $upload_info->progress%]"
                        );
                        if ($ftp_upload_info->success) {
                            DUP_PRO_Log::infoTrace("SUCCESS: archive uploaded to FTP $server.");
                            $upload_info->copied_archive = true;
                            $this->purgeOldPackages();
                            $package->update();
                        } else {
                            // Need to quit all together b/c ftp connection stays open
                            DUP_PRO_Log::trace("Exiting process since ftp upload_chunk was partial");
                            // A real hack since the ftp_close doesn't work on the async put
                            $package->update();
                            // Kick the worker again
                            // DUP_PRO_Package_Runner::kick_off_worker();
                            DUP_PRO_Package_Runner::$delayed_exit_and_kickoff = true;
                            //exit();
                            return;
                        }
                    } else {
                        DUP_PRO_Log::infoTrace(
                            "FAIL: archive for package $package->Name upload to FTP $server. Getting Error from FTP: $ftp_upload_info->error_details"
                        );
                        if ($ftp_upload_info->fatal_error) {
                            $installer_filename     = basename($source_installer_filepath);
                            $installer_ftp_filepath = "{$folder}/$installer_filename";
                            DUP_PRO_Log::trace("Failed archive transfer so deleting $installer_ftp_filepath");
                            $ftp_client->delete($installer_ftp_filepath);
                            $upload_info->failed = true;
                        } else {
                            $upload_info->archive_offset = $ftp_upload_info->next_offset;
                            $upload_info->increase_failure_count();
                        }
                    }
                } else {
                    DUP_PRO_Log::trace("Already copied archive on previous execution of FTP $this->name so skipping");
                }
            } catch (Exception $e) {
                $upload_info->increase_failure_count();
                DUP_PRO_Log::traceError("Problems copying package $package->Name to $folder. " . $e->getMessage());
            }

            $this->closeConnection();
        } else {
            $upload_info->increase_failure_count();
            DUP_PRO_Log::traceError("Couldn't open ftp connection " . $ftp_client->get_info());
        }

        if ($upload_info->failed) {
            DUP_PRO_Log::infoTrace('FTP storage failed flag ($upload_info->failed) has been already set.');
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
            $ftp_client = $this->getOpenedConnection();
            $global     = DUP_PRO_Global_Entity::getInstance();
            $folder     = $this->config['storage_folder'];
            $userCurl   = $this->config['use_curl'];

            $file_list = $ftp_client->get_filelist($folder);
            if ($file_list == false) {
                DUP_PRO_Log::traceError("FAIL: purging FTP packages. Error retrieving file list for " . $ftp_client->get_info());
            } else {
                $valid_file_list = array();
                foreach ($file_list as $file_name) {
                    DUP_PRO_Log::trace("considering filename {$file_name}");
                    if (DUP_PRO_Package::get_timestamp_from_filename($file_name) !== false) {
                        $valid_file_list[] = $file_name;
                    }
                }

                DUP_PRO_Log::traceObject('Valid file list', $valid_file_list);
                try {
                    // Sort list by the timestamp associated with it
                    usort($valid_file_list, array(DUP_PRO_Package::class, 'compare_package_filenames_by_date'));
                } catch (Exception $e) {
                    DUP_PRO_Log::traceError("FAIL: purging FTP packages. Sort error when attempting to purge old FTP files.");
                    return false;
                }

                $php_files         = array();
                $archive_filepaths = array();
                foreach ($valid_file_list as $file_name) {
                    if ($userCurl) {
                        $file_path = $file_name;
                    } else {
                        $file_path = rtrim($folder, '/') . '/' . $file_name;
                    }
                    // just look for the archives and installer files
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
                        $ftp_client->delete($installer_filepath);
                        $ftp_client->delete($archive_filepath);
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
