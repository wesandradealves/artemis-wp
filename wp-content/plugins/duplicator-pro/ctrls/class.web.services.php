<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

use Duplicator\Ajax\AbstractAjaxService;
use Duplicator\Ajax\AjaxWrapper;
use Duplicator\Ajax\ServicesDashboard;
use Duplicator\Ajax\ServicesImport;
use Duplicator\Ajax\ServicesNotifications;
use Duplicator\Ajax\ServicesRecovery;
use Duplicator\Ajax\ServicesSchedule;
use Duplicator\Ajax\ServicesSettings;
use Duplicator\Ajax\ServicesStorage;
use Duplicator\Core\CapMng;
use Duplicator\Core\MigrationMng;
use Duplicator\Libs\Snap\SnapIO;
use Duplicator\Libs\Snap\SnapJson;
use Duplicator\Libs\Snap\SnapURL;
use Duplicator\Models\BrandEntity;
use Duplicator\Models\Storages\AbstractStorageEntity;
use Duplicator\Models\Storages\StoragesUtil;
use Duplicator\Models\SystemGlobalEntity;
use Duplicator\Utils\ExpireOptions;
use Duplicator\Utils\Settings\MigrateSettings;
use Duplicator\Utils\ZipArchiveExtended;
use Duplicator\Views\AdminNotices;
use VendorDuplicator\Amk\JsonSerialize\JsonSerialize;

/**
 * WEB Service Execution Status
 */
abstract class DUP_PRO_Web_Service_Execution_Status
{
    const Pass            = 1;
    const Warn            = 2;
    const Fail            = 3;
    const Incomplete      = 4; // Still more to go
    const ScheduleRunning = 5;
}

/**
 * DUPLICATOR_PRO_WEB_SERVICES
 */
class DUP_PRO_Web_Services extends AbstractAjaxService
{
    /**
     * Init ajax hooks
     *
     * @return void
     */
    public function init()
    {
        $importServices = new ServicesImport();
        $importServices->init();
        $recoveryService = new ServicesRecovery();
        $recoveryService->init();
        $scheduleService = new ServicesSchedule();
        $scheduleService->init();
        $storageService = new ServicesStorage();
        $storageService->init();
        $dashboardService = new ServicesDashboard();
        $dashboardService->init();
        $settingsService = new ServicesSettings();
        $settingsService->init();
        $notificationsService = new ServicesNotifications();
        $notificationsService->init();

        $this->addAjaxCall('wp_ajax_duplicator_pro_package_scan', 'duplicator_pro_package_scan');
        $this->addAjaxCall('wp_ajax_duplicator_pro_package_delete', 'duplicator_pro_package_delete');
        $this->addAjaxCall('wp_ajax_duplicator_pro_reset_user_settings', 'duplicator_pro_reset_user_settings');
        $this->addAjaxCall('wp_ajax_duplicator_pro_reset_packages', 'duplicator_pro_reset_packages');

        $this->addAjaxCall('wp_ajax_duplicator_pro_get_trace_log', 'get_trace_log');
        $this->addAjaxCall('wp_ajax_duplicator_pro_delete_trace_log', 'delete_trace_log');
        $this->addAjaxCall('wp_ajax_duplicator_pro_get_package_statii', 'get_package_statii');
        $this->addAjaxCall('wp_ajax_duplicator_pro_get_package_status', 'duplicator_pro_get_package_status');
        $this->addAjaxCall('wp_ajax_duplicator_pro_get_package_log', 'get_package_log');
        $this->addAjaxCall('wp_ajax_duplicator_pro_get_package_delete', 'duplicator_pro_get_package_delete');
        $this->addAjaxCall('wp_ajax_duplicator_pro_is_pack_running', 'is_pack_running');

        $this->addAjaxCall('wp_ajax_duplicator_pro_process_worker', 'process_worker');
        $this->addAjaxCall('wp_ajax_nopriv_duplicator_pro_process_worker', 'process_worker');

        $this->addAjaxCall('wp_ajax_duplicator_pro_manual_transfer_storage', 'manual_transfer_storage');

        /* Screen-Specific Web Methods */
        $this->addAjaxCall('wp_ajax_duplicator_pro_packages_details_transfer_get_package_vm', 'packages_details_transfer_get_package_vm');

        /* Granular Web Methods */
        $this->addAjaxCall('wp_ajax_duplicator_pro_package_stop_build', 'package_stop_build');
        $this->addAjaxCall('wp_ajax_duplicator_pro_export_settings', 'export_settings');

        $this->addAjaxCall('wp_ajax_duplicator_pro_brand_delete', 'duplicator_pro_brand_delete');

        /* Quick Fix */
        $this->addAjaxCall('wp_ajax_duplicator_pro_quick_fix', 'duplicator_pro_quick_fix');

        /* Dir scan utils */
        $this->addAjaxCall('wp_ajax_duplicator_pro_get_folder_children', 'duplicator_pro_get_folder_children');

        $this->addAjaxCall('wp_ajax_duplicator_pro_restore_backup_prepare', 'duplicator_pro_restore_backup_prepare');

        $this->addAjaxCall('wp_ajax_duplicator_pro_admin_notice_to_dismiss', 'admin_notice_to_dismiss');

        $this->addAjaxCall('wp_ajax_duplicator_pro_download_package_file', 'download_package_file');
        $this->addAjaxCall('wp_ajax_nopriv_duplicator_pro_download_package_file', 'download_package_file');
    }

