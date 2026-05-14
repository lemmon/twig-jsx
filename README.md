# Twig JSX

A lightweight, zero-dependency implementation of JSX-like component syntax for Twig.

This project transforms modern, HTML-like component tags into native Twig `{% include %}` and `{% embed %}` calls.

## Features

- **JSX Syntax**: Use `<ComponentName />` instead of verbose Twig functions.
- **Static Props**: Pass strings directly via `label="Click Me"`.
- **Dynamic Props**: Pass variables or complex Twig expressions using the `:` prefix.
- **Shorthands**: Support for `:type` (variable) and `important` (boolean) shorthands.
- **Smart Props**: General HTML attributes are collected into a smart object that can be easily rendered.

## Example Usage

### 1. Create a Component
Save your component in `templates/components/Alert.twig`:

```twig
{% set type      = props.type|default('info') %}
{% set important = props.important|default(false) %}
{% set title     = props.title|default(null) %}
{% set message   = props.message|default('No message provided.') %}

<div class="alert alert-{{ type }}{% if important %} alert-important{% endif %}{% if props.class|default('') %} {{ props.class }}{% endif %}"
     {{ props.except('type', 'important', 'title', 'message', 'class')|render }}>
    {% if title %}<strong>{{ title }}</strong>{% endif %}
    {% block content %}{{ message }}{% endblock %}
</div>
```

### 2. Register the Lexer and Extension
```php
use Lemmon\TwigJsx\JSXPreLexer;
use Lemmon\TwigJsx\AttributeExtension;

$twig = new \Twig\Environment($loader);

// 1. Register the extension (required for props rendering)
$twig->addExtension(new AttributeExtension());

// 2. Register the Lexer
$twig->setLexer(new JSXPreLexer($twig));
```

### 3. Use it in a Template
```twig
<!-- Self-closing with shorthand -->
<Alert {type} important message="Everything is great!" />

<!-- With children and extra attributes -->
<Alert type="warning" class="shadow-lg" data-id="123">
    <strong>Wait!</strong> Something needs your attention.
</Alert>
```

## Prop Types

| Syntax | Example | Compiles to (inside `props` bag) |
| :--- | :--- | :--- |
| **Static** | `type="info"` | `'type': 'info'` |
| **Expression** | `type={userType}` | `'type': userType` |
| **Expression** | `count={items\|length}` | `'count': items\|length` |
| **Expression** | `theme={dark ? 'd' : 'l'}` | `'theme': dark ? 'd' : 'l'` |
| **Shorthand** | `{type}` | `'type': type` |
| **Boolean** | `important` | `'important': true` |

## Configuration Options

| Option | Default | Description |
| :--- | :--- | :--- |
| `directory` | `components` | The subdirectory where component templates are stored. |
| `extension` | `.twig` | The file extension of the component templates. |
| `prefix` | `""` | Optional tag prefix. If empty, matches Capitalized tags (JSX-style). |
| `props_variable` | `props` | The name of the variable that holds all props in the component template. |
| `content_block` | `content` | The Twig block name where a bodied tag's children are rendered. |

## How a Component Reads Its Inputs

Every prop the caller passes — semantic inputs and HTML attributes alike — arrives in a single
`props` bag of type `ComponentAttributes`. The component template decides what to extract:

```twig
{# Destructure semantic inputs as locals #}
{% set type    = props.type|default('info') %}
{% set message = props.message|default('') %}

{# Spread the remaining keys as HTML attributes #}
<div class="alert-{{ type }}" {{ props.except('type', 'message')|render }}>
    {{ message }}
</div>
```

- `props.key` — read any value
- `props.except('a', 'b', ...)` — returns a new bag without the listed keys, useful for spreading leftovers onto an element
- `{{ props|render }}` — renders all entries as HTML attribute pairs

## Installation
```bash
composer require lemmon/twig-jsx
```

## Comparison & Inspiration

This library was inspired by the modern component movement in the JavaScript ecosystem (React, Vue, Svelte) and existing Twig component implementations.

| Feature | [Symfony UX](https://symfony.com/bundles/ux-twig-component/current/index.html) | [TwigX](https://github.com/alma-oss/twigx-bundle) | **Twig JSX** |
| :--- | :--- | :--- | :--- |
| **Dependencies** | High (Symfony Bundle) | Moderate (Symfony Config/DI) | **Zero** (Standalone) |
| **Syntax** | `<twig:Alert />` | `<Alert />` | **`<Alert />`** |
| **Shorthands** | No (`:type="type"`) | No | **Yes (`:type`, `important`)** |
| **Philosophy** | PHP-centric (Classes) | Template-centric | **Modern DX (JSX-like)** |

### Why use this over others?
- **Zero-Dependency**: Most other libraries are tightly coupled to the Symfony Framework or its specific components. `twig-jsx` is a single-class transformer that works in any Twig environment.
- **Modern Shorthands**: We support `:variable` and `boolean-prop` shorthands natively, bringing the developer experience closer to Vue 3.4 or Svelte.
- **Lightweight**: It doesn't require PHP Component classes, though it works perfectly alongside them if you choose to implement your own loader logic.
