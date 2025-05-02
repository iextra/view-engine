<?php

declare(strict_types=1);

namespace Extro\ViewEngine;

use Extro\ViewEngine\Contracts\ConfigInterface;
use Extro\ViewEngine\Exceptions\ViewException;
use Throwable;

class View
{
    private array $data = [];
    private array $sections = [];
    private array $sectionKeys = [];
    private array $extends = [];
    private ?string $currentTemplate = null;

    public function __construct(
        private readonly ConfigInterface $config
    ) {
    }

    /**
     * @throws ViewException
     */
    private static function assertFile(string $file): void
    {
        if (!is_file($file)) {
            throw new ViewException(sprintf(
                "File '%s' does not exist",
                $file,
            ));
        }
    }

    /**
     * @throws ViewException
     */
    public function render(string $templateName, array $data = []): ?string
    {
        if (!empty($data)) {
            $this->data = array_merge($this->data, $data);
        }

        $level = ob_get_level();
        $this->currentTemplate = $templateName;

        try {
            ob_start();

            $file = $this->getTemplatePath($templateName);
            self::assertFile($file);
            include $file;

            $content = ob_get_clean();

            $extendedTemplate = $this->extends[$templateName] ?? null;
            if ($extendedTemplate === null) {
                return $content;
            }

            return $this->render($extendedTemplate);
        }
        catch (Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }

            throw new ViewException(
                sprintf(
                    'Error rendering template "%s": %s',
                    $templateName,
                    $e->getMessage()
                ),
                $e->getCode(),
                $e
            );
        }
    }

    private function getTemplatePath(string $name): string
    {
        $relPath = str_replace('.', DIRECTORY_SEPARATOR, $name) . '.php';
        return $this->config->getTemplateDir() . DIRECTORY_SEPARATOR . $relPath;
    }

    protected function escape(string $data): string
    {
        return htmlspecialchars($data);
    }

    protected function raw(string $data): string
    {
        return htmlspecialchars_decode($data);
    }

    protected function getData(string $name, bool $raw = false): mixed
    {
        $data = $this->data[$name] ?? null;

        if (is_string($data)) {
            return ($raw === true) ? $this->raw($data) : $this->escape($data);
        }

        return $data;
    }

    protected function renderSection(string $name): ?string
    {
        $templateName = $this->currentTemplate;

        if (in_array($this->currentTemplate, $this->extends, true)) {
            $templateName = array_search($this->currentTemplate, $this->extends, true);
        }

        $sectionKey = "$templateName:$name";
        return $this->sections[$sectionKey] ?? null;
    }

    protected function extend(string $template): void
    {
        $this->extends[$this->currentTemplate] = $template;
    }

    /**
     * @throws ViewException
     */
    protected function startSection(string $name, bool $rewrite = false): void
    {
        $sectionKey = "$this->currentTemplate:$name";

        if (
            $rewrite === false
            && isset($this->sections[$sectionKey])
        ) {
            throw new ViewException(sprintf('Section with name "%s" already exists.', $sectionKey));
        }

        $this->sectionKeys[] = $sectionKey;
        ob_start();
    }

    protected function endSection(): void
    {
        $content = ob_get_clean();

        $sectionName = array_pop($this->sectionKeys);
        $this->sections[$sectionName] = $content;
    }
}
