<?php

namespace Duplicator\Utils\Settings;

interface ModelMigrateSettingsInterface
{
    /**
     * To export data
     *
     * @return array<string, mixed>
     */
    public function settingsExport();

    /**
     * Update object properties from import data
     *
     * @param array<string, mixed> $data        data to import
     * @param string               $dataVersion version of data
     * @param array<string, mixed> $extraData   extra data, useful form id mapping etc.
     *
     * @return bool True if success, otherwise false
     */
    public function settingsImport($data, $dataVersion, array $extraData = []);
}
