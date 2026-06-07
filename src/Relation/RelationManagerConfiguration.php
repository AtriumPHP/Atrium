<?php

declare(strict_types=1);

namespace Atrium\Relation;

use Atrium\Form\Schema;
use Atrium\Table\TableConfiguration;

/**
 * A dedicated config class for a relation manager (REL-19): a plain PHP class —
 * NOT a Live Component — that supplies a relation's `table()` and/or `form()`,
 * the same inline-or-dedicated split {@see \Atrium\Resource\AdminResource::pages()}
 * offers. Point a relation at one with `Relation::using(MyConfig::class)`; the
 * manager uses its `table()` instead of the inline `Relation::table()` closure,
 * and the modal `Form` applies its `form()` over the target resource's form.
 */
abstract class RelationManagerConfiguration
{
    public function table(TableConfiguration $table): TableConfiguration
    {
        return $table;
    }

    public function form(Schema $schema): Schema
    {
        return $schema;
    }
}