    /**
     * Restore backup prepare callback
     *
     * @return string
     */
    public function duplicator_pro_restore_backup_prepare_callback()
    {
        $packageId = filter_input(INPUT_POST, 'packageId', FILTER_VALIDATE_INT);
        if (!$packageId) {
            throw new Exception('Invalid package ID in request.');
        }
        $result = array();

        if (($package = DUP_PRO_Package::get_by_id($packageId)) === false) {
            throw new Exception(DUP_PRO_U::esc_html__('Invalid package ID'));
        }
        $updDirs = wp_upload_dir();

        $result = DUPLICATOR_PRO_SSDIR_URL . '/' . $package->Installer->getInstallerName() . '?dup_folder=dupinst_' . $package->Hash;

        $installerParams = array(
            'inst_mode'              => array('value' => 2 ), // mode restore backup
            'url_old'                => array('formStatus' => "st_skip"),
            'url_new'                => array(
                'value'      => DUP_PRO_Archive::getOriginalUrls('home'),
                'formStatus' => "st_infoonly",
            ),
            'path_old'               => array('formStatus' => "st_skip"),
            'path_new'               => array(
                'value'      => duplicator_pro_get_home_path(),
                'formStatus' => "st_infoonly",
            ),
            'dbaction'               => array(
                'value'      => 'empty',
                'formStatus' => "st_infoonly",
            ),
            'dbhost'                 => array(
                'value'      => DB_HOST,
                'formStatus' => "st_infoonly",
            ),
            'dbname'                 => array(
                'value'      => DB_NAME,
                'formStatus' => "st_infoonly",
            ),
            'dbuser'                 => array(
                'value'      => DB_USER,
                'formStatus' => "st_infoonly",
            ),
            'dbpass'                 => array(
                'value'      => DB_PASSWORD,
                'formStatus' => "st_infoonly",
            ),
            'dbtest_ok'              => array('value' => true),
            'siteurl_old'            => array('formStatus' => "st_skip"),
            'siteurl'                => array(
                'value'      => 'site_url',
                'formStatus' => "st_skip",
            ),
            'path_cont_old'          => array('formStatus' => "st_skip"),
            'path_cont_new'          => array(
                'value'      => WP_CONTENT_DIR,
                'formStatus' => "st_skip",
            ),
            'path_upl_old'           => array('formStatus' => "st_skip"),
            'path_upl_new'           => array(
                'value'      => $updDirs['basedir'],
                'formStatus' => "st_skip",
            ),
            'url_cont_old'           => array('formStatus' => "st_skip"),
            'url_cont_new'           => array(
                'value'      => content_url(),
                'formStatus' => "st_skip",
            ),
            'url_upl_old'            => array('formStatus' => "st_skip"),
            'url_upl_new'            => array(
                'value'      => $updDirs['baseurl'],
                'formStatus' => "st_skip",
            ),
            'exe_safe_mode'          => array('formStatus' => "st_skip"),
            'remove-redundant'       => array('formStatus' => "st_skip"),
            'blogname'               => array('formStatus' => "st_infoonly"),
            'replace_mode'           => array('formStatus' => "st_skip"),
            'empty_schedule_storage' => array(
                'value'      => false,
                'formStatus' => "st_skip",
            ),
            'wp_config'              => array(
                'value'      => 'original',
                'formStatus' => "st_infoonly",
            ),
            'ht_config'              => array(
                'value'      => 'original',
                'formStatus' => "st_infoonly",
            ),
            'other_config'           => array(
                'value'      => 'original',
                'formStatus' => "st_infoonly",
            ),
            'zip_filetime'           => array(
                'value'      => 'original',
                'formStatus' => "st_infoonly",
            ),
            'mode_chunking'          => array(
                'value'      => 3,
                'formStatus' => "st_infoonly",
            ),
        );
        $localParamsFile = DUPLICATOR_PRO_SSDIR_PATH . '/' . DUPLICATOR_PRO_LOCAL_OVERWRITE_PARAMS . '_' . $package->get_package_hash() . '.json';
        file_put_contents($localParamsFile, SnapJson::jsonEncodePPrint($installerParams));

        return $result;
    }

    /**
     * Hook ajax restore backup prepare
     *
     * @return void
     */
    public function duplicator_pro_restore_backup_prepare()
    {
        AjaxWrapper::json(
            array(
                __CLASS__,
                'duplicator_pro_restore_backup_prepare_callback',
            ),
            'duplicator_pro_restore_backup_prepare',
            $_POST['nonce'],
            CapMng::CAP_BACKUP_RESTORE
        );
    }

    /**
     * Hook ajax process worker
     *
     * @return never
     */
    public function process_worker()
    {
        DUP_PRO_Handler::init_error_handler();
        DUP_PRO_U::checkAjax();
        header("HTTP/1.1 200 OK");

        /*
          $nonce = sanitize_text_field($_REQUEST['nonce']);
          if (!wp_verify_nonce($nonce, 'duplicator_pro_process_worker')) {
          DUP_PRO_Log::trace('Security issue');
          die('Security issue');
          }
         */

        DUP_PRO_Log::trace("Process worker request");

        DUP_PRO_Package_Runner::process();

        DUP_PRO_Log::trace("Exiting process worker request");

        echo 'ok';
        exit();
    }

    /**
     * Hook ajax manual transfer storage
     *
     * @return never
     */
    public function manual_transfer_storage()
    {
        DUP_PRO_Handler::init_error_handler();
        check_ajax_referer('duplicator_pro_manual_transfer_storage', 'nonce');

        $json      = array(
            'success' => false,
            'message' => '',
        );
        $isValid   = true;
        $inputData = filter_input_array(INPUT_POST, array(
            'package_id'  => array(
                'filter'  => FILTER_VALIDATE_INT,
                'flags'   => FILTER_REQUIRE_SCALAR,
                'options' => array('default' => false),
            ),
            'storage_ids' => array(
                'filter'  => FILTER_VALIDATE_INT,
                'flags'   => FILTER_REQUIRE_ARRAY,
                'options' => array('default' => false),
            ),
        ));

        $package_id   = $inputData['package_id'];
        $storage_ids  = $inputData['storage_ids'];
        $json['data'] = $inputData;
        if (!$package_id || !$storage_ids) {
            $isValid = false;
        }

        try {
            if (!CapMng::can(CapMng::CAP_STORAGE, false) && !CapMng::can(CapMng::CAP_CREATE, false)) {
                throw new Exception('Security issue.');
            }
            if (!$isValid) {
                throw new Exception(DUP_PRO_U::__("Invalid request."));
            }

            if (DUP_PRO_Package::isPackageRunning()) {
                throw new Exception(DUP_PRO_U::__("Trying to queue a transfer for package $package_id but a package is already active!"));
            }

            $package = DUP_PRO_Package::get_by_id($package_id);
            DUP_PRO_Log::open($package->NameHash);

            if (!$package) {
                throw new Exception(sprintf(DUP_PRO_U::esc_html__('Could not find package ID %d!'), $package_id));
            }

            if (empty($storage_ids)) {
                throw new Exception("Please select a storage.");
            }

            $info  = "\n";
            $info .= "********************************************************************************\n";
            $info .= "********************************************************************************\n";
            $info .= "PACKAGE MANUAL TRANSFER REQUESTED: " . @date("Y-m-d H:i:s") . "\n";
            $info .= "********************************************************************************\n";
            $info .= "********************************************************************************\n\n";
            DUP_PRO_Log::infoTrace($info);

            foreach ($storage_ids as $storage_id) {
                if (($storage = AbstractStorageEntity::getById($storage_id)) === false) {
                    throw new Exception(sprintf(DUP_PRO_U::__('Could not find storage ID %d!'), $storage_id));
                }

                DUP_PRO_Log::infoTrace(
                    'Storage adding to the package "' . $package->Name .
                    ' [Package Id: ' . $package_id . ']":: Storage Id: "' . $storage_id .
                    '" Storage Name: "' . esc_html($storage->getName()) .
                    '" Storage Type: "' . esc_html($storage->getStypeName()) . '"'
                );

                $upload_info = new DUP_PRO_Package_Upload_Info($storage_id);
                array_push($package->upload_infos, $upload_info);
            }

            $package->set_status(DUP_PRO_PackageStatus::STORAGE_PROCESSING);
            $package->timer_start = DUP_PRO_U::getMicrotime();

            $json['success'] = true;

            $package->update();
        } catch (Exception $ex) {
            $json['message'] = $ex->getMessage();
            DUP_PRO_Log::trace($ex->getMessage());
        }

        DUP_PRO_Log::close();

        die(SnapJson::jsonEncode($json));
    }

