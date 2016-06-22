<?php

/*
 * Maintenance addon for Bear Framework
 * https://github.com/bearframework/maintenance-addon
 * Copyright (c) 2016 Ivo Petkov
 * Free to use under the MIT license.
 */

namespace BearFramework\Maintenance;

class Addons
{

    /**
     * 
     * @param string $filenameOrUrl
     * @return string The ID of the addon
     * @throws \Exception
     * @throws \InvalidArgumentException
     */
    public function getID($filenameOrUrl)
    {
        if (!is_string($filenameOrUrl)) {
            throw new \InvalidArgumentException('');
        }
        $filename = null;
        $extension = pathinfo($filenameOrUrl, PATHINFO_EXTENSION);
        if ($extension === 'zip') {
            if (is_file($filenameOrUrl)) {
                $filename = $filenameOrUrl;
            } else {
                $filename = \BearFramework\Maintenance\Utilities::downloadFile($filenameOrUrl);
            }
        } elseif ($extension === 'json') {
            $latestReleaseUrl = $this->getLastestReleaseUrl($filenameOrUrl);
            $filename = \BearFramework\Maintenance\Utilities::downloadFile($latestReleaseUrl);
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
            return $this->getIDFromAutoloadFile($autoloadFileContent);
        } else {
            throw new \Exception('Cannot open Zip file');
        }
    }

    /**
     * @param string $directory
     * @param string $filenameOrUrl
     * @return string The ID of the installed addon
     * @throws \Exception
     * @throws \InvalidArgumentException
     */
    public function install($directory, $filenameOrUrl)
    {
        if (!is_string($directory)) {
            throw new \InvalidArgumentException('');
        }
        if (!is_string($filenameOrUrl)) {
            throw new \InvalidArgumentException('');
        }
        $extension = pathinfo($filenameOrUrl, PATHINFO_EXTENSION);
        if ($extension === 'zip') {
            if (is_file($filenameOrUrl)) {
                return $this->installFromFile($directory, $filenameOrUrl);
            } else {
                return $this->installFromFile($directory, \BearFramework\Maintenance\Utilities::downloadFile($filenameOrUrl));
            }
        } elseif ($extension === 'json') {
            $latestReleaseUrl = $this->getLastestReleaseUrl($filenameOrUrl);
            if ($latestReleaseUrl === false) {
                throw new \Exception('Cannot find release URL');
            } else {
                return $this->installFromFile($directory, \BearFramework\Maintenance\Utilities::downloadFile($latestReleaseUrl));
            }
        } else {
            throw new \Exception('Invalid filename or URL');
        }
    }

    /**
     * 
     * @param string $directory
     * @param string $id
     * @throws \Exception
     * @throws \InvalidArgumentException
     */
    public function update($directory, $id)
    {
        if (!is_string($directory)) {
            throw new \InvalidArgumentException('');
        }
        if (!is_string($id)) {
            throw new \InvalidArgumentException('');
        }
        if (\BearFramework\Addons::exists($id)) {
            $options = \BearFramework\Addons::getOptions($id);
            if (isset($options['releasesUrl'])) {
                $this->install($directory, $options['releasesUrl']);
            }
        } else {
            throw new \Exception('Addon does not exists');
        }
    }

    /**
     * 
     * @param string $directory
     * @param string $id
     * @throws \Exception
     * @throws \InvalidArgumentException
     */
    public function delete($directory, $id)
    {
        if (!is_string($directory)) {
            throw new \InvalidArgumentException('');
        }
        if (!is_string($id)) {
            throw new \InvalidArgumentException('');
        }
        \BearFramework\Maintenance\Utilities::moveToRecycleBin($directory, $id);
    }

    /**
     * 
     * @param string $releasesUrl
     * @return boolean|string
     * @throws \Exception
     * @throws \InvalidArgumentException
     */
    private function getLastestReleaseUrl($releasesUrl)
    {
        if (!is_string($releasesUrl)) {
            throw new \InvalidArgumentException('');
        }
        $tempFilename = \BearFramework\Maintenance\Utilities::downloadFile($releasesUrl);
        $releases = json_decode(file_get_contents($tempFilename), true);
        unlink($tempFilename);
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
     * @throws \InvalidArgumentException
     */
    private function getIDFromAutoloadFile($fileContent)
    {
        if (!is_string($fileContent)) {
            throw new \InvalidArgumentException('');
        }
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
     * @throws \InvalidArgumentException
     */
    private function installFromFile($directory, $filename)
    {
        if (!is_string($directory)) {
            throw new \InvalidArgumentException('');
        }
        if (!is_string($filename)) {
            throw new \InvalidArgumentException('');
        }
        $directory = realpath($directory);
        if ($directory === false) {
            throw new \Exception('Invalid directory');
        }
        $filename = realpath($filename);
        if ($filename === false) {
            throw new \Exception('Invalid filename');
        }
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
            $tempAddonDir = sys_get_temp_dir() . '/bearframework_addon_' . md5($filename) . '_' . md5(uniqid()) . DIRECTORY_SEPARATOR;
            mkdir($tempAddonDir);
            $zip->extractTo($tempAddonDir);
            $zip->close();

            $id = $this->getIDFromAutoloadFile(file_get_contents($tempAddonDir . 'autoload.php'));
            $addonDir = $directory . DIRECTORY_SEPARATOR . $id . DIRECTORY_SEPARATOR;
            if (!is_dir($addonDir)) {
                $result = mkdir($addonDir, 0777, true);
                if ($result === false) {
                    throw new \Exception('Cannot create addon dir (' . $addonDir . ')');
                }
            }
            if (!\BearFramework\Maintenance\Utilities::compareFiles($addonDir, $tempAddonDir)) {
                \BearFramework\Maintenance\Utilities::moveToRecycleBin($directory, $id);
                rename($tempAddonDir, $addonDir);
                \BearFramework\Maintenance\Utilities::clearFilesCache();
            }
            return $id;
        } else {
            throw new \Exception('Cannot open zip file (' . $filename . ')');
        }
    }

}
