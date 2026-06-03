<?php

declare(strict_types=1);

namespace Atrium\Page;

use Atrium\Widget\WidgetLayoutConfiguration;

/**
 * The header/footer widget layout of a resource's list screen (LW-01).
 *
 * Declared on the list page — {@see ListPage::headerWidgets()} and
 * {@see ListPage::footerWidgets()}, next to the list's heading and header actions.
 * Same shape as a {@see \Atrium\Dashboard\DashboardConfiguration} — widgets in a
 * {@see widgets()} stack or a {@see schema()} of layout containers — but a distinct
 * type so a list screen never reads as "a dashboard".
 *
 * These widgets are independent of the table's live state: they compute their own
 * data and do not react to the current search/filters (see the integration guide).
 */
final class ListWidgetsConfiguration extends WidgetLayoutConfiguration
{
}
