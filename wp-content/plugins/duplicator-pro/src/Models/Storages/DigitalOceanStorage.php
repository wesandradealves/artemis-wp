<?php

/**
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Models\Storages;

class DigitalOceanStorage extends AmazonS3CompatibleStorage
{
    /**
     * Return the storage type
     *
     * @return int
     */
    public static function getSType()
    {
        return 14;
    }

    /**
     * Returns the storage type name.
     *
     * @return string
     */
    public static function getStypeName()
    {
        return __('Digital Ocean Spaces', 'duplicator-pro');
    }

    /**
     * Return true if the region is generated automatically
     *
     * @return bool
     */
    public function isAutofillRegion()
    {
        return true;
    }

    /**
     * Returns the storage type icon url.
     *
     * @return string The icon url
     */
    protected static function getIconUrl()
    {
        return DUPLICATOR_PRO_IMG_URL . '/digital-ocean.svg';
    }

    /**
     * Get storage location string
     *
     * @return string
     */
    public function getLocationString()
    {
        return 'https://cloud.digitalocean.com/spaces/' . $this->config['bucket'] . $this->config['storage_folder'];
    }

    /**
     * Get documentation links
     *
     * @return array<int,array<string,string>>
     */
    protected static function getDocumentationLinks()
    {
        return [
            [
                'label' => __('Spaces Object Storage', 'duplicator-pro'),
                'url'   => 'https://docs.digitalocean.com/products/spaces/',
            ],
            [
                'label' => __('Spaces API', 'duplicator-pro'),
                'url'   => 'https://docs.digitalocean.com/reference/api/spaces-api/',
            ],
        ];
    }
}
