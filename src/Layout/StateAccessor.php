<?php

declare(strict_types=1);

namespace Atrium\Layout;

/**
 * A read accessor over some view state, keyed by name.
 *
 * Lives in the layout subsystem (not forms) so layout containers can evaluate
 * conditional visibility without depending on the form layer. The form's
 * {@see \Atrium\Form\Get} implements it, so the same `visible()` closures work on
 * fields and on layout containers.
 */
interface StateAccessor
{
    public function __invoke(string $key): mixed;
}
