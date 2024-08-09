<?php

/**
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Models\Storages;

use DUP_PRO_GDrive_U;
use DUP_PRO_Global_Entity;
use DUP_PRO_Log;
use DUP_PRO_Package;
use DUP_PRO_Package_File_Type;
use DUP_PRO_Package_Upload_Info;
use DUP_PRO_STR;
use DUP_PRO_U;
use Duplicator\Core\Views\TplMng;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator_Pro_Google_Client;
use Duplicator_Pro_Google_Service_Drive;
use Duplicator_Pro_Google_Service_Drive_DriveFile;
use Exception;

class GDriveStorage extends AbstractStorageEntity implements StorageAuthInterface
{
    // These numbers represent clients created in Google Cloud Console
    const GDRIVE_CLIENT_NATIVE  = 1; // Native client 1
    const GDRIVE_CLIENT_WEB0722 = 2; // Web client 07/2022
    const GDRIVE_CLIENT_LATEST  = 2; // Latest out of these above

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
                'token_json'    => '',
                'refresh_token' => '',
                'client_number' => -1,
                'authorized'    => false,
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
                'token_json'     => $this->gdrive_access_token_set_json,
                'refresh_token'  => $this->gdrive_refresh_token,
                'storage_folder' => ltrim($this->gdrive_storage_folder, '/\\'),
                'client_number'  => $this->gdrive_client_number,
                'max_packages'   => $this->gdrive_max_files,
                'authorized'     => ($this->gdrive_authorization_state == 1),
            ];
            // reset old values
            $this->gdrive_access_token_set_json = '';
            $this->gdrive_refresh_token         = '';
            $this->gdrive_storage_folder        = '';
            $this->gdrive_client_number         = -1;
            $this->gdrive_max_files             = 10;
            $this->gdrive_authorization_state   = 0;
        }
    }

    /**
     * Return the storage type
     *
     * @return int
     */
    public static function getSType()
    {
        return 3;
    }

    /**
     * Returns the FontAwesome storage type icon.
     *
     * @return string Returns the font-awesome icon
     */
    public static function getStypeIcon()
    {
        return '<i class="fab fa-google-drive fa-fw"></i>';
    }

    /**
     * Returns the storage type name.
     *
     * @return string
     */
    public static function getStypeName()
    {
        return __('Google Drive', 'duplicator-pro');
    }

    /**
     * Get storage location string
     *
     * @return string
     */
    public function getLocationString()
    {
        return 'google://' . $this->getStorageFolder();
    }

    /**
     * Check if storage is supported
     *
     * @return bool
     */
    public static function isSupported()
    {
        return (SnapUtil::isCurlEnabled() || SnapUtil::isUrlFopenEnabled());
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

        if (!SnapUtil::isCurlEnabled() && !SnapUtil::isUrlFopenEnabled()) {
            return esc_html__(
                'Google Drive requires either the PHP CURL extension enabled or the allow_url_fopen runtime configuration to be enabled.',
                'duplicator-pro'
            );
        } elseif (!SnapUtil::isCurlEnabled()) {
            return esc_html__('Google Drive requires the PHP CURL extension enabled.', 'duplicator-pro');
        } else {
            return esc_html__('Google Drive requires the allow_url_fopen runtime configuration to be enabled.', 'duplicator-pro');
        }
    }

    /**
     * Check if storage is valid
     *
     * @return bool Return true if storage is valid and ready to use, false otherwise
     */
    public function isValid()
    {
        return $this->isAuthorized();
    }

    /**
     * Is autorized
     *
     * @return bool
     */
    public function isAuthorized()
    {
        return $this->config['authorized'];
    }

    /**
     * Returns an html anchor tag of location
     *
     * @return string Returns an html anchor tag with the storage location as a hyperlink.
     */
    public function getHtmlLocationLink()
    {
        return '<span>' . esc_html($this->getLocationString()) . '</span>';
    }

    /**
     * Authorized from HTTP request
     *
     * @param string $message Message
     *
     * @return bool True if authorized, false if failed
     */
    public function authorizeFromRequest(&$message = '')
    {
        $tokenPairString = '';
        try {
            if (($authCode = SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'auth_code')) === '') {
                throw new Exception(__('Authorization code is empty', 'duplicator-pro'));
            }

            $this->name                     = SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'name', '');
            $this->notes                    = SnapUtil::sanitizeDefaultInput(SnapUtil::INPUT_REQUEST, 'notes', '');
            $this->config['max_packages']   = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'max_packages', 10);
            $this->config['storage_folder'] = self::getSanitizedInputFolder('storage_folder', 'remove');

            $this->revokeAuthorization();

            $rawClient       = DUP_PRO_GDrive_U::get_raw_google_client();
            $tokenPairString = $rawClient->authenticate($authCode);
            $tokenPair       = json_decode($tokenPairString, true);

            if (!is_array($tokenPair)) {
                throw new Exception(__('Couldn\'t connect. Google Drive token pair not found.', 'duplicator-pro'));
            }

            if (!isset($tokenPair['refresh_token'])) {
                throw new Exception(__("Couldn't connect. Google Drive refresh token not found.", 'duplicator-pro'));
            }

            if (!isset($tokenPair['scope'])) {
                throw new Exception(__("Couldn't connect. Google Drive scopes not found.", 'duplicator-pro'));
            }

            if (!DUP_PRO_GDrive_U::checkScopes($tokenPair['scope'])) {
                throw new Exception(
                    __(
                        "Authorization failed. You did not allow all required permissions. Try again and make sure that you checked all checkboxes.",
                        'duplicator-pro'
                    )
                );
            }

            $this->config['refresh_token'] = $tokenPair['refresh_token'];
            $this->config['token_json']    = $rawClient->getAccessToken();
            $this->config['client_number'] = self::GDRIVE_CLIENT_LATEST;

            $this->config['authorized'] = true;
        } catch (Exception $e) {
            DUP_PRO_Log::traceException($e, "Problem authorizing Google Drive access token");
            DUP_PRO_Log::traceObject('Token pair string from authorization:', $tokenPairString);
            $message = $e->getMessage();
            return false;
        }

        $message = __('Google Drive is connected successfully and Storage Provider Updated.', 'duplicator-pro');
        return true;
    }

    /**
     * Revokes authorization
     *
     * @param string $message Message
     *
     * @return bool True if authorized, false if failed
     */
    public function revokeAuthorization(&$message = '')
    {
        if (!$this->isAuthorized()) {
            $message = __('Google Drive isn\'t authorized.', 'duplicator-pro');
            return true;
        }

        try {
            $client = DUP_PRO_GDrive_U::get_raw_google_client($this->config['client_number']);

            if (!empty($this->config['refresh_token'])) {
                $client->revokeToken($this->config['refresh_token']);
            }

            $accessTokenObj = json_decode($this->config['token_json']);
            if (is_object($accessTokenObj) && property_exists($accessTokenObj, 'access_token')) {
                $gdrive_access_token = $accessTokenObj->access_token;
            } else {
                $gdrive_access_token = false;
            }

            if (!empty($gdrive_access_token)) {
                $client->revokeToken($gdrive_access_token);
            }

            $this->config['token_json']    = '';
            $this->config['refresh_token'] = '';
            $this->config['client_number'] = -1;
            $this->config['authorized']    = false;
        } catch (Exception $e) {
            DUP_PRO_Log::trace("Problem revoking Google Drive access token msg: " . $e->getMessage());
            $message = $e->getMessage();
            return false;
        }

        $message = __('Google Drive is disconnected successfully.', 'duplicator-pro');
        return true;
    }

    /**
     * Get authorization URL
     *
     * @return string
     */
    public function getAuthorizationUrl()
    {
        $google_client = DUP_PRO_GDrive_U::get_raw_google_client();
        return $google_client->createAuthUrl();
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
        $userInfo    = false;
        $quotaString = '';

        if ($this->isAuthorized() && ($client = $this->getClient()) != null) {
            $userInfo = DUP_PRO_GDrive_U::get_user_info($client);

            $serviceDrive = new Duplicator_Pro_Google_Service_Drive($client);
            $optParams    = array('fields' => '*');
            $about        = $serviceDrive->about->get($optParams);
            $quota_total  = max($about->storageQuota['limit'], 1);
            $quota_used   = $about->storageQuota['usage'];

            if (is_numeric($quota_total) && is_numeric($quota_used)) {
                $available_quota = $quota_total - $quota_used;
                $used_perc       = round($quota_used * 100 / $quota_total, 1);
                $quotaString     = sprintf(
                    __('%1$s %% used, %2$s available', 'duplicator-pro'),
                    $used_perc,
                    round($available_quota / 1048576, 1) . ' MB'
                );
            }
        }

        return TplMng::getInstance()->render(
            'admin_pages/storages/configs/google_drive',
            [
                'storage'       => $this,
                'storageFolder' => $this->config['storage_folder'],
                'maxPackages'   => $this->config['max_packages'],
                'userInfo'      => $userInfo,
                'quotaString'   => $quotaString,
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

        $this->config['max_packages']   = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'gdrive_max_files', 10);
        $this->config['storage_folder'] = self::getSanitizedInputFolder('_gdrive_storage_folder', 'remove');

        $message = sprintf(
            __('Google Drive Storage Updated.', 'duplicator-pro'),
            $this->config['server'],
            $this->getStorageFolder()
        );
        return true;
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

        $result          = false;
        $source_handle   = null;
        $dest_handle     = null;
        $source_filepath = '';
        $dest_filepath   = '';

        try {
            $storageFolder   = $this->getStorageFolder();
            $source_filepath = wp_tempnam('DUP', DUPLICATOR_PRO_SSDIR_PATH_TMP);
            DUP_PRO_Log::trace("Created temp file $source_filepath");

            $source_handle = fopen($source_filepath, 'w');
            $rnd           = rand();
            fwrite($source_handle, "$rnd");
            DUP_PRO_Log::trace("Wrote $rnd to $source_filepath");
            fclose($source_handle);
            $source_handle = null;

            // -- Send the file --
            $basename        = basename($source_filepath);
            $gdrive_filepath = $storageFolder . '/' . $basename;

            $this->testLog->addMessage('Init Google Drive client');
            $client = $this->getClient();
            if ($client == null) {
                throw new Exception(DUP_PRO_U::__("Couldn't get Google client when performing Google Drive file test"));
            }

            DUP_PRO_Log::trace("About to send $source_filepath to $gdrive_filepath on Google Drive");

            $google_service_drive = new Duplicator_Pro_Google_Service_Drive($client);
            $this->testLog->addMessage('Get Google Drive folder id');
            $directory_id = DUP_PRO_GDrive_U::get_directory_id($google_service_drive, $storageFolder);
            if ($directory_id == null) {
                throw new Exception(DUP_PRO_U::__("Couldn't get directory ID for folder {$storageFolder} when performing Google Drive file test"));
            }

            $this->testLog->addMessage('Start upload file ' . $source_filepath . ' to Google Drive');
            $google_file = DUP_PRO_GDrive_U::upload_file($client, $source_filepath, $directory_id);
            if ($google_file == null) {
                throw new Exception(DUP_PRO_U::__("Couldn't upload file to Google Drive."));
            }

            // -- Download the file --
            $dest_filepath = wp_tempnam('GDRIVE_TMP', DUPLICATOR_PRO_SSDIR_PATH_TMP);

            if (file_exists($dest_filepath)) {
                @unlink($dest_filepath);
            }

            DUP_PRO_Log::trace("About to download $gdrive_filepath on Google Drive to $dest_filepath");

            $this->testLog->addMessage('Try to download the file uploaded to Google Drive to ' . $dest_filepath);

            if (DUP_PRO_GDrive_U::download_file($client, $google_file, $dest_filepath)) {
                try {
                    $google_service_drive = new Duplicator_Pro_Google_Service_Drive($client);
                    $google_service_drive->files->delete($google_file->id);
                } catch (Exception $ex) {
                    DUP_PRO_Log::traceException($ex, "Error deleting temporary file generated on Google File test");
                }

                /** @todo add rturn chcks for all IO functions */
                $dest_handle = fopen($dest_filepath, 'r');
                $dest_string = fread($dest_handle, 100);
                fclose($dest_handle);
                $dest_handle = null;

                /* The values better match or there was a problem */
                if ($rnd == (int) $dest_string) {
                    DUP_PRO_Log::trace("Files match! $rnd $dest_string");
                    $result  = true;
                    $message = DUP_PRO_U::esc_html__('Successfully stored and retrieved file');
                } else {
                    DUP_PRO_Log::traceError("mismatch in files $rnd != $dest_string");
                    $message = DUP_PRO_U::esc_html__('There was a problem storing or retrieving the temporary file on this account.');
                }
            } else {
                DUP_PRO_Log::traceError("Couldn't download $source_filepath after it had been uploaded");
            }
        } catch (Exception $e) {
            DUP_PRO_Log::traceException($e, 'Google Drive test error');
            $message = $e->getMessage();
        }

        if (file_exists($source_filepath)) {
            unlink($source_filepath);
            DUP_PRO_Log::trace("Deleted temp file $source_filepath");
        }

        if (file_exists($dest_filepath)) {
            unlink($dest_filepath);
            DUP_PRO_Log::trace("Deleted temp file $dest_filepath");
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
        $dest_installer_filename   = $package->Installer->getInstallerName();

        if ($source_archive_filepath === false) {
            DUP_PRO_Log::traceError("Archive doesn't exist for $package->Name!? - $source_archive_filepath");
            $upload_info->failed = true;
        }

        if ($source_installer_filepath === false) {
            DUP_PRO_Log::traceError("Installer doesn't exist for $package->Name!? - $source_installer_filepath");
            $upload_info->failed = true;
        }

        if ($upload_info->failed == true) {
            DUP_PRO_Log::infoTrace('Google Drive storage failed flag ($upload_info->failed) has been already set.');
            $package->update();
            return;
        }

        try {
            $client = $this->getClient();
            if ($client == null) {
                throw new Exception("Google client is null!");
            }

            if (empty($upload_info->data)) {
                $google_service_drive = new Duplicator_Pro_Google_Service_Drive($client);
                $upload_info->data    = DUP_PRO_GDrive_U::get_directory_id($google_service_drive, $this->getStorageFolder());
                if ($upload_info->data == null) {
                    $upload_info->failed = true;
                    DUP_PRO_Log::infoTrace("Error getting/creating Google Drive directory " . $this->getStorageFolder());
                    $package->update();
                    return;
                }
            }

            $tried_copying_installer = false;
            if (!$upload_info->copied_installer) {
                $tried_copying_installer = true;
                DUP_PRO_Log::trace("ATTEMPT: GDrive upload installer file $source_installer_filepath to " . $this->getStorageFolder());
                $google_service_drive = new Duplicator_Pro_Google_Service_Drive($client);
                //$upload_info->data is the parent file id
                $source_installer_filename = basename($source_installer_filepath);
                $existing_file_id          = DUP_PRO_GDrive_U::get_file(
                    $google_service_drive,
                    $source_installer_filename,
                    $upload_info->data
                );
                if ($existing_file_id != null) {
                    DUP_PRO_Log::trace(
                        "Installer already exists so deleting $source_installer_filename before uploading again. " .
                        "Existing file id = $existing_file_id"
                    );
                    DUP_PRO_GDrive_U::delete_file($google_service_drive, $existing_file_id);
                } else {
                    DUP_PRO_Log::trace("Installer doesn't exist already so no need to delete $source_installer_filename");
                }

                if (DUP_PRO_GDrive_U::upload_file($client, $source_installer_filepath, $upload_info->data, $dest_installer_filename)) {
                    DUP_PRO_Log::infoTrace('SUCCESS: Installer upload to Google Drive.');
                    $upload_info->copied_installer = true;
                    $upload_info->progress         = 5;
                } else {
                    $upload_info->failed = true;
                    DUP_PRO_Log::infoTrace('FAIL: Installer upload to Google Drive.');
                }

                // The package update will automatically capture the upload_info since its part of the package
                $package->update();
            } else {
                DUP_PRO_Log::trace("Already copied installer on previous execution of Google Drive $this->name so skipping");
            }

            if ((!$upload_info->copied_archive) && (!$tried_copying_installer)) {
                $global = DUP_PRO_Global_Entity::getInstance();

                // Warning: Google client is set to defer mode within this function
                // The upload_id for google drive is just the resume uri

                if ($upload_info->archive_offset == 0) {
                    // If just starting on this go ahead and delete existing file

                    $google_service_drive = new Duplicator_Pro_Google_Service_Drive($client);
                    //$upload_info->data is the parent file id
                    $source_archive_filename = basename($source_archive_filepath);
                    $existing_file_id        = DUP_PRO_GDrive_U::get_file($google_service_drive, $source_archive_filename, $upload_info->data);
                    if ($existing_file_id != null) {
                        DUP_PRO_Log::trace("Archive already exists so deleting $source_archive_filename before uploading again");
                        DUP_PRO_GDrive_U::delete_file($google_service_drive, $existing_file_id);
                    } else {
                        DUP_PRO_Log::trace("Archive doesn't exist so no need to delete $source_archive_filename");
                    }
                }

                // error_log('## offset: '.$upload_info->archive_offset);
                // Google Drive worker time capped at 10 seconds
                $gdrive_upload_info = DUP_PRO_GDrive_U::upload_file_chunk(
                    $client,
                    $source_archive_filepath,
                    $upload_info->data,
                    $global->gdrive_upload_chunksize_in_kb * 1024,
                    10,
                    $upload_info->archive_offset,
                    $upload_info->upload_id,
                    (50 + $global->getMicrosecLoadReduction())
                );
                $file_size          = filesize($source_archive_filepath);
                // Attempt to test self killing
                /*
                if (time() % 5 === 0) {
                    error_log('Attempting to make custom error');
                    $gdrive_upload_info->error_details = "Custom Error";
                }
                */

                if ($gdrive_upload_info->error_details == null) {
                    // Clear the failure count - we are just looking for consecutive errors
                    $upload_info->failure_count  = 0;
                    $upload_info->archive_offset = $gdrive_upload_info->next_offset;
                    // We are considering the whole Resume URI as the Upload ID
                    $upload_info->upload_id = $gdrive_upload_info->resume_uri;
                    $upload_info->progress  = max(5, DUP_PRO_U::percentage($upload_info->archive_offset, $file_size, 0));
                    DUP_PRO_Log::infoTrace(
                        "Archive upload offset: $upload_info->archive_offset [File size: $file_size] [Upload progress: $upload_info->progress%]"
                    );
                    if ($gdrive_upload_info->is_complete) {
                        DUP_PRO_Log::infoTrace('SUCCESS: Archive upload to Google Drive.');
                        $upload_info->copied_archive = true;
                        $this->purgeOldPackages();
                    }
                } else {
                    DUP_PRO_Log::traceError('FAIL: Archive upload to Google Drive. ERROR: ' . $gdrive_upload_info->error_details);
                    // error_log('$$ ELSE: '.$gdrive_upload_info->error_details);
                    $this->setArchiveOffset($upload_info);
                    $upload_info->increase_failure_count();
                }
            } else {
                DUP_PRO_Log::trace("Already copied archive on previous execution of Google Drive $this->name so skipping");
            }
        } catch (Exception $e) {
            // error_log('**** Catch ****');
            DUP_PRO_Log::traceError(
                "EXCEPTION ERROR: Problems copying package " . $package->Name . " to " . $this->getStorageFolder() . ". Message: " . $e->getMessage()
            );
            $this->setArchiveOffset($upload_info);
            $upload_info->increase_failure_count();
        }

        if ($upload_info->failed) {
            DUP_PRO_Log::infoTrace('Google Drive storage failed flag ($upload_info->failed) has been already set.');
        }

        // The package update will automatically capture the upload_info since its part of the package
        $package->update();
    }

    /**
     * Set google drive archive offset
     *
     * @param DUP_PRO_Package_Upload_Info $upload_info Upload info
     *
     * @return void
     */
    protected function setArchiveOffset(DUP_PRO_Package_Upload_Info $upload_info)
    {
        $resume_url = $upload_info->upload_id;
        if (is_null($resume_url)) {
            $upload_info->archive_offset = 0;
        } else {
            $args          = array(
                'headers' => array(
                    'Content-Length' => "0",
                    'Content-Range'  => "bytes */*",
                ),
                'method'  => 'PUT',
                'timeout' => 60,
            );
            $response      = wp_remote_request($resume_url, $args);
            $response_code = wp_remote_retrieve_response_code($response);
            DUP_PRO_Log::infoTrace("Google Drive API response code: $response_code");
            // error_log('response code:'.$response_code);
            switch ($response_code) {
                case 308:
                    DUP_PRO_Log::infoTrace("Google Drive transfer is incomplete.");
                    // error_log("Google Drive transfer is incomplete");
                    $range = wp_remote_retrieve_header($response, 'range');
                    if (!empty($range) && preg_match('/bytes=0-(\d+)$/', $range, $matches)) {
                        $upload_info->archive_offset = 1 + (int) $matches[1];
                    } else {
                        $upload_info->archive_offset = 0;
                    }
                    break;
                case 200:
                case 201:
                    DUP_PRO_Log::infoTrace("SUCCESS: archive upload to Google Drive.");
                    $upload_info->copied_archive = true;
                    $this->purgeOldPackages();
                    break;
                case 404:
                default:
                    $upload_info->archive_offset = 0;
                    break;
            }
        }
        // error_log("Setting archive offset to the ".$upload_info->archive_offset);
        DUP_PRO_Log::trace("Setting archive offset to the " . $upload_info->archive_offset);
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
            $client = $this->getClient();
            if ($client == null) {
                throw new Exception("Google client is null!");
            }

            $serviceDrive = new Duplicator_Pro_Google_Service_Drive($client);
            if (($directory_id = DUP_PRO_GDrive_U::get_directory_id($serviceDrive, $this->getStorageFolder())) == null) {
                throw new Exception("Couldn't get directory ID for folder {$this->getStorageFolder()} when performing Google Drive file test");
            }

            $global = DUP_PRO_Global_Entity::getInstance();
            if (($file_list            = DUP_PRO_GDrive_U::get_files_in_directory($serviceDrive, $directory_id)) == null) {
                throw new Exception("ERROR: Couldn't retrieve file list from Google Drive so can purge old packages");
            }

            /** @var Duplicator_Pro_Google_Service_Drive_DriveFile[] */
            $php_files         = array();
            $archive_filenames = array();

            foreach ($file_list as $drive_file) {
                $file_title = $drive_file->getName();
                if (DUP_PRO_STR::endsWith($file_title, "_{$global->installer_base_name}")) {
                    array_push($php_files, $drive_file);
                } elseif (DUP_PRO_STR::endsWith($file_title, '_archive.zip') || DUP_PRO_STR::endsWith($file_title, '_archive.daf')) {
                    array_push($archive_filenames, $drive_file);
                }
            }

            $index                  = 0;
            $num_archives           = count($archive_filenames);
            $num_archives_to_delete = $num_archives - $this->config['max_packages'];
            DUP_PRO_Log::trace(
                "Num zip files to delete=$num_archives_to_delete since there are $num_archives on the drive and max files={$this->config['max_packages']}"
            );

            while ($index < $num_archives_to_delete) {
                $archive_file  = $archive_filenames[$index];
                $archive_title = $archive_file->getName();
                // Matching installer has to be present for us to delete
                if (DUP_PRO_STR::endsWith($archive_title, '_archive.zip')) {
                    $installer_title = str_replace('_archive.zip', "_{$global->installer_base_name}", $archive_title);
                } else {
                    $installer_title = str_replace('_archive.daf', "_{$global->installer_base_name}", $archive_title);
                }

                // Now get equivalent installer
                foreach ($php_files as $installer_file) {
                    if ($installer_title == $installer_file->getName()) {
                        DUP_PRO_Log::trace("Attempting to delete $installer_title from Google Drive");
                        if (DUP_PRO_GDrive_U::delete_file($serviceDrive, $installer_file->getid()) == false) {
                            DUP_PRO_Log::traceError("FAIL: purging Google Drive packages. Error purging old Google Drive file $installer_title");
                        }

                        DUP_PRO_Log::trace("Attempting to delete $archive_title from Google Drive");
                        if (DUP_PRO_GDrive_U::delete_file($serviceDrive, $archive_file->getid()) == false) {
                            DUP_PRO_Log::traceError("FAIL: purging Google Drive packages. Error in purging old Google Drive file $archive_title");
                        }
                        break;
                    }
                }

                $index++;
            }
        } catch (Exception $e) {
            DUP_PRO_Log::infoTraceException($e, "FAIL: purge package for storage " . $this->name . '[ID: ' . $this->id . '] type:' . $this->getStypeName());
            return false;
        }

        DUP_PRO_Log::infoTrace("Purge of old packages at " . $this->name . '[ID: ' . $this->id . "] storage completed.");

        return true;
    }

    /**
     * Retrieves the google client based on storage and auto updates the access token if necessary
     *
     * @return ?Duplicator_Pro_Google_Client
     */
    public function getClient()
    {
        $client = null;

        if (!empty($this->config['token_json'])) {
            $client = DUP_PRO_GDrive_U::get_raw_google_client($this->config['client_number']);
            $client->setAccessToken($this->config['token_json']);
            // Reference on access/refresh token http://stackoverflow.com/questions/9241213/how-to-refresh-token-with-google-api-client
            if ($client->isAccessTokenExpired()) {
                DUP_PRO_Log::trace("Access token is expired so checking token.");
                $client->refreshToken($this->config['refresh_token']);
                // getAccessToken return json encoded value of access token and other stuff
                $token_json = $client->getAccessToken();
                if ($token_json != null) {
                    $this->config['token_json'] = $token_json;
                    DUP_PRO_Log::trace("Retrieved acess token set from google: " . $this->config['token_json']);
                    $this->save();
                } else {
                    DUP_PRO_Log::trace("Can't retrieve access token!");
                    $client = null;
                }
            } else {
                DUP_PRO_Log::trace("Access token ISNT expired");
            }
        } else {
            DUP_PRO_Log::trace("Access token not set!");
        }

        return $client;
    }
}
