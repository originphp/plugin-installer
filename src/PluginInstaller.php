<?php
/**
 * OriginPHP Framework
 * Copyright 2018 - 2020 Jamiel Sharief.
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

use RuntimeException;
use React\Promise\PromiseInterface;
use Composer\Package\PackageInterface;
use Composer\Installer\LibraryInstaller;
use Composer\Repository\InstalledRepositoryInterface;

class PluginInstaller extends LibraryInstaller
{

    /**
     * Returns the path, you cant get pluginName from ns at this stage
     *
     * @param PackageInterface $package
     * @return string
     */
    public function getInstallPath(PackageInterface $package)
    {
        /**
         * Check composer.json for extra data
         */
        $extra = $package->getExtra();
        if (! empty($extra['install'])) {
            return $extra['install'];
        }

        return parent::getInstallPath($package);
    }
   
    /**
     * Handles the Install
     *
     * @param InstalledRepositoryInterface $repo
     * @param PackageInterface $package
     * @return void
     */
    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        $promise = parent::install($repo, $package);

        $installer = $this;
        $updateStatus = function () use ($installer,$package) {
            list($plugin, $path) = $installer->getPluginInfo($package);
            $installer->updateTracker($plugin, $path);
        };

        if ($promise instanceof PromiseInterface) {
            return $promise->then($updateStatus);
        }
        $updateStatus();
    }
 
    /**
     * Handles the uninstall
     *
     * @param InstalledRepositoryInterface $repo
     * @param PackageInterface $package
     * @return void
     */
    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        $promise = parent::uninstall($repo, $package);

        $installer = $this;
        $updateStatus = function () use ($installer,$package) {
            list($plugin, ) = $installer->getPluginInfo($package);
            $installer->updateTracker($plugin, null);
        };

        if ($promise instanceof PromiseInterface) {
            return $promise->then($updateStatus);
        }
        $updateStatus();
    }

    protected function updateTracker($plugin, $path)
    {
        $filename = $this->vendorDir . DIRECTORY_SEPARATOR . 'originphp-plugins.json';

        /**
         * Path should be relative to root
         * - vendor/originphp/generate
         * - plugins/user_authentication
         */
        $root = dirname($this->vendorDir);
        $path = str_replace($root, '', $path);
        $path = ltrim($path, '/');
        
        if (! file_exists($filename)) {
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
        $path = $this->getInstallPath($package);
        $pluginName = $this->getPluginName($package);

        return [$pluginName,$path];
    }

    protected function getPluginName(PackageInterface $package)
    {
        $pluginName = null;
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

        if (! $pluginName) {
            throw new RuntimeException(
                sprintf("Error getting Plugin name from namespace in package %s\nCheck that psr-4 autoloaders PluginName => 'src/' ", $package->getName())
            );
        }

        return $pluginName;
    }
    
    /**
     * This is how the type is setup
     */
    public function supports($packageType)
    {
        return 'originphp-plugin' === $packageType;
    }
}
