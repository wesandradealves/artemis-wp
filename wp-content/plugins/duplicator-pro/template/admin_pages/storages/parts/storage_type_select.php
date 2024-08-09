<?php

/**
 * Duplicator messages sections
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

use Duplicator\Models\Storages\AbstractStorageEntity;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 * @var AbstractStorageEntity $storage
 */
$storage = $tplData["storage"];

$sTypeSelected = ($storage->isSelectable() ? $storage->getSType() : -1);
$types         = AbstractStorageEntity::getResisteredTypesByPriority();

if ($storage->getId() < 0) {
    $supportedNotices = [];
    ?>
    <select id="change-mode" name="storage_type" onchange="DupPro.Storage.ChangeMode()">
        <?php foreach ($types as $type) {
            $class = AbstractStorageEntity::getSTypePHPClass($type);
            call_user_func([$class, 'isSelectable']);
            if (!call_user_func([$class, 'isSelectable'])) {
                continue;
            }
            if (!call_user_func([$class, 'isSupported'])) {
                $supportedNotices[] = call_user_func([$class, 'getNotSupportedNotice']);
                continue;
            }
            $name = call_user_func([$class, 'getStypeName']);
            ?>
            <option value="<?php echo $type; ?>" <?php selected($sTypeSelected, $type); ?>>
                <?php echo esc_html($name) ?>
            </option>
        <?php } ?>
    </select>
    <?php
    if (count($supportedNotices) > 0) { ?>
        <div class="margin-top-1" >
        <small class="dpro-store-type-notice"><b><?php esc_html_e('Unsupported storages: ', 'duplicator-pro'); ?></b></small><br>
        <?php foreach ($supportedNotices as $notice) { ?>
            <small class="dpro-store-type-notice"> - <?php echo $notice; ?></small><br>
        <?php } ?>
        </div>
        <?php
    }
} else {
    ?>
    <span id="dup-storage-mode-fixed" data-storage-type="<?php echo $storage->getSType(); ?>">
        <?php echo $storage->getStypeIcon(); ?>&nbsp;<b><?php echo esc_html($storage->getStypeName()); ?></b>
    </span>
    <?php
} ?>

