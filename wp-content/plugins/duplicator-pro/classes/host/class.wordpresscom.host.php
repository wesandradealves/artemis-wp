<?php

/**
 * godaddy custom hosting class
 *
 * Standard: PSR-2
 *
 * @package SC\DUPX\HOST
 * @link    http://www.php-fig.org/psr/psr-2/
 */

defined('ABSPATH') || defined('DUPXABSPATH') || exit;

class DUP_PRO_WordpressCom_Host implements DUP_PRO_Host_interface
{
    /**
     * Get the identifier for this host
     *
     * @return string
     */
    public static function getIdentifier()
    {
        return DUP_PRO_Custom_Host_Manager::HOST_WORDPRESSCOM;
    }

    /**
     * @return bool
     */
    public function isHosting()
    {
        return apply_filters('duplicator_pro_wordpress_host_check', file_exists(WPMU_PLUGIN_DIR . '/wpcomsh-loader.php'));
    }

    public function init()
    {
        add_filter('duplicator_pro_is_shellzip_available', '__return_false');
        add_filter('duplicator_pro_overwrite_params_data', array(__CLASS__, 'installerParams'));
    }

    /**
     * Add installer params
     *
     * @param array<string,array{formStatus?:string,value:mixed}> $data Data
     *
     * @return array<string,array{formStatus?:string,value:mixed}>
     */
    public static function installerParams($data)
    {
        // disable plugins
        $data['fd_plugins'] = array(
            'value' => array(
                'wpcomsh-loader.php',
                'advanced-cache.php',
                'object-cache.php',
            ),
        );

        // generare new wp-config.php file
        $data['wp_config'] = array(
            'value'      => 'new',
            'formStatus' => 'st_infoonly',
        );

        // disable WP_CACHE
        $data['wpc_WP_CACHE'] = array(
            'value'      => array(
                'value'      => false,
                'inWpConfig' => false,
            ),
            'formStatus' => 'st_infoonly',
        );

        return $data;
    }
}
