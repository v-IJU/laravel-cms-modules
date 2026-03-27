<?php

namespace cms\core\menu\helpers;

use Cms;
use User;
use cms\core\menu\Models\AdminMenuGroupModel;
use cms\core\menu\Models\AdminMenuModel;
use cms\core\menu\Models\AdminMenuPermissionModel;

abstract class Menu
{
    protected static $menu_groups;
    protected static $menus;
    protected static $menulist;

    function __construct() {}

    /**
     * Register ALL module menus — existing behavior
     */
    public static function registerMenu(): void
    {
        $path = Cms::allModulesPath();
        static::processMenuPaths($path);
    }

    /**
     * Register menus for SPECIFIC modules only — for tenant setup
     */
    public static function registerMenusForModules(array $allowedModules): void
    {
        // Wildcard — register all
        if (in_array('*', $allowedModules)) {
            static::registerMenu();
            return;
        }

        $allPaths     = Cms::allModulesPath();
        $filteredPaths = [];

        foreach ($allPaths as $modulePath) {
            // Extract module name from path
            $parts      = explode(DIRECTORY_SEPARATOR, trim($modulePath, DIRECTORY_SEPARATOR));
            $moduleName = end($parts);

            if (in_array($moduleName, $allowedModules)) {
                $filteredPaths[] = $modulePath;
            }
        }

        static::processMenuPaths($filteredPaths);
    }

    /**
     * Process menu.xml files from given paths
     */
    protected static function processMenuPaths(array $paths): void
    {
        self::$menu_groups = [];
        self::$menus       = [];
        $group_id          = [];

        foreach ($paths as $module) {
            $menuFile = $module . DIRECTORY_SEPARATOR . 'menu.xml';

            if (!file_exists($menuFile)) continue;

            $xml = simplexml_load_file($menuFile);

            foreach ($xml->group as $group_key => $group) {
                $name        = (string) $xml->group['name'];
                $group_order = isset($group->attributes()['order'])
                    ? (string) $group->attributes()['order']
                    : $group_key;

                $menugroup = AdminMenuGroupModel::where('name', $name)
                    ->where('parent', 0)
                    ->first();

                if (count((array) $menugroup) == 0) {
                    $menugroup = new AdminMenuGroupModel();
                }

                $menugroup->name  = $name;
                $menugroup->order = $group_order;
                $menugroup->save();

                $parent_id  = $menugroup->id;
                $group_id[] = $parent_id;

                if (isset($group->menugroup)) {
                    foreach ($group->menugroup as $menu_key => $menus) {
                        self::registerMenugroup($menus, $parent_id, $menu_key);
                    }
                }

                if (isset($group->menu)) {
                    self::registerMenus($group->menu, $parent_id);
                }
            }
        }

        AdminMenuGroupModel::whereNotIn('id', $group_id)
            ->where('parent', 0)
            ->delete();

        AdminMenuGroupModel::whereNotIn('id', self::$menu_groups)
            ->where('parent', '!=', 0)
            ->delete();

        AdminMenuModel::whereNotIn('id', self::$menus)->delete();
    }

    protected static function registerMenugroup($menus, $parent_id, $menu_key): void
    {
        $name       = (string) $menus['name'];
        $menu_order = isset($menus['order']) ? (string) $menus['order'] : $menu_key;

        $menugroup = AdminMenuGroupModel::where('name', $name)
            ->where('parent', $parent_id)
            ->first();

        if (count((array) $menugroup) == 0) {
            $menugroup = new AdminMenuGroupModel();
        }

        $menugroup->name   = $name;
        $menugroup->order  = $menu_order;
        $menugroup->parent = $parent_id;
        $menugroup->icon   = (string) $menus['icon'];
        $menugroup->save();

        $parent_id            = $menugroup->id;
        self::$menu_groups[]  = $parent_id;

        if (isset($menus->menu)) {
            self::registerMenus($menus->menu, $parent_id);
        }

        if (isset($menus->menugroup)) {
            foreach ($menus->menugroup as $menu_key => $menus) {
                self::registerMenugroup($menus, $parent_id, $menu_key);
            }
        }
    }

    protected static function registerMenus($menus, $parent_id): void
    {
        foreach ($menus as $menu) {
            $menumodel = AdminMenuModel::where('name', $menu['name'])
                ->where('group_id', $parent_id)
                ->first();

            if (count((array) $menumodel) == 0) {
                $menumodel = new AdminMenuModel();
            }

            $menumodel->name     = $menu['name'];
            $menumodel->group_id = $parent_id;
            $menumodel->url      = isset($menu['route']) ? $menu['route'] : $menu['url'];
            $menumodel->is_url   = isset($menu['is_url']) ? $menu['is_url'] : 0;
            $menumodel->icon     = $menu['icon'];
            $menumodel->save();

            self::$menus[] = $menumodel->id;
        }
    }

    static function getAdminMenu(): array
    {
        $menugroup = AdminMenuGroupModel::with(['menu' => function ($q) {
            $q->where('status', 1);
        }])
            ->where('status', '=', 1)
            ->orderBy('order', 'ASC')
            ->get()->toArray();

        $current_user_group = User::getUser()->group[0]['id'];

        if (User::isSuperAdmin() == false) {
            $permissions = AdminMenuPermissionModel::get();
            $permission  = [];

            foreach ($permissions as $datas) {
                $permission[$datas->group_id][$datas->menu_id] = $datas->status;
            }

            foreach ($menugroup as $groupkey => $group) {
                foreach ($group['menu'] as $menu_key => $menu) {
                    if (
                        !isset($permission[$current_user_group][$menu['id']])
                        || $permission[$current_user_group][$menu['id']] == 0
                    ) {
                        unset($menugroup[$groupkey]['menu'][$menu_key]);
                    }
                }
            }
        }

        $return_array = self::buildTree($menugroup);

        if (User::isSuperAdmin() == false) {
            foreach ($return_array as $key_n => $gorup_n) {
                if (count((array) $gorup_n['menu']) == 0 && !isset($gorup_n['group'])) {
                    unset($return_array[$key_n]);
                }
            }
        }

        return $return_array;
    }

    protected static function buildTree(array $elements, $parentId = 0): array
    {
        $branch = [];

        foreach ($elements as $element) {
            if ($element['parent'] == $parentId) {
                $children = self::buildTree($elements, $element['id']);

                if ($children) {
                    $element['group'] = $children;
                }

                if (count((array) $element['menu']) != 0 || $parentId == 0) {
                    $branch[] = $element;
                }
            }
        }

        return $branch;
    }

    static function getAdminMenuOnly(): array
    {
        return AdminMenuModel::get()->toArray();
    }

    static function get($menu): void {}

    static function registerMenuByScope(string $scope): void
    {
        $allPaths      = Cms::allModulesPath();
        $filteredPaths = [];

        foreach ($allPaths as $modulePath) {
            // Get module name from path
            $parts      = explode(DIRECTORY_SEPARATOR, trim($modulePath, DIRECTORY_SEPARATOR));
            $moduleName = end($parts);

            // Read module.json
            $jsonFile = $modulePath . DIRECTORY_SEPARATOR . 'module.json';
            if (!file_exists($jsonFile)) continue;

            $config  = json_decode(file_get_contents($jsonFile), true);
            $dbScope = $config['db_scope'] ?? 'both';

            $include = match ($scope) {
                'central' => in_array($dbScope, ['central', 'both']),
                'tenant'  => in_array($dbScope, ['tenant', 'both']),
                default   => true,
            };

            if ($include) {
                $filteredPaths[] = $modulePath;
            }
        }

        static::processMenuPaths($filteredPaths);
    }
}
