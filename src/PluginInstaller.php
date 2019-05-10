<?php
/**
 * OriginPHP Framework
 * Copyright 2018 - 2019 Jamiel Sharief.
 *
 * Licensed under The MIT License
 * The above copyright notice and this permission notice shall be included in all copies or substantial
 * portions of the Software.
 *
 * @copyright   Copyright (c) Jamiel Sharief
 * @link        https://www.originphp.com
 * @license     https://opensource.org/licenses/mit-license.php MIT License
 */
/**
 * When creating plugins require this
 * @see https://getcomposer.org/doc/articles/custom-installers.md
 */
namespace Origin\Composer;

use Composer\Package\PackageInterface;
use Composer\Installer\LibraryInstaller;
use Composer\Repository\InstalledRepositoryInterface;

class PluginInstaller extends LibraryInstaller
{
    public function getInstallPath(PackageInterface $package)
    {
        list($plugin, $path) = $this->getPluginInfo($package);
        return 'plugins/' . $plugin;
    }

    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        parent::install($repo, $package);
        list($plugin, $path) = $this->getPluginInfo($package);
        $this->updateTracker($plugin, $path);
    }
 
    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        parent::uninstall($repo, $package);
        list($plugin, $path) = $this->getPluginInfo($package);
        $this->updateTracker($plugin, null);
    }

    protected function updateTracker($plugin, $path)
    {
        $filename = $this->vendorDir . DIRECTORY_SEPARATOR . 'originphp-plugins.json';

        if (!file_exists($filename)) {
            file_put_contents($filename, json_encode([]));
        }

        $data = json_decode(file_get_contents($filename), true);
        
        if ($path) {
            $data[$plugin] = $path;
        } else {
            unset($data[$plugin]);
        }
        
        file_put_contents($filename, json_encode($data));
    }

    /**
     * Gets the plugin Name and path
     *
     * @param PackageInterface $package
     * @return void
     */
    protected function getPluginInfo(PackageInterface $package)
    {
        $pluginName = null;
        $path = $this->getInstallPath($package);

        $autoLoaders = $package->getAutoload();
        foreach ($autoLoaders as $type => $pathMap) {
            if ($type === 'psr-4') {
                if (count($pathMap) == 1) {
                    $pluginName = trim(key($pathMap), '\\');
                    break;
                }
                foreach ($pathMap as $ns => $path) {
                    if ($path === 'src/' or strpos($path, '/src/') !== false) {
                        $pluginName = trim($ns, '\\');
                        break;
                    }
                }
            }
        }
        return [$pluginName,$path];
    }

    /**
     * This is how the type is setup
     */
    public function supports($packageType)
    {
        return 'originphp-plugin' === $packageType;
    }
}
