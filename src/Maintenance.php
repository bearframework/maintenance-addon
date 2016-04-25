<?php

/*
 * Maintenance addon for Bear Framework
 * https://github.com/bearframework/maintenance-addon
 * Copyright (c) 2016 Ivo Petkov
 * Free to use under the MIT license.
 */

namespace BearFramework;

class Maintenance
{

    public $addonsDir = null;
    public $frameworkDir = null;

    /**
     * 
     * @param array $options
     */
    public function __construct($options)
    {
        $this->addonsDir = isset($options['addonsDir']) ? rtrim($options['addonsDir'], '/') . '/' : null;
        $this->frameworkDir = isset($options['frameworkDir']) ? rtrim($options['frameworkDir'], '/') . '/' : null;
    }

    public function addonExists($id)
    {
        if ($this->addonsDir === null) {
            throw new \Exception('addonsDir is not set');
        }
        return is_file($this->addonsDir . $id . '/index.php');
    }

    /**
     * 
     * @param string $filenameOrUrl
     * @return string The ID of the installed addon
     * @throws \Exception
     */
    public function getAddonID($filenameOrUrl)
    {
        if ($this->addonsDir === null) {
            throw new \Exception('addonsDir is not set');
        }
        $filename = null;
        $extension = pathinfo($filenameOrUrl, PATHINFO_EXTENSION);
        if ($extension === 'zip') {
            if (is_file($filenameOrUrl)) {
                $filename = $filenameOrUrl;
            } else {
                $filename = $this->downloadFile($filenameOrUrl);
            }
        } elseif ($extension === 'json') {
            $latestReleaseUrl = $this->getLastestAddonReleaseUrl($filenameOrUrl);
            $filename = $this->downloadFile($latestReleaseUrl);
        } else {
            throw new \Exception('Invalid filename or URL');
        }

        $zip = new \ZipArchive;
        if ($zip->open($filename) === TRUE) {
            $autoloadFileContent = $zip->getFromName('autoload.php');
            if ($autoloadFileContent === false) {
                $zip->close();
                throw new \Exception('Cannot find autoload.php');
            }
            $zip->close();
            return $this->getAddonIDFromAutoloadFile($autoloadFileContent);
        } else {
            throw new \Exception('Cannot open Zip file');
        }
    }

    /**
     * 
     * @param string $filenameOrUrl
     * @return string The ID of the installed addon
     * @throws \Exception
     */
    public function installAddon($filenameOrUrl)
    {
        $extension = pathinfo($filenameOrUrl, PATHINFO_EXTENSION);
        if ($extension === 'zip') {
            if (is_file($filenameOrUrl)) {
                return $this->installAddonFromFile($filenameOrUrl);
            } else {
                return $this->installAddonFromFile($this->downloadFile($filenameOrUrl));
            }
        } elseif ($extension === 'json') {
            $latestReleaseUrl = $this->getLastestAddonReleaseUrl($filenameOrUrl);
            return $this->installAddonFromFile($this->downloadFile($latestReleaseUrl));
        } else {
            throw new \Exception('Invalid filename or URL');
        }
    }

    /**
     * 
     * @param string $id
     * @throws \Exception
     */
    public function updateAddon($id)
    {
        if ($this->addonsDir === null) {
            throw new \Exception('addonsDir is not set');
        }
        if ($this->addonExists($id)) {
            if (\BearFramework\Addons::exists($id)) {
                $options = \BearFramework\Addons::getOptions($id);
                if (isset($options['releasesUrl'])) {
                    $this->installAddon($options['releasesUrl']);
                }
            } else {
                throw new \Exception('Addon does not exists');
            }
        } else {
            throw new \Exception('Addon does not exists');
        }
    }

    public function deleteAddon($id)
    {
        if ($this->addonsDir === null) {
            throw new \Exception('addonsDir is not set');
        }
        $this->moveToRecycleBin($id);
    }

    public function updateAllAddons()
    {
        if ($this->addonsDir === null) {
            throw new \Exception('addonsDir is not set');
        }
        //$files = scandir($this->addonsDir);
        //foreach($files as $file)
    }

    public function updateFramework()
    {
        if ($this->frameworkDir === null) {
            throw new \Exception('frameworkDir is not set');
        }
    }

    /**
     * 
     * @param string $url
     * @return string
     * @throws \Exception
     */
    private function downloadFile($url)
    {
        $tempFilename = sys_get_temp_dir() . '/bearframework_download_' . md5($url) . '.zip';
        if (!is_file($tempFilename)) {
            try {
                file_put_contents($tempFilename, file_get_contents($url));
            } catch (\Exception $e) {
                throw new \Exception('Cannot download ' . $url);
            }
        }
        return $tempFilename;
    }

