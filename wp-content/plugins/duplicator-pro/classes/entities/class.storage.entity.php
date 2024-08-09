<?php

/**
 * Storage entity layer
 */

defined('ABSPATH') || defined('DUPXABSPATH') || exit;

use Duplicator\Core\Models\AbstractEntityList;
use Duplicator\Utils\Crypt\CryptBlowfish;
use VendorDuplicator\Amk\JsonSerialize\JsonSerialize;

/**
 * @copyright 2016 Snap Creek LLC
 */
abstract class DUP_PRO_Storage_Entity extends AbstractEntityList
{
    const PROPERTIES_TO_ENCRYPT = [
        'dropbox_access_token',
        'dropbox_v2_access_token',
        'dropbox_access_token_secret',
        'gdrive_access_token_set_json',
        'gdrive_refresh_token',
        's3_access_key',
        's3_secret_key',
        'ftp_username',
        'ftp_password',
        'ftp_storage_folder',
        'sftp_username',
        'sftp_password',
        'sftp_private_key',
        'sftp_private_key_password',
        'sftp_storage_folder',
        'onedrive_user_id',
        'onedrive_access_token',
        'onedrive_refresh_token',
    ];

    /** @todo Legacy values to remove when storages will'be fully migrated */
    /** @var string */
    protected $local_storage_folder = '';
    /** @var int */
    protected $local_max_files = 10;
    /** @var bool */
    protected $local_filter_protection = true;
    /** @var bool */
    protected $purge_package_record = true;
    // DROPBOX FIELDS
    /** @var string */
    protected $dropbox_access_token = '';
    /** @var string */
    protected $dropbox_access_token_secret = '';
    /** @var string */
    protected $dropbox_v2_access_token = '';
    //to use different name for OAuth 2 token
    /** @var string */
    protected $dropbox_storage_folder = '';
    /** @var int */
    protected $dropbox_max_files = 10;
    /** @var int */
    protected $dropbox_authorization_state = 0;
    //ONEDRIVE FIELDS
    /** @var string */
    protected $onedrive_endpoint_url = '';
    /** @var string */
    protected $onedrive_resource_id = '';
    /** @var string */
    protected $onedrive_access_token = '';
    /** @var string */
    protected $onedrive_refresh_token = '';
    /** @var int */
    protected $onedrive_token_obtained = 0;
    /** @var string */
    protected $onedrive_user_id = '';
    /** @var string */
    protected $onedrive_storage_folder = '';
    /** @var int */
    protected $onedrive_max_files = 10;
    /** @var string */
    protected $onedrive_storage_folder_id = '';
    /** @var int */
    protected $onedrive_authorization_state = 0;
    /** @var string */
    protected $onedrive_storage_folder_web_url = '';
    // FTP FIELDS
    /** @var string */
    protected $ftp_server = '';
    /** @var int */
    protected $ftp_port = 21;
    /** @var string */
    protected $ftp_username = '';
    /** @var string */
    protected $ftp_password = '';
    /** @var bool */
    protected $ftp_use_curl = false;
    /** @var string */
    protected $ftp_storage_folder = '';
    /** @var int */
    protected $ftp_max_files = 10;
    /** @var int */
    protected $ftp_timeout_in_secs = 15;
    /** @var bool */
    protected $ftp_ssl = false;
    /** @var bool */
    protected $ftp_passive_mode = false;
    // SFTP FIELDS
    /** @var string */
    protected $sftp_server = '';
    /** @var int */
    protected $sftp_port = 22;
    /** @var string */
    protected $sftp_username = '';
    /** @var string */
    protected $sftp_password = '';
    /** @var string */
    protected $sftp_private_key = '';
    /** @var string */
    protected $sftp_private_key_password = '';
    /** @var string */
    protected $sftp_storage_folder = '';
    /** @var int */
    protected $sftp_timeout_in_secs = 15;
    /** @var int */
    protected $sftp_max_files = 10;
    /** @var bool */
    protected $sftp_disable_chunking_mode = false;
    // GOOGLE DRIVE FIELDS
    /** @var string */
    protected $gdrive_access_token_set_json = '';
    /** @var string */
    protected $gdrive_refresh_token = '';
    /** @var string */
    protected $gdrive_storage_folder = '';
    /** @var int */
    protected $gdrive_max_files = 10;
    /** @var int */
    protected $gdrive_authorization_state = 0;
    /** @var int */
    protected $gdrive_client_number = -1;

    // S3 FIELDS
    /** @var string */
    protected $s3_access_key = '';
    /** @var string */
    protected $s3_bucket = '';
    /** @var int */
    protected $s3_max_files = 10;
    /** @var string */
    protected $s3_provider = 'amazon';
    /** @var string */
    protected $s3_region = '';
    /** @var string */
    protected $s3_endpoint = '';
    /** @var string */
    protected $s3_secret_key = '';
    /** @var string */
    protected $s3_storage_class = 'STANDARD';
    /** @var string */
    protected $s3_storage_folder = '';
    /** @var bool */
    protected $s3_ACL_full_control = true;

    /**
     * Will be called, automatically, when Serialize
     *
     * @return array<string, mixed>
     */
    public function __serialize() // phpcs:ignore PHPCompatibility.FunctionNameRestrictions.NewMagicMethods.__serializeFound
    {
        $data = JsonSerialize::serializeToData($this, JsonSerialize::JSON_SKIP_MAGIC_METHODS |  JsonSerialize::JSON_SKIP_CLASS_NAME);

        if (DUP_PRO_Global_Entity::getInstance()->crypt) {
            foreach (self::PROPERTIES_TO_ENCRYPT as $prop) {
                if (!empty($data[$prop])) {
                    $data[$prop] = CryptBlowfish::encrypt($data[$prop]);
                }
            }
        }

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
        if (DUP_PRO_Global_Entity::getInstance()->crypt) {
            foreach (self::PROPERTIES_TO_ENCRYPT as $prop) {
                if (!empty($this->{$prop})) {
                    $this->{$prop} = CryptBlowfish::decrypt($this->{$prop});
                }
            }
        }
    }
}
