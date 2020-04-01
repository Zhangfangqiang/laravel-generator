<?php

namespace ZhangFang\Generator\Common;

class TemplatesManager
{
    protected $useLocale = false;

    /**
     * 正在使用语言环境
     * @return bool
     */
    public function isUsingLocale(): bool
    {
        return $this->useLocale;
    }

    /**
     * 设置使用语言环境
     * @param bool $useLocale
     */
    public function setUseLocale(bool $useLocale): void
    {
        $this->useLocale = $useLocale;
    }
}
