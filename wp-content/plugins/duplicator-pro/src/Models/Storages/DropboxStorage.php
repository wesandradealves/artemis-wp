<?php

/**
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Models\Storages;

use DUP_PRO_Dropbox_Transfer_Mode;
use DUP_PRO_DropboxClient;
use DUP_PRO_DropboxV2Client;
use DUP_PRO_Global_Entity;
use DUP_PRO_Log;
use DUP_PRO_Package;
use DUP_PRO_Package_File_Type;
use DUP_PRO_Package_Upload_Info;
use DUP_PRO_STR;
use DUP_PRO_U;
use Duplicator\Core\Views\TplMng;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Utils\Crypt\CryptBlowfish;
use Exception;
use stdClass;

class DropboxStorage extends AbstractStorageEntity implements StorageAuthInterface
{
    /** @var ?DUP_PRO_DropboxV2Client */
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
                'access_token'        => '',
                'access_token_secret' => '',
                'v2_access_token'     => '',
                'authorized'          => false,
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
                'access_token'        => $this->dropbox_access_token,
                'access_token_secret' => $this->dropbox_access_token_secret,
                'v2_access_token'     => $this->dropbox_v2_access_token,
                'storage_folder'      => ltrim($this->dropbox_storage_folder, '/\\'),
                'max_packages'        => $this->dropbox_max_files,
                'authorized'          => ($this->dropbox_authorization_state == 4),
            ];
            // reset old values
            $this->dropbox_access_token        = '';
            $this->dropbox_access_token_secret = '';
            $this->dropbox_v2_access_token     = '';
            $this->dropbox_storage_folder      = '';
            $this->dropbox_max_files           = 10;
            $this->dropbox_authorization_state = 0;
        }
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
     * Return the storage type
     *
     * @return int
     */
    public static function getSType()
    {
        return 1;
    }

    /**
     * Returns the FontAwesome storage type icon.
     *
     * @return string Returns the font-awesome icon
     */
    public static function getStypeIcon()
    {
        return '<i class="fab fa-dropbox fa-fw"></i>';
    }

    /**
     * Returns the storage type name.
     *
     * @return string
     */
    public static function getStypeName()
    {
        return __('Dropbox', 'duplicator-pro');
    }

    /**
     * Get storage location string
     *
     * @return string
     */
    public function getLocationString()
    {
        $dropBoxInfo = $this->getAccountInfo();
        if (!isset($dropBoxInfo->locale) || $dropBoxInfo->locale == 'en') {
            return "https://dropbox.com/home/Apps/Duplicator%20Pro" . $this->getStorageFolder();
        } else {
            return "https://dropbox.com/home";
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
     * Authorized from HTTP request
     *
     * @param string $message Message
     *
     * @return bool True if authorized, false if failed
     */
    public function authorizeFromRequest(&$message = '')
    {
        try {
            if (($authCode = SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'auth_code')) === '') {
                throw new Exception(__('Authorization code is empty', 'duplicator-pro'));
            }

            $this->name                     = SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'name', '');
            $this->notes                    = SnapUtil::sanitizeDefaultInput(SnapUtil::INPUT_REQUEST, 'notes', '');
            $this->config['max_packages']   = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'max_packages', 10);
            $this->config['storage_folder'] = self::getSanitizedInputFolder('storage_folder', 'remove');

            $this->revokeAuthorization();

            $client = $this->getDropboxClient();
            if (($token  = $client->authenticate($authCode)) === false) {
                throw new Exception(__("Couldn't connect. Dropbox access token not found.", 'duplicator-pro'));
            }

            $this->config['v2_access_token'] = $token;
            $this->config['authorized']      = true;
        } catch (Exception $e) {
            DUP_PRO_Log::trace("Problem authorizing Dropbox access token msg: " . $e->getMessage());
            $message = $e->getMessage();
            return false;
        }

        $message = __('Dropbox is connected successfully and Storage Provider Updated.', 'duplicator-pro');
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
            $message = __('Dropbox isn\'t authorized.', 'duplicator-pro');
            return true;
        }

        try {
            $client = $this->getDropboxClient();
            if ($client->revokeToken() === false) {
                throw new Exception(__('DropBox can\'t be unauthorized.', 'duplicator-pro'));
            }

            $this->config['v2_access_token'] = '';
            $this->config['authorized']      = false;
        } catch (Exception $e) {
            DUP_PRO_Log::trace("Problem revoking Dropbox access token msg: " . $e->getMessage());
            $message = $e->getMessage();
            return false;
        }

        $message = __('Dropbox is disconnected successfully.', 'duplicator-pro');
        return true;
    }

    /**
     * Get authorization URL
     *
     * @return string
     */
    public function getAuthorizationUrl()
    {
        $dropbox_client = $this->getDropboxClient(false);
        return $dropbox_client->createAuthUrl();
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
                    __('Transferring to Dropbox folder:<br/> <i>%1$s</i>', "duplicator-pro"),
                    $this->getStorageFolder()
                );
            case 'pending':
                return sprintf(
                    __('Transfer to Dropbox folder %1$s is pending', "duplicator-pro"),
                    $this->getStorageFolder()
                );
            case 'failed':
                return sprintf(
                    __('Failed to transfer to Dropbox folder %1$s', "duplicator-pro"),
                    $this->getStorageFolder()
                );
            case 'cancelled':
                return sprintf(
                    __('Cancelled before could transfer to Dropbox folder %1$s', "duplicator-pro"),
                    $this->getStorageFolder()
                );
            case 'success':
                return sprintf(
                    __('Transferred package to Dropbox folder %1$s', "duplicator-pro"),
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
            'admin_pages/storages/configs/dropbox',
            [
                'storage'           => $this,
                'accountInfo'       => $this->getAccountInfo(),
                'quotaInfo'         => $this->getQuota(),
                'storageFolder'     => $this->config['storage_folder'],
                'maxPackages'       => $this->config['max_packages'],
                'accessToken'       => $this->config['access_token'],
                'accessTokenSecret' => $this->config['access_token_secret'],
                'v2AccessToken'     => $this->config['v2_access_token'],
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

        $this->config['max_packages']   = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'dropbox_max_files', 10);
        $this->config['storage_folder'] = self::getSanitizedInputFolder('_dropbox_storage_folder', 'remove');

        $message = sprintf(
            __('Dropbox Storage Updated. Folder: %1$s', 'duplicator-pro'),
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
        $source_filepath = null;

        try {
            $source_filepath = tempnam(sys_get_temp_dir(), 'DUP');
            if ($source_filepath === false) {
                throw new Exception(DUP_PRO_U::__("Couldn't create the temp file for the Dropbox send test"));
            }
            DUP_PRO_Log::trace("Created temp file $source_filepath");

            $source_handle = fopen($source_filepath, 'w');
            $rnd           = rand();
            fwrite($source_handle, "$rnd");
            DUP_PRO_Log::trace("Wrote $rnd to $source_filepath");
            fclose($source_handle);
            $source_handle = null;

            // -- Send the file --
            $basename = basename($source_filepath);
            $filepath = $this->getStorageFolder() . "/$basename";
            $client   = $this->getClientWithAccess();
            if ($client == null) {
                throw new Exception(DUP_PRO_U::__("Couldn't get the DropBox client when performing the DropBox file test"));
            }

            DUP_PRO_Log::trace("About to send $source_filepath to $filepath in dropbox");
            $upload_result = $client->UploadFile($source_filepath, $filepath);

            $client->Delete($filepath);

            /* The values better match or there was a problem */
            if ($client->checkFileHash($upload_result, $source_filepath)) {
                DUP_PRO_Log::trace("Files match!");
                $result  = true;
                $message = DUP_PRO_U::__('Successfully stored and retrieved file');
            } else {
                DUP_PRO_Log::traceError("mismatch in files");
                $message = DUP_PRO_U::__('There was a problem storing or retrieving the temporary file on this account.');
            }
        } catch (Exception $ex) {
            DUP_PRO_Log::trace($ex->getMessage());
            $message = $ex->getMessage();
        }

        if (file_exists($source_filepath)) {
            DUP_PRO_Log::trace("Removing temp file $source_filepath");
            unlink($source_filepath);
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
            DUP_PRO_Log::infoTrace('Dropbox storage failed flag ($upload_info->failed) has been already set.');
            $package->update();
            return;
        }

        try {
            $client                  = $this->getClientWithAccess();
            $dropbox_archive_path    = basename($source_archive_filepath);
            $dropbox_archive_path    = $this->getStorageFolder() . "/$dropbox_archive_path";
            $dest_installer_filename = $package->Installer->getInstallerName();
            $dropbox_installer_path  = $this->getStorageFolder() . "/$dest_installer_filename";

            if (!$upload_info->copied_installer) {
                DUP_PRO_Log::trace("ATTEMPT: Dropbox upload installer file $source_installer_filepath to $dropbox_installer_path");
                $installer_meta = $client->UploadFile($source_installer_filepath, $dropbox_installer_path, $dest_installer_filename);
                if (!$client->checkFileHash($installer_meta, $source_installer_filepath)) {
                    throw new Exception(
                        "**ERROR: installer upload to DropBox" . $dropbox_installer_path . ". Uploaded installer file may be corrupted. Hashes don't match."
                    );
                }

                DUP_PRO_Log::infoTrace("SUCCESS: installer upload to DropBox " . $dropbox_installer_path);
                $upload_info->copied_installer = true;
                $upload_info->progress         = 5;
            } else {
                DUP_PRO_Log::trace("Already uploaded installer on previous execution of Dropbox $this->name so skipping");
            }

            if (!$upload_info->copied_archive) {
                /* Delete the archive if we are just starting it (in the event they are pushing another copy */
                if ($upload_info->archive_offset == 0) {
                    DUP_PRO_Log::trace("Archive offset is 0 so deleting $dropbox_archive_path");
                    try {
                        $client->Delete($dropbox_archive_path);
                    } catch (Exception $ex) {
                        // Burying exceptions
                    }
                }

                $global = DUP_PRO_Global_Entity::getInstance();

                $dropbox_upload_info = $client->upload_file_chunk(
                    $source_archive_filepath,
                    $dropbox_archive_path,
                    $global->dropbox_upload_chunksize_in_kb * 1024,
                    $global->php_max_worker_time_in_sec,
                    $upload_info->archive_offset,
                    $upload_info->upload_id,
                    $global->getMicrosecLoadReduction()
                );

                $upload_info->archive_offset = isset($dropbox_upload_info->next_offset) ? $dropbox_upload_info->next_offset : 0;
                $upload_info->upload_id      = $dropbox_upload_info->upload_id;

                if ($dropbox_upload_info->error_details !== null) {
                    throw new Exception("FAIL: archive upload to dropbox. Error received from Dropbox API: $dropbox_upload_info->error_details");
                }

                // Clear the failure count - we are just looking for consecutive errors
                $file_size                  = filesize($source_archive_filepath);
                $upload_info->progress      = max(5, DUP_PRO_U::percentage($upload_info->archive_offset, $file_size, 0));
                $upload_info->failure_count = 0;
                DUP_PRO_Log::infoTrace(
                    "Archive upload offset: $upload_info->archive_offset [File size: $file_size] [Upload progress: $upload_info->progress%]"
                );


                if (
                    $dropbox_upload_info->file_meta != null &&
                    property_exists($dropbox_upload_info->file_meta, "size") &&
                    $dropbox_upload_info->file_meta->size === $file_size
                ) {
                    DUP_PRO_Log::infoTrace("UPLOAD FINISHED. FILE META IS " . print_r($dropbox_upload_info->file_meta, true));
                    $upload_info->copied_archive = true;
                    $this->purgeOldPackages();
                }
            } else {
                DUP_PRO_Log::trace("Already copied archive on previous execution of Dropbox $this->name so skipping");
            }
        } catch (Exception $e) {
            $upload_info->increase_failure_count();
            DUP_PRO_Log::trace("Exception caught copying package $package->Name to " . $this->getStorageFolder() . " " . $e->getMessage());
        }

        if ($upload_info->failed) {
            DUP_PRO_Log::infoTrace('Dropbox storage failed flag ($upload_info->failed) has been already set.');
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
            $client    = $this->getClientWithAccess();
            $global    = DUP_PRO_Global_Entity::getInstance();
            $file_list = $client->GetFiles($this->getStorageFolder());
            usort($file_list, array(__CLASS__, 'compareFileDates'));
            $php_filenames     = array();
            $archive_filenames = array();
            foreach ($file_list as $file_metadata) {
                if (DUP_PRO_STR::endsWith($file_metadata->file_path, "_{$global->installer_base_name}")) {
                    array_push($php_filenames, $file_metadata);
                } elseif (
                    DUP_PRO_STR::endsWith($file_metadata->file_path, '_archive.zip') ||
                    DUP_PRO_STR::endsWith($file_metadata->file_path, '_archive.daf')
                ) {
                    array_push($archive_filenames, $file_metadata);
                }
            }

            DUP_PRO_Log::infoTrace("Dropbox archive file names: " . print_r($archive_filenames, true));

            if ($this->config['max_packages'] > 0) {
                $num_php_files     = count($php_filenames);
                $num_php_to_delete = $num_php_files - $this->config['max_packages'];
                $index             = 0;
                DUP_PRO_Log::trace("Num php files to delete=$num_php_to_delete");
                while ($index < $num_php_to_delete) {
                    $client->Delete($php_filenames[$index]->file_path);
                    $index++;
                }

                $index                  = 0;
                $num_archives           = count($archive_filenames);
                $num_archives_to_delete = $num_archives - $this->config['max_packages'];
                DUP_PRO_Log::trace("Num archives to delete=$num_archives_to_delete");
                while ($index < $num_archives_to_delete) {
                    $client->Delete($archive_filenames[$index]->file_path);
                    $index++;
                }
            }
        } catch (Exception $e) {
            DUP_PRO_Log::infoTraceException($e, "FAIL: purge package for storage " . $this->name . '[ID: ' . $this->id . '] type:' . $this->getStypeName());
            return false;
        }

        DUP_PRO_Log::infoTrace("Purge of old packages at " . $this->getStypeName() . " storage completed.");

        return true;
    }

    /**
     * Get dropbox client
     *
     * @param bool $full_access if true, will return a client with full access
     *
     * @return DUP_PRO_DropboxV2Client
     */
    protected function getDropboxClient($full_access = false)
    {
        if (is_null($this->client)) {
            $global        = DUP_PRO_Global_Entity::getInstance();
            $use_curl      = ($global->dropbox_transfer_mode == DUP_PRO_Dropbox_Transfer_Mode::cURL);
            $configuration = self::getApiKeySecret();
            if ($full_access) {
                $configuration['app_full_access'] = true;
            }
            // Note it's possible dropbox is in disabled mode but we are still constructing it.  Should have better error handling
            $this->client = new DUP_PRO_DropboxV2Client($configuration, 'en', $use_curl);
        }
        return $this->client;
    }

    /**
     * Set access token
     *
     * @return DUP_PRO_DropboxV2Client
     */
    protected function getClientWithAccess()
    {
        $client = $this->getDropboxClient();
        $this->setV2AccessTokenFromV1Client();
        $client->SetAccessToken([
            'v2_access_token' => $this->config['v2_access_token'],
        ]);
        return $client;
    }

    /**
     * Get dropbox api key and secret
     *
     * @return array{app_key:string,app_secret:string}
     */
    protected static function getApiKeySecret()
    {
        $dk   = self::getDk1();
        $dk   = self::getDk2() . $dk;
        $akey = CryptBlowfish::decrypt('EQNJ53++6/40fuF5ke+IaQ==', $dk);
        $asec = CryptBlowfish::decrypt('ui25chqoBexPt6QDi9qmGg==', $dk);
        $akey = trim($akey);
        $asec = trim($asec);
        if (($akey != $asec) || ($akey != "fdda100")) {
            $akey = self::getAk1() . self::getAk2();
            $asec = self::getAs1() . self::getAs2();
        }
        $configuration = array(
            'app_key'    => $asec,
            'app_secret' => $akey,
        );
        return $configuration;
    }

    /**
     * Get dk1
     *
     * @return string
     */
    private static function getDk1()
    {
        return 'y8!!';
    }

    /**
     * Get dk2
     *
     * @return string
     */
    private static function getDk2()
    {
        return '32897';
    }

    /**
     * Get ak1
     *
     * @return string
     */
    private static function getAk1()
    {
        return strrev('i6gh72iv');
    }

    /**
     * Get ak2
     *
     * @return string
     */
    private static function getAk2()
    {
        return strrev('1xgkhw2');
    }

    /**
     * Get as1
     *
     * @return string
     */
    private static function getAs1()
    {
        return strrev('z7fl2twoo');
    }

    /**
     * Get as2
     *
     * @return string
     */
    private static function getAs2()
    {
        return strrev('2z2bfm');
    }

    /**
     * Set v2 access token from v1 client
     *
     * @return string V2 access token
     */
    protected function setV2AccessTokenFromV1Client()
    {
        if (strlen($this->config['v2_access_token']) > 0) {
            return $this->config['v2_access_token'];
        }

        if (strlen($this->config['access_token']) == 0 || strlen($this->config['access_token_secret']) == 0) {
            return '';
        }

        $useCurl       = (DUP_PRO_Global_Entity::getInstance()->dropbox_transfer_mode === DUP_PRO_Dropbox_Transfer_Mode::cURL);
        $configuration = self::getApiKeySecret();
        $dropbox_v1    = new DUP_PRO_DropboxClient($configuration, 'en', $useCurl);
        $dropbox_v1->SetAccessToken([
            't' => $this->config['access_token'],
            's' => $this->config['access_token_secret'],
        ]);
        $response = $dropbox_v1->token_from_oauth1();

        if (isset($response->access_token)) {
            $this->config['access_token']        = '';
            $this->config['access_token_secret'] = '';
            $this->config['v2_access_token']     = $response->access_token;
            $this->save();
        }

        return $this->config['v2_access_token'];
    }

    /**
     * Get account info
     *
     * @return false|object
     */
    protected function getAccountInfo()
    {
        if (!$this->config['authorized']) {
            return false;
        }
        return $this->getClientWithAccess()->GetAccountInfo();
    }

    /**
     * Get dropbox quota
     *
     * @return false|array{used:int,total:int,perc:float,available:string}
     */
    protected function getQuota()
    {
        if (!$this->config['authorized']) {
            return false;
        }
        $client =  $this->getClientWithAccess();
        $quota  = $client->getQuota();
        if (
            !isset($quota->used) ||
            !isset($quota->allocation->allocated) ||
            $quota->allocation->allocated <= 0
        ) {
            return false;
        }

        $quota_used      = $quota->used;
        $quota_total     = $quota->allocation->allocated;
        $used_perc       = round($quota_used * 100 / $quota_total, 1);
        $available_quota = $quota_total - $quota_used;

        return array(
            'used'      => $quota_used,
            'total'     => $quota_total,
            'perc'      => $used_perc,
            'available' => round($available_quota / 1048576, 1) . ' MB',
        );
    }


    /**
     * Dropbox compare file dates
     *
     * @param stdClass $a File info
     * @param stdClass $b File info
     *
     * @return int
     */
    protected static function compareFileDates($a, $b)
    {
        $a_ts = strtotime($a->modified);
        $b_ts = strtotime($b->modified);
        if ($a_ts == $b_ts) {
            return 0;
        }

        return ($a_ts < $b_ts) ? -1 : 1;
    }
}