    /**
     *  DUPLICATOR_PRO_PACKAGE_SCAN
     *
     *  @example to test: /wp-admin/admin-ajax.php?action=duplicator_pro_package_scan
     *
     *  @return never
     */
    public function duplicator_pro_package_scan()
    {
        DUP_PRO_Handler::init_error_handler();
        try {
            CapMng::can(CapMng::CAP_CREATE);
            $global = DUP_PRO_Global_Entity::getInstance();

            // Should be used $_REQUEST sometimes it gets in _GET and sometimes in _POST
            check_ajax_referer('duplicator_pro_package_scan', 'nonce');
            header('Content-Type: application/json');
            @ob_flush();

            $json     = array();
            $errLevel = error_reporting();

            // Keep the locking file opening and closing just to avoid adding even more complexity
            $locking_file = true;
            if ($global->lock_mode == DUP_PRO_Thread_Lock_Mode::Flock) {
                $locking_file = fopen(DUPLICATOR_PRO_LOCKING_FILE_FILENAME, 'c+');
            }

            if ($locking_file != false) {
                if ($global->lock_mode == DUP_PRO_Thread_Lock_Mode::Flock) {
                    $acquired_lock = (flock($locking_file, LOCK_EX | LOCK_NB) != false);
                    if ($acquired_lock) {
                        DUP_PRO_Log::trace("File lock acquired " . DUPLICATOR_PRO_LOCKING_FILE_FILENAME);
                    } else {
                        DUP_PRO_Log::trace("File lock denied " . DUPLICATOR_PRO_LOCKING_FILE_FILENAME);
                    }
                } else {
                    $acquired_lock = DUP_PRO_U::getSqlLock();
                }

                if ($acquired_lock) {
                    @set_time_limit(0);
                    error_reporting(E_ERROR);
                    StoragesUtil::getDefaultStorage()->initStorageDirectory(true);

                    $package     = DUP_PRO_Package::get_temporary_package();
                    $package->ID = null;
                    $report      = $package->create_scan_report();
                    //After scanner runs save FilterInfo (unreadable, warnings, globals etc)
                    $package->set_temporary_package();

                    //delif($package->Archive->ScanStatus == DUP_PRO_Archive::ScanStatusComplete){
                    $report['Status'] = DUP_PRO_Web_Service_Execution_Status::Pass;

                    // The package has now been corrupted with directories and scans so cant reuse it after this point
                    DUP_PRO_Package::set_temporary_package_member('ScanFile', $package->ScanFile);
                    DUP_PRO_Package::tmp_cleanup();
                    DUP_PRO_Package::set_temporary_package_member('Status', DUP_PRO_PackageStatus::AFTER_SCAN);

                    //del}

                    if ($global->lock_mode == DUP_PRO_Thread_Lock_Mode::Flock) {
                        if (!flock($locking_file, LOCK_UN)) {
                            DUP_PRO_Log::trace("File lock can't release " . $locking_file);
                        } else {
                            DUP_PRO_Log::trace("File lock released " . $locking_file);
                        }
                        fclose($locking_file);
                    } else {
                        DUP_PRO_U::releaseSqlLock();
                    }
                } else {
                    // File is already locked indicating schedule is running
                    $report['Status'] = DUP_PRO_Web_Service_Execution_Status::ScheduleRunning;
                    DUP_PRO_Log::trace("Already locked when attempting manual build - schedule running");
                }
            } else {
                // Problem opening the locking file report this is a critical error
                $report['Status'] = DUP_PRO_Web_Service_Execution_Status::Fail;

                DUP_PRO_Log::trace("Problem opening locking file so auto switching to SQL lock mode");
                $global->lock_mode = DUP_PRO_Thread_Lock_Mode::SQL_Lock;
                $global->save();
            }
        } catch (Exception $ex) {
            $data = array(
                'Status'  =>  3,
                'Message' => sprintf(DUP_PRO_U::__("Exception occurred. Exception message: %s"), $ex->getMessage()),
                'File'    => $ex->getFile(),
                'Line'    => $ex->getLine(),
                'Trace'   => $ex->getTrace(),
            );
            die(json_encode($data));
        } catch (Error $ex) {
            $data = array(
                'Status'  =>  3,
                'Message' =>  sprintf(
                    DUP_PRO_U::esc_html__("Fatal Error occurred. Error message: %s<br>\nTrace: %s"),
                    $ex->getMessage(),
                    $ex->getTraceAsString()
                ),
                'File'    => $ex->getFile(),
                'Line'    => $ex->getLine(),
                'Trace'   => $ex->getTrace(),
            );
            die(json_encode($data));
        }

        try {
            if (($json = JsonSerialize::serialize($report, JSON_PRETTY_PRINT | JsonSerialize::JSON_SKIP_CLASS_NAME)) === false) {
                throw new Exception('Problem encoding json');
            }
        } catch (Exception $ex) {
            $data = array(
                'Status'  =>  3,
                'Message' =>  sprintf(DUP_PRO_U::esc_html__("Fatal Error occurred. Error message: %s"), $ex->getMessage()),
                'File'    => $ex->getFile(),
                'Line'    => $ex->getLine(),
                'Trace'   => $ex->getTrace(),
            );
            die(json_encode($data));
        }

        error_reporting($errLevel);
        die($json);
    }

    /**
     * Return scan error message
     *
     * @return string
     */
    public static function getScanErrorMessage()
    {
        return '<br><b>' . DUP_PRO_U::__("Please Retry:") . '</b><br/>'
            . DUP_PRO_U::__("Unable to perform a full scan and read JSON file, please try the following actions.") . '<br/>'
            . DUP_PRO_U::__("1. Go back and create a root path directory filter to validate the site is scan-able.") . '<br/>'
            . DUP_PRO_U::__("2. Continue to add/remove filters to isolate which path is causing issues.") . '<br/>'
            . DUP_PRO_U::__("3. This message will go away once the correct filters are applied.") . '<br/><br/>'
            . '<b>' . DUP_PRO_U::__("Common Issues:") . '</b><br/>'
            . DUP_PRO_U::__("- On some budget hosts scanning over 30k files can lead to timeout/gateway issues. "
                . "Consider scanning only your main WordPress site and avoid trying to backup other external directories.") . '<br/>'
            . DUP_PRO_U::__("- Symbolic link recursion can cause timeouts.  Ask your server admin if any are present in the scan path. "
                . "If they are add the full path as a filter and try running the scan again.") . '<br/><br/>'
            . '<b>' . DUP_PRO_U::__("Details:") . '</b><br/>'
            . DUP_PRO_U::__("JSON Service:") . ' /wp-admin/admin-ajax.php?action=duplicator_pro_package_scan<br/>'
            . DUP_PRO_U::__("Scan Path:") . '[' . duplicator_pro_get_home_path() . ']<br/><br/>'
            . '<b>' . DUP_PRO_U::__("More Information:") . '</b><br/>'
            . sprintf(
                DUP_PRO_U::__('Please see the online FAQ titled <a href="%s" target="_blank">"How to resolve scanner warnings/errors and timeout issues?"</a>'),
                DUPLICATOR_PRO_DUPLICATOR_DOCS_URL . "how-to-resolve-scanner-warnings-errors-and-timeout-issues"
            );
    }

