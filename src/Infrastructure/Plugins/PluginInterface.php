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
    public static function getInstance(): PluginInterface;

    /**
     * @return string
     */
    public static function getName(): string;

    /**
     * @return string
     */
    public static function getVersion(): string;

    /**
     * @return array
     */
    public static function getMetadata(): array;

    /**
     * @return array
     */
    public static function getDependencies(): array;

    /**
     * @return array
     */
    public static function getSupportedLanguages(): array;

    /**
     * @param string|null $type
     * @param string $path
     * @return array
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public static function getStaticFiles(?string $type = null, string $path = ''): array;

    /**
     * @return array
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public static function getPluginConfig(): array;

    /**
     * @return string
     */
    public static function getPluginPath() : string;

    /**
     * @return int
     */
    public static function getComponentId(): int;

    /**
     * @return string
     */
    public static function getComponentName(): string;

    /**
     * @return int
     * @throws Exception
     */
    public static function getPluginSequence(): int;

    /**
     * @return bool
     * @throws Exception
     */
    public static function checkDependencies(): bool;

    /**
     * @return bool
     * @throws Exception
     */
    public static function isInstalled(): bool;

    /**
     * @return bool
     * @throws Exception
     */
    public static function isActivated(): bool;

    /**
     * @return bool
     * @throws Exception
     */
    public static function isVisible(): bool;

    /**
     * @return bool
     * @throws Exception
     */
    public static function isOverviewPlugin(): bool;

    /**
     * @return bool
     * @throws Exception
     */
    public static function isUpdateAvailable(): bool;

    /**
     * @return bool
     * @throws Exception
     */
    public static function doClassAutoload(): bool;

    /**
     * @return bool
     * @throws Exception
     */
    public static function doInstall(bool $addMenuEntry = true): bool;

    /**
     * @param array $options
     * @return bool
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public static function doUninstall(bool $removeMenuEntry = true, array $options = array()): bool;

    /**
     * @return bool
     * @throws Exception
     */
    public static function doUpdate(): bool;

    /**
     * @param array $params
     * @return bool
     * @throws Exception
     */
    public static function initParams(array $params = array()): bool;

    /**
     * @param PagePresenter|null $page
     * @return bool
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public static function doRender(?PagePresenter $page = null): bool;
}