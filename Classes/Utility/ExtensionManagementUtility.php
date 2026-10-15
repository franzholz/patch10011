<?php

namespace JambageCom\Patch10011\Utility;

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility as CoreExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

class ExtensionManagementUtility
{
    /**
     * Gets information for an extension from its composer.json with explicit legacy support
     *
     * @param	string		$extKey Extension key
     * @param	string		$path Predefined path ... needed if you have the extension in another place
     * @return	array|string	Information array (unless an error occurred)
     */
    public static function getExtensionInfo($extKey, $path = '')
    {
        $result = '';

        if (!$path) {
            $path = CoreExtensionManagementUtility::extPath($extKey);
        }

        if (is_dir($path)) {
            $file = $path . 'composer.json';

            if (@is_file($file)) {
                $jsonContent = file_get_contents($file);
                $extConf = json_decode($jsonContent, true);

                if (is_array($extConf)) {
                    $eInfo = [];
                    $composerName = $extConf['name'] ?? '';

                    $eInfo['name'] = $composerName;
                    $eInfo['description'] = $extConf['description'] ?? '';
                    $eInfo['type'] = $extConf['type'] ?? '';
                    $eInfo['homepage'] = $extConf['homepage'] ?? '';
                    $eInfo['keywords'] = $extConf['keywords'] ?? [];
                    $eInfo['license'] = $extConf['license'] ?? [];

                    $detectedVersion = '';

                    // 1. Check local composer.json top-level field
                    if (!empty($extConf['version'])) {
                        $detectedVersion = $extConf['version'];
                    }

                    // 2. Check local composer.json extra/typo3/cms block (highly common in TYPO3 legacy)
                    if (empty($detectedVersion) && !empty($extConf['extra']['typo3/cms']['version'])) {
                        $detectedVersion = $extConf['extra']['typo3/cms']['version'];
                    }

                    // 3. Fallback: Check vendor/composer/installed.json only if still empty
                    if (empty($detectedVersion)) {
                        $installedJsonPath = GeneralUtility::getFileAbsPath('vendor/composer/installed.json');
                        if ($composerName && @is_file($installedJsonPath)) {
                            $installedData = json_decode(file_get_contents($installedJsonPath), true);
                            $packages = $installedData['packages'] ?? $installedData;

                            if (is_array($packages)) {
                                foreach ($packages as $package) {
                                    if (($package['name'] ?? '') === $composerName) {
                                        $detectedVersion = $package['version'] ?? '';
                                        break;
                                    }
                                }
                            }
                        }
                    }

                    if (empty($detectedVersion)) {
                        $detectedVersion = '0.0.0';
                    }

                    $eInfo['version'] = ltrim($detectedVersion, 'vV');

                    if (!empty($extConf['authors']) && is_array($extConf['authors'])) {
                        $firstAuthor = $extConf['authors'][0];
                        $eInfo['author'] = $firstAuthor['name'] ?? '';
                        $eInfo['author_email'] = $firstAuthor['email'] ?? '';
                        $eInfo['author_company'] = $firstAuthor['homepage'] ?? '';
                        $eInfo['author_role'] = $firstAuthor['role'] ?? '';
                    } else {
                        $eInfo['author'] = '';
                        $eInfo['author_email'] = '';
                        $eInfo['author_company'] = '';
                        $eInfo['author_role'] = '';
                    }

                    $eInfo['support'] = $extConf['support'] ?? [];
                    $eInfo['require'] = $extConf['require'] ?? [];
                    $eInfo['conflict'] = $extConf['conflict'] ?? [];
                    $eInfo['suggest'] = $extConf['suggest'] ?? [];
                    $eInfo['TYPO3_version'] = $extConf['require']['typo3/cms-core'] ?? '';

                    $typo3Extra = $extConf['extra']['typo3/cms'] ?? [];
                    $eInfo['title'] = $typo3Extra['title'] ?? $eInfo['name'] ?? $extKey;
                    $eInfo['category'] = $typo3Extra['category'] ?? 'plugin';
                    $eInfo['state'] = $typo3Extra['state'] ?? 'stable';
                    $eInfo['extension-key'] = $typo3Extra['extension-key'] ?? $extKey;
                    $eInfo['manual'] = @is_file($path . '/Documentation/Index.rst') || @is_file($path . '/doc/manual.odt');

                    $result = $eInfo;
                } else {
                    $result = 'ERROR: The file composer.json contains invalid JSON: ' . $file;
                }
            } else {
                $result = 'ERROR: No composer.json file found at: ' . $file;
            }
        } else {
            $result = 'ERROR: Path not found: ' . $path;
        }

        return $result;
    }

    /**
     * Standardizes version strings so they can be reliably compared
     * as strings inside TypoScript expressions (e.g., "03.06.00" > "03.05.99").
     */
    public static function makeVersion($versionStr, $placeholder = '')
    {
        $versionParts = explode('-', $versionStr);
        $cleanVersion = $versionParts[0];

        $parts = explode('.', $cleanVersion);
        $major = isset($parts[0]) ? (int)$parts[0] : 0;
        $minor = isset($parts[1]) ? (int)$parts[1] : 0;
        $patch = isset($parts[2]) ? (int)$parts[2] : 0;

        return sprintf('%02d.%02d.%02d', $major, $minor, $patch);
    }

    /**
    * Parses the version number x.x.x and returns an array with the various parts.
    */
    public static function renderVersion ($v, $raise = '')
    {
        $parts = GeneralUtility::intExplode('.', $v . '..');
        $parts[0] = MathUtility::forceIntegerInRange($parts[0], 0, 999);
        $parts[1] = MathUtility::forceIntegerInRange($parts[1], 0, 999);
        $parts[2] = MathUtility::forceIntegerInRange($parts[2], 0, 999);

        switch((string)$raise) {
            case 'main':
                $parts[0]++;
                $parts[1] = 0;
                $parts[2] = 0;
                break;
            case 'sub':
                $parts[1]++;
                $parts[2] = 0;
                break;
            case 'dev':
                $parts[2]++;
                break;
        }

        $result = [];
        $result['version'] = $parts[0] . '.' . $parts[1] . '.' . $parts[2];
        $result['version_int'] = intval($parts[0] * 1000000 + $parts[1] * 1000 + $parts[2]);
        $result['version_main'] = $parts[0];
        $result['version_sub'] = $parts[1];
        $result['version_dev'] = $parts[2];

        return $result;
    }
}
