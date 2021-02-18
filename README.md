# WordPress GitHub Plugin Updater

This class is meant to be used with your GitHub hosted WordPress plugins. The purpose of the class is to allow your WordPress plugin to be updated whenever you push out a new version of your plugin, similar to the experience users know and love with the WordPress.org plugin repository.

Not all plugins can or should be hosted on the WordPress.org plugin repository, or you may chose to host it on GitHub only.

This class was originally developed by [Joachim Kudish](https://github.com/jkudish), but because he hasn't had a chance to update it in a while, we stepped in. We are using this class in a couple of our own plugins (dogfooding!) and will continue to develop it as we go.

## Usage instructions
* The class should be installed using Composer. Include the following in your plugin's composer.json file and run `php composer.phar install`:

```
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/radishconcepts/WordPress-GitHub-Plugin-Updater"
        }
    ],
    "require": {
      "radishconcepts/wordpress-github-plugin-updater": "1.7"
	}
```

* Include the class using Composer's autoloader

* You will need to initialize the class using something similar to this:

```
	<?php

	use WPGitHubUpdater;

	if (is_admin()) { // note the use of is_admin() to double check that this is happening in the admin
		$config = array(
			'slug' => plugin_basename(__FILE__), // this is the slug of your plugin
			'proper_folder_name' => 'plugin-name', // this is the name of the folder your plugin lives in
			'api_url' => 'https://api.github.com/repos/username/repository-name', // the GitHub API url of your GitHub repo
			'raw_url' => 'https://raw.github.com/username/repository-name/master', // the GitHub raw url of your GitHub repo
			'github_url' => 'https://github.com/username/repository-name', // the GitHub url of your GitHub repo
			'zip_url' => 'https://github.com/username/repository-name/zipball/master', // the zip url of the GitHub repo
			'sslverify' => true, // whether WP should check the validity of the SSL cert when getting an update, see https://github.com/jkudish/WordPress-GitHub-Plugin-Updater/issues/2 and https://github.com/jkudish/WordPress-GitHub-Plugin-Updater/issues/4 for details
			'requires' => '3.0', // which version of WordPress does your plugin require?
			'tested' => '3.3', // which version of WordPress is your plugin tested up to?
			'readme' => 'README.md', // which file to use as the readme for the version number
			'changelog' => 'CHANGELOG.md', // file to use as the changelog (optional: will use the GitHub release description if available)
			'access_token' => '', // Access private repositories by authorizing under Plugins > GitHub Updates when this example plugin is installed
		);
		new WPGitHubUpdater($config);
	}
```

* From v1.7, the updater will collect the current version number of your plugin from the __latest release tag__ in GitHub. We recommend making use of GitHub releases to take advantage of this new feature! (see [About GitHub Releases](https://docs.github.com/en/github/administering-a-repository/about-releases) for details). The updater will also gather the release notes, if available, to use as the Changelog entry in the admin panel.

* From v1.6, the updater can pick up the version from the plugin header (now 2nd choice if releases/latest tag is not available).

* Prior to v1.6, you will need to include the following line (formatted exactly like this) anywhere in your Readme file (3rd choice if there is no releases/latest tag and the version number is not in the plugin header):

	`~Current Version:1.4~`

* You will need to update the version number anytime you update the plugin, this will ultimately let the plugin know that a new version is available.

* Support for private repository was added in v1.5

## Changelog

### 1.7 (in graybirchsolutions fork)
* Plugin class and file renamed to allow for PSR-0 autoloading through Composer
* Support for extracting the release number from the `releases/latest` tag in your repository
* Retrieve the README file and render HTML from Markdown to use as the plugin description the admin panel
* Retrieve the current release notes and render the text to use as the plugin changelog in the admin panel
* Option to specify a changelog file instead of relying on release notes
* composer.json updated to expose PSR-0 autoload class and require inclusion of erusev/parsedown to render README from Markdown to HTML

### 1.6 (in development)
* Get version from plugin header instead of readme with backwards compatibility support for readme, added by [@ninnypants](https://github.com/ninnypants)
* Better ways to handle GitHub API calls and the way the data is stored, thanks to [@coenjacobs](https://github.com/coenjacobs)
* Follow WordPress code standards and remove trailing whitespace
* Fix a PHP notice in the Plugins admin screen, props [@ninnypants](https://github.com/ninnypants)
* Use a central function for building the query used to communicate with the GitHub API, props [@davidmosterd](https://github.com/davidmosterd)


### 1.5
* Support for private repositories added by [@pdclark](http://profiles.wordpress.org/pdclark)
* Additional sslverify fix

### 1.4
* Minor fixes from [@sc0ttkclark](https://github.com/sc0ttkclark)'s use in Pods Framework
* Added readme file into config

### 1.3
* Fixed all php notices
* Fixed minor bugs
* Added an example plugin that's used as a test
* Minor documentation/readme adjustments

### 1.2
* Added phpDoc and minor syntax/readability adjusments, props [@franz-josef-kaiser](https://github.com/franz-josef-kaiser), [@GaryJones](https://github.com/GaryJones)
* Added a die to prevent direct access, props [@franz-josef-kaiser](https://github.com/franz-josef-kaiser)

### 1.0.3
* Fixed sslverify issue, props [@pmichael](https://github.com/pmichael)

### 1.0.2
* Fixed potential timeout

### 1.0.1
* Fixed potential fatal error with wp_error

### 1.0
* Initial Public Release

## Credits
This class was originally built by [Joachim Kudish](http://jkudish.com "Joachim Kudish") and is now being maintained by [Radish Concepts](http://www.radishconcepts.com/).

## License
This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to:

Free Software Foundation, Inc.
51 Franklin Street, Fifth Floor,
Boston, MA
02110-1301, USA.
