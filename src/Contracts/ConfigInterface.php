<?php

declare(strict_types=1);

namespace Extro\ViewEngine\Contracts;

interface ConfigInterface
{
    /**
     * Should return the absolute path to the templates directory
     *
     * @return string
     */
    public function getTemplateDir(): string;
}
