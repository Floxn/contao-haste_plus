<?php
/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2016 Heimrich & Hannot GmbH
 *
 * @package haste_plus
 * @author  Rico Kaltofen <r.kaltofen@heimrich-hannot.de>
 * @license http://www.gnu.org/licences/lgpl-3.0.html LGPL
 */

namespace HeimrichHannot\Haste\Util;


class Files
{

    /**
     * Returns the file list for a given directory
     *
     * @param string $strDir           - the absolute local path to the directory (e.g. /dir/mydir)
     * @param string $baseUrl          - the relative uri (e.g. /tl_files/mydir)
     * @param string $protectedBaseUrl - domain + request uri -> absUrl will be domain + request uri + ?file=$baseUrl/filename.ext
     *
     * @return array file list containing file objects.
     */
    public static function getFileList($strDir, $baseUrl, $protectedBaseUrl = null)
    {
        $arrResult = array();
        if (is_dir($strDir))
        {
            if ($handler = opendir($strDir))
            {
                while (($strFile = readdir($handler)) !== false)
                {
                    if (substr($strFile, 0, 1) == '.')
                    {
                        continue;
                    }
                    $arrFile             = array();
                    $arrFile['filename'] = htmlentities($strFile);
                    if ($protectedBaseUrl)
                    {
                        $arrFile['absUrl'] = $protectedBaseUrl . (empty($_GET) ? '?' : '&') . 'file=' . urlencode($arrFile['absUrl']);
                    }
                    else
                    {
                        $arrFile['absUrl'] = str_replace('\\', '/', str_replace('//', '', $baseUrl . '/' . $strFile));
                    }
                    $arrFile['path']     = str_replace($arrFile['filename'], '', $arrFile['absUrl']);
                    $arrFile['filesize'] = self::formatSizeUnits(filesize(str_replace('\\', '/', str_replace('//', '', $strDir . '/' . $strFile))), true);

                    $arrResult[] = $arrFile;
                }
                closedir($handler);
            }
        }
        Arrays::aasort($arrResult, 'filename');

        return $arrResult;
    }

    public static function formatSizeUnits($bytes, $keepTogether = false)
    {
        if ($bytes >= 1073741824)
        {
            $bytes = number_format($bytes / 1073741824, 2) . ($keepTogether ? '&nbsp;' : ' ') . 'GB';
        }
        elseif ($bytes >= 1048576)
        {
            $bytes = number_format($bytes / 1048576, 2) . ($keepTogether ? '&nbsp;' : ' ') . 'MB';
        }
        elseif ($bytes >= 1024)
        {
            $bytes = number_format($bytes / 1024, 2) . ($keepTogether ? '&nbsp;' : ' ') . 'KB';
        }
        elseif ($bytes > 1)
        {
            $bytes = $bytes . ($keepTogether ? '&nbsp;' : ' ') . 'Bytes';
        }
        elseif ($bytes == 1)
        {
            $bytes = $bytes . ($keepTogether ? '&nbsp;' : ' ') . 'Byte';
        }
        else
        {
            $bytes = '0' . ($keepTogether ? '&nbsp;' : ' ') . 'Bytes';
        }

        return $bytes;
    }

    public static function getPathWithoutFilename($strPathToFile)
    {
        $path = pathinfo($strPathToFile);

        return $path['dirname'];
    }

    public static function getFileExtension($strPath)
    {
        return pathinfo($strPath, PATHINFO_EXTENSION);
    }

    /**
     * @param      $varUuid
     * @param bool $blnCheckExists
     *
     * @return null|string Return the path of the file, or null if not exists
     */
    public static function getPathFromUuid($varUuid, $blnCheckExists = true)
    {
        if (($objFile = \FilesModel::findByUuid($varUuid)) !== null)
        {
            if (!$blnCheckExists)
            {
                return $objFile->path;
            }

            if (file_exists(TL_ROOT . '/' . $objFile->path))
            {
                return $objFile->path;
            }
        }

        return null;
    }

    /**
     * @param      $varUuid
     * @param bool $blnDoNotCreate
     *
     * @return \File|null Return the file object
     */
    public static function getFileFromUuid($varUuid, $blnDoNotCreate = true)
    {
        if ($strPath = static::getPathFromUuid($varUuid))
        {
            return new \File($strPath, $blnDoNotCreate);
        }
    }

    /**
     * @param      $varUuid
     * @param bool $blnDoNotCreate
     *
     * @return \Folder Return the folder object
     */
    public static function getFolderFromUuid($varUuid, $blnDoNotCreate = true)
    {
        if ($strPath = static::getPathFromUuid($varUuid))
        {
            return new \Folder($strPath, $blnDoNotCreate);
        }
    }

    public static function sanitizeFileName($strFileName, $maxCount = 64)
    {
        $strFileName = strtolower($strFileName);

        // umlauts
        $strFileName = str_replace(array('ä', 'ö', 'ü', 'Ä', 'Ö', 'Ü', 'ß'), array('ae', 'oe', 'ue', 'ae', 'Oe', 'Ue', 'ss'), $strFileName);

        $strFileName = preg_replace("@[^a-z0-9_-]@", '-', $strFileName);
        $strFileName = preg_replace("@-+@", '-', $strFileName);
        $strFileName = ltrim($strFileName, '-');
        $strFileName = rtrim($strFileName, '-');



        return substr($strFileName, 0, $maxCount - 1);
    }

    public static function sendTextAsFileToBrowser($strContent, $strFileName)
    {
        header('Content-Disposition: attachment; filename="' . $strFileName . '"');
        header('Content-Type: text/plain');
        header('Connection: close');
        echo $strContent;
        die();
    }

    /**
     * Get real folder from datacontainer attribute
     *
     * @param  mixed              $varFolder The folder as uuid, function, callback array('CLASS', 'method') or string (files/...)
     * @param \DataContainer|null $dc        Optional \DataContainer, required for function and callback
     *
     * @return mixed|null The folder path or null
     * @throws \Exception If ../ is part of the path
     */
    public static function getFolderFromDca($varFolder, \DataContainer $dc = null, $blnDoNotCreate = true)
    {

        // upload folder
        if (is_array($varFolder) && $dc !== null)
        {
            $arrCallback = $varFolder;
            $varFolder   = \System::importStatic($arrCallback[0])->$arrCallback[1]($dc);
        }
        elseif (is_callable($varFolder) && $dc !== null)
        {
            $strMethod = $varFolder;
            $varFolder = $strMethod($dc);
        }
        else
        {
            if (strpos($varFolder, '../') !== false)
            {
                throw new \Exception("Invalid target path $varFolder");
            }
        }

        if($varFolder instanceof \File)
        {
            $varFolder = $varFolder->value;
        }
        else if($varFolder instanceof \FilesModel)
        {
            $varFolder = $varFolder->path;
        }

        if (\Validator::isUuid($varFolder))
        {
            $varFolder = static::getFolderFromUuid($varFolder, $blnDoNotCreate);
        }

        return $varFolder;
    }
}