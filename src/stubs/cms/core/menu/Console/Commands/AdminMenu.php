<?php

namespace cms\core\menu\Console\Commands;

use Illuminate\Console\Command;
use Menu;

class AdminMenu extends Command
{
    protected $signature = 'update:cms-menu
                            {--modules=* : Only register menus for specific modules}';

    protected $description = 'Update Admin menus';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle(): void
    {
        $onlyModules = $this->option('modules');

        if (!empty($onlyModules)) {
            $this->info('Registering menus for: ' . implode(', ', $onlyModules));
            Menu::registerMenusForModules($onlyModules);
        } else {
            $this->info('Registering all menus...');
            Menu::registerMenu();
        }

        $this->info('✅ Menus updated successfully!');
    }
}