    /**
     * DUPLICATOR_PRO_QUICK_FIX
     * Set default quick fix values automaticaly to help user
     *
     * @return never
     */
    public function duplicator_pro_quick_fix()
    {
        DUP_PRO_Handler::init_error_handler();
        check_ajax_referer('duplicator_pro_quick_fix', 'nonce');

        $json      = array(
            'success' => false,
            'message' => '',
        );
        $isValid   = true;
        $inputData = filter_input_array(INPUT_POST, array(
            'id'    => array(
                'filter'  => FILTER_SANITIZE_SPECIAL_CHARS,
                'flags'   => FILTER_REQUIRE_SCALAR,
                'options' => array('default' => false),
            ),
            'setup' => array(
                'filter'  => FILTER_SANITIZE_SPECIAL_CHARS,
                'flags'   => FILTER_REQUIRE_ARRAY,
                'options' => array('default' => false),
            ),
        ));
        $setup     = $inputData['setup'];
        $id        = $inputData['id'];

        if (!$id || empty($setup)) {
            $isValid = false;
        }
        //END OF VALIDATION

        try {
            CapMng::can(CapMng::CAP_BASIC);
            if (!$isValid) {
                throw new Exception(DUP_PRO_U::__("Invalid request."));
            }

            $data      = array();
            $isSpecial = isset($setup['special']) && is_array($setup['special']) && count($setup['special']) > 0;

            /* ****************
             *  GENERAL SETUP
             * **************** */
            if (isset($setup['global']) && is_array($setup['global'])) {
                $global = DUP_PRO_Global_Entity::getInstance();

                foreach ($setup['global'] as $object => $value) {
                    $value = DUP_PRO_U::valType($value);
                    if (isset($global->$object)) {
                        // Get current setup
                        $current = $global->$object;

                        // If setup is not the same - fix this
                        if ($current !== $value) {
                            // Set new value
                            $global->$object = $value;
                            // Check value
                            $data[$object] = $global->$object;
                        }
                    }
                }
                $global->save();
            }

            /* ****************
             *  SPECIAL SETUP
             * **************** */
            if ($isSpecial) {
                $special              = $setup['special'];
                $stuck5percent        = isset($special['stuck_5percent_pending_fix']) && $special['stuck_5percent_pending_fix'] == 1;
                $basicAuth            = isset($special['set_basic_auth']) && $special['set_basic_auth'] == 1;
                $removeInstallerFiles = isset($special['remove_installer_files']) && $special['remove_installer_files'] == 1;
                /**
                 * SPECIAL FIX: Package build stuck at 5% or Pending?
                 * */
                if ($stuck5percent) {
                    $data = array_merge($data, $this->special_quick_fix_stuck_5_percent());
                }

                /**
                 * SPECIAL FIX: Set basic auth username & password
                 * */
                if ($basicAuth) {
                    $data = array_merge($data, $this->special_quick_fix_basic_auth());
                }

                /**
                 * SPECIAL FIX: Remove installer files
                 * */
                if ($removeInstallerFiles) {
                    $data = array_merge($data, $this->special_quick_fix_remove_installer_files());
                }
            }

            // Save new property
            $find = count($data);
            if ($find > 0) {
                $system_global = SystemGlobalEntity::getInstance();
                if (strlen($id) > 0) {
                    $system_global->removeFixById($id);
                    $json['id'] = $id;
                }

                $json['success']           = true;
                $json['setup']             = $data;
                $json['fixed']             = $find;
                $json['recommended_fixes'] = count($system_global->recommended_fixes);
            }
        } catch (Exception $ex) {
            $json['message'] = $ex->getMessage();
            DUP_PRO_Log::trace("Error while implementing quick fix: " . $ex->getMessage());
        }

        die(SnapJson::jsonEncode($json));
    }

    /**
     * Quick fix for removing installer files
     *
     * @return array{removed_installer_files:bool} $data
     */
    private function special_quick_fix_remove_installer_files()
    {
        $data        = array();
        $fileRemoved = MigrationMng::cleanMigrationFiles();
        $removeError = false;
        if (count($fileRemoved) > 0) {
            $data['removed_installer_files'] = true;
        } else {
            throw new Exception(DUP_PRO_U::esc_html__("Unable to remove installer files."));
        }
        return $data;
    }

    /**
     * Quick fix for stuck at 5% or pending
     *
     * @return array<string, mixed> $data
     */
    private function special_quick_fix_stuck_5_percent()
    {
        $global = DUP_PRO_Global_Entity::getInstance();

        $data    = array();
        $kickoff = true;
        $custom  = false;

        if ($global->ajax_protocol === 'custom') {
            $custom = true;
        }

        // Do things if SSL is active
        if (SnapURL::isCurrentUrlSSL()) {
            if ($custom) {
                // Set default admin ajax
                $custom_ajax_url = admin_url('admin-ajax.php', 'https');
                if ($global->custom_ajax_url != $custom_ajax_url) {
                    $global->custom_ajax_url = $custom_ajax_url;
                    $data['custom_ajax_url'] = $global->custom_ajax_url;
                    $kickoff                 = false;
                }
            } else {
                // Set HTTPS protocol
                if ($global->ajax_protocol === 'http') {
                    $global->ajax_protocol = 'https';
                    $data['ajax_protocol'] = $global->ajax_protocol;
                    $kickoff               = false;
                }
            }
        } else {
            // SSL is OFF and we must handle that
            if ($custom) {
                // Set default admin ajax
                $custom_ajax_url = admin_url('admin-ajax.php', 'http');
                if ($global->custom_ajax_url != $custom_ajax_url) {
                    $global->custom_ajax_url = $custom_ajax_url;
                    $data['custom_ajax_url'] = $global->custom_ajax_url;
                    $kickoff                 = false;
                }
            } else {
                // Set HTTP protocol
                if ($global->ajax_protocol === 'https') {
                    $global->ajax_protocol = 'http';
                    $data['ajax_protocol'] = $global->ajax_protocol;
                    $kickoff               = false;
                }
            }
        }

        // Set KickOff true if all setups are gone
        if ($kickoff) {
            if ($global->clientside_kickoff !== true) {
                $global->clientside_kickoff = true;
                $data['clientside_kickoff'] = $global->clientside_kickoff;
            }
        }

        $global->save();
        return $data;
    }

    /**
     * Quick fix for basic auth
     *
     * @return array{basic_auth_enabled:bool,basic_auth_user:string,basic_auth_password:string}
     */
    private function special_quick_fix_basic_auth()
    {
        $global   = DUP_PRO_Global_Entity::getInstance();
        $sglobal  = DUP_PRO_Secure_Global_Entity::getInstance();
        $username = isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : false;
        $password = isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : false;
        if ($username === false || $password === false) {
            throw new Exception(DUP_PRO_U::esc_html__("Username or password were not set."));
        }

        $data                       = array();
        $global->basic_auth_enabled = true;
        $data['basic_auth_enabled'] = true;

        $global->basic_auth_user = $username;
        $data['basic_auth_user'] = $username;

        $sglobal->basic_auth_password = $password;
        $data['basic_auth_password']  = "**Secure Info**";

        $global->save();
        $sglobal->save();

        return $data;
    }

