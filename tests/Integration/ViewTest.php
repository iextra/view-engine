<?php

declare(strict_types=1);

namespace Extro\ViewEngine\Tests\Integration;

use Extro\ViewEngine\Contracts\ConfigInterface;
use Extro\ViewEngine\Exceptions\ViewException;
use Extro\ViewEngine\View;
use PHPUnit\Framework\TestCase;

class ViewTest extends TestCase
{
    private string $tempDir;
    private ConfigInterface $config;

    protected function setUp(): void
    {
        parent::setUp();

        // Создаем временную директорию для тестовых шаблонов
        $this->tempDir = sys_get_temp_dir() . '/view_engine_test_' . uniqid();
        mkdir($this->tempDir);

        // Мок конфигурации
        $this->config = $this->createMock(ConfigInterface::class);
        $this->config->method('getTemplateDir')->willReturn($this->tempDir);
    }

    protected function tearDown(): void
    {
        // Удаляем временную директорию
        array_map('unlink', glob($this->tempDir . '/*'));
        rmdir($this->tempDir);

        parent::tearDown();
    }

    private function createViewFile(string $name, string $content): void
    {
        $path = $this->tempDir . '/' . str_replace('.', '/', $name) . '.php';
        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($path, $content);
    }

    /**
     * @throws ViewException
     */
    public function testRenderSimpleTemplate()
    {
        $this->createViewFile('test', 'Hello, <?= $this->get("name") ?>!');

        $view = new View($this->config);
        $result = $view->render('test', ['name' => 'World']);

        $this->assertEquals('Hello, World!', $result);
    }

    public function testRenderNonExistentTemplateThrowsException()
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessage("File '");
        $this->expectExceptionMessage("' does not exist");

        $view = new View($this->config);
        $view->render('nonexistent');
    }

    /**
     * @throws ViewException
     */
    public function testRenderWithSections()
    {
        $this->createViewFile('parent', '
            <html>
            <head><?= $this->section("head") ?></head>
            <body><?= $this->section() ?></body>
            </html>
        ');

        $this->createViewFile('child', '
            <?php $this->extend("parent") ?>
            <?php $this->start("head") ?>
                <title>Test</title>
            <?php $this->end() ?>
            Hello, World!
        ');

        $view = new View($this->config);
        $result = $view->render('child');

        $expected = '<html><head><title>Test</title></head><body>Hello,World!</body></html>';
        $this->assertEquals($expected, str_replace(["\n", " "], '', $result));
    }

    /**
     * @throws ViewException
     */
    public function testDuplicateSectionWithRewrite()
    {
        $this->createViewFile('test', '
            <?php $this->start("header", true) ?>First<?php $this->end() ?>
            <?php $this->start("header", true) ?>Second<?php $this->end() ?>
            <?= $this->section("header") ?>
        ');

        $view = new View($this->config);
        $result = $view->render('test');

        $this->assertEquals('Second', trim($result));
    }

    /**
     * @throws ViewException
     */
    public function testNestedTemplates()
    {
        $this->createViewFile('grandparent', '
            Grandparent Start
            <?= $this->section() ?>
            Grandparent End
        ');

        $this->createViewFile('parent', '
            <?php $this->extend("grandparent") ?>
            Parent Start
            <?= $this->section() ?>
            Parent End
        ');

        $this->createViewFile('child', '
            <?php $this->extend("parent") ?>
            Child Content
        ');

        $view = new View($this->config);
        $result = $view->render('child');

        $expected = 'Grandparent Start Parent Start Child Content Parent End Grandparent End';
        $this->assertEquals($expected, preg_replace('/\s+/', ' ', trim($result)));
    }

    public function testErrorInTemplateIsWrappedInViewException()
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessage('Error rendering view "test":');

        $this->createViewFile('test', '<?php throw new \RuntimeException("Test error");');

        $view = new View($this->config);
        $view->render('test');
    }
}
