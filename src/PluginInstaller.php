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
use RuntimeException;

class PluginInstaller extends LibraryInstaller
{
   
    public function getInstallPath(PackageInterface $package)
    {
        /**
         * Check extra data
         */
        $extra = $package->getExtra();
        if(!empty($install)){
            return "plugins/{$extra['folder']}";
        }
  
        list($plugin, $path) = $this->getPluginName($package);
        if($plugin){
            return 'plugins/' . $this->underscore($plugin);
        }
      
        list($username, $package) = explode('/',$package->getPrettyName());
        return "plugins/{$package}";
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
        $directory = dirname($this->vendorDir);

        $filename = $directory . DIRECTORY_SEPARATOR . 'originphp-plugins.json';

        if (!file_exists($filename)) {
            file_put_contents($filename, json_encode([]));
        }

        $data = json_decode(file_get_contents($filename), true);
        
        if ($path) {
            $data[$plugin] = $directory . DIRECTORY_SEPARATOR  . $path;
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

    protected function getPluginName(PackageInterface $package){
        $pluginName = null;
        $autoLoaders = $package->getAutoload();
        
        file_put_contents($this->vendorDir. '/debug.json',json_encode($autoLoaders));

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

        if (!$pluginName) {
            throw new RuntimeException(
                sprintf("Error getting Plugin name from namespace in package %s\nCheck that psr-4 autoloaders PluginName => 'src/' ",$package->getName())
            );
        }

        return $pluginName;
    }
    
    protected function underscore(string $camelCasedWord){
        return strtolower(preg_replace('/(?<=\\w)([A-Z])/', '_\\1', $camelCasedWord));
    }

    /**
     * This is how the type is setup
     */
    public function supports($packageType)
    {
        return 'originphp-plugin' === $packageType;
    }
}