    /**
     * Hook ajax wp_ajax_duplicator_pro_brand_delete
     *
     * @return never
     */
    public function duplicator_pro_brand_delete()
    {
        DUP_PRO_Handler::init_error_handler();
        check_ajax_referer('duplicator_pro_brand_delete', 'nonce');

        $json      = array(
            'success' => false,
            'message' => '',
        );
        $isValid   = true;
        $inputData = filter_input_array(INPUT_POST, array(
            'brand_ids' => array(
                'filter'  => FILTER_VALIDATE_INT,
                'flags'   => FILTER_REQUIRE_ARRAY,
                'options' => array('default' => false),
            ),
        ));
        $brandIDs  = $inputData['brand_ids'];
        $delCount  = 0;

        if (empty($brandIDs) || in_array(false, $brandIDs)) {
            $isValid = false;
        }

        try {
            CapMng::can(CapMng::CAP_CREATE);
            if (!$isValid) {
                throw new Exception(DUP_PRO_U::__('Invalid Request.'));
            }

            foreach ($brandIDs as $id) {
                $brand = BrandEntity::deleteById($id);
                if ($brand) {
                    $delCount++;
                }
            }

            $json['success'] = true;
            $json['ids']     = $brandIDs;
            $json['removed'] = $delCount;
        } catch (Exception $e) {
            $json['message'] = $e->getMessage();
        }

        die(SnapJson::jsonEncode($json));
    }

    /**
     * Hook ajax wp_ajax_duplicator_pro_package_delete
     * Deletes the files and database record entries
     *
     * @return never
     */
    public function duplicator_pro_package_delete()
    {
        DUP_PRO_Handler::init_error_handler();
        check_ajax_referer('duplicator_pro_package_delete', 'nonce');

        $json         = array(
            'error'   => '',
            'ids'     => '',
            'removed' => 0,
        );
        $isValid      = true;
        $deletedCount = 0;

        $inputData     = filter_input_array(INPUT_POST, array(
            'package_ids' => array(
                'filter'  => FILTER_VALIDATE_INT,
                'flags'   => FILTER_REQUIRE_ARRAY,
                'options' => array('default' => false),
            ),
        ));
        $packageIDList = $inputData['package_ids'];

        if (empty($packageIDList) || in_array(false, $packageIDList)) {
            $isValid = false;
        }
        //END OF VALIDATION

        try {
            CapMng::can(CapMng::CAP_CREATE);
            if (!$isValid) {
                throw new Exception(DUP_PRO_U::__("Invalid request."));
            }

            DUP_PRO_Log::traceObject("Starting deletion of packages by ids: ", $packageIDList);
            foreach ($packageIDList as $id) {
                if ($package = DUP_PRO_Package::get_by_id($id)) {
                    if ($package->delete()) {
                        $deletedCount++;
                    }
                } else {
                    $json['error'] = "Invalid package ID.";
                    break;
                }
            }
        } catch (Exception $ex) {
            $json['error'] = $ex->getMessage();
        }

        $json['ids']     = $packageIDList;
        $json['removed'] = $deletedCount;
        die(SnapJson::jsonEncode($json));
    }

    /**
     * Hook ajax wp_ajax_duplicator_pro_reset_user_settings
     * Resets user settings to default
     *
     *  @return never
     */
    public function duplicator_pro_reset_user_settings()
    {
        ob_start();
        try {
            DUP_PRO_Handler::init_error_handler();

            $error  = false;
            $result = array(
                'data'    => array('status' => null),
                'html'    => '',
                'message' => '',
            );

            $nonce = sanitize_text_field($_POST['nonce']);
            if (!wp_verify_nonce($nonce, 'duplicator_pro_reset_user_settings')) {
                DUP_PRO_Log::trace('Security issue');
                throw new Exception('Security issue');
            }
            CapMng::can(CapMng::CAP_SETTINGS);

            $global = DUP_PRO_Global_Entity::getInstance();
            $global->resetUserSettings();
            $global->save();

            ExpireOptions::set(
                DUPLICATOR_PRO_SETTINGS_MESSAGE_TRANSIENT,
                DUP_PRO_U::__('Settings reset to defaults successfully'),
                DUPLICATOR_PRO_SETTINGS_MESSAGE_TIMEOUT
            );
        } catch (Exception $e) {
            $error             = true;
            $result['message'] = $e->getMessage();
        }

        $result['html'] = ob_get_clean();
        if ($error) {
            wp_send_json_error($result);
        } else {
            wp_send_json_success($result);
        }
    }

    /**
     * Hook ajax wp_ajax_duplicator_pro_reset_packages
     *
     * @return never
     */
    public function duplicator_pro_reset_packages()
    {
        ob_start();
        try {
            DUP_PRO_Handler::init_error_handler();

            $error  = false;
            $result = array(
                'data'    => array('status' => null),
                'html'    => '',
                'message' => '',
            );

            $nonce = sanitize_text_field($_POST['nonce']);
            if (!wp_verify_nonce($nonce, 'duplicator_pro_reset_packages')) {
                DUP_PRO_Log::trace('Security issue');
                throw new Exception('Security issue');
            }
            CapMng::can(CapMng::CAP_SETTINGS);

            // first last package id
            $ids = DUP_PRO_Package::get_ids_by_status(array(array('op' => '<', 'status' => DUP_PRO_PackageStatus::COMPLETE)), false, 0, '`id` DESC');
            foreach ($ids as $id) {
                // A smooth deletion is not performed because it is a forced reset.
                DUP_PRO_Package::force_delete($id);
            }
        } catch (Exception $e) {
            $error             = true;
            $result['message'] = $e->getMessage();
        }

        $result['html'] = ob_get_clean();
        if ($error) {
            wp_send_json_error($result);
        } else {
            wp_send_json_success($result);
        }
    }

    /**
     * Hook ajax wp_ajax_duplicator_pro_get_trace_log
     *
     * @return never
     */
    public function get_trace_log()
    {
        /**
         * don't init DUP_PRO_Handler::init_error_handler() in get trace
         */
        check_ajax_referer('duplicator_pro_get_trace_log', 'nonce');
        DUP_PRO_Log::trace("enter");

        $file_path   = DUP_PRO_Log::getTraceFilepath();
        $backup_path = DUP_PRO_Log::getBackupTraceFilepath();
        $zip_path    = DUPLICATOR_PRO_SSDIR_PATH . "/" . DUP_PRO_Constants::ZIPPED_LOG_FILENAME;

        try {
            CapMng::can(CapMng::CAP_CREATE);

            if (file_exists($zip_path)) {
                SnapIO::unlink($zip_path);
            }
            $zipArchive = new ZipArchiveExtended($zip_path);

            if ($zipArchive->open() == false) {
                throw new Exception('Can\'t open ZIP archive');
            }

            if ($zipArchive->addFile($file_path, basename($file_path)) == false) {
                throw new Exception('Can\'t add ZIP file ');
            }

            if (file_exists($backup_path) && $zipArchive->addFile($backup_path, basename($backup_path)) == false) {
                throw new Exception('Can\'t add ZIP file ');
            }

            $zipArchive->close();

            if (($fp = fopen($zip_path, 'rb')) === false) {
                throw new Exception('Can\'t open ZIP archive');
            }

            $zip_filename = basename($zip_path);

            header("Pragma: public");
            header("Expires: 0");
            header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
            header("Cache-Control: private", false);
            header("Content-Transfer-Encoding: binary");
            header("Content-Type: application/octet-stream");
            header("Content-Disposition: attachment; filename=\"$zip_filename\";");

            // required or large files wont work
            if (ob_get_length()) {
                ob_end_clean();
            }

            DUP_PRO_Log::trace("streaming $zip_path");
            fpassthru($fp);
            fclose($fp);
            @unlink($zip_path);
        } catch (Exception $e) {
            header("Content-Type: text/plain");
            header("Content-Disposition: attachment; filename=\"error.txt\";");
            $message = 'Create Log Zip error message: ' . $e->getMessage();
            DUP_PRO_Log::trace($message);
            echo esc_html($message);
        }
        die();
    }

