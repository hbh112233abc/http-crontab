<?php

namespace bingher\crontab;

/**
 * 工具类
 *
 * 提供常用的环境检测和版本比较功能
 *
 * @package bingher\crontab
 */
class Tool
{
    /**
     * 检测函数是否被禁用
     *
     * @param string $method 函数名
     * @return bool
     */
    public static function isFunctionDisabled($method)
    {
        return in_array($method, explode(',', ini_get('disable_functions')));
    }

    /**
     * 检测扩展是否加载
     *
     * @param string $extension 扩展名
     * @return bool
     */
    public static function isExtensionLoaded($extension)
    {
        return in_array($extension, get_loaded_extensions());
    }

    /**
     * 检测是否为 Linux 操作系统
     *
     * @return bool
     */
    public static function isLinux()
    {
        return strpos(PHP_OS, "Linux") !== false ? true : false;
    }

    /**
     * PHP 版本比较
     *
     * @param string $version 版本号
     * @param string $operator 比较操作符（默认 >=）
     * @return bool
     */
    public static function versionCompare($version, $operator = ">=")
    {
        return version_compare(phpversion(), $version, $operator);
    }
}
