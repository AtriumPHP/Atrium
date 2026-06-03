# Customising the panel

> Brand, theme, shell and landing screen — how to make the whole admin panel
> yours, from a one-line label change to overriding the layout template.

## When to use

Most of Atrium is configured per resource. This page is about the **panel itself**
— the things that apply to every screen: the brand, the accent colour, dark mode,
the surrounding shell (sidebar, header, logo), how the stylesheet is loaded, and
what `/admin` shows. Everything here is optional; the defaults give you a polished,
fully-styled panel with no setup.

## Panel configuration

The bundle exposes two settings, both with sensible defaults:

```yaml
# config/packages/atrium.yaml
atrium:
    path_prefix: '/admin'   # where the panel is mounted
    brand: 'Acme Admin'     # label in the shell, the <title>, the sidebar logo
```

| Key | Default | Effect |
| --- | --- | --- |
| `path_prefix` | `/admin` | The URL prefix every panel route is mounted under. |
| `brand` | `Atrium` | Shown in the sidebar (logo glyph = its first letter, plus the full label), the default browser `<title>`, and the "Powered by" footer. |

These are the only PHP-config knobs — everything else below is theming or template
overrides.

## Changing the URL the panel lives at

### A different path (e.g. `/administrator`)

Set `path_prefix` — that is all. It is the single source of truth for the panel's
location: the parametric routes are mounted under it **and** every URL the panel
generates (sidebar links, the edit/view links, redirect-after-save) is built from
it, so the two can never drift apart.

```yaml
# config/packages/atrium.yaml
atrium:
    path_prefix: '/administrator'
```

`/administrator` now serves the dashboard, `/administrator/{resource}` the lists,
and so on; the old `/admin` URLs stop matching. You do **not** touch the route
import — leave `config/routes/atrium.yaml` exactly as the
[getting-started guide](../getting-started.md) shows it.

> **Write the prefix without a trailing slash** (`/administrator`, not
> `/administrator/`), matching the `/admin` default. A leading slash is required.

### A dedicated subdomain (e.g. `admin.example.com`)

The host is a routing concern, not a panel setting, so it lives on the **route
import** rather than in `atrium.yaml`. Add a `host:` (and, if you want the panel
at the root of that host, set `path_prefix: '/'`):

```yaml
# config/routes/atrium.yaml
atrium:
    resource: '@AtriumBundle/config/routes.php'
    host: 'admin.example.com'
```

```yaml
# config/packages/atrium.yaml
atrium:
    path_prefix: '/'   # serve the panel at the host root: admin.example.com/
```

