# phpci-pho-plugin
This is a plugin for [PHPCI](https://github.com/block8/phpci). It allows you
to run [pho](https://github.com/danielstjules/pho) tests via PHPCI.

## Installation
1. Navigate to your PHPCI path. `cd /path/to/phpci`
2. Edit the composer.json file. `nano composer.json`
3. Add `"austp\/phpci-pho-plugin": "~1.1"` in the `"require"` section.

        "require": {
          ...,
          ...,
          "austp\/phpci-pho-plugin": "~1.1"
        }
4. Download the plugin via composer. `composer update austp/phpci-pho-plugin`
5. Copy `build-plugins/pho.js` to `/path/to/phpci/public/assets/js/build-plugins/pho.js`

        cd /path/to/phpci/vendor/austp/phpci-pho-plugin/build-plugins
        cp pho.js /path/to/phpci/public/assets/js/build-plugins/pho.js

That's it as far as installation goes. Continue reading to see available options.


## Configuration
In order to configure PHPCI to run pho, you need to edit the `phpci.yml` file.
If you don't already have this file in your repository, [go ahead and add it](https://www.phptesting.org/wiki/Adding-PHPCI-Support-to-Your-Projects).
*Note: If you can't add a phpci.yml file to the repo, you can edit your project in PHPCI and configure it there.*

### Options
    directory:  "specs/"         | The directory to run the tests on.
    log:        true             | (optional) Log pho's output to PHPCI.
    executable: "/path/to/pho"   | (optional) Full path to a pho executable.
    bootstrap:  "bootstrap.file" | (optional) Bootstrap file to load.
    filter:     "filter"         | (optional) Run specs according to this filter.
    namespace:  true             | (optional) Only use namespaced functions.

### phpci.yml
1. Navigate to your repository. `cd /path/to/repo`
2. Edit the phpci.yml file. `nano phpci.yml`
3. Add `\PHPCI_Pho_Plugin\Pho:` in the `"test"` section.

        test:
          ...:
            ...: ...
            ...: ...
          ...:
            ...: ...
          \PHPCI_Pho_Plugin\Pho:
4. Add your options under the `\PHPCI_Pho_Plugin\Pho:` line.

        \PHPCI_Pho_Plugin\Pho:
          directory: "specs/"
          log: true
