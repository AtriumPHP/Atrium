# Content

> Place non-input content — explanatory text, images, lists — between fields, so a
> form can guide and inform, not just collect.

## When to use

Forms aren't only inputs. A short instruction above a tricky field, a preview
image, a checklist of requirements — these are **content components**. They sit in
the schema tree alongside fields and layout, render no input, and hold no state.

```php
use Atrium\Content\Text;
use Atrium\Form\Field\TextField;

$schema->components([
    Text::make('The slug is generated from the title — edit it for a custom URL.')
        ->color('info')->columnSpanFull(),
    TextField::make('title')->required(),
    TextField::make('slug'),
]);
```

Content components support the layout options ([`columnSpan`](layout.md),
`columnSpanFull`, and [visibility](reactivity.md)), so they flow inside grids,
sections and tabs like anything else.

## `Text`

A run of text — an inline note, a hint, a small heading.

| Method | Description |
| --- | --- |
| `make(string\|Stringable $content): self` | The text to show. |
| `color(string): self` | Semantic colour (e.g. `info`, `gray`, `green`, `amber`). |
| `weight(string): self` | Font weight (e.g. `medium`, `semibold`). |
| `size(string): self` | Text size (e.g. `sm`, `lg`). |
| `badge(bool = true): self` | Render as a small badge pill. |
| `html(bool = true): self` | Treat the content as trusted HTML instead of plain text. |

> `html()` renders the string unescaped — only pass content you control, never
> user input.

## `Image`

An inline image — a logo, a diagram, a preview.

| Method | Description |
| --- | --- |
| `make(string $url, string $alt = ''): self` | Image source and alt text. |
| `imageWidth(int)` / `imageHeight(int)` / `imageSize(int)` | Dimensions in pixels (`imageSize` sets both). |
| `alignStart()` / `alignCenter()` / `alignEnd()` | Horizontal alignment. |

```php
Image::make('/img/diagram.png', 'How publishing works')->imageSize(320)->alignCenter(),
```

## `UnorderedList`

A simple bulleted list — requirements, tips, a summary.

| Method | Description |
| --- | --- |
| `make(array $items): self` | The list items (strings). |

```php
UnorderedList::make([
    'Use a descriptive title',
    'Keep the slug short',
    'Add at least one tag',
]),
```

## See also

- [Layout](layout.md) — placing content within grids and sections
- [Form overview](overview.md) — `fields()` vs `components()`