    /**
     * Hook ajax wp_ajax_duplicator_pro_delete_trace_log
     *
     * @return never
     */
    public function delete_trace_log()
    {
        /**
         * don't init DUP_PRO_Handler::init_error_handler() in get trace
         */
        check_ajax_referer('duplicator_pro_delete_trace_log', 'nonce');
        CapMng::can(CapMng::CAP_CREATE);

        $res = DUP_PRO_Log::deleteTraceLog();
        if ($res) {
            wp_send_json_success();
        } else {
            wp_send_json_error();
        }
    }

    /**
     * Hook ajax wp_ajax_duplicator_pro_export_settings
     *
     * @return never
     */
    public function export_settings()
    {
        DUP_PRO_Handler::init_error_handler();
        check_ajax_referer('duplicator_pro_import_export_settings', 'nonce');

        try {
            DUP_PRO_Log::trace("Export settings start");
            CapMng::can(CapMng::CAP_SETTINGS);

            $message = '';

            if (($filePath = MigrateSettings::export($message)) === false) {
                throw new Exception($message);
            }

            DUP_PRO_U::getDownloadAttachment($filePath, 'application/octet-stream');
        } catch (Exception $ex) {
            // RSR TODO: set the error message to this $this->message = 'Error processing with export:' .  $e->getMessage();
            header("Content-Type: text/plain");
            header("Content-Disposition: attachment; filename=\"error.txt\";");
            $message = DUP_PRO_U::__("{$ex->getMessage()}");
            DUP_PRO_Log::trace($message);
            echo esc_html($message);
        }
        die();
    }

    /**
     * Hook ajax wp_ajax_duplicator_pro_package_stop_build
     *
     * @return never
     */
    public function package_stop_build()
    {
        DUP_PRO_Handler::init_error_handler();
        check_ajax_referer('duplicator_pro_package_stop_build', 'nonce');

        CapMng::can(CapMng::CAP_CREATE);

        $json       = array(
            'success' => false,
            'message' => '',
        );
        $isValid    = true;
        $inputData  = filter_input_array(INPUT_POST, array(
            'package_id' => array(
                'filter'  => FILTER_VALIDATE_INT,
                'flags'   => FILTER_REQUIRE_SCALAR,
                'options' => array('default' => false),
            ),
        ));
        $package_id = $inputData['package_id'];

        if (!$package_id) {
            $isValid = false;
        }

        try {
            if (!$isValid) {
                throw new Exception('Invalid request.');
            }

            DUP_PRO_Log::trace("Web service stop build of $package_id");
            $package = DUP_PRO_Package::get_by_id($package_id);

            if ($package == null) {
                DUP_PRO_Log::trace("could not find package so attempting hard delete. Old files may end up sticking around although chances are there isnt much if we couldnt nicely cancel it.");
                $result = DUP_PRO_Package::force_delete($package_id);

                if ($result) {
                    $json['message'] = 'Hard delete success';
                    $json['success'] = true;
                } else {
                    throw new Exception('Hard delete failure');
                }
            } else {
                DUP_PRO_Log::trace("set $package->ID for cancel");
                $package->set_for_cancel();
                $json['success'] = true;
            }
        } catch (Exception $ex) {
            DUP_PRO_Log::trace($ex->getMessage());
            $json['message'] = $ex->getMessage();
        }

        die(SnapJson::jsonEncode($json));
    }

    /**
     * Hook ajax handler for packages_details_transfer_get_package_vm
     * Retrieve view model for the Packages/Details/Transfer screen
     * active_package_id: true/false
     * percent_text: Percent through the current transfer
     * text: Text to display
     * transfer_logs: array of transfer request vms (start, stop, status, message)
     *
     * @return never
     */
    public function packages_details_transfer_get_package_vm()
    {
        DUP_PRO_Handler::init_error_handler();
        check_ajax_referer('duplicator_pro_packages_details_transfer_get_package_vm', 'nonce');

        $json      = array(
            'success' => false,
            'message' => '',
        );
        $isValid   = true;
        $inputData = filter_input_array(INPUT_POST, array(
            'package_id' => array(
                'filter'  => FILTER_VALIDATE_INT,
                'flags'   => FILTER_REQUIRE_SCALAR,
                'options' => array('default' => false),
            ),
        ));

        $package_id = $inputData['package_id'];
        if (!$package_id) {
            $isValid = false;
        }

        try {
            if (!CapMng::can(CapMng::CAP_STORAGE, false) && !CapMng::can(CapMng::CAP_CREATE, false)) {
                throw new Exception('Security issue.');
            }

            if (!$isValid) {
                throw new Exception(DUP_PRO_U::__("Invalid request."));
            }

            $package = DUP_PRO_Package::get_by_id($package_id);
            if (!$package) {
                throw new Exception(DUP_PRO_U::__("Could not get package by ID $package_id"));
            }

            $vm = new stdClass();

            /* -- First populate the transfer log information -- */

            // If this is the package being requested include the transfer details
            $vm->transfer_logs = array();

            $active_upload_info = null;

            $storages = AbstractStorageEntity::getAll();

            foreach ($package->upload_infos as &$upload_info) {
                if ($upload_info->getStorageId() === StoragesUtil::getDefaultStorageId()) {
                    continue;
                }

                $status      = $upload_info->get_status();
                $status_text = $upload_info->get_status_text();

                $transfer_log = new stdClass();

                if ($upload_info->get_started_timestamp() == null) {
                    $transfer_log->started = DUP_PRO_U::__('N/A');
                } else {
                    $transfer_log->started = DUP_PRO_DATE::getLocalTimeFromGMTTicks($upload_info->get_started_timestamp());
                }

                if ($upload_info->get_stopped_timestamp() == null) {
                    $transfer_log->stopped = DUP_PRO_U::__('N/A');
                } else {
                    $transfer_log->stopped = DUP_PRO_DATE::getLocalTimeFromGMTTicks($upload_info->get_stopped_timestamp());
                }

                $transfer_log->status_text = $status_text;
                $transfer_log->message     = $upload_info->get_status_message();

                $transfer_log->storage_type_text = DUP_PRO_U::__('Unknown');
                foreach ($storages as $storage) {
                    if ($storage->getId() == $upload_info->getStorageId()) {
                        $transfer_log->storage_type_text = $storage->getStypeName();
                        // break;
                    }
                }

                array_unshift($vm->transfer_logs, $transfer_log);

                if ($status == DUP_PRO_Upload_Status::Running) {
                    if ($active_upload_info != null) {
                        DUP_PRO_Log::trace("More than one upload info is running at the same time for package {$package->ID}");
                    }

                    $active_upload_info = &$upload_info;
                }
            }

            /* -- Now populate the activa package information -- */
            $active_package = DUP_PRO_Package::get_next_active_package();

            if ($active_package == null) {
                // No active package
                $vm->active_package_id = -1;
                $vm->text              = DUP_PRO_U::__('No package is building.');
            } else {
                $vm->active_package_id = $active_package->ID;

                if ($active_package->ID == $package_id) {
                    //$vm->is_transferring = (($package->Status >= DUP_PRO_PackageStatus::COPIEDPACKAGE) && ($package->Status < DUP_PRO_PackageStatus::COMPLETE));
                    if ($active_upload_info != null) {
                        $vm->percent_text = "{$active_upload_info->progress}%";
                        $vm->text         = $active_upload_info->get_status_message();
                    } else {
                        // We see this condition at the beginning and end of the transfer so throw up a generic message
                        $vm->percent_text = "";
                        $vm->text         = DUP_PRO_U::__("Synchronizing with server...");
                    }
                } else {
                    $vm->text = DUP_PRO_U::__("Another package is presently running.");
                }

                if ($active_package->is_cancel_pending()) {
                    // If it's getting cancelled override the normal text
                    $vm->text = DUP_PRO_U::__("Cancellation pending...");
                }
            }

            $json['success'] = true;
            $json['vm']      = $vm;
        } catch (Exception $ex) {
            $json['message'] = $ex->getMessage();
            DUP_PRO_Log::trace($ex->getMessage());
        }

        die(SnapJson::jsonEncode($json));
    }

