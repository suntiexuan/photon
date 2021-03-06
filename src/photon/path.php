<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
# ***** BEGIN LICENSE BLOCK *****
#
# This file is part of Photon, High Performance PHP Framework.
# Copyright (C) 2010, 2011 Ceondo Ltd and contributors.
#
# This program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful, but
# WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
# General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA
# 02110-1301, USA.
#
# ***** END LICENSE BLOCK ***** */

/**
 * File utilities.
 */
namespace photon\path;

/**
 * Directory utilities.
 */
class Dir
{
    /**
     * List recursively all the files of a directory.
     *
     * The root directory is not returned as part of the file. For
     * example, if you give the directory '/home/login' and you have
     * the files '.profile' and '.ssh/authorized_keys' into the
     * directory, you will get array('.profile',
     * '.ssh/authorized_keys') a returned value.
     *
     * @param $dir string Directory to get the files from without trailing slash
     * @param $regex Regular expression to exclude some files/folders (array())
     * @return array Files
     */
    public static function listFiles($dir, $regex=array())
    {
         $dirItr = new \RecursiveDirectoryIterator($dir);
         $filterItr = new RecursiveDotDirsFilterIterator($dirItr, null, $regex);
         $itr = new \RecursiveIteratorIterator($filterItr,
                                      \RecursiveIteratorIterator::SELF_FIRST);
         $files = array();
         $dirl = strlen($dir) + 1;
         foreach ($itr as $filePath => $fileInfo) {
             if ($fileInfo->isFile()) {
                 $files[] = substr($filePath, $dirl, strlen($filePath));
             }
         }

         return $files;
    }

    /**
     * Remove recursively all the files of a directory, and finally the directory itself.
     *
     * @param $dir string Directory to delete
     */
    public static function remove($dir)
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            if ($fileinfo->isDir() === true) {
                rmdir($fileinfo->getRealPath());
            } else {
                unlink($fileinfo->getRealPath());
            }
        }

        rmdir($dir);
    }

    /**
     *  Get the list of include path,
     *  We need a smart explode here bacause some path contains the path separator
     *  like: phar:// or http://
     *
     *  @param $path string A path string to explode
     */
    public static function getIncludePath($path=null)
    {
        $path = ($path === null) ? get_include_path() : $path;

        if (PATH_SEPARATOR !== ':') {
            return explode(PATH_SEPARATOR, $path);
        }

        return preg_split('#:(?!//)#', $path);
    }
}


/**
 * Filter out the common .* files/folders we do not want when listing files.
 *
 * Usage:
 *
 * <pre>
 * $dirItr = new \RecursiveDirectoryIterator('/sample/path');
 * $filterItr = new RecursiveDotDirsFilterIterator($dirItr);
 * $itr = new \RecursiveIteratorIterator($filterItr,
 *                                      \RecursiveIteratorIterator::SELF_FIRST);
 * foreach ($itr as $filePath => $fileInfo) {
 *     echo $fileInfo->getFilename() . PHP_EOL;
 * }
 *</pre>
 */
class RecursiveDotDirsFilterIterator extends \RecursiveFilterIterator
{
    public static $filters = array('.', '..', '.svn', '.git', '.DS_Store');
    public static $regex = array();

    public function __construct($iterator, $filters=null, $regex=null)
    {
        parent::__construct($iterator);
        self::$filters = (null !== $filters) ? $filters : self::$filters;
        self::$regex = (null !== $regex) ? $regex : self::$regex;
    }

    public function accept()
    {
        if (in_array($this->current()->getFilename(), self::$filters, true)) {

            return false;
        }
        foreach (self::$regex as $regex) {
            if (preg_match($regex, $this->current()->getFilename())) {

                return false;
            }
        }

        return true;
    }
}


/**
 * Filter out files a bit like a .gitignore way.
 *
 * Usage:
 *
 * <pre>
 * $dirItr = new \RecursiveDirectoryIterator('/sample/path');
 * $filterItr = new IgnoreFilterIterator($dirItr, '/sample/path',
 *                                       '/path/to/.ignoredef');
 * $itr = new \RecursiveIteratorIterator($filterItr,
 *                                      \RecursiveIteratorIterator::SELF_FIRST);
 * foreach ($itr as $filePath => $fileInfo) {
 *     echo $fileInfo->getFilename() . PHP_EOL;
 * }
 *</pre>
 */
class IgnoreFilterIterator extends \RecursiveFilterIterator
{
    public static $regex = array();

    /**
     * Constructor.
     *
     * @param $iterator Iterator object
     * @param $base_path Directory without trailing slash
     * @param $ignore_file File with patterns to ignore, or
     *                     Array of File with patterns to ignore, where array keys are base path
     *                     All files must be in the base path, or un sub-folders
     */
    public function __construct($iterator, $base_path=null, $ignore_file=null)
    {
        parent::__construct($iterator);

        if (null !== $ignore_file) {
            if (is_array($ignore_file)) {
                self::$regex = array();
                foreach($ignore_file as $base => $file) {
                    $base = realpath($base);
                    self::$regex = array_merge(self::$regex, self::parsePatterns(file($base . '/' . $file), $base));
                }
            } else if (file_exists($ignore_file)) {
                self::$regex = self::parsePatterns(file($ignore_file), realpath($base_path));
            }
        }
    }

    public function accept()
    {
        $path = $this->current()->getRealPath();

        foreach (self::$regex as $regex) {
            if (preg_match($regex, $path)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Given an ignore file returns the matching patterns in it.
     *
     * @param $lines Array of raw patterns
     * @return array Array of patterns
     */
    public static function parsePatterns($lines, $prefix=null)
    {
        $patterns = array();
        $from = array('.',  '*');
        $to =   array('\.', '.+');
        foreach ($lines as $pattern) {
            $pattern = trim($pattern);

            // Ignore comment & empty lines
            if (0 === strlen($pattern) || '#' === $pattern[0]) {
                continue;
            }

            // Add prefix to handle ignore files in subfolders
            if ($prefix !== null) {
                $pattern = $prefix . '/' . $pattern;
            }

            // Ignore all files and subfolders if the pattern is a folder
            $folder = "";
            if (substr($pattern, -1) === '/') {
                $folder = ".*";
            }

            $pattern = str_replace($from, $to, $pattern);
            $patterns[] = '#^' . $pattern . $folder . '$#';
        }

        return $patterns;
    }
}