<script>
    jQuery(document).ready(function ($) {

        DupPro.Storage.Modes = {
            LOCAL: <?php echo \Duplicator\Models\Storages\LocalStorage::getSType(); ?>,
            DROPBOX: <?php echo \Duplicator\Models\Storages\DropboxStorage::getSType(); ?>,
            FTP: <?php echo \Duplicator\Models\Storages\FTPStorage::getSType(); ?>,
            GDRIVE: 3,
            S3: <?php echo \Duplicator\Models\Storages\AmazonS3Storage::getSType(); ?>,
            SFTP: <?php echo \Duplicator\Models\Storages\SFTPStorage::getSType(); ?>,
            ONEDRIVE: <?php echo \Duplicator\Models\Storages\OneDriveStorage::getSType(); ?>,
            ONEDRIVE_MSGRAPH: 7,
            S3_COMPATIBLE: <?php echo \Duplicator\Models\Storages\AmazonS3CompatibleStorage::getSType(); ?>,
            BACKBLAZE: <?php echo \Duplicator\Models\Storages\BackblazeStorage::getSType(); ?>,
            WASABI: <?php echo \Duplicator\Models\Storages\WasabiStorage::getSType(); ?>,
            VULTR: <?php echo \Duplicator\Models\Storages\VultrStorage::getSType(); ?>,
            CLOUDFLARE: <?php echo \Duplicator\Models\Storages\CloudflareStorage::getSType(); ?>,
            DREAM: <?php echo \Duplicator\Models\Storages\DreamStorage::getSType(); ?>,
            DIGITAL_OCEAN: <?php echo \Duplicator\Models\Storages\DigitalOceanStorage::getSType(); ?>
        };

        var counter = 0;

        DupPro.Storage.BindParsley = function (mode)
        {
            if (counter++ > 0) {
                $('#dup-storage-form').parsley().destroy();
            }

            $('#dup-storage-form input').removeAttr('data-parsley-required');
            $('#dup-storage-form input').removeAttr('data-parsley-type');
            $('#dup-storage-form input').removeAttr('data-parsley-range');
            $('#dup-storage-form input').removeAttr('data-parsley-min');
            $('#name').attr('data-parsley-required', 'true');

            switch (parseInt(mode)) {
                case DupPro.Storage.Modes.LOCAL:
                    $('#_local_storage_folder').attr('data-parsley-required', 'true');
                    $('#local_max_files').attr('data-parsley-required', 'true');
                    $('#local_max_files').attr('data-parsley-type', 'number');
                    $('#local_max_files').attr('data-parsley-min', '0');
                    break;
                case DupPro.Storage.Modes.DROPBOX:
                    $('#dropbox_max_files').attr('data-parsley-required', 'true');
                    $('#dropbox_max_files').attr('data-parsley-type', 'number');
                    $('#dropbox_max_files').attr('data-parsley-min', '0');
                    break;
                case DupPro.Storage.Modes.FTP:
                    $('#ftp_server').attr('data-parsley-required', 'true');
                    $('#ftp_port').attr('data-parsley-required', 'true');
                    // $('#ftp_password, #ftp_password2').attr('data-parsley-required', 'true');
                    $('#ftp_max_files').attr('data-parsley-required', 'true');
                    $('#ftp_timeout').attr('data-parsley-required', 'true');
                    $('#ftp_port').attr('data-parsley-type', 'number');
                    $('#ftp_max_files').attr('data-parsley-type', 'number');
                    $('#ftp_timeout').attr('data-parsley-type', 'number');
                    $('#ftp_port').attr('data-parsley-range', '[1,65535]');
                    $('#ftp_max_files').attr('data-parsley-min', '0');
                    $('#ftp_timeout').attr('data-parsley-min', '10');
                    break;
                case DupPro.Storage.Modes.GDRIVE:
                    $('#gdrive_max_files').attr('data-parsley-required', 'true');
                    $('#gdrive_max_files').attr('data-parsley-type', 'number');
                    $('#gdrive_max_files').attr('data-parsley-min', '0');
                    break;
                case DupPro.Storage.Modes.S3:
                    // Common for all s3 providers:
                    $('#s3_access_key_amazon').attr('data-parsley-required', 'true');
                    $('#s3_max_files_amazon').attr('data-parsley-required', 'true');
                    $('#s3_bucket_amazon').attr('data-parsley-required', 'true');
                    break;
                case DupPro.Storage.Modes.S3_COMPATIBLE:
                case DupPro.Storage.Modes.BACKBLAZE:
                case DupPro.Storage.Modes.WASABI:
                case DupPro.Storage.Modes.VULTR:
                case DupPro.Storage.Modes.CLOUDFLARE:
                case DupPro.Storage.Modes.DREAM:
                case DupPro.Storage.Modes.DIGITAL_OCEAN:
                    $('#s3_access_key_' + mode).attr('data-parsley-required', 'true');
                    $('#s3_max_files_' + mode).attr('data-parsley-required', 'true');
                    $('#s3_bucket_' + mode).attr('data-parsley-required', 'true');
                    $('#s3_region_' + mode).attr('data-parsley-required', 'true');
                    $('#s3_region_' + mode).attr('data-parsley-pattern', '\[0-9-a-z-_]+');
                    $('#s3_endpoint_' + mode).attr('data-parsley-required', 'true');   
                    break;
            }
            $('#dup-storage-form').parsley();
        };
        
        DupPro.Storage.Autofill = function (mode) {
            switch (parseInt(mode)) {
                case DupPro.Storage.Modes.BACKBLAZE:
                    autoFillRegion(mode, 1);
                    break;
                case DupPro.Storage.Modes.DREAM:
                case DupPro.Storage.Modes.VULTR:
                case DupPro.Storage.Modes.DIGITAL_OCEAN:
                    autoFillRegion(mode, 0);
                    break;
                case DupPro.Storage.Modes.WASABI:
                    let wasabiRegion   = $("#s3_region_" + mode);
                    let wasabiEndpoint = $("#s3_endpoint_" + mode);

                    if (wasabiRegion.val().length > 0) {
                        wasabiEndpoint.val("s3." + wasabiRegion.val() + ".wasabisys.com");
                    }

                    wasabiRegion.change(function(e) {
                        let regionVal = $(this).val();
                        if (regionVal.length > 0) {
                            wasabiEndpoint.val("s3." + regionVal + ".wasabisys.com");
                        } else {
                            wasabiEndpoint.val("");
                        }
                    });
                    break;
            }

            function autoFillRegion(type, regionPos) {
                let region      = $("#s3_region_" + type);
                let endpoint    = $("#s3_endpoint_" + type);

                bindEndpointToRegion(region, endpoint, regionPos);

                endpoint.change(function(e) {
                    bindEndpointToRegion(region, endpoint, regionPos);
                });
            }

            function bindEndpointToRegion(region, endpoint, pos) {
                if (endpoint.val().length > 0) {
                    let regionStr = endpoint.val().replace(/.*:\/\//g,'').split(".")[pos];
                    region.val(regionStr);
                } else {
                    region.val("");
                }
            }
        }

        // GENERAL STORAGE LOGIC
        DupPro.Storage.ChangeMode = function (animateOverride) {
            let mode = 0;
            if ($('#dup-storage-mode-fixed').length > 0) {
                mode = $('#dup-storage-mode-fixed').data('storage-type');
            } else {
                let optionSelected = $("#change-mode option:selected");
                mode = optionSelected.val();
            }
            

            let animate = 400;
            if (arguments.length == 1) {
                animate = animateOverride;
            }
            $('.provider').hide();
            $('#provider-' + mode).show(animate);
            DupPro.Storage.BindParsley(mode);
            DupPro.Storage.Autofill(mode);
        }

        DupPro.Storage.ChangeMode(0);
    });
</script>

