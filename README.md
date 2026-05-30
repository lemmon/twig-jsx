# Twig JSX

JSX-like component syntax for Twig.

[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/php-%5E8.1-blue)](https://php.net)
[![Twig](https://img.shields.io/badge/twig-%5E3.0-green)](https://twig.symfony.com)
[![CI](https://github.com/lemmon/twig-jsx/actions/workflows/ci.yml/badge.svg)](https://github.com/lemmon/twig-jsx/actions/workflows/ci.yml)

Write `<Alert {type} important />` instead of a verbose `{% include %}` call. Twig JSX transforms JSX-like component tags into native Twig `{% include %}` and `{% embed %}` calls at the lexer level — no runtime overhead, no Symfony dependency.

## Requirements

- PHP `^8.1`
- Twig `^3.0`

## Installation

```bash
composer require lemmon/twig-jsx
```

## Quick Start

### 1. Register the Extension and Lexer

```php
use Lemmon\TwigJsx\JSXPreLexer;
use Lemmon\TwigJsx\AttributeExtension;

$twig = new \Twig\Environment($loader);
$twig->addExtension(new AttributeExtension());
$twig->setLexer(new JSXPreLexer($twig));
```

### 2. Create a Component

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

### 3. Use It in a Template

```twig
<!-- Self-closing with shorthand -->
<Alert {type} important message="Everything is great!" />

<!-- With children and extra attributes -->
<Alert type="warning" class="shadow-lg" data-id="123">
    <strong>Wait!</strong> Something needs your attention.
</Alert>
```

## Prop Syntax

| Syntax | Example | Compiles to (inside `props` bag) |
| :--- | :--- | :--- |
| **Static** | `type="info"` | `'type': 'info'` |
| **Expression** | `type={userType}` | `'type': userType` |
| **Expression** | `count={items\|length}` | `'count': items\|length` |
| **Expression** | `theme={dark ? 'd' : 'l'}` | `'theme': dark ? 'd' : 'l'` |
| **Shorthand** | `{type}` | `'type': type` |
| **Boolean** | `important` | `'important': true` |

A quoted value is a **static string** — it is not interpolated. For a dynamic
value, use the expression form: `class={'alert-' ~ type}` rather than
`class="alert-{{ type }}"`. A quoted value containing a `{{ … }}` output tag is
rejected with a `SyntaxError` that points at the expression form, instead of
silently passing the literal text. (To pass a literal `{{ … }}` through, wrap it
in a quoted expression: `tpl={'{{ name }}'}`.) See
[docs/decisions/0001-no-interpolation-in-quoted-props.md](docs/decisions/0001-no-interpolation-in-quoted-props.md)
for the reasoning.

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
- `props.except('a', 'b', ...)` — returns a new bag without the listed keys; useful for spreading HTML fallthrough attributes onto the root element
- `{{ props|render }}` — renders all entries as HTML attribute pairs

## Configuration

| Option | Default | Description |
| :--- | :--- | :--- |
| `directory` | `components` | Subdirectory inside `templates/` where component files are looked up. |
| `extension` | `.twig` | File extension for component templates. |
| `prefix` | `""` | Tag prefix. When empty, any Capitalized tag is treated as a component (JSX-style). |
| `props_variable` | `props` | Name of the variable that holds all props in the component template. |
| `content_block` | `content` | Twig block name where a bodied tag's children are rendered. |

## Alternatives

- [Symfony UX Twig Component](https://symfony.com/bundles/ux-twig-component/current/index.html) — PHP class-backed components; requires the Symfony UX bundle.
- [TwigX](https://github.com/alma-oss/twigx-bundle) — similar `<Component />` syntax as a Symfony bundle; requires Symfony Config and DI.

## Contributing

Bug reports and pull requests are welcome on [GitHub](https://github.com/lemmon/twig-jsx).

## License

MIT. See [LICENSE](LICENSE).
