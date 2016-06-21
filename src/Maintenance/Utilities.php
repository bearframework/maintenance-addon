<?php

/*
 * Maintenance addon for Bear Framework
 * https://github.com/bearframework/maintenance-addon
 * Copyright (c) 2016 Ivo Petkov
 * Free to use under the MIT license.
 */

namespace BearFramework\Maintenance;

class Utilities
{

    /**
     * 
     * @param string $url
     * @return string
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    static function downloadFile($url)
    {
        if (!is_string($url)) {
            throw new \InvalidArgumentException('');
        }
        set_error_handler(function($number, $message, $file, $line) {
            throw new \ErrorException($message, 0, $number, $file, $line);
        });
        $tempFilename = sys_get_temp_dir() . '/bearframework_download_' . md5($url) . '.zip';
        if (!is_file($tempFilename)) {
            try {
                file_put_contents($tempFilename, file_get_contents($url));
            } catch (\Exception $e) {
                restore_error_handler();
                throw new \Exception('Cannot download ' . $url);
            }
        }
        restore_error_handler();
        return $tempFilename;
    }

    /**
     * 
     */
    static function clearFilesCache()
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

    /**
     * 
     * @param string $dir1
     * @param string $dir2
     * @return boolean TRUE if dirs are the same, FALSE otherwise
     * @throws \InvalidArgumentException
     */
    static function compareFiles($dir1, $dir2)
    {
        if (!is_string($dir1)) {
            throw new \InvalidArgumentException('');
        }
        if (!is_string($dir2)) {
            throw new \InvalidArgumentException('');
        }
        $getMD5 = function($dir, $files) {
            sort($files);
            $temp = [];
            foreach ($files as $file) {
                $temp[$file] = md5_file($dir . $file);
            }
            return md5(serialize($temp));
        };
        return $getMD5($dir1, self::getFilesInDir($dir1)) === $getMD5($dir2, self::getFilesInDir($dir2));
    }

    /**
     * 
     * @param string $dir
     * @param boolean $recursive
     * @return array
     * @throws \InvalidArgumentException
     */
    static function getFilesInDir($dir, $recursive = false)
    {
        if (!is_string($dir)) {
            throw new \InvalidArgumentException('');
        }
        if (!is_bool($recursive)) {
            throw new \InvalidArgumentException('');
        }
        $result = [];
        if (is_dir($dir)) {
            $list = scandir($dir);
            if (is_array($list)) {
                foreach ($list as $filename) {
                    if ($filename != '.' && $filename != '..') {
                        if (is_dir($dir . $filename)) {
                            if ($recursive === true) {
                                $temp = self::getFilesInDir($dir . $filename . DIRECTORY_SEPARATOR, true);
                                if (!empty($temp)) {
                                    foreach ($temp as $index => $value) {
                                        $temp[$index] = $filename . DIRECTORY_SEPARATOR . $value;
                                    }
                                    $result = array_merge($result, $temp);
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
     * @param string $directory
     * @param string $id
     * @return boolean
     * @throws \InvalidArgumentException
     */
    static function moveToRecycleBin($directory, $id)
    {
        if (!is_string($directory)) {
            throw new \InvalidArgumentException('');
        }
        if (!is_string($id)) {
            throw new \InvalidArgumentException('');
        }
        $addonDir = $directory . DIRECTORY_SEPARATOR . $id . DIRECTORY_SEPARATOR;
        if (is_dir($addonDir)) {
            $recycleBinDir = $directory . DIRECTORY_SEPARATOR . '.recyclebin' . DIRECTORY_SEPARATOR . $id . DIRECTORY_SEPARATOR;
            if (!is_dir($recycleBinDir)) {
                mkdir($recycleBinDir, 0777, true);
            }
            rename($addonDir, $recycleBinDir . date('Y-m-d-h-i-s') . '-' . uniqid() . DIRECTORY_SEPARATOR);
            return true;
        }
        return false;
    }

}
