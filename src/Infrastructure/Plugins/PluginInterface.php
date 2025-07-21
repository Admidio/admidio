<?php

namespace Admidio\Infrastructure\Plugins;

use InvalidArgumentException;
use Exception;
/**
 * Interface PluginInterface
 */
interface PluginInterface
{
    /**
     * @return PluginInterface
     */
    public static function getInstance();

    /**
     * @return string
     */
    public static function getName();

    /**
     * @return string
     */
    public static function getVersion();

    /**
     * @return array
     */
    public static function getMetadata();

    /**
     * @return array
     */
    public static function getDependencies();

    /**
     * @return array
     */
    public static function getSupportedLanguages();

    /**
     * @param string $type
     * @throws InvalidArgumentException
     * @throws Exception
     * @return array
     */
    public static function getStaticFiles($type = null);

    /**
     * @throws InvalidArgumentException
     * @throws Exception
     * @return string
     */
    public static function getPluginConfig() : array;

    /**
     * @throws Exception
     * @return int
     */
    public static function getComponentId() : int;

    /**
     * @throws Exception
     * @return bool
     */
    public static function isInstalled();

    /**
     * @throws Exception
     * @return bool
     */
    public static function isActivated();

    /**
     * @throws Exception
     * @return bool
     */
    public static function isUpdateAvailable() : bool;

    /**
     * @throws Exception
     * @return bool
     */
    public static function doClassAutoload();

    /**
     * @throws Exception
     * @return bool
     */
    public static function doInstall();

    /**
     * @param array $options
     * @throws InvalidArgumentException
     * @throws Exception
     * @return bool
     */
    public static function doUninstall(array $options = array());

    /**
     * @throws Exception
     * @return bool
     */
    public static function doUpdate();

    /**
     * @param array $config
     * @throws InvalidArgumentException
     * @throws Exception
     * @return bool
     */
    public static function doRender(array $config = array());
}