    /**
     * 
     * @param string $releasesUrl
     * @return boolean|string
     * @throws \Exception
     */
    private function getLastestAddonReleaseUrl($releasesUrl)
    {
        try {
            $content = file_get_contents($releasesUrl);
        } catch (\Exception $e) {
            throw new \Exception('Cannot download ' . $releasesUrl);
        }
        $releases = json_decode($content, true);
        if (is_array($releases)) {
            $temp = [];
            foreach ($releases as $release) {
                if (is_array($release) && isset($release['version'], $release['url'])) {
                    $version = ltrim((string) $release['version'], 'v');
                    $url = (string) $release['url'];
                    $temp[] = [$version, $url];
                }
            }
            if (isset($temp[0])) {
                usort($temp, function($a, $b) {
                    return version_compare($a[0], $b[0]) * -1;
                });
                return $temp[0][1];
            }
        }
        return false;
    }

    /**
     * 
     * @param string $fileContent
     * @return string
     * @throws \Exception
     */
    private function getAddonIDFromAutoloadFile($fileContent)
    {
        $matches = [];
        preg_match('/Addons\:\:register\([ \'\"](.*?)[\'\"]/', $fileContent, $matches);
        if (isset($matches[1])) {
            return $matches[1];
        }
        throw new \Exception('Cannot find addon id');
    }

    /**
     * 
     * @param string $filename
     * @return string The ID of the installed addon
     * @throws \Exception
     */
    private function installAddonFromFile($filename)
    {
        $zip = new \ZipArchive;
        if ($zip->open($filename) === TRUE) {
            $autoloadFileContent = $zip->getFromName('autoload.php');
            $indexFileContent = $zip->getFromName('index.php');
            if ($autoloadFileContent === false) {
                $zip->close();
                throw new \Exception('Cannot find autoload.php');
            }
            if ($indexFileContent === false) {
                $zip->close();
                throw new \Exception('Cannot find index.php');
            }
            $tempAddonDir = sys_get_temp_dir() . '/bearframework_addon_' . md5($filename) . '_' . md5(uniqid()) . '/';
            mkdir($tempAddonDir);
            $zip->extractTo($tempAddonDir);
            $zip->close();

            $id = $this->getAddonIDFromAutoloadFile(file_get_contents($tempAddonDir . 'autoload.php'));
            $addonDir = $this->addonsDir . $id . '/';
            if (!is_dir($addonDir)) {
                mkdir($addonDir, 0777, true);
            }
            if (!$this->compareFiles($addonDir, $tempAddonDir)) {
                if (sizeof(scandir($addonDir)) === 2) {
                    rmdir($addonDir);
                } else {
                    $this->moveToRecycleBin($id);
                }
                rename($tempAddonDir, $addonDir);
                $this->clearFilesCache();
            }
            return $id;
        } else {
            throw new \Exception('Cannot open Zip file');
        }
    }

    /**
     * 
     * @param string $id
     */
    private function moveToRecycleBin($id)
    {
        $addonDir = $this->addonsDir . $id . '/';
        if (is_dir($addonDir)) {
            $recycleBinDir = $this->addonsDir . '.recyclebin/' . $id . '/';
            if (!is_dir($recycleBinDir)) {
                mkdir($recycleBinDir, 0777, true);
            }
            rename($addonDir, $recycleBinDir . date('Y-m-d-h-i-s') . '-' . uniqid() . '/');
        }
    }

    /**
     * 
     * @param string $dir1
     * @param string $dir2
     * @return boolean TRUE if dirs are the same, FALSE otherwise
     */
    private function compareFiles($dir1, $dir2)
    {
        $getMD5 = function($dir, $files) {
            sort($files);
            $temp = [];
            foreach ($files as $file) {
                $temp[$file] = md5_file($dir . $file);
            }
            return md5(serialize($temp));
        };
        return $getMD5($dir1, $this->getFilesInDir($dir1)) === $getMD5($dir2, $this->getFilesInDir($dir2));
    }

    /**
     * 
     * @param string $dir
     * @param boolean $recursive
     * @return array
     */
    private function getFilesInDir($dir, $recursive = false)
    {
        $result = [];
        if (is_dir($dir)) {
            $list = scandir($dir);
            if (is_array($list)) {
                foreach ($list as $filename) {
                    if ($filename != '.' && $filename != '..') {
                        if (is_dir($dir . $filename)) {
                            if ($recursive === true) {
                                $dirResult = $this->getFilesInDir($dir . $filename . '/', true);
                                if (!empty($dirResult)) {
                                    foreach ($dirResult as $index => $value) {
                                        $dirResult[$index] = $filename . '/' . $value;
                                    }
                                    $result = array_merge($result, $dirResult);
                                }
                            }
                        } else {
                            $result[] = $filename;
                        }
                    }
                }
            }
        }
        return $result;
    }

    /**
     * 
     */
    private function clearFilesCache()
    {
        if (function_exists('clearstatcache')) {
            clearstatcache();
        }
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
        if (function_exists('apc_clear_cache')) {
            apc_clear_cache();
        }
    }

}
