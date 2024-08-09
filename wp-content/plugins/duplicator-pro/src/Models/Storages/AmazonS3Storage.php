<?php

/**
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Models\Storages;

use DUP_PRO_Global_Entity;
use DUP_PRO_Handler;
use DUP_PRO_Log;
use DUP_PRO_Package;
use DUP_PRO_Package_File_Type;
use DUP_PRO_Package_Upload_Info;
use DUP_PRO_S3_Client_UploadInfo;
use DUP_PRO_S3_U;
use DUP_PRO_STR;
use DUP_PRO_U;
use Duplicator\Core\Views\TplMng;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Models\SystemGlobalEntity;
use Duplicator\Views\AdminNotices;
use DuplicatorPro\Aws\S3\S3Client;
use Exception;

class AmazonS3Storage extends AbstractStorageEntity
{
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
                'access_key'       => '',
                'bucket'           => '',
                'region'           => '',
                'endpoint'         =>   '',
                'secret_key'       => '',
                'storage_class'    => 'STANDARD',
                'ACL_full_control' => true,
            ]
        );
        return $config;
    }

    /**
     * Return the field label
     *
     * @param string $field Field name
     *
     * @return string
     */
    public static function getFieldLabel($field)
    {
        switch ($field) {
            case 'accessKey':
                return __('Access Key', 'duplicator-pro');
            case 'secretKey':
                return __('Secret Key', 'duplicator-pro');
            case 'region':
                return __('Region', 'duplicator-pro');
            case 'endpoint':
                return __('Endpoint', 'duplicator-pro');
            case 'bucket':
                return __('Bucket', 'duplicator-pro');
            case 'aclFullControl':
                return __('Additional Settings', 'duplicator-pro');
            default:
                throw new Exception("Unknown field: $field");
        }
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
                'storage_folder'   => $this->s3_storage_folder,
                'max_packages'     => $this->s3_max_files,
                'access_key'       => $this->s3_access_key,
                'bucket'           => $this->s3_bucket,
                'region'           => $this->s3_region,
                'endpoint'         => $this->s3_endpoint,
                'secret_key'       => $this->s3_secret_key,
                'storage_class'    => $this->s3_storage_class,
                'ACL_full_control' => $this->s3_ACL_full_control,
            ];
            // reset old values
            $this->s3_storage_folder   = '';
            $this->s3_max_files        = 10;
            $this->s3_access_key       = '';
            $this->s3_bucket           = '';
            $this->s3_provider         = 'amazon';
            $this->s3_region           = '';
            $this->s3_endpoint         = '';
            $this->s3_secret_key       = '';
            $this->s3_storage_class    = 'STANDARD';
            $this->s3_ACL_full_control = true;
        }
    }

    /**
     * Return the storage type
     *
     * @return int
     */
    public static function getSType()
    {
        return 4;
    }

    /**
     * Returns the storage type icon.
     *
     * @return string Returns the icon
     */
    public static function getStypeIcon()
    {
        return '<img src="' . esc_url(static::getIconUrl()) . '" class="dup-s3-icon" alt="' . esc_attr(static::getStypeName()) . '" />';
    }

    /**
     * Returns the storage type icon url.
     *
     * @return string The icon url
     */
    protected static function getIconUrl()
    {
        return DUPLICATOR_PRO_IMG_URL . '/aws.svg';
    }


    /**
     * Returns the storage type name.
     *
     * @return string
     */
    public static function getStypeName()
    {
        return __('Amazon S3', 'duplicator-pro');
    }

    /**
     * Get priority, used to sort storages.
     * 100 is neutral value, 0 is the highest priority
     *
     * @return int
     */
    public static function getPriority()
    {
        return 150;
    }

    /**
     * Get storage location string
     *
     * @return string
     */
    public function getLocationString()
    {
        $params = [
            'region' => $this->config['region'],
            'bucket' => $this->config['bucket'],
            'prefix' => $this->getStorageFolder(),
        ];

        return 'https://console.aws.amazon.com/s3/home' . '?' . http_build_query($params);
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
        return '<a href="' . esc_url($this->getLocationString()) . '" target="_blank" >' . esc_html($this->getLocationLabel()) . '</a>';
    }

    /**
     * Returns the storage location label.
     *
     * @return string The storage location label
     */
    protected function getLocationLabel()
    {
        return 's3://' . $this->config['bucket'] . $this->getStorageFolder();
    }

    /**
     * Check if storage is supported
     *
     * @return bool
     */
    public static function isSupported()
    {
        return SnapUtil::isCurlEnabled(true, true);
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

        if (!SnapUtil::isCurlEnabled()) {
            $result = sprintf(
                __(
                    "The Storage %s requires the PHP cURL extension and related functions to be enabled.",
                    'duplicator-pro'
                ),
                static::getStypeName()
            );
        } elseif (!SnapUtil::isCurlEnabled(true, true)) {
            $result = sprintf(
                __(
                    "The Storage %s requires 'curl_multi_' type functions to be enabled. One or more are disabled on your server.",
                    'duplicator-pro'
                ),
                static::getStypeName()
            );
        } else {
            $result = sprintf(
                __(
                    'The Storage %s is not supported on this server.',
                    'duplicator-pro'
                ),
                static::getStypeName()
            );
        }

        return esc_html($result);
    }

    /**
     * Check if storage is valid
     *
     * @return bool Return true if storage is valid and ready to use, false otherwise
     */
    public function isValid()
    {
        if (strlen($this->config['storage_folder']) == 0) {
            return false;
        }

        if (strlen($this->config['storage_class']) == 0) {
            return false;
        }

        if (strlen($this->config['bucket']) == 0) {
            return false;
        }

        if (strlen($this->config['region']) == 0) {
            return false;
        }

        if (strlen($this->config['access_key']) == 0) {
            return false;
        }

        if (strlen($this->config['secret_key']) == 0) {
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
                    __('Transferring to %1$s folder:<br/> <i>%2$s</i>', "duplicator-pro"),
                    $this->getStypeName(),
                    $this->getStorageFolder()
                );
            case 'pending':
                return sprintf(
                    __('Transfer to %1$s folder %2$s is pending', "duplicator-pro"),
                    $this->getStypeName(),
                    $this->getStorageFolder()
                );
            case 'failed':
                return sprintf(
                    __('Failed to transfer to %1$s folder %2$s', "duplicator-pro"),
                    $this->getStypeName(),
                    $this->getStorageFolder()
                );
            case 'cancelled':
                return sprintf(
                    __('Cancelled before could transfer to %1$s folder %2$s', "duplicator-pro"),
                    $this->getStypeName(),
                    $this->getStorageFolder()
                );
            case 'success':
                return sprintf(
                    __('Transferred package to %1$s folder %2$s', "duplicator-pro"),
                    $this->getStypeName(),
                    $this->getStorageFolder()
                );
            default:
                throw new Exception('Invalid key');
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
            'admin_pages/storages/configs/amazon_s3',
            [
                'storage'        => $this,
                'maxPackages'    => $this->config['max_packages'],
                'storageFolder'  => $this->config['storage_folder'],
                'accessKey'      => $this->config['access_key'],
                'bucket'         => $this->config['bucket'],
                'region'         => $this->config['region'],
                'endpoint'       => $this->config['endpoint'],
                'secretKey'      => $this->config['secret_key'],
                'storageClass'   => $this->config['storage_class'],
                'aclFullControl' => $this->config['ACL_full_control'],
                'regionOptions'  => self::regionOptions(),
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

        $this->config['max_packages']   = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 's3_max_files', 10);
        $this->config['storage_folder'] = self::getSanitizedInputFolder('_s3_storage_folder');

        $this->config['access_key'] = SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 's3_access_key');
        $secretKey                  = SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 's3_secret_key');
        if (strlen($secretKey) > 0) {
            $this->config['secret_key'] = $secretKey;
        }
        $this->config['region']        = SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 's3_region');
        $this->config['storage_class'] = SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 's3_storage_class');
        $this->config['bucket']        = SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 's3_bucket');


        $message = sprintf(
            __('Storage Updated.', 'duplicator-pro'),
            $this->config['server'],
            $this->getStorageFolder()
        );
        return true;
    }

    /**
     * Get full s3 client
     *
     * @return S3Client
     */
    protected function getClient()
    {
        return DUP_PRO_S3_U::get_s3_client(
            $this->config['region'],
            $this->config['access_key'],
            $this->config['secret_key'],
            $this->config['endpoint']
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

        $result = false;

        $bucket           = $this->config['bucket'];
        $storage_class    = $this->config['storage_class'];
        $ACL_full_control = $this->config['ACL_full_control'];

        $source_handle   = null;
        $source_filepath = '';
        try {
            $storage_folder = $this->getStorageFolder();
            $this->testLog->addMessage(__('Attempting to create a temp file', 'duplicator-pro'));
            $source_filepath = tempnam(sys_get_temp_dir(), 'DUP');

            if ($source_filepath === false) {
                throw new Exception(__("Couldn't create the temp file for the S3 send test", 'duplicator-pro'));
            }
            $this->testLog->addMessage(sprintf(__('Created a temp file "%1$s"', 'duplicator-pro'), $source_filepath));
            DUP_PRO_Log::trace("Created a temp file $source_filepath");

            $this->testLog->addMessage(__('Attempting to write to the temp file', 'duplicator-pro'));
            $source_handle = fopen($source_filepath, 'w');
            if (!$source_handle) {
                throw new Exception(__("Couldn't open temp file for writing.", 'duplicator-pro'));
            }
            $rnd = rand();
            fwrite($source_handle, "$rnd");

            $this->testLog->addMessage(sprintf(__('Wrote %1$s to "%2$s"', 'duplicator-pro'), $rnd, $source_filepath));
            DUP_PRO_Log::trace("Wrote $rnd to $source_filepath");
            fclose($source_handle);
            $source_handle = null;

            // -- Send the file --
            $filename = basename($source_filepath);

            $this->testLog->addMessage(__('Attempting to get S3 client object', 'duplicator-pro'));
            $s3_client = $this->getClient() ;
            if (!$s3_client) {
                throw new Exception(__("Couldn't get the S3 client for the S3 send test", 'duplicator-pro'));
            }
            $this->testLog->addMessage(__('Got S3 client object', 'duplicator-pro'));
            $this->testLog->addMessage(
                sprintf(
                    __('About to send "%1$s" to "%2$s" in bucket %3$s on S3', 'duplicator-pro'),
                    $source_filepath,
                    $storage_folder,
                    $bucket
                )
            );
            DUP_PRO_Log::trace("About to send $source_filepath to $storage_folder in bucket $bucket on S3");

            if (DUP_PRO_S3_U::upload_file($s3_client, $bucket, $source_filepath, $storage_folder, $storage_class, $ACL_full_control, '', $this->testLog)) {
                $this->testLog->addMessage(__('Successfully stored test file to remote storage', 'duplicator-pro'));
                $remote_filepath = "$storage_folder/$filename";
                $this->testLog->addMessage(sprintf(__('Attempting to delete temporary file on S3: "%1$s"', 'duplicator-pro'), $remote_filepath));
                if (DUP_PRO_S3_U::delete_file($s3_client, $bucket, $remote_filepath, $this->testLog) == false) {
                    $this->testLog->addMessage(__('Error deleting temporary file on S3', 'duplicator-pro'));
                    DUP_PRO_Log::trace("Error deleting temporary file generated on S3 File test - {$remote_filepath}");
                    $message = __(
                        'Test failed. Double check configuration and read status messages above, as they could help you identify the problem.',
                        'duplicator-pro'
                    );
                } else {
                    $this->testLog->addMessage(__('Successfully deleted temporary file on S3', 'duplicator-pro'));
                    $result  = true;
                    $message = __('Successfully stored and retrieved test file', 'duplicator-pro');
                }
            } else {
                $this->testLog->addMessage(__('Upload of test file failed. Check configuration.', 'duplicator-pro'));
                $message = __(
                    'Test failed. Double check configuration and read status messages above, as they could help you identify the problem.',
                    'duplicator-pro'
                );
            }
        } catch (Exception $e) {
            if ($source_handle != null) {
                fclose($source_handle);
            }

            $errorMessage = esc_html($e->getMessage());
            $this->testLog->addMessage($errorMessage);
            DUP_PRO_Log::trace($errorMessage);
            $message = $errorMessage;
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
            DUP_PRO_Log::infoTrace('S3 storage failed flag ($upload_info->failed) has been already set.');
            $package->update();
            return;
        }

        $s3_client         = $this->getClient();
        $throttleDelayInUs = DUP_PRO_Global_Entity::getInstance()->getMicrosecLoadReduction();
        try {
            $tried_copying_installer = !$upload_info->copied_installer;
            if ($upload_info->copied_installer == false) {
                DUP_PRO_Log::trace("ATTEMPT: S3 upload installer file $source_installer_filepath to " . $this->getStorageFolder());
                $dest_installer_filename = $package->Installer->getInstallerName();

                // Temporarily switch mode to try to catch an error
                DUP_PRO_Handler::setMode(DUP_PRO_Handler::MODE_VAR);
                if (
                    DUP_PRO_S3_U::upload_file(
                        $s3_client,
                        $this->config['bucket'],
                        $source_installer_filepath,
                        $this->getStorageFolder(),
                        $this->config['storage_class'],
                        $this->config['ACL_full_control'],
                        $dest_installer_filename
                    )
                ) {
                    DUP_PRO_Log::infoTrace("SUCCESS: installer upload to S3 " . $this->getStorageFolder());
                    $upload_info->copied_installer = true;
                    $upload_info->progress         = 5;
                } else {
                    $upload_info->failed = true;
                    DUP_PRO_Log::infoTrace("FAIL: installer upload to S3.");
                }

                // The following call will check for DUP_PRO_Handler errors caught with MODE_VAR,
                // throw exception in case of some of them, but also switch back MODE_VAR to MODE_LOG.
                $errorsOutput = DUP_PRO_Handler::getVarLogClean();
                DUP_PRO_Handler::setMode(DUP_PRO_Handler::MODE_LOG);
                $this->checkS3ErrorHandler($errorsOutput);

                // The package update will automatically capture the upload_info since its part of the package
                $package->update();
                return;
            } else {
                DUP_PRO_Log::trace("Already copied installer on previous execution of S3 $this->name so skipping");
            }

            if ($upload_info->copied_archive == false && $tried_copying_installer == false) {
                $global = DUP_PRO_Global_Entity::getInstance();
                // Data
                $s3_upload_info                 = new DUP_PRO_S3_Client_UploadInfo();
                $s3_upload_info->bucket         = $this->config['bucket'];
                $s3_upload_info->upload_id      = $upload_info->upload_id;
                $s3_upload_info->dest_directory = $this->getStorageFolder();
                $s3_upload_info->src_filepath   = $source_archive_filepath;
                $s3_upload_info->next_offset    = $upload_info->archive_offset;
                $s3_upload_info->storage_class  = $this->config['storage_class'];
                // Storing array of [part] and [parts] in an array within data
                if ($upload_info->data == '') {
                    $upload_info->data = 1;
                    // part number
                    $upload_info->data2 = array();
                    // parts array
                }

                $s3_upload_info->part_number      = $upload_info->data;
                $s3_upload_info->parts            = $upload_info->data2;
                $s3_upload_info->upload_part_size = $global->s3_upload_part_size_in_kb * 1024;

                // Temporarily switch mode to try to catch an error
                DUP_PRO_Handler::setMode(DUP_PRO_Handler::MODE_VAR);
                $s3_upload_info = DUP_PRO_S3_U::upload_file_chunk($s3_client, $s3_upload_info, $global->php_max_worker_time_in_sec, $throttleDelayInUs);
                // The following call will check for DUP_PRO_Handler errors caught with MODE_VAR,
                // throw exception in case of some of them, but also switch back MODE_VAR to MODE_LOG.
                $errorsOutput = DUP_PRO_Handler::getVarLogClean();
                DUP_PRO_Handler::setMode(DUP_PRO_Handler::MODE_LOG);
                $this->checkS3ErrorHandler($errorsOutput);

                if ($s3_upload_info->error_details == null) {
                    // Clear the failure count - we are just looking for consecutive errors
                    $upload_info->failure_count  = 0;
                    $upload_info->archive_offset = $s3_upload_info->next_offset;
                    $upload_info->upload_id      = $s3_upload_info->upload_id;
                    $upload_info->data           = $s3_upload_info->part_number;
                    $upload_info->data2          = $s3_upload_info->parts;
                    $file_size                   = filesize($source_archive_filepath);
                    $upload_info->progress       = max(5, DUP_PRO_U::percentage($upload_info->archive_offset, $file_size, 0));
                    DUP_PRO_Log::infoTrace(
                        "Archive upload offset: $upload_info->archive_offset [File size: $file_size] [Upload progress: $upload_info->progress%]"
                    );
                    if ($s3_upload_info->is_complete) {
                        DUP_PRO_Log::infoTrace("SUCCESS: archive upload to S3.");
                        $upload_info->copied_archive = true;
                        $this->purgeOldPackages();
                    }
                } else {
                    DUP_PRO_Log::infoTrace("FAIL: archive upload to S3. Get error from S3 API: " . $s3_upload_info->error_details);
                    // Could have partially uploaded so retain that offset.
                    $upload_info->archive_offset = $s3_upload_info->next_offset;
                    $upload_info->increase_failure_count();
                }
            } else {
                if ($upload_info->copied_archive) {
                    DUP_PRO_Log::trace("Already copied archive on previous execution of S3 $this->name so skipping");
                }
            }
        } catch (Exception $e) {
            DUP_PRO_Log::trace("Exception caught copying package $package->Name to S3 " . $this->getStorageFolder() . ": " . $e->getMessage());
            $upload_info->increase_failure_count();
        }

        if ($upload_info->failed) {
            DUP_PRO_Log::infoTrace('S3 storage failed flag ($upload_info->failed) has been already set.');
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

        DUP_PRO_Log::infoTrace("Attempting to purge old packages at " . $this->name . '[ID: ' . $this->id . '] type:' . $this->getStypeName());

        try {
            $s3_client = $this->getClient();
            $global    = DUP_PRO_Global_Entity::getInstance();

            // listObjects works fine for root folder only if Prefix is set to an empty string.
            $prefix       = (trim($this->getStorageFolder(), '/') == "") ? "" : trim($this->getStorageFolder(), '/') . '/';
            $return_value = $s3_client->listObjects(array(
                'Bucket'    => $this->config['bucket'],
                'Delimiter' => '/',
                'Prefix'    => $prefix,
            ));

            if (!isset($return_value['Contents']) || !is_array($return_value['Contents'])) {
                update_option(AdminNotices::OPTION_KEY_S3_CONTENTS_FETCH_FAIL_NOTICE, true);
                return false;
            }

            $s3_objects = $return_value['Contents'];
            usort($s3_objects, array(__CLASS__, 'compareFileDates'));

            $php_files         = array();
            $archive_filenames = array();
            foreach ($s3_objects as $s3_object) {
                $filename = basename($s3_object['Key']);
                if (DUP_PRO_STR::endsWith($filename, "_{$global->installer_base_name}")) {
                    array_push($php_files, $s3_object['Key']);
                } elseif (DUP_PRO_STR::endsWith($filename, '_archive.zip') || DUP_PRO_STR::endsWith($filename, '_archive.daf')) {
                    array_push($archive_filenames, $s3_object['Key']);
                }
            }

            DUP_PRO_Log::traceObject("php files", $php_files);
            DUP_PRO_Log::traceObject("archives", $archive_filenames);
            if ($this->config['max_packages'] > 0) {
                $num_php_files     = count($php_files);
                $num_php_to_delete = $num_php_files - $this->config['max_packages'];
                $index             = 0;
                DUP_PRO_Log::trace("Num php files to delete=$num_php_to_delete");
                while ($index < $num_php_to_delete) {
                    DUP_PRO_Log::trace("Deleting {$php_files[$index]}");
                    $s3_client->deleteObject(array(
                        'Bucket' => $this->config['bucket'],
                        'Key'    => $php_files[$index],
                    ));
                    DUP_PRO_Log::trace("Deleted {$php_files[$index]}");
                    $index++;
                }

                $index                  = 0;
                $num_archives           = count($archive_filenames);
                $num_archives_to_delete = $num_archives - $this->config['max_packages'];
                DUP_PRO_Log::trace("Num archives to delete=$num_archives_to_delete");
                while ($index < $num_archives_to_delete) {
                    DUP_PRO_Log::trace("Deleting {$archive_filenames[$index]}");
                    $s3_client->deleteObject(array(
                        'Bucket' => $this->config['bucket'],
                        'Key'    => $archive_filenames[$index],
                    ));
                    DUP_PRO_Log::trace("Deleting {$archive_filenames[$index]}");
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

    /**
     * This function will check for DUP_PRO_Handler errors caught with MODE_VAR,
     * throw exception in case of some of them.
     *
     * @param string $errorsOutput These are errors from DUP_PRO_Handler's internal log string
     *
     * @return void
     */
    private function checkS3ErrorHandler($errorsOutput)
    {
        if (strlen($errorsOutput) == 0) {
            return;
        }

        if (preg_match('/fwrite.+write.+failed.+errno\s*=\s*28/i', $errorsOutput) === 1) {
            $errorText = "***ERROR*** " . sys_get_temp_dir() . " folder is probably on a full partition. ";
            $fixText   = "You should contact your server/hosting administrator and ask " .
                "why is the partition that contains folder " . sys_get_temp_dir() . " " .
                "full? Can they free up more space? ";
            DUP_PRO_Log::infoTrace($errorText . $fixText);

            $systemGlobal = SystemGlobalEntity::getInstance();
            $systemGlobal->addTextFix($errorText, $fixText);
            $systemGlobal->save();

            throw new Exception(
                $errorText . $fixText .
                "\nList of errors caught in error handler log:\n" .
                $errorsOutput .
                "End of list of errors caught in error handler log"
            );
        }

        DUP_PRO_Log::trace(
            "\nList of errors caught in error handler log:\n" .
            $errorsOutput .
            "End of list of errors caught in error handler log"
        );
    }


    /**
     * S3 compare file dates
     *
     * @param array<string,mixed> $array_a File info
     * @param array<string,mixed> $array_b File info
     *
     * @return int
     */
    protected static function compareFileDates($array_a, $array_b)
    {
        $a_ts = strtotime($array_a['LastModified']);
        $b_ts = strtotime($array_b['LastModified']);
        if ($a_ts == $b_ts) {
            return 0;
        }

        return ($a_ts < $b_ts) ? -1 : 1;
    }

    /**
     * Returns value => label pairs for region drop-down options for S3 Amazon Direct storage type
     *
     * @return string[]
     */
    protected static function regionOptions()
    {
        return array(
            "us-east-1"      => __("US East (N. Virginia)", 'duplicator-pro'),
            "us-east-2"      => __("US East (Ohio)", 'duplicator-pro'),
            "us-west-1"      => __("US West (N. California)", 'duplicator-pro'),
            "us-west-2"      => __("US West (Oregon)", 'duplicator-pro'),
            "af-south-1"     => __("Africa (Cape Town)", 'duplicator-pro'),
            "ap-east-1"      => __("Asia Pacific (Hong Kong)", 'duplicator-pro'),
            "ap-south-1"     => __("Asia Pacific (Mumbai)", 'duplicator-pro'),
            "ap-northeast-1" => __("Asia Pacific (Tokyo)", 'duplicator-pro'),
            "ap-northeast-2" => __("Asia Pacific (Seoul)", 'duplicator-pro'),
            "ap-northeast-3" => __("Asia Pacific (Osaka-Local)", 'duplicator-pro'),
            "ap-southeast-1" => __("Asia Pacific (Singapore)", 'duplicator-pro'),
            "ap-southeast-2" => __("Asia Pacific (Sydney)", 'duplicator-pro'),
            "ap-southeast-3" => __("Asia Pacific (Jakarta)", 'duplicator-pro'),
            "ca-central-1"   => __("Canada (Central)", 'duplicator-pro'),
            "cn-north-1"     => __("China (Beijing)", 'duplicator-pro'),
            "cn-northwest-1" => __("China (Ningxia)", 'duplicator-pro'),
            "eu-central-1"   => __("EU (Frankfurt)", 'duplicator-pro'),
            "eu-west-1"      => __("EU (Ireland)", 'duplicator-pro'),
            "eu-west-2"      => __("EU (London)", 'duplicator-pro'),
            "eu-west-3"      => __("EU (Paris)", 'duplicator-pro'),
            "eu-south-1"     => __("Europe (Milan)", 'duplicator-pro'),
            "eu-north-1"     => __("Europe (Stockholm)", 'duplicator-pro'),
            "me-south-1"     => __("Middle East (Bahrain)", 'duplicator-pro'),
            "sa-east-1"      => __("South America (Sao Paulo)", 'duplicator-pro'),
        );
    }

    /**
     * Purge old multipart uploads
     *
     * @return void
     */
    public function purgeMultipartUpload()
    {
        $s3_client      = $this->getClient();
        $active_uploads = DUP_PRO_S3_U::get_active_multipart_uploads(
            $s3_client,
            $this->config['bucket'],
            $this->getStorageFolder()
        );

        if (!is_array($active_uploads)) {
            return;
        }

        foreach ($active_uploads as $active_upload) {
            // Needs to be at least 48 hours old - don't want to much around with timezone so this is safe
            $time_delta = time() - $active_upload->timestamp;

            if ($time_delta <= (48 * 3600)) {
                continue;
            }

            DUP_PRO_Log::trace("Aborting upload because timestamp = {$active_upload->timestamp} while time is " . time());
            DUP_PRO_S3_U::abort_multipart_upload(
                $s3_client,
                $this->config['bucket'],
                $active_upload->key,
                $active_upload->upload_id
            );
        }
    }
}
