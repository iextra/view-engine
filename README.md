[![Latest Version](https://img.shields.io/packagist/v/extro/view-engine.svg?style=flat-square)](https://packagist.org/packages/extro/view-engine)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

# Extro View Engine

Lightweight template engine for PHP with inheritance support.

## 📦 Installation

```bash
composer require extro/view-engine
```

## 🚀 Quick Start

1. Create a base template: resources/views/layout.php
```php
<!DOCTYPE html>
<html>
<head><?= $this->section('head') ?></head>
<body>
    <?= $this->section() ?>
</body>
</html>
```

2. Create a child template: resources/views/profile.php
```php
<?php $this->extend('layout') ?>

<?php $this->start('head') ?>
    <title>My profile</title>
<?php $this->end() ?>

<h1>Hello, <?= $this->get('name') ?>!</h1>
```

3. Render in your code
```php
use Extro\ViewEngine\View;
use Extro\ViewEngine\ConfigInterface;

// Implement your config (e.g., path to views)
$config = new class implements ConfigInterface {
    public function getTemplateDir(): string {
        return __DIR__ . '/resources/views';
    }
};

$view = new View($config);
echo $view->render('profile', ['name' => 'John']);
```

## 📚 Documentation

### Template Inheritance
```php
<!-- base.php -->
<header><?= $this->section('header') ?></header>
<main><?= $this->section() ?></main>

<!-- page.php -->
<?php $this->extend('base') ?>

<?php $this->start('header') ?>
    Welcome Page
<?php $this->end() ?>

Main content goes here...
```

### Data Handling
```php
// Auto-escaped output (default)
<?= $this->get('user_content') ?>

// Raw HTML output (trusted content only)
<?= $this->get('trusted_html', true) ?>
```