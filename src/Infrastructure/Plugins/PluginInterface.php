<?php

namespace Admidio\Infrastructure\Plugins;

use Admidio\UI\Presenter\PagePresenter;
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
    public static function getInstance() ;

    /**
     * @return string
     */
    public static function getName() : string;

    /**
     * @return string
     */
    public static function getVersion() : string;

    /**
     * @return array
     */
    public static function getMetadata() : array;

    /**
     * @return array
     */
    public static function getDependencies() : array;

    /**
     * @return array
     */
    public static function getSupportedLanguages() : array;

    /**
     * @param string $type
     * @throws InvalidArgumentException
     * @throws Exception
     * @return array
     */
    public static function getStaticFiles(?string $type = null) : array;

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
     * @return string
     */
    public static function getComponentName() : string;

    /**
     * @throws Exception
     * @return int
     */
    public static function getPluginSequence() : int;

    /**
     * @throws Exception
     * @return bool
     */
    public static function isInstalled() : bool;

    /**
     * @throws Exception
     * @return bool
     */
    public static function isActivated() : bool;

    /**
     * @throws Exception
     * @return bool
     */
    public static function isOverviewPlugin() : bool;

    /**
     * @throws Exception
     * @return bool
     */
    public static function isUpdateAvailable() : bool;

    /**
     * @throws Exception
     * @return bool
     */
    public static function doClassAutoload() : bool;

    /**
     * @throws Exception
     * @return bool
     */
    public static function doInstall() : bool;

    /**
     * @param array $options
     * @throws InvalidArgumentException
     * @throws Exception
     * @return bool
     */
    public static function doUninstall(array $options = array()) : bool;

    /**
     * @throws Exception
     * @return bool
     */
    public static function doUpdate() : bool;

    /**
     * @throws Exception
     * @return void
     */
    public static function initParams(array $params = array()) : bool;

    /**
     * @param PagePresenter $page
     * @throws InvalidArgumentException
     * @throws Exception
     * @return bool
     */
    public static function doRender(?PagePresenter $page = null) : bool;
}