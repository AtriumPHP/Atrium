<?php

declare(strict_types=1);

namespace Atrium\Dashboard;

use Atrium\Widget\WidgetLayoutConfiguration;

/**
 * The layout of a {@see Dashboard} — the dashboard analogue of the form
 * {@see \Atrium\Form\Schema} (DSH-09).
 *
 * Configure it from {@see Dashboard::dashboard()}: the simple path is
 * {@see widgets()} (a flat list of widget classes); richer dashboards use
 * {@see schema()} to nest {@see \Atrium\Widget\WidgetSlot}s inside layout
 * containers (Grid, Section, Fieldset, Flex). A distinct type from the
 * list-screen configuration so the intent reads correctly at the call site.
 */
final class DashboardConfiguration extends WidgetLayoutConfiguration
{
}
