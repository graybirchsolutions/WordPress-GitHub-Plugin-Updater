<?php

/**
 * 
 * updater.php has been renamed to WPGitHubUpdater.php in this release to allow PSR-0 autoload via Composer.
 * 
 * This file is included to preserve backward compatability. Merely loads the new class file and defines
 * an class_alias to the new class name using the old class name as an alias.
 * 
 */

require __DIR__  . '/WpGitHubUpdater.php';

class_alias('WpGitHubUpdater', 'WP_GitHub_Updater');
