# Pages

> The screens a resource exposes — list, create, edit — and how to customise their
> presentation (heading, header actions) and behaviour (post-save redirect).

## When to use

A **Page** is a controller/descriptor class — *not* a Live Component. It owns a
panel screen's **presentation and behaviour** — its heading/subheading, its header
actions, and its post-save redirect; the reactive widgets inside it (the table, the
form) are the Live Components. You rarely write a page from scratch: every resource
gets the three defaults automatically. You reach for a custom Page (or the inline
shortcuts below) to change one of those.

> Pages own *presentation*, not data. They are plain descriptor classes
> instantiated with `new` — **not** services — so they cannot inject dependencies.
> Data and lifecycle hooks (form mutation, save side-effects, authorization) live
> on the [resource](../resources/lifecycle-hooks.md), which is a service.

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

## Heading & subheading

Each page renders a heading (the `<h1>`) and an optional subheading, with sensible
defaults: the list shows the plural label, create shows `New {singular}`, edit
shows `Edit {singular}`. Override on a custom page — for example, an edit page with
a subheading:

```php
namespace App\Admin\Pages;

use Atrium\Page\EditPage;
use Atrium\Page\PageContext;

final class EditProduct extends EditPage
{
    public function getSubheading(PageContext $context): ?string
    {
        return 'Editing product #'.$context->entityId;
    }
}
```

`getHeading()`, `getTitle()` (the browser tab title, defaults to the heading) and
`getSubheading()` all receive the `PageContext` (slug, path prefix, entity id, and
the resource's singular/plural labels).

## Header actions

Header actions are the buttons above a screen — the list's "New" button, or
record-scoped actions on the edit screen (Delete, Duplicate). They run
server-driven through the screen's Live Component, so an `->action()` handler works
(on the edit screen it runs against the **record being edited**; after a
destructive action the screen redirects to the list).

The **inline shortcut** lives on the resource — one method, no Page class:

```php
use Atrium\Page\PageContext;
use Atrium\Table\Action\DeleteAction;

public function getHeaderActions(string $action, PageContext $context): array
{
    return 'edit' === $action
        ? [DeleteAction::make()]          // server-driven; deletes the edited record
        : parent::getHeaderActions($action, $context); // keep the default New button on the list
}
```

`$action` is `index`/`create`/`edit`; the default is a `CreateAction` on the list
and nothing elsewhere. Header actions respect each action's `authorize()`/`visible()`.

For a dedicated screen, override `getHeaderActions(PageContext)` on a **Page**
instead — it returns the actions and **takes precedence** over the resource's
inline method (the base Page returns `null`, which defers to the resource). Inline
for small cases, a Page when you want one — exactly like `table()`/`form()`.

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

Or "save and add another" — after creating a record, return to a fresh create
form instead of the list:

```php
namespace App\Admin\Pages;

use Atrium\Page\CreatePage;
use Atrium\Page\PageContext;

final class CreateProduct extends CreatePage
{
    public function getRedirectUrl(PageContext $context): ?string
    {
        return $context->createUrl(); // back to /admin/product/new
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