Keep the default `/admin` prefix instead if you prefer `admin.example.com/admin`.
Point the subdomain's DNS/web-server config at the same Symfony app, and make sure
the host is allowed (Symfony's `trusted_hosts`, if you use it).

## Theming: the accent colour

The entire panel is accented with a single semantic colour, **`primary`**.
Templates only ever use `*-primary-*` classes (`bg-primary-600`,
`text-primary-700`, …), never a brand name, and those utilities resolve **CSS
custom properties** at runtime. So re-skinning is changing eleven variables —
`--color-primary-50` through `--color-primary-950`.

### Option A — runtime override (no rebuild)

Point the variables at your own palette in a stylesheet that loads **after**
Atrium's. The reliable insertion point is the layout's `stylesheets` block (see
[Overriding the shell](#overriding-the-shell)); copy the layout once and add your
override:

```twig
{# templates/bundles/AtriumBundle/admin/layout.html.twig #}
{% block stylesheets %}
    {{ atrium_stylesheet() }}
    <style>
        :root {
            --color-primary-50:  #f5f3ff;
            --color-primary-100: #ede9fe;
            --color-primary-200: #ddd6fe;
            --color-primary-300: #c4b5fd;
            --color-primary-400: #a78bfa;
            --color-primary-500: #8b5cf6;
            --color-primary-600: #7c3aed;  /* the main accent */
            --color-primary-700: #6d28d9;
            --color-primary-800: #5b21b6;
            --color-primary-900: #4c1d95;
            --color-primary-950: #2e1065;
        }
    </style>
{% endblock %}
```

The buttons, active nav item, links, focus rings, stat accents and chart defaults
all follow — no Tailwind build, no recompiled CSS.

> Generate a coherent 50–950 ramp from one brand colour with any Tailwind palette
> generator; you only need the eleven values.

### Option B — rebuild the stylesheet

If you maintain a fork or want the palette baked into the shipped CSS, edit the
`@theme` block in `assets/atrium.css` and recompile:

```bash
composer build-css   # tailwindcss -i assets/atrium.css -o assets/dist/atrium.css --minify
```

This is the path for bundle authors; integrators should prefer Option A.

## Dark mode

The panel ships with **complete dark-mode styling that follows the user's
operating-system / browser preference automatically** (`prefers-color-scheme:
dark`). There is nothing to enable — a visitor whose OS is in dark mode sees the
dark palette.

There is **no built-in manual toggle** (no `.dark` class switch). If you need a
user-controlled light/dark switch, that's an advanced customisation: rebuild the
stylesheet (Option B) after declaring a class-based dark variant at the top of
`assets/atrium.css` —

```css
@import "tailwindcss" source(none);
@custom-variant dark (&:where(.dark, .dark *));
```

— then toggle a `dark` class on `<html>` from your own JavaScript.

## Overriding the shell

The chrome around every screen is one Twig template,
`@Atrium/admin/layout.html.twig` — the sidebar (logo + navigation), the sticky
header (heading, subheading, header-action slot) and the content area. Override it
the standard Symfony way: drop a file at the matching path under
`templates/bundles/AtriumBundle/`, and it replaces the bundle's.

```
templates/bundles/AtriumBundle/admin/layout.html.twig
```

(`AtriumBundle` is the bundle's name; `@Atrium` is its template namespace. The
override directory mirrors the namespace path after it.)

You can replace the whole file, or copy the original and change only what you need.
The layout exposes these blocks, so a child screen — or your override — can fill
them without touching the rest:

| Block | What it wraps |
| --- | --- |
| `title` | The browser `<title>` (defaults to the brand / screen heading). |
| `stylesheets` | The `<head>` stylesheet tags (`atrium_stylesheet()`). |
| `javascripts` | The `<head>` script tags (`atrium_importmap('app')`). |
| `heading` | The `<h1>` text in the sticky header. |
| `subheading` | The optional sub-line under the heading. |
| `header_actions` | The top-right header slot. |
| `body` | The main content of the screen. |

### A custom logo

The sidebar logo is a rounded badge showing `brand|first|upper` next to the brand
label. There is no dedicated logo block, so to use an image or custom mark, copy
the layout into your override and replace the badge markup (around the
`<a href="{{ panel.pathPrefix }}">` at the top of the `<aside>`):

```twig
<a href="{{ panel.pathPrefix }}" class="flex items-center gap-2.5">
    <img src="{{ asset('logo.svg') }}" alt="{{ panel.brand }}" class="h-8 w-8">
    <span class="text-base font-semibold tracking-tight">{{ panel.brand }}</span>
</a>
```

## Icons

Every glyph in the panel is rendered with [Symfony UX Icons][ux-icons], which
Atrium requires (you don't install it yourself — it comes with the bundle). The
panel ships its own [Lucide][lucide] set under the **`atrium:`** prefix, so it
renders fully offline with no icon setup in your app.

Anywhere Atrium accepts an icon name — navigation (`getNavigationIcon()`), actions
(`->icon()`), tabs and wizard steps — the value resolves like this:

- **A bare name** (`cube`, `users`, `pencil`, …) resolves to the bundle's built-in
  set. These are the glyphs the panel itself uses; an unknown bare name falls back
  to a neutral placeholder rather than erroring.
- **A namespaced name** (`lucide:rocket`, `mdi:home`, `tabler:flask`, …) is passed
  straight through to UX Icons, which fetches it on demand from
  [Iconify][iconify] (190 000+ icons) and caches it locally. You are never limited
  to the built-in set:

  ```php
  public function getNavigationIcon(): ?string
  {
      return 'lucide:shopping-cart';
  }
  ```

### Overriding a built-in glyph

To change one of the panel's own icons, alias its name in your app's
`config/packages/ux_icons.yaml` (your app config wins over the bundle's):

```yaml
ux_icons:
    aliases:
        'atrium:cube': 'lucide:package'   # re-point the panel's "cube" glyph
```

To replace the whole built-in set with your own SVGs, point the `atrium` icon set
at your directory:

```yaml
ux_icons:
    icon_sets:
        atrium:
            path: '%kernel.project_dir%/assets/atrium-icons'
```

### Rendering an icon in a template override

In a layout override, render icons with the UX Icons Twig function — a built-in
panel glyph or any Iconify icon:

```twig
{{ ux_icon('atrium:home', { class: 'h-5 w-5' }) }}
{{ ux_icon('lucide:rocket', { class: 'h-5 w-5' }) }}
```

[ux-icons]: https://symfony.com/bundles/ux-icons
[lucide]: https://lucide.dev
[iconify]: https://iconify.design

## How the stylesheet is loaded

If your app uses **AssetMapper** (the Symfony default), there is nothing to do: the
bundle registers its precompiled stylesheet under the `atrium` asset namespace and
the layout emits it via `atrium_stylesheet()` (which resolves
`atrium/atrium.css`). The panel's small JS (Live Components, Stimulus, Chart.js) is
loaded by `atrium_importmap('app')`.

If you **don't** use AssetMapper, override the `stylesheets` block to serve the
file however you prefer — e.g. copy `vendor/atriumphp/atrium/assets/dist/atrium.css`
into your public assets and link it directly:

```twig
{% block stylesheets %}
    <link rel="stylesheet" href="{{ asset('css/atrium.css') }}">
{% endblock %}
```

## The landing screen at `/admin`

`/admin` renders the dashboard registered at the **root slug** (`dashboard`). Out
of the box that's the built-in `DefaultDashboard` — a welcome card plus a link per
resource. To replace the home screen with your own, register a dashboard whose
`getSlug()` returns `'dashboard'`:

```php
namespace App\Admin\Dashboard;

use Atrium\Dashboard\Dashboard;
use Atrium\Dashboard\DashboardConfiguration;
use Atrium\Dashboard\DefaultDashboard;

final class HomeDashboard extends Dashboard
{
    public function getSlug(): string
    {
        return DefaultDashboard::ROOT_SLUG; // 'dashboard' — claims /admin
    }

    public function getTitle(): string
    {
        return 'Overview';
    }

    public function dashboard(DashboardConfiguration $dashboard): DashboardConfiguration
    {
        return $dashboard->widgets([/* your widgets */]);
    }
}
```

Dashboards are auto-discovered (like resources), so no registration is needed
beyond the class. See [Dashboards](../widgets/dashboards.md) for the full layout
API, and note that slugs must be unique across dashboards and resources.

## Navigation

The sidebar's contents — grouping, order, badges, icons, and whether an entry
appears at all — are controlled per resource (and per dashboard), not here. See
[Navigation](../resources/navigation.md).

## API reference

**Configuration** (`config/packages/atrium.yaml`):

| Key | Type | Default | Description |
| --- | --- | --- | --- |
| `path_prefix` | string | `/admin` | URL prefix the panel is mounted under. |
| `brand` | string | `Atrium` | Brand label used across the shell. |

**Twig functions** (available in panel templates):

| Function | Description |
| --- | --- |
| `atrium_stylesheet()` | Emits the panel stylesheet `<link>` (AssetMapper). |
| `atrium_importmap('app')` | Emits the panel's import-map script tags. |
| `ux_icon('atrium:home', { … })` | Renders an icon (built-in `atrium:` set or any Iconify name). |

**Theme variables** (override in CSS): `--color-primary-50` … `--color-primary-950`.

## See also

- [Getting started](../getting-started.md) — installing and mounting the panel
- [Navigation](../resources/navigation.md) — the sidebar entries
- [Dashboards](../widgets/dashboards.md) — the `/admin` landing screen and other dashboards
- [Embedding widgets](../widgets/embedding.md) — drop Atrium widgets into your own pages
