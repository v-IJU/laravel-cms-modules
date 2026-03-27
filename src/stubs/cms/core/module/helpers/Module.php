<?php

namespace cms\core\module\helpers;

use Cms;
use cms\core\module\Models\ModuleModel;

class Module
{
    /**
     * Register ALL modules — existing behavior
     */
    public static function registerModule(): void
    {
        $modules = Cms::allModules();

        foreach ($modules as $module) {
            static::registerSingleModule($module);
        }
    }

    /**
     * Register SPECIFIC modules only — for tenant setup
     */
    public static function registerModules(array $allowedModules): void
    {
        // Wildcard — register all
        if (in_array('*', $allowedModules)) {
            static::registerModule();
            return;
        }

        $modules = Cms::allModules();

        foreach ($modules as $module) {
            if (!in_array($module['name'], $allowedModules)) {
                continue; // skip not allowed
            }

            static::registerSingleModule($module);
        }
    }

    /**
     * Register a single module into DB
     */
    protected static function registerSingleModule(array $module): void
    {
        $type = ($module['type'] === 'core') ? 1 : 2;

        $old = ModuleModel::select('version', 'id')
            ->where('name', $module['name'])
            ->where('type', $type)
            ->first();

        if (count((array) $old) > 0) {
            // Update if version changed
            if ($old->version != $module['version']) {
                $obj          = ModuleModel::find($old->id);
                $obj->version = $module['version'];

                if (isset($module['configuration'])) {
                    $obj->configuration_view = $module['configuration'];
                }
                if (isset($module['configuration_data'])) {
                    $obj->configuration_data = $module['configuration_data'];
                }

                $obj->save();
            }
        } else {
            // Create new
            $obj          = new ModuleModel();
            $obj->name    = $module['name'];
            $obj->type    = $type;
            $obj->version = $module['version'];
            $obj->status  = 1;

            if (isset($module['configuration'])) {
                $obj->configuration_view = $module['configuration'];
            }
            if (isset($module['configuration_data'])) {
                $obj->configuration_data = $module['configuration_data'];
            }

            $obj->save();
        }
    }

    /**
     * Get module ID by name
     */
    public static function getId(string $module_name, int $type = 2): int
    {
        $data = ModuleModel::where('name', $module_name)
            ->where('type', $type)
            ->select('id')
            ->first();

        return count((array) $data) ? $data->id : 0;
    }

    static function registerModuleByScope(string $scope): void
    {
        $modules = Cms::allModules();

        foreach ($modules as $module) {
            // Read db_scope from module.json
            $jsonFile = base_path(
                'cms/core/' . $module['name'] . '/module.json'
            );

            // Also check local modules
            if (!file_exists($jsonFile)) {
                $theme    = \Cms::getCurrentTheme();
                $jsonFile = base_path(
                    "cms/local/{$theme}/{$module['name']}/module.json"
                );
            }

            if (!file_exists($jsonFile)) {
                // No module.json → default both
                $dbScope = 'both';
            } else {
                $config  = json_decode(file_get_contents($jsonFile), true);
                $dbScope = $config['db_scope'] ?? 'both';
            }

            $include = match ($scope) {
                'central' => in_array($dbScope, ['central', 'both']),
                'tenant'  => in_array($dbScope, ['tenant', 'both']),
                default   => true,
            };
            

            if ($include) {
                
                static::registerSingleModule($module);
            }
        }
    }
}
