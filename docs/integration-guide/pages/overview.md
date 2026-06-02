# Pages

> The screens a resource exposes — list, create, edit — and how to customise their
> behaviour (like where a save sends the user).

## When to use

A **Page** is a controller/descriptor class — *not* a Live Component. It owns a
panel screen's behaviour and hooks; the reactive widgets inside it (the table, the
form) are the Live Components. You rarely write a page from scratch: every
resource gets the three defaults automatically. You reach for this when you want to
**change a screen's behaviour** — most commonly, the post-save redirect.

## The default pages

Each resource exposes three pages, mapped by action:

| Action | Page | Renders |
| --- | --- | --- |
| `index` | `ListPage` | The list screen (the [table](../tables/table-configuration.md)). |
| `create` | `CreatePage` | The create [form](../forms/overview.md). |
| `edit` | `EditPage` | The edit form. |

They are wired to the parametric routes (`/admin/{resource}`,
`/admin/{resource}/new`, `/admin/{resource}/{id}/edit`), so adding a resource
needs no routing.

## Customising the post-save redirect

By default, creating or editing a record sends the user back to the list. Override
`getRedirectUrl()` on a custom page subclass to change that — for example, to stay
on the edit screen after saving:

```php
namespace App\Admin\Pages;

use Atrium\Page\EditPage;
use Atrium\Page\PageContext;

final class StayOnEditPage extends EditPage
{
    public function getRedirectUrl(PageContext $context): ?string
    {
        // PageContext gives you the resource's URLs.
        return $context->editUrl($context->id ?? '');
    }
}
```

`PageContext` exposes `indexUrl()`, `createUrl()` and `editUrl(string $id)` so you
can compose any destination. Returning `null` keeps the user on the form and shows
the success notice instead of redirecting.

## Supplying custom pages

Point a resource at your page subclasses by overriding `pages()` — a map of action
to page class:

```php
use Atrium\Page\CreatePage;
use Atrium\Page\ListPage;
use App\Admin\Pages\StayOnEditPage;

public static function pages(): array
{
    return [
        'index' => ListPage::class,
        'create' => CreatePage::class,
        'edit' => StayOnEditPage::class,
    ];
}
```

Only override the entries you change; the defaults cover the rest.

## A note on custom (non-CRUD) screens

The page system covers the standard list/create/edit screens that hang off the
parametric routes. A genuinely custom screen (a dashboard widget, a report) is an
ordinary Symfony controller + Twig template that embeds Atrium's Live Components
where it wants reactivity — it doesn't need to be a `Page`.

## See also

- [Table configuration](../tables/table-configuration.md) — what the list page renders
- [Form overview](../forms/overview.md) — what the create/edit pages render
- [Lifecycle hooks](../resources/lifecycle-hooks.md) — data/side-effect hooks around save