    /**
     * Get the package status
     *
     * @param DUP_PRO_Package $package The package to get the status for
     *
     * @return int|float
     */
    private static function get_adjusted_package_status(DUP_PRO_Package $package)
    {
        $estimated_progress = ($package->build_progress->current_build_mode == DUP_PRO_Archive_Build_Mode::Shell_Exec) ||
            ($package->ziparchive_mode == DUP_PRO_ZipArchive_Mode::SingleThread);

        if (($package->Status == DUP_PRO_PackageStatus::ARCSTART) && $estimated_progress) {
            // Amount of time passing before we give them a 1%
            $time_per_percent       = 11;
            $thread_age             = time() - $package->build_progress->thread_start_time;
            $total_percentage_delta = DUP_PRO_PackageStatus::ARCDONE - DUP_PRO_PackageStatus::ARCSTART;

            if ($thread_age > ($total_percentage_delta * $time_per_percent)) {
                // It's maxed out so just give them the done condition for the rest of the time
                return DUP_PRO_PackageStatus::ARCDONE;
            } else {
                $percentage_delta = (int) ($thread_age / $time_per_percent);

                return DUP_PRO_PackageStatus::ARCSTART + $percentage_delta;
            }
        } else {
            return $package->Status;
        }
    }

    /**
     * Hook ajax wp_ajax_duplicator_pro_is_pack_running
     *
     * @return never
     */
    public function is_pack_running()
    {
        DUP_PRO_Handler::init_error_handler();
        check_ajax_referer('duplicator_pro_is_pack_running', 'nonce');

        ob_start();
        try {
            CapMng::can(CapMng::CAP_BASIC);

            $error  = false;
            $result = array(
                'running' => false,
                'data'    => array(
                    'run_ids'      => array(),
                    'cancel_ids'   => array(),
                    'error_ids'    => array(),
                    'complete_ids' => array(),
                ),
                'html'    => '',
                'message' => '',
            );

            $nonce = sanitize_text_field($_POST['nonce']);
            if (!wp_verify_nonce($nonce, 'duplicator_pro_is_pack_running')) {
                DUP_PRO_Log::trace('Security issue');
                throw new Exception('Security issue');
            }

            $tmpPackages = DUP_PRO_Package::get_row_by_status(array(
                array(
                    'op'     => '>=',
                    'status' => DUP_PRO_PackageStatus::COMPLETE,
                ),
            ));
            foreach ($tmpPackages as $cPack) {
                $result['data']['complete_ids'][] = $cPack->id;
            }

            $tmpPackages = DUP_PRO_Package::get_row_by_status(array(
                'relation' => 'AND',
                array(
                    'op'     => '>=',
                    'status' => DUP_PRO_PackageStatus::PRE_PROCESS,
                ),
                array(
                    'op'     => '<',
                    'status' => DUP_PRO_PackageStatus::COMPLETE,
                )
            ));
            foreach ($tmpPackages as $cPack) {
                $result['data']['run_ids'][] = $cPack->id;
            }
            $tmpPackages = DUP_PRO_Package::get_row_by_status(array(
                array(
                    'op'     => '=',
                    'status' => DUP_PRO_PackageStatus::PENDING_CANCEL,
                ),
            ));
            foreach ($tmpPackages as $cPack) {
                $result['data']['run_ids'][] = $cPack->id;
            }

            $tmpPackages = DUP_PRO_Package::get_row_by_status(array(
                'relation' => 'OR',
                array(
                    'op'     => '=',
                    'status' => DUP_PRO_PackageStatus::BUILD_CANCELLED,
                ),
                array(
                    'op'     => '=',
                    'status' => DUP_PRO_PackageStatus::STORAGE_CANCELLED,
                )
            ));
            foreach ($tmpPackages as $cPack) {
                $result['data']['cac_ids'][] = $cPack->id;
            }

            $tmpPackages = DUP_PRO_Package::get_row_by_status(array(
                'relation' => 'AND',
                array(
                    'op'     => '<',
                    'status' => DUP_PRO_PackageStatus::PRE_PROCESS,
                ),
                array(
                    'op'     => '!=',
                    'status' => DUP_PRO_PackageStatus::BUILD_CANCELLED,
                ),
                array(
                    'op'     => '!=',
                    'status' => DUP_PRO_PackageStatus::STORAGE_CANCELLED,
                ),
                array(
                    'op'     => '!=',
                    'status' => DUP_PRO_PackageStatus::PENDING_CANCEL,
                )
            ));
            foreach ($tmpPackages as $cPack) {
                $result['data']['err_ids'][] = $cPack->id;
            }

            $result['running'] = count($result['data']['run_ids']) > 0;
        } catch (Exception $e) {
            $error             = true;
            $result['message'] = $e->getMessage();
        }

        $result['html'] = ob_get_clean();
        if ($error) {
            wp_send_json_error($result);
        } else {
            wp_send_json_success($result);
        }
    }


    /** @var object[] */
    private static $package_statii_data = [];

    /**
     * wp_ajax_duplicator_pro_get_package_statii callback
     *
     * @param DUP_PRO_Package $package The package to get the status for
     *
     * @return void
     */
    public static function statii_callback(DUP_PRO_Package $package)
    {
        $package_status                  = new stdClass();
        $package_status->ID              = $package->ID;
        $package_status->status          = self::get_adjusted_package_status($package);
        $package_status->status_progress = $package->get_status_progress();
        $package_status->size            = $package->get_display_size();

        $active_storage = $package->get_active_storage();
        if ($active_storage !== false) {
            $package_status->status_progress_text = $active_storage->getActionText();
        } else {
            $package_status->status_progress_text = '';
        }

        self::$package_statii_data[] = $package_status;
    }

