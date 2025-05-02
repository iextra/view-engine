<?php

declare(strict_types=1);

namespace Extro\ViewEngine;

use Extro\ViewEngine\Contracts\ConfigInterface;
use Extro\ViewEngine\Exceptions\ViewException;
use Throwable;

class View
{
    public const HTML = 'html';
    public const TEXT = 'text';
    private const SECTION_CONTENT = 'content';

    private array $data = [];
    private array $sections = [];
    private array $sectionNames = [];
    private array $content = [];
    private ?string $extend = null;

    public function __construct(
        private readonly ConfigInterface $config
    ) {
    }

    /**
     * @throws ViewException
     */
    private static function assertFile(string $filePath): void
    {
        if (!is_file($filePath)) {
            throw new ViewException(sprintf(
                "File '%s' does not exist",
                $filePath,
            ));
        }
    }

    /**
     * @throws ViewException
     */
    public function render(string $name, array $data = []): ?string
    {
        if (!empty($data)) {
            $this->data = array_merge($this->data, $data);
        }

        $level = ob_get_level();

        try {
            ob_start();

            $filePath = $this->getViewPath($name);
            self::assertFile($filePath);

            include $filePath;
            $this->content[] = ob_get_clean();

            if (($extend = $this->extend) !== null) {
                $this->extend = null;
                $this->content[] = $this->render($extend);
            }

            return array_pop($this->content);
        }
        catch (Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }

            throw new ViewException(
                sprintf('Error rendering view "%s": %s', $name, $e->getMessage()),
                $e->getCode(),
                $e
            );
        }
    }

    public function getViewPath(string $name): string
    {
        $relPath = str_replace('.', DIRECTORY_SEPARATOR, $name) . '.php';
        return $this->config->getTemplateDir() . DIRECTORY_SEPARATOR . $relPath;
    }

    protected function text(string $data): string
    {
        return htmlspecialchars($data);
    }

    protected function html(string $data): string
    {
        return htmlspecialchars_decode($data);
    }

    protected function get(string $name, bool $asHtml = false): mixed
    {
        $data = $this->data[$name] ?? null;

        if (is_string($data)) {
            return ($asHtml === true) ? $this->html($data) : $this->text($data);
        }

        return $data;
    }

    protected function section(string $name = self::SECTION_CONTENT): ?string
    {
        if ($name == self::SECTION_CONTENT) {
            return array_pop($this->content);
        }

        return $this->sections[$name] ?? null;
    }

    protected function extend(string $template): void
    {
        $this->extend = $template;
    }

    /**
     * @throws ViewException
     */
    protected function start(string $name, bool $rewrite = false): void
    {
        if ($name === self::SECTION_CONTENT) {
            throw new ViewException(sprintf(
                'The section name "%s" is reserved.',
                self::SECTION_CONTENT
            ));
        }

        if (
            $rewrite === false
            && isset($this->sections[$name])
        ) {
            throw new ViewException(sprintf('Section with name "%s" already exists.', $name));
        }

        $this->sectionNames[] = $name;
        ob_start();
    }

    protected function end(): void
    {
        $name = array_pop($this->sectionNames);

        if (!empty($name)){
            $this->sections[$name] = ob_get_clean();
        }
    }
}
