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
<div
    class="alert alert-{{ type|default('info') }}{% if important|default(false) %} alert-important{% endif %}{% if props.class|default('') %} {{ props.class }}{% endif %}"
    {{ props.except('class')|render }}
>
    {% if title is defined %}<strong>{{ title }}</strong>{% endif %}

    <div>
        {% block content %}
            {{ message|default('No message provided.') }}
        {% endblock %}
    </div>
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
$twig->setLexer(new JSXPreLexer($twig, [
    'attr_name' => 'props'
]));
```

### 3. Use it in a Template
```twig
<!-- Self-closing with shorthand -->
<Alert :type important message="Everything is great!" />

<!-- With children and extra attributes -->
<Alert type="warning" class="shadow-lg" data-id="123">
    <strong>Wait!</strong> Something needs your attention.
</Alert>
```

## Prop Types

| Syntax | Example | Twig Equivalent | Result |
| :--- | :--- | :--- | :--- |
| **Static** | `type="info"` | `'type': 'info'` | String |
| **Variable**| `:type="userType"` | `'type': userType` | Value of `$userType` |
| **Boolean** | `:important="true"`| `'important': true` | Boolean `true` |
| **Shorthand Var** | `:type` | `'type': type` | Passes `$type` variable |
| **Shorthand Bool**| `important` | `'important': true`| Boolean `true` |
| **Logic**   | `:count="items\|length"` | `'count': items\|length` | Integer |
| **Complex** | `:theme="dark ? 'd' : 'l'"` | `'theme': dark ? 'd' : 'l'` | Expression result |

## Configuration Options

| Option | Default | Description |
| :--- | :--- | :--- |
| `directory` | `components` | The subdirectory where component templates are stored. |
| `extension` | `.twig` | The file extension of the component templates. |
| `prefix` | `""` | Optional tag prefix. If empty, matches Capitalized tags (JSX-style). |
| `known_props`| `['title', 'message', 'type', 'important']` | Props passed as variables instead of being added to the bucket. |
| `attr_name` | `attributes` | The name of the variable that holds leftover HTML attributes. |

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
