<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

use Duplicator\Core\Models\AbstractEntitySingleton;
use Duplicator\Core\Models\UpdateFromInputInterface;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Utils\Crypt\CryptBlowfish;
use Duplicator\Utils\Settings\ModelMigrateSettingsInterface;
use VendorDuplicator\Amk\JsonSerialize\JsonSerialize;

/**
 * Secure Global Entity. Used to store settings requiring encryption.
 *
 * @todo remove this entity and put props on globals
 */
class DUP_PRO_Secure_Global_Entity extends AbstractEntitySingleton implements UpdateFromInputInterface, ModelMigrateSettingsInterface
{
    /** @var string */
    public $basic_auth_password = '';
    /** @var string */
    public $lkp = '';

    /**
     * Class contructor
     */
    protected function __construct()
    {
    }

    /**
     * Entity type
     *
     * @return string
     */
    public static function getType()
    {
        return 'DUP_PRO_Secure_Global_Entity';
    }

    /**
     * Will be called, automatically, when Serialize
     *
     * @return array<string,mixed>
     */
    public function __serialize() // phpcs:ignore PHPCompatibility.FunctionNameRestrictions.NewMagicMethods.__serializeFound
    {
        $data = JsonSerialize::serializeToData($this, JsonSerialize::JSON_SKIP_MAGIC_METHODS |  JsonSerialize::JSON_SKIP_CLASS_NAME);
        if (strlen($this->basic_auth_password)) {
            $data['basic_auth_password'] = CryptBlowfish::encrypt($this->basic_auth_password);
        }
        if (strlen($this->lkp)) {
            $data['lkp'] = CryptBlowfish::encrypt($this->lkp);
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
        $this->basic_auth_password = (string) $this->basic_auth_password; // for old versions
        if (strlen($this->basic_auth_password)) {
            $this->basic_auth_password = CryptBlowfish::decrypt($this->basic_auth_password);
        }

        $this->lkp = (string) $this->lkp; // for old versions
        if (strlen($this->lkp)) {
            $this->lkp = CryptBlowfish::decrypt($this->lkp);
        }
    }

    /**
     * Set data from query input
     *
     * @param int $type One of INPUT_GET, INPUT_POST, INPUT_COOKIE, INPUT_SERVER, or INPUT_ENV, SnapUtil::INPUT_REQUEST
     *
     * @return bool true on success or false on failure
     */
    public function setFromInput($type)
    {
        $input = SnapUtil::getInputFromType($type);

        $this->basic_auth_password = isset($input['basic_auth_password']) ? SnapUtil::sanitizeNSCharsNewlineTrim($input['basic_auth_password']) : '';
        $this->basic_auth_password = stripslashes($this->basic_auth_password);
        return true;
    }

    /**
     * To export data
     *
     * @return array<string, mixed>
     */
    public function settingsExport()
    {
        $skipProps = [
            'id',
            'lkp',
        ];

        $data = JsonSerialize::serializeToData($this, JsonSerialize::JSON_SKIP_MAGIC_METHODS |  JsonSerialize::JSON_SKIP_CLASS_NAME);
        foreach ($skipProps as $prop) {
            unset($data[$prop]);
        }
        return $data;
    }

    /**
     * Update object properties from import data
     *
     * @param array<string, mixed> $data        data to import
     * @param string               $dataVersion version of data
     * @param array<string, mixed> $extraData   extra data, useful form id mapping etc.
     *
     * @return bool True if success, otherwise false
     */
    public function settingsImport($data, $dataVersion, array $extraData = [])
    {
        $skipProps = [
            'id',
            'lkp',
        ];

        $reflect = new ReflectionClass(self::class);
        $props   = $reflect->getProperties();

        foreach ($props as $prop) {
            if (in_array($prop->getName(), $skipProps)) {
                continue;
            }
            if (!isset($data[$prop->getName()])) {
                continue;
            }
            $prop->setAccessible(true);
            $prop->setValue($this, $data[$prop->getName()]);
        }
        return true;
    }

    /**
     * Set data from import data
     *
     * @param object $global_data Global data
     *
     * @return void
     */
    public function setFromImportData($global_data)
    {
        $this->basic_auth_password = $global_data->basic_auth_password;
        // skip in import settings
        //$this->lkp                 = $global_data->lkp;
    }
}
