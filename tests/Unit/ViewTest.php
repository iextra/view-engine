<?php

declare(strict_types=1);

namespace Extro\ViewEngine\Tests\Unit;

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

    public function testSectionContentReservedNameThrowsException()
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessage('The section name "content" is reserved.');

        $this->createViewFile('test', '
            <?php $this->start("content") ?>
            Content
            <?php $this->end() ?>
        ');

        $view = new View($this->config);
        $view->render('test');
    }

    public function testDuplicateSectionThrowsException()
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessage('Section with name "header" already exists.');

        $this->createViewFile('test', '
            <?php $this->start("header") ?>First<?php $this->end() ?>
            <?php $this->start("header") ?>Second<?php $this->end() ?>
        ');

        $view = new View($this->config);
        $view->render('test');
    }

    /**
     * @throws ViewException
     */
    public function testDataEscaping()
    {
        $this->createViewFile('test', '
            Text: <?= $this->get("text") ?>
            HTML: <?= $this->get("html", true) ?>
        ');

        $view = new View($this->config);
        $result = $view->render('test', [
            'text' => '<script>alert(1)</script>',
            'html' => '<b>bold</b>'
        ]);

        $expected = 'Text:&lt;script&gt;alert(1)&lt;/script&gt;HTML:<b>bold</b>';
        $this->assertEquals($expected, str_replace(["\n", " "], '', $result));
    }

    /**
     * @throws ViewException
     */
    public function testGetNonStringData()
    {
        $this->createViewFile('test', '<?= $this->get("array") === [1,2,3] ? "OK" : "FAIL";');

        $view = new View($this->config);
        $result = $view->render('test', ['array' => [1, 2, 3]]);

        $this->assertEquals('OK', $result);
    }

    /**
     * @throws ViewException
     */
    public function testGetReturnsNullForMissingKeyInTemplate()
    {
        $this->createViewFile('test', '<?= $this->get("invalid_key") === null ? "OK" : "FAIL";');
        $view = new View($this->config);
        $result = $view->render('test');
        $this->assertEquals('OK', $result);
    }
}