    /**
     * Hook ajax wp_ajax_duplicator_pro_get_package_statii
     *
     * @return never
     */
    public function get_package_statii()
    {
        DUP_PRO_Handler::init_error_handler();
        check_ajax_referer('duplicator_pro_get_package_statii', 'nonce');
        CapMng::can(CapMng::CAP_BASIC);

        self::$package_statii_data = [];
        DUP_PRO_Package::by_status_callback(array(__CLASS__, 'statii_callback'));

        die(SnapJson::jsonEncode(self::$package_statii_data));
    }

    /**
     * Hook ajax wp_ajax_duplicator_pro_get_folder_children
     *
     * @return never
     */
    public function duplicator_pro_get_folder_children()
    {
        DUP_PRO_Handler::init_error_handler();
        check_ajax_referer('duplicator_pro_get_folder_children', 'nonce');

        $json      = array();
        $isValid   = true;
        $inputData = filter_input_array(INPUT_GET, array(
            'folder'  => array(
                'filter'  => FILTER_SANITIZE_SPECIAL_CHARS,
                'flags'   => FILTER_REQUIRE_SCALAR,
                'options' => array('default' => false),
            ),
            'exclude' => array(
                'filter'  => FILTER_SANITIZE_SPECIAL_CHARS,
                'flags'   => FILTER_REQUIRE_ARRAY,
                'options' => array(
                    'default' => array(),
                ),
            ),
        ));
        $folder    = $inputData['folder'];
        $exclude   = $inputData['exclude'];

        if ($folder === false) {
            $isValid = false;
        }

        ob_start();
        try {
            CapMng::can(CapMng::CAP_BASIC);

            if (!$isValid) {
                throw new Exception(DUP_PRO_U::__('Invalid request.'));
            }
            if (is_dir($folder)) {
                try {
                    $Package = DUP_PRO_Package::get_temporary_package();
                } catch (Exception $e) {
                    $Package = null;
                }

                $treeObj = new DUP_PRO_Tree_files($folder, true, $exclude);
                $treeObj->uasort(array('DUP_PRO_Archive', 'sortTreeByFolderWarningName'));
                if (!is_null($Package)) {
                    $treeObj->treeTraverseCallback(array($Package->Archive, 'checkTreeNodesFolder'));
                }

                $jsTreeData = DUP_PRO_Archive::getJsTreeStructure($treeObj, '', false);
                $json       = $jsTreeData['children'];
            }
        } catch (Exception $e) {
            DUP_PRO_Log::trace($e->getMessage());
            $json['message'] = $e->getMessage();
        }
        ob_clean();
        wp_send_json($json);
    }


    /**
     * AJjax callback for admin_notice_to_dismiss
     *
     * @return boolean
     */
    public static function admin_notice_to_dismiss_callback()
    {

        $noticeToDismiss = filter_input(INPUT_POST, 'notice', FILTER_SANITIZE_SPECIAL_CHARS);
        $systemGlobal    = SystemGlobalEntity::getInstance();
        switch ($noticeToDismiss) {
            case AdminNotices::OPTION_KEY_ACTIVATE_PLUGINS_AFTER_INSTALL:
            case AdminNotices::OPTION_KEY_MIGRATION_SUCCESS_NOTICE:
                $ret = delete_option($noticeToDismiss);
                break;
            case AdminNotices::OPTION_KEY_S3_CONTENTS_FETCH_FAIL_NOTICE:
                $ret = update_option(AdminNotices::OPTION_KEY_S3_CONTENTS_FETCH_FAIL_NOTICE, false);
                break;
            case AdminNotices::QUICK_FIX_NOTICE:
                $systemGlobal->clearFixes();
                $ret = $systemGlobal->save();
                break;
            case AdminNotices::FAILED_SCHEDULE_NOTICE:
                $systemGlobal->schedule_failed = false;
                $ret                           = $systemGlobal->save();
                break;
            default:
                throw new Exception('Notice invalid');
        }
        return $ret;
    }

    /**
     * Hook ajax wp_ajax_duplicator_pro_admin_notice_to_dismiss
     *
     * @return never
     */
    public static function admin_notice_to_dismiss()
    {
        AjaxWrapper::json(
            array(
                __CLASS__,
                'admin_notice_to_dismiss_callback',
            ),
            'duplicator_pro_admin_notice_to_dismiss',
            $_POST['nonce'],
            CapMng::CAP_BASIC
        );
    }

    /**
     * Hook ajax wp_ajax_duplicator_pro_download_package_file
     *
     * @return never
     */
    public function download_package_file()
    {
        DUP_PRO_Handler::init_error_handler();
        $inputData = filter_input_array(INPUT_GET, array(
            'fileType' => array(
                'filter'  => FILTER_VALIDATE_INT,
                'flags'   => FILTER_REQUIRE_SCALAR,
                'options' => array('default' => false),
            ),
            'hash'     => array(
                'filter'  => FILTER_SANITIZE_SPECIAL_CHARS,
                'flags'   => FILTER_REQUIRE_SCALAR,
                'options' => array('default' => false),
            ),
            'token'    => array(
                'filter'  => FILTER_SANITIZE_SPECIAL_CHARS,
                'flags'   => FILTER_REQUIRE_SCALAR,
                'options' => array('default' => false),
            ),
        ));

        try {
            if (
                $inputData['token'] === false || $inputData['hash'] === false || $inputData["fileType"] === false
                || md5(\Duplicator\Utils\Crypt\CryptBlowfish::encrypt($inputData['hash'])) !== $inputData['token']
                || ($package = DUP_PRO_Package::get_by_hash($inputData['hash'])) == false
            ) {
                throw new Exception(DUP_PRO_U::__("Invalid request."));
            }

            switch ($inputData['fileType']) {
                case DUP_PRO_Package_File_Type::Installer:
                    $filePath = $package->getLocalPackageFilePath(DUP_PRO_Package_File_Type::Installer);
                    $fileName = $package->Installer->getDownloadName();
                    break;
                case DUP_PRO_Package_File_Type::Archive:
                    $filePath = $package->getLocalPackageFilePath(DUP_PRO_Package_File_Type::Archive);
                    $fileName = basename($filePath);
                    break;
                case DUP_PRO_Package_File_Type::Log:
                    $filePath = $package->getLocalPackageFilePath(DUP_PRO_Package_File_Type::Log);
                    $fileName = basename($filePath);
                    break;
                default:
                    throw new Exception(DUP_PRO_U::__("File type not supported."));
            }

            if ($filePath == false) {
                throw new Exception(DUP_PRO_U::__("File don\'t exists"));
            }

            \Duplicator\Libs\Snap\SnapIO::serveFileForDownload($filePath, $fileName, DUPLICATOR_PRO_BUFFER_DOWNLOAD_SIZE);
        } catch (Exception $ex) {
            wp_die($ex->getMessage());
        }
        die();
    }
}